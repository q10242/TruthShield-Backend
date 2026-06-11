<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityTask;
use App\Models\Journalist;
use App\Models\JournalistMatchExclusion;
use App\Models\JournalistNewsUrl;
use App\Models\NewsUrl;
use App\Services\MediaOutletService;
use App\Services\ModerationEventService;
use App\Services\NewsAggregationService;
use App\Services\ReportLabelStatsService;
use App\Services\UrlFingerprintService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class JournalistController extends Controller
{
    public function index(Request $request, ReportLabelStatsService $stats): JsonResponse
    {
        $query = Journalist::query()
            ->with('mediaOutlet:id,name,slug')
            ->withCount([
                'matches as confirmed_news_count' => fn (Builder $query) => $query->where('review_status', 'confirmed'),
                'matches as suspected_news_count' => fn (Builder $query) => $query->where('review_status', 'suspected'),
            ])
            ->where('status', 'active');

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('display_name', 'like', "%{$term}%")
                    ->orWhere('canonical_name', 'like', "%{$term}%")
                    ->orWhereHas('aliases', fn (Builder $alias) => $alias->where('alias', 'like', "%{$term}%"));
            });
        }

        $journalists = $query
            ->orderByDesc('confirmed_news_count')
            ->orderBy('display_name')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 30))));

        $journalists->getCollection()->transform(fn (Journalist $journalist) => [
            'id' => $journalist->id,
            'display_name' => $journalist->display_name,
            'canonical_name' => $journalist->canonical_name,
            'media_outlet' => $journalist->mediaOutlet,
            'confirmed_news_count' => $journalist->confirmed_news_count,
            'suspected_news_count' => $journalist->suspected_news_count,
            'stats' => $stats->journalistStats($journalist, 0),
            'updated_at' => $journalist->updated_at?->toJSON(),
        ]);

        return response()->json($journalists);
    }

    public function aggregateStats(Request $request, ReportLabelStatsService $stats): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query('per_page', 30)));

        $journalists = Journalist::query()
            ->with('mediaOutlet:id,name,slug')
            ->withCount([
                'matches as confirmed_news_count' => fn (Builder $query) => $query->where('review_status', 'confirmed'),
                'matches as suspected_news_count' => fn (Builder $query) => $query->where('review_status', 'suspected'),
            ])
            ->where('status', 'active')
            ->orderByDesc('confirmed_news_count')
            ->orderBy('display_name')
            ->limit($limit)
            ->get()
            ->map(fn (Journalist $journalist) => [
                'id' => $journalist->id,
                'display_name' => $journalist->display_name,
                'canonical_name' => $journalist->canonical_name,
                'media_outlet' => $journalist->mediaOutlet,
                'confirmed_news_count' => $journalist->confirmed_news_count,
                'suspected_news_count' => $journalist->suspected_news_count,
                'stats' => $stats->journalistStats($journalist, 0),
                'updated_at' => $journalist->updated_at?->toJSON(),
            ])
            ->values();

        return response()->json([
            'data' => $journalists,
            'meta' => [
                'per_page' => $limit,
                'total' => Journalist::query()->where('status', 'active')->count(),
                'tracked_tag' => ReportLabelStatsService::TRACKED_TAG_SLUG,
                'formal_stats_review_status' => 'confirmed',
            ],
        ]);
    }

    public function show(Journalist $journalist, ReportLabelStatsService $stats): JsonResponse
    {
        abort_unless($journalist->status === 'active', 404);

        return response()->json([
            'data' => $journalist->load(['mediaOutlet:id,name,slug', 'aliases:id,journalist_id,alias,domain,confidence']),
            'stats' => $stats->journalistStats($journalist, 50),
            'pending_candidates_count' => $journalist->matches()->where('review_status', 'suspected')->count(),
        ]);
    }

    public function stats(Journalist $journalist, ReportLabelStatsService $stats): JsonResponse
    {
        abort_unless($journalist->status === 'active', 404);

        return response()->json(['data' => $stats->journalistStats($journalist, 50)]);
    }

    public function cache(): JsonResponse
    {
        $journalists = Journalist::query()
            ->with(['aliases:id,journalist_id,alias,domain,confidence', 'mediaOutlet:id,name,slug'])
            ->where('status', 'active')
            ->orderBy('updated_at')
            ->get(['id', 'media_outlet_id', 'display_name', 'canonical_name', 'status', 'updated_at'])
            ->map(fn (Journalist $journalist) => [
                'id' => $journalist->id,
                'display_name' => $journalist->display_name,
                'canonical_name' => $journalist->canonical_name,
                'media_outlet' => $journalist->mediaOutlet,
                'aliases' => $journalist->aliases->map(fn ($alias) => [
                    'alias' => $alias->alias,
                    'domain' => $alias->domain,
                    'confidence' => $alias->confidence,
                ])->values()->all(),
                'updated_at' => $journalist->updated_at?->toJSON(),
            ])
            ->values();

        $exclusions = JournalistMatchExclusion::query()
            ->whereNull('news_url_id')
            ->latest()
            ->limit(1000)
            ->get(['id', 'journalist_id', 'alias', 'domain', 'news_url_id', 'reason', 'updated_at']);

        return response()->json([
            'version' => sha1($journalists->toJson() . '|' . $exclusions->toJson()),
            'updated_at' => now()->toJSON(),
            'ttl_seconds' => 86400,
            'journalists' => $journalists,
            'exclusions' => $exclusions,
        ]);
    }

    public function storeMatch(
        Request $request,
        UrlFingerprintService $fingerprints,
        NewsAggregationService $aggregation,
        MediaOutletService $mediaOutlets,
    ): JsonResponse {
        $validated = $request->validate([
            'news_url' => ['required', 'url', 'max:4096'],
            'journalist_id' => ['required', 'integer', 'exists:journalists,id'],
            'match_source' => ['required', Rule::in(['json_ld', 'meta_author', 'selector', 'regex', 'full_text', 'admin'])],
            'matched_text' => ['nullable', 'string', 'max:320'],
            'confidence' => ['required', Rule::in(['high', 'medium', 'low'])],
            'title_snapshot' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $fingerprint = $fingerprints->fingerprint($validated['news_url']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $newsUrl = NewsUrl::query()->firstOrCreate(
            ['hash' => $fingerprint['hash']],
            [
                'original_url' => $fingerprint['original_url'],
                'normalized_url' => $fingerprint['normalized_url'],
                'title_snapshot' => $validated['title_snapshot'] ?? null,
                'voting_closes_at' => now()->addHours(72),
            ],
        );

        if (! $newsUrl->title_snapshot && ! empty($validated['title_snapshot'])) {
            $newsUrl->forceFill(['title_snapshot' => $validated['title_snapshot']])->save();
        }

        $aggregation->forgetMissingStatusCache($fingerprint['hash']);
        $mediaOutlets->attachOutlet($newsUrl);

        $reviewStatus = $this->autoReviewStatus($validated, $newsUrl);

        $match = JournalistNewsUrl::query()->updateOrCreate(
            [
                'journalist_id' => $validated['journalist_id'],
                'news_url_id' => $newsUrl->id,
            ],
            [
                'match_source' => $validated['match_source'],
                'matched_text' => $validated['matched_text'] ?? null,
                'confidence' => $validated['confidence'],
                'review_status' => $reviewStatus,
                'confirmed_at' => $reviewStatus === 'confirmed' ? now() : null,
                'metadata' => [
                    ...($validated['metadata'] ?? []),
                    'source' => 'extension',
                ],
            ],
        );

        $aggregation->forgetStatusCache($newsUrl);

        return response()->json([
            'data' => $match->fresh()->load('journalist:id,display_name,canonical_name'),
            'news_url' => $newsUrl->only(['id', 'normalized_url', 'title_snapshot']),
        ], 201);
    }

    public function reportMatch(Request $request, JournalistNewsUrl $match): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'suggested_name' => ['nullable', 'string', 'max:120'],
        ]);

        $match->forceFill([
            'review_status' => 'reported',
            'rejected_reason' => $validated['reason'] ?? '使用者回報作者對應可能有誤。',
            'metadata' => [
                ...($match->metadata ?? []),
                'reported_at' => now()->toJSON(),
                'suggested_name' => $validated['suggested_name'] ?? null,
            ],
        ])->save();

        if ($match->newsUrl) {
            app(NewsAggregationService::class)->forgetStatusCache($match->newsUrl);
        }
        app(ModerationEventService::class)->record(request(), 'journalist_match.reported', $match, '使用者回報新聞作者對應可能有誤，已建立審核任務。', [
            'journalist_id' => $match->journalist_id,
            'news_url_id' => $match->news_url_id,
        ]);

        CommunityTask::query()->firstOrCreate(
            [
                'type' => 'journalist_match_review',
                'subject_type' => JournalistNewsUrl::class,
                'subject_id' => $match->id,
            ],
            [
                'subject_key' => 'journalist_match:' . $match->id,
                'title' => '確認新聞作者對應是否正確',
                'description' => '這篇新聞的記者對應被回報可能有誤，請確認作者欄、結構化資料或媒體頁面是否支持此關聯。',
                'priority' => 70,
                'status' => 'open',
                'action_url' => '/community-tasks',
                'metrics' => [
                    'match_id' => $match->id,
                    'journalist_id' => $match->journalist_id,
                    'news_url_id' => $match->news_url_id,
                    'options' => [
                        'confirm_author_byline',
                        'reject_name_only_mention',
                        'unable_to_determine',
                        'suggest_other_author',
                    ],
                ],
            ],
        );

        return response()->json(['data' => $match->fresh()]);
    }

    private function autoReviewStatus(array $validated, NewsUrl $newsUrl): string
    {
        if ($validated['confidence'] !== 'high') {
            return 'suspected';
        }

        if (! in_array($validated['match_source'], ['json_ld', 'meta_author', 'selector', 'regex'], true)) {
            return 'suspected';
        }

        $host = parse_url($newsUrl->normalized_url, PHP_URL_HOST);
        $matchedText = trim((string) ($validated['matched_text'] ?? ''));
        $hasExclusion = JournalistMatchExclusion::query()
            ->where(function (Builder $query) use ($validated): void {
                $query->whereNull('journalist_id')
                    ->orWhere('journalist_id', $validated['journalist_id']);
            })
            ->where(function (Builder $query) use ($host): void {
                $query->whereNull('domain')
                    ->orWhere('domain', $host);
            })
            ->where(function (Builder $query) use ($newsUrl): void {
                $query->whereNull('news_url_id')
                    ->orWhere('news_url_id', $newsUrl->id);
            })
            ->where(function (Builder $query) use ($matchedText): void {
                $query->whereNull('alias');
                if ($matchedText !== '') {
                    $query->orWhere('alias', $matchedText);
                }
            })
            ->exists();

        return $hasExclusion ? 'suspected' : 'confirmed';
    }
}
