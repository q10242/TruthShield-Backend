<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityTask;
use App\Models\NewsEvent;
use App\Models\NewsUrl;
use App\Models\ReaderReaction;
use App\Services\TrustScoreService;
use App\Services\UrlFingerprintService;
use App\Services\BotProtectionService;
use App\Support\EventTaxonomy;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;

class ReaderReactionController extends Controller
{
    private const FEELINGS = [
        'confused' => ['emoji' => '😕', 'label' => '資訊混亂'],
        'worried' => ['emoji' => '😟', 'label' => '擔心'],
        'absurd' => ['emoji' => '🙄', 'label' => '很瞎'],
        'angry' => ['emoji' => '😠', 'label' => '憤怒'],
        'sad' => ['emoji' => '😔', 'label' => '難過'],
        'powerless' => ['emoji' => '😮‍💨', 'label' => '無力'],
        'skeptical' => ['emoji' => '🤨', 'label' => '懷疑'],
        'misled' => ['emoji' => '⚠️', 'label' => '覺得被誤導'],
        'relieved' => ['emoji' => '😌', 'label' => '安心'],
        'happy' => ['emoji' => '😊', 'label' => '看了開心'],
        'indifferent' => ['emoji' => '😐', 'label' => '無所謂'],
        'clear' => ['emoji' => '🙂', 'label' => '覺得清楚'],
        'thankful' => ['emoji' => '🙏', 'label' => '感謝補充'],
        'insightful' => ['emoji' => '💡', 'label' => '有收穫'],
        'credible' => ['emoji' => '✅', 'label' => '覺得可信'],
        'shocked' => ['emoji' => '😮', 'label' => '震驚'],
        'heartwarming' => ['emoji' => '🥰', 'label' => '溫馨'],
        'exaggerated' => ['emoji' => '🤯', 'label' => '誇張'],
        'frightened' => ['emoji' => '😨', 'label' => '驚嚇'],
        'terrifying' => ['emoji' => '😰', 'label' => '可怕'],
        'resigned' => ['emoji' => '😑', 'label' => '無奈'],
        'facepalm' => ['emoji' => '🤦', 'label' => '智障'],
        'funny' => ['emoji' => '😂', 'label' => '搞笑'],
        'regret' => ['emoji' => '😣', 'label' => '後悔'],
        'clown_self' => ['emoji' => '🤡', 'label' => '小丑竟是我自己'],
    ];

    private const NEEDS = [
        'timeline' => ['emoji' => '🧭', 'label' => '想看時間線'],
        'official_info' => ['emoji' => '📄', 'label' => '想看官方資料'],
        'fact_check' => ['emoji' => '🔎', 'label' => '想看求證'],
        'all_sides' => ['emoji' => '🧩', 'label' => '想看各方說法'],
        'follow_up' => ['emoji' => '⏳', 'label' => '想看後續追蹤'],
        'source_data' => ['emoji' => '📊', 'label' => '想看原始數據'],
    ];

    public function summary(Request $request, UrlFingerprintService $fingerprints): JsonResponse
    {
        $validated = $request->validate([
            'news_url' => ['nullable', 'url', 'max:4096'],
            'event_id' => ['nullable', 'integer', 'exists:news_events,id'],
        ]);

        $newsUrl = isset($validated['news_url'])
            ? $this->newsUrlFor($fingerprints, $validated['news_url'], false)
            : null;
        $event = isset($validated['event_id'])
            ? NewsEvent::query()->find($validated['event_id'])
            : null;
        $relatedEvents = $newsUrl ? $this->relatedEvents($newsUrl) : collect();

        $target = $event
            ? ['subject_type' => ReaderReaction::SUBJECT_NEWS_EVENT, 'subject_id' => $event->id]
            : ($newsUrl ? ['subject_type' => ReaderReaction::SUBJECT_NEWS_URL, 'subject_id' => $newsUrl->id] : null);
        $reactions = $target
            ? $this->reactionsForTarget($target['subject_type'], (int) $target['subject_id'])
            : collect();
        $user = $this->optionalUser($request);

        return response()->json([
            'target' => $target,
            'options' => $this->options(),
            'related_events' => $relatedEvents->map(fn (NewsEvent $event): array => $this->eventPayload($event))->values(),
            'hover_reactions' => $this->topRows($reactions, 3),
            'summary' => $this->aggregate($reactions),
            'my_reaction' => $target && $user ? ReaderReaction::query()
                ->where('user_id', $user->id)
                ->where('subject_type', $target['subject_type'])
                ->where('subject_id', $target['subject_id'])
                ->first() : null,
        ]);
    }

    public function store(
        Request $request,
        UrlFingerprintService $fingerprints,
        TrustScoreService $trustScores,
        BotProtectionService $botProtection,
    ): JsonResponse
    {
        if ($response = $botProtection->enforce($request, 'reader.reaction')) {
            return $response;
        }

        $validated = $request->validate([
            'news_url' => ['required', 'url', 'max:4096'],
            'event_id' => ['nullable', 'integer', 'exists:news_events,id'],
            'feelings' => ['nullable', 'array', 'max:3'],
            'feelings.*' => ['string', Rule::in(array_keys(self::FEELINGS))],
            'needs' => ['nullable', 'array', 'max:3'],
            'needs.*' => ['string', Rule::in(array_keys(self::NEEDS))],
            'challenge_token' => ['nullable', 'string', 'max:2048'],
            'challenge_retry' => ['nullable', 'boolean'],
        ]);

        $feelings = collect($validated['feelings'] ?? [])->unique()->values()->all();
        $needs = collect($validated['needs'] ?? [])->unique()->values()->all();
        if ($feelings === [] && $needs === []) {
            return response()->json([
                'message' => 'At least one feeling or need is required.',
                'errors' => ['feelings' => ['At least one feeling or need is required.']],
            ], 422);
        }

        $newsUrl = $this->newsUrlFor($fingerprints, $validated['news_url'], true);
        if ((bool) config('truthshield_bot.quick_action_read_gate_enabled', false)) {
            $minimumReadSeconds = (int) config('truthshield.min_read_seconds_before_vote', 15);
            $secondsRead = (int) $request->user()
                ->readSessions()
                ->where('news_url_id', $newsUrl->id)
                ->value('seconds_read');
            if ($minimumReadSeconds > 0 && $secondsRead < $minimumReadSeconds) {
                return response()->json([
                    'message' => 'Please read the article before reacting.',
                    'minimum_read_seconds' => $minimumReadSeconds,
                    'seconds_read' => $secondsRead,
                ], 428);
            }
        }
        $relatedEvents = $this->relatedEvents($newsUrl);
        $event = null;
        if ($validated['event_id'] ?? null) {
            $event = $relatedEvents->firstWhere('id', (int) $validated['event_id']);
            if (! $event) {
                return response()->json([
                    'message' => 'The selected event is not related to this news URL.',
                    'errors' => ['event_id' => ['The selected event is not related to this news URL.']],
                ], 422);
            }
        }

        $subjectType = $event ? ReaderReaction::SUBJECT_NEWS_EVENT : ReaderReaction::SUBJECT_NEWS_URL;
        $subjectId = $event?->id ?? $newsUrl->id;
        $reaction = ReaderReaction::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
            ],
            [
                'source_news_url_id' => $newsUrl->id,
                'feelings' => $feelings,
                'needs' => $needs,
                'weight_score' => $trustScores->voteWeightFor($request->user()),
            ],
        );

        if ($subjectType === ReaderReaction::SUBJECT_NEWS_URL) {
            $this->maybeCreateEventTask($newsUrl);
        }

        $reactions = $this->reactionsForTarget($subjectType, (int) $subjectId);

        return response()->json([
            'message' => 'Reader reaction recorded.',
            'reaction' => $reaction->fresh(),
            'target' => ['subject_type' => $subjectType, 'subject_id' => $subjectId],
            'options' => $this->options(),
            'hover_reactions' => $this->topRows($reactions, 3),
            'summary' => $this->aggregate($reactions),
        ], $reaction->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, UrlFingerprintService $fingerprints): JsonResponse
    {
        $validated = $request->validate([
            'news_url' => ['required', 'url', 'max:4096'],
        ]);

        $newsUrl = $this->newsUrlFor($fingerprints, $validated['news_url'], false);

        if ($newsUrl) {
            ReaderReaction::query()
                ->where('user_id', $request->user()->id)
                ->where('source_news_url_id', $newsUrl->id)
                ->delete();
        }

        return response()->json(['message' => 'Reaction removed.']);
    }

    private function newsUrlFor(UrlFingerprintService $fingerprints, string $url, bool $create): ?NewsUrl
    {
        try {
            $fingerprint = $fingerprints->fingerprint($url);
        } catch (InvalidArgumentException $exception) {
            throw new HttpResponseException(response()->json(['message' => $exception->getMessage()], 422));
        }

        return $create
            ? NewsUrl::query()->firstOrCreate(
                ['hash' => $fingerprint['hash']],
                [
                    'original_url' => $fingerprint['original_url'],
                    'normalized_url' => $fingerprint['normalized_url'],
                    'voting_closes_at' => now()->addHours(72),
                ],
            )
            : NewsUrl::query()->where('hash', $fingerprint['hash'])->first();
    }

    private function relatedEvents(?NewsUrl $newsUrl): Collection
    {
        if (! $newsUrl) {
            return collect();
        }

        return NewsEvent::query()
            ->withCount(['items', 'timelineEntries', 'relationships'])
            ->where(function ($query) use ($newsUrl): void {
                $query->where('primary_news_url_id', $newsUrl->id)
                    ->orWhereHas('items', fn ($item) => $item->where('news_url_id', $newsUrl->id))
                    ->orWhereHas('timelineEntries', fn ($entry) => $entry->where('news_url_id', $newsUrl->id))
                    ->orWhereHas('relationships', fn ($relationship) => $relationship->where('news_url_id', $newsUrl->id));
            })
            ->latest('last_activity_at')
            ->limit(10)
            ->get();
    }

    private function reactionsForTarget(string $subjectType, int $subjectId): Collection
    {
        if ($subjectType === ReaderReaction::SUBJECT_NEWS_URL) {
            return ReaderReaction::query()
                ->where('subject_type', ReaderReaction::SUBJECT_NEWS_URL)
                ->where('subject_id', $subjectId)
                ->get();
        }

        $event = NewsEvent::query()->with(['items:id,news_event_id,news_url_id', 'timelineEntries:id,news_event_id,news_url_id', 'relationships:id,news_event_id,news_url_id'])->find($subjectId);
        if (! $event) {
            return collect();
        }

        $newsIds = collect([$event->primary_news_url_id])
            ->merge($event->items->pluck('news_url_id'))
            ->merge($event->timelineEntries->pluck('news_url_id'))
            ->merge($event->relationships->pluck('news_url_id'))
            ->filter()
            ->unique()
            ->values();

        $direct = ReaderReaction::query()
            ->where('subject_type', ReaderReaction::SUBJECT_NEWS_EVENT)
            ->where('subject_id', $subjectId)
            ->get()
            ->keyBy('user_id');
        $news = $newsIds->isEmpty()
            ? collect()
            : ReaderReaction::query()
                ->where('subject_type', ReaderReaction::SUBJECT_NEWS_URL)
                ->whereIn('subject_id', $newsIds)
                ->latest('updated_at')
                ->get()
                ->groupBy('user_id')
                ->map(fn ($rows) => $rows->first());

        return $direct->union($news->except($direct->keys()->all()))->values();
    }

    private function aggregate(Collection $reactions): array
    {
        return [
            'total_users' => $reactions->pluck('user_id')->unique()->count(),
            'feelings' => $this->rowsFor($reactions, 'feelings', self::FEELINGS),
            'needs' => $this->rowsFor($reactions, 'needs', self::NEEDS),
        ];
    }

    private function topRows(Collection $reactions, int $limit): array
    {
        $aggregate = $this->aggregate($reactions);

        return collect([...$aggregate['feelings'], ...$aggregate['needs']])
            ->sort(fn ($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp($a['key'], $b['key']))
            ->take($limit)
            ->values()
            ->all();
    }

    private function rowsFor(Collection $reactions, string $field, array $definitions): array
    {
        $total = max(1, $reactions->pluck('user_id')->unique()->count());

        return $reactions
            ->flatMap(fn (ReaderReaction $reaction) => $reaction->{$field} ?: [])
            ->countBy()
            ->map(function (int $count, string $key) use ($definitions, $total): array {
                return [
                    'key' => $key,
                    'emoji' => $definitions[$key]['emoji'] ?? '',
                    'label' => $definitions[$key]['label'] ?? $key,
                    'count' => $count,
                    'strength' => $this->strength($count, $total),
                ];
            })
            ->sort(fn ($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp($a['key'], $b['key']))
            ->values()
            ->all();
    }

    private function options(): array
    {
        return [
            'feelings' => $this->definitionRows(self::FEELINGS),
            'needs' => $this->definitionRows(self::NEEDS),
        ];
    }

    private function definitionRows(array $definitions): array
    {
        return collect($definitions)
            ->map(fn (array $definition, string $key): array => [
                'key' => $key,
                'emoji' => $definition['emoji'],
                'label' => $definition['label'],
            ])
            ->values()
            ->all();
    }

    private function strength(int $count, int $total): string
    {
        if ($total < 3) {
            return 'new';
        }

        $ratio = $count / max(1, $total);
        if ($count >= 3 && $ratio >= 0.4) {
            return 'high';
        }

        if ($count >= 2 || $ratio >= 0.2) {
            return 'medium';
        }

        return 'low';
    }

    private function maybeCreateEventTask(NewsUrl $newsUrl): void
    {
        $reactions = $this->reactionsForTarget(ReaderReaction::SUBJECT_NEWS_URL, $newsUrl->id);
        $summary = $this->aggregate($reactions);
        $topNeeds = collect($summary['needs'])->take(3)->pluck('key');

        if (($summary['total_users'] ?? 0) < 3 || ! $topNeeds->intersect(['timeline', 'all_sides'])->count()) {
            return;
        }

        CommunityTask::query()->updateOrCreate(
            ['type' => 'event_creation_request', 'subject_key' => "news:event-creation:{$newsUrl->id}", 'status' => 'open'],
            [
                'subject_type' => NewsUrl::class,
                'subject_id' => $newsUrl->id,
                'title' => '需要建立事件脈絡',
                'description' => ($newsUrl->title_snapshot ?: $newsUrl->normalized_url) . ' 的讀者反應顯示需要時間線或各方說法。',
                'priority' => 58,
                'action_url' => '/events',
                'metrics' => $summary,
                'generation_snapshot' => [
                    'reason' => 'reader_reaction_context_need',
                    'metrics' => $summary,
                    'generated_at' => now()->toJSON(),
                ],
                'expires_at' => now()->addDays(14),
            ],
        );
    }

    private function eventPayload(NewsEvent $event): array
    {
        $locale = $this->locale(request());

        return [
            'id' => $event->id,
            'name' => $event->name,
            'summary' => $event->summary,
            'primary_category' => $event->primary_category,
            'primary_category_label' => EventTaxonomy::categoryLabel($event->primary_category, $locale),
            'tags' => $event->tags ?? [],
            'tag_labels' => collect($event->tags ?? [])
                ->map(fn (string $tag): string => EventTaxonomy::tagLabel($tag, $locale) ?? $tag)
                ->values()
                ->all(),
            'progress_status' => $event->progress_status,
            'progress_status_label' => EventTaxonomy::progressStatusLabel($event->progress_status, $locale),
            'counts' => [
                'items' => $event->items_count ?? null,
                'timeline' => $event->timeline_entries_count ?? null,
                'relationships' => $event->relationships_count ?? null,
            ],
        ];
    }

    private function locale(Request $request): string
    {
        return str_starts_with(strtolower($request->header('Accept-Language', 'zh-TW')), 'en') ? 'en' : 'zh-TW';
    }

    private function optionalUser(Request $request)
    {
        $user = $request->user();
        if ($user) {
            return $user;
        }

        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        return PersonalAccessToken::findToken($token)?->tokenable;
    }
}
