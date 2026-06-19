<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\NewsUrl;
use App\Models\Tag;
use App\Models\Vote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NewsAggregationService
{
    public function __construct(
        private readonly NewsSnapshotService $snapshots,
        private readonly ReportLabelStatsService $reportStats,
        private readonly MediaOutletService $mediaOutlets,
    ) {}

    public function statusForFingerprint(array $fingerprint, string $locale = 'zh-TW'): array
    {
        $locale = $this->normalizeLocale($locale);
        $missingCacheKey = $this->missingStatusCacheKey($fingerprint['hash']);
        $cachedMissing = Cache::store(config('truthshield.status_cache_store'))->get($missingCacheKey);

        if (is_array($cachedMissing)) {
            return $this->withCacheStatus($this->localizeStatusPayload($this->normalizeEmptyStatus($cachedMissing), $locale), 'hit');
        }

        $newsUrl = NewsUrl::query()->where('hash', $fingerprint['hash'])->first();

        if (! $newsUrl) {
            $empty = $this->emptyStatus($fingerprint['hash'], $fingerprint['normalized_url']);
            Cache::store(config('truthshield.status_cache_store'))->put($missingCacheKey, $empty, now()->addMinutes(2));

            return $this->withCacheStatus($this->localizeStatusPayload($empty, $locale), 'miss');
        }

        $previousMediaOutletId = $newsUrl->media_outlet_id;
        $this->mediaOutlets->attachOutlet($newsUrl);

        if ($newsUrl->media_outlet_id !== $previousMediaOutletId) {
            $this->forgetStatusCache($newsUrl);
        }

        $this->ensureVotingWindow($newsUrl);

        if (! $this->isOpen($newsUrl)) {
            return $this->localizeStatusPayload($this->withCurrentSnapshot($newsUrl, $this->finalize($newsUrl)['status']), $locale);
        }

        $cacheKey = $this->statusCacheKey($newsUrl, $locale);
        $ttl = now()->addSeconds(max(1, min(600, now()->diffInSeconds($newsUrl->voting_closes_at, false))));
        $cache = Cache::store(config('truthshield.status_cache_store'));

        if ($cache->has($cacheKey)) {
            return $this->withCacheStatus($cache->get($cacheKey), 'hit');
        }

        $status = $this->localizeStatusPayload($this->buildStatusPayload($newsUrl), $locale);
        $cache->put($cacheKey, $status, $ttl);

        return $this->withCacheStatus($status, 'miss');
    }

    public function evidenceForFingerprint(array $fingerprint, string $locale = 'zh-TW'): array
    {
        $locale = $this->normalizeLocale($locale);
        $newsUrl = NewsUrl::query()->where('hash', $fingerprint['hash'])->first();

        if (! $newsUrl) {
            return [];
        }

        $this->ensureVotingWindow($newsUrl);

        if (! $this->isOpen($newsUrl)) {
            return $this->localizeEvidencePayload($this->finalize($newsUrl)['evidence'], $locale);
        }

        return $this->localizeEvidencePayload($this->buildEvidencePayload($newsUrl), $locale);
    }

    public function ensureVotingWindow(NewsUrl $newsUrl): void
    {
        if ($newsUrl->voting_closes_at) {
            return;
        }

        $newsUrl->forceFill([
            'voting_closes_at' => ($newsUrl->created_at ?? now())->copy()->addHours(72),
        ])->save();
    }

    public function isOpen(NewsUrl $newsUrl): bool
    {
        $this->ensureVotingWindow($newsUrl);

        return $newsUrl->voting_closes_at && now()->lt($newsUrl->voting_closes_at);
    }

    public function forgetStatusCache(NewsUrl $newsUrl): void
    {
        Cache::store(config('truthshield.status_cache_store'))->forget($this->statusCacheKey($newsUrl, 'zh-TW'));
        Cache::store(config('truthshield.status_cache_store'))->forget($this->statusCacheKey($newsUrl, 'en'));
        Cache::store(config('truthshield.status_cache_store'))->forget($this->legacyStatusCacheKey($newsUrl));
        $this->forgetMissingStatusCache($newsUrl->hash);
    }

    public function forgetMissingStatusCache(string $hash): void
    {
        Cache::store(config('truthshield.status_cache_store'))->forget($this->missingStatusCacheKey($hash));
    }

    public function finalizeNewsUrl(NewsUrl $newsUrl): array
    {
        $this->ensureVotingWindow($newsUrl);

        return $this->finalize($newsUrl);
    }

    private function finalize(NewsUrl $newsUrl): array
    {
        if ($newsUrl->finalized_at && $newsUrl->final_status_payload && is_array($newsUrl->final_evidence_payload)) {
            return [
                'status' => $this->withCurrentSnapshot($newsUrl, $newsUrl->final_status_payload),
                'evidence' => $newsUrl->final_evidence_payload,
            ];
        }

        $lock = Cache::store(config('truthshield.status_cache_store'))->lock("news:finalize:{$newsUrl->hash}", 15);

        return $lock->block(5, function () use ($newsUrl): array {
            $fresh = $newsUrl->fresh();

            if ($fresh?->finalized_at && $fresh->final_status_payload && is_array($fresh->final_evidence_payload)) {
                return [
                    'status' => $this->withCurrentSnapshot($fresh, $fresh->final_status_payload),
                    'evidence' => $fresh->final_evidence_payload,
                ];
            }

            return $this->writeFinalPayload($fresh ?? $newsUrl);
        });
    }

    private function writeFinalPayload(NewsUrl $newsUrl): array
    {
        $finalizedAt = now();
        $status = $this->buildStatusPayload($newsUrl, $finalizedAt);
        $evidence = $this->buildEvidencePayload($newsUrl);

        $newsUrl->forceFill([
            'finalized_at' => $finalizedAt,
            'algorithm_version' => config('truthshield.algorithm_version', 'truthshield-v1'),
            'final_status_payload' => $status,
            'final_evidence_payload' => $evidence,
        ])->save();

        $this->forgetStatusCache($newsUrl);

        return [
            'status' => $status,
            'evidence' => $evidence,
        ];
    }

    private function withCurrentSnapshot(NewsUrl $newsUrl, array $status): array
    {
        $newsUrl->loadMissing('cluster');

        $status['snapshot'] = $this->snapshots->statusPayload($newsUrl);
        $status['cluster'] = $this->clusterPayload($newsUrl);
        $status['cluster_id'] = $newsUrl->news_cluster_id;
        $status['cluster_url_count'] = $newsUrl->cluster?->url_count ?? ($newsUrl->news_cluster_id ? 1 : 0);
        $status['canonical_title'] = $newsUrl->cluster?->canonical_title ?: $newsUrl->title_snapshot;
        $status['evidence_verdict'] = $this->evidenceVerdictPayload($newsUrl);
        $status['media_context'] = $newsUrl->mediaOutlet ? $this->reportStats->mediaContext($newsUrl->mediaOutlet) : null;
        $status['journalist_context'] = $this->reportStats->journalistContextForNews($newsUrl);

        return $status;
    }

    private function buildStatusPayload(NewsUrl $newsUrl, ?Carbon $finalizedAt = null): array
    {
        $rows = $newsUrl->votes()
            ->select('tag_id', DB::raw('SUM(weight_score) as total_weight'))
            ->with('tag:id,name,slug,color,severity,requires_evidence,description,translations')
            ->groupBy('tag_id')
            ->orderByDesc('total_weight')
            ->get();

        $totalWeight = (float) $rows->sum('total_weight');
        $top = $rows->first();
        $percentage = $top && $totalWeight > 0 ? round(((float) $top->total_weight / $totalWeight) * 100, 2) : 0.0;
        $distribution = $rows->map(fn ($row) => [
            'tag' => $row->tag,
            'weight' => round((float) $row->total_weight, 4),
            'percentage' => $totalWeight > 0 ? round(((float) $row->total_weight / $totalWeight) * 100, 2) : 0.0,
        ])->values()->all();
        $secondaryDistribution = $this->secondaryTagDistribution($newsUrl);
        $newsUrl->loadMissing('cluster');
        $newsUrl->loadMissing('mediaOutlet');

        return [
            'url_hash' => $newsUrl->hash,
            'normalized_url' => $newsUrl->normalized_url,
            'cluster' => $this->clusterPayload($newsUrl),
            'cluster_id' => $newsUrl->news_cluster_id,
            'cluster_url_count' => $newsUrl->cluster?->url_count ?? ($newsUrl->news_cluster_id ? 1 : 0),
            'canonical_title' => $newsUrl->cluster?->canonical_title ?: $newsUrl->title_snapshot,
            'top_tag' => $top?->tag,
            'distribution' => $distribution,
            'secondary_distribution' => $secondaryDistribution,
            'evidence_verdict' => $this->evidenceVerdictPayload($newsUrl),
            'media_context' => $newsUrl->mediaOutlet ? $this->reportStats->mediaContext($newsUrl->mediaOutlet) : null,
            'journalist_context' => $this->reportStats->journalistContextForNews($newsUrl),
            'display_text' => $this->displayText($top?->tag?->severity, $percentage, $top?->tag?->name),
            'tone' => $this->toneFor($top?->tag?->severity),
            'percentage' => $percentage,
            'total_weight' => round($totalWeight, 4),
            'is_open' => $finalizedAt ? false : $this->isOpen($newsUrl),
            'voting_closes_at' => $newsUrl->voting_closes_at?->toJSON(),
            'finalized_at' => $finalizedAt?->toJSON(),
            'algorithm_version' => $newsUrl->algorithm_version ?: config('truthshield.algorithm_version', 'truthshield-v1'),
            'snapshot' => $this->snapshots->statusPayload($newsUrl),
        ];
    }

    private function buildEvidencePayload(NewsUrl $newsUrl): array
    {
        return $newsUrl->votes()
            ->where('hidden', false)
            ->whereNotNull('evidence_url')
            ->with([
                'tag:id,name,slug,color,severity,description,translations',
                'user:id,name,display_name,is_real_name_public,public_identity_label,trust_score,selected_badge_id',
                'user.selectedBadge:id,name,slug,description,color',
                'evidence:id,vote_id,archive_url,preview_url,quality_score,snapshot_status',
                'reactions:id,vote_id,helpful,credibility,relevance,direction,weight_score',
            ])
            ->withSum(['reactions as helpful_weight' => fn ($query) => $query->where('helpful', true)], 'weight_score')
            ->withSum(['reactions as unhelpful_weight' => fn ($query) => $query->where('helpful', false)], 'weight_score')
            ->withCount(['reactions as helpful_count' => fn ($query) => $query->where('helpful', true)])
            ->withCount(['reactions as unhelpful_count' => fn ($query) => $query->where('helpful', false)])
            ->orderByDesc(
                Evidence::query()
                    ->select('quality_score')
                    ->whereColumn('evidences.vote_id', 'votes.id')
                    ->limit(1)
            )
            ->latest('votes.updated_at')
            ->limit(20)
            ->get()
            ->map(fn (Vote $vote) => [
                'id' => $vote->id,
                'tag' => $vote->tag,
                'evidence_url' => $vote->evidence_url,
                'evidence_type' => $vote->evidence_type,
                'evidence_host' => $vote->evidence_host ?: $this->hostForUrl($vote->evidence_url),
                'evidence_safety' => $vote->evidence_safety,
                'is_trusted_evidence' => $vote->evidence_safety === 'trusted',
                'preview_url' => $vote->evidence?->preview_url ?: $this->previewUrl($vote->evidence_url, $vote->evidence_type),
                'archive_url' => $vote->evidence?->archive_url,
                'snapshot_status' => $vote->evidence?->snapshot_status,
                'quality_score' => round((float) ($vote->evidence?->quality_score ?? 0), 2),
                'evidence_note' => $vote->evidence_note,
                'author' => [
                    'name' => $vote->user?->publicName(),
                    'identity_label' => $vote->user?->public_identity_label,
                    'trust_score' => round((float) $vote->user?->trust_score, 2),
                    'selected_badge' => $vote->user?->selectedBadge ? [
                        'id' => $vote->user->selectedBadge->id,
                        'name' => $vote->user->selectedBadge->name,
                        'slug' => $vote->user->selectedBadge->slug,
                        'description' => $vote->user->selectedBadge->description,
                        'color' => $vote->user->selectedBadge->color,
                    ] : null,
                ],
                'vote_weight' => round((float) $vote->weight_score, 4),
                'helpful_count' => $vote->helpful_count,
                'unhelpful_count' => $vote->unhelpful_count,
                'helpful_weight' => round((float) ($vote->helpful_weight ?? 0), 4),
                'unhelpful_weight' => round((float) ($vote->unhelpful_weight ?? 0), 4),
                'net_helpful_weight' => round((float) ($vote->helpful_weight ?? 0) - (float) ($vote->unhelpful_weight ?? 0), 4),
                'direction_summary' => $this->evidenceDirectionForVote($vote),
            ])
            ->values()
            ->all();
    }

    private function clusterPayload(NewsUrl $newsUrl): ?array
    {
        $cluster = $newsUrl->cluster;

        if (! $cluster) {
            return null;
        }

        return [
            'id' => $cluster->id,
            'url_count' => (int) $cluster->url_count,
            'canonical_title' => $cluster->canonical_title,
            'source_host' => $cluster->source_host,
            'title_key' => $cluster->title_key,
            'last_matched_at' => $cluster->last_matched_at?->toJSON(),
        ];
    }

    private function evidenceVerdictPayload(NewsUrl $newsUrl): array
    {
        $rows = DB::table('evidence_reactions')
            ->join('votes', 'votes.id', '=', 'evidence_reactions.vote_id')
            ->where('votes.news_url_id', $newsUrl->id)
            ->where('votes.hidden', false)
            ->whereNotNull('votes.evidence_url')
            ->get([
                'evidence_reactions.direction',
                'evidence_reactions.credibility',
                'evidence_reactions.relevance',
                'evidence_reactions.weight_score',
            ]);

        $weights = [
            'supports' => 0.0,
            'refutes' => 0.0,
            'contextual' => 0.0,
        ];

        foreach ($rows as $row) {
            $direction = in_array($row->direction, ['supports', 'refutes', 'contextual'], true)
                ? $row->direction
                : 'contextual';
            $credibility = $row->credibility ? max(1, min(5, (int) $row->credibility)) : 3;
            $relevance = $row->relevance ? max(1, min(5, (int) $row->relevance)) : 3;
            $qualityFactor = (($credibility + $relevance) / 2) / 3;

            $weights[$direction] += ((float) $row->weight_score) * $qualityFactor;
        }

        $supports = round($weights['supports'], 4);
        $refutes = round($weights['refutes'], 4);
        $contextual = round($weights['contextual'], 4);
        $net = round($supports - $refutes, 4);
        $direction = match (true) {
            $supports > 0 && $supports >= ($refutes * 1.2) => 'supports',
            $refutes > 0 && $refutes >= ($supports * 1.2) => 'refutes',
            ($supports + $refutes + $contextual) > 0 => 'mixed_or_contextual',
            default => 'insufficient_evidence',
        };

        return [
            'direction' => $direction,
            'label' => match ($direction) {
                'supports' => '證據目前較支持新聞內容',
                'refutes' => '證據目前較反駁新聞內容',
                'mixed_or_contextual' => '證據目前以脈絡補充或分歧為主',
                default => '尚無足夠證據評價',
            },
            'supports_weight' => $supports,
            'refutes_weight' => $refutes,
            'contextual_weight' => $contextual,
            'net_support_weight' => $net,
            'rating_count' => $rows->count(),
        ];
    }

    private function evidenceDirectionForVote(Vote $vote): array
    {
        $weights = [
            'supports' => 0.0,
            'refutes' => 0.0,
            'contextual' => 0.0,
        ];

        foreach ($vote->reactions as $reaction) {
            $direction = in_array($reaction->direction, ['supports', 'refutes', 'contextual'], true)
                ? $reaction->direction
                : 'contextual';
            $credibility = $reaction->credibility ? max(1, min(5, (int) $reaction->credibility)) : 3;
            $relevance = $reaction->relevance ? max(1, min(5, (int) $reaction->relevance)) : 3;
            $weights[$direction] += ((float) $reaction->weight_score) * ((($credibility + $relevance) / 2) / 3);
        }

        return [
            'supports_weight' => round($weights['supports'], 4),
            'refutes_weight' => round($weights['refutes'], 4),
            'contextual_weight' => round($weights['contextual'], 4),
        ];
    }

    private function hostForUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return strtolower((string) parse_url($url, PHP_URL_HOST)) ?: null;
    }

    private function previewUrl(?string $url, ?string $type): ?string
    {
        if (! $url || ! in_array($type, ['image', 'cloud_drive'], true)) {
            return null;
        }

        $host = $this->hostForUrl($url);
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if (in_array($host, ['imgur.com', 'www.imgur.com'], true)) {
            $id = collect(explode('/', $path))->filter()->last();
            if ($id && ! str_contains($id, '.')) {
                return "https://i.imgur.com/{$id}.jpg";
            }
        }

        if ($type === 'cloud_drive') {
            return null;
        }

        return $url;
    }

    private function emptyStatus(string $hash, string $normalizedUrl): array
    {
        $mediaOutlet = $this->mediaOutlets->findOutletForUrl($normalizedUrl);

        return [
            'url_hash' => $hash,
            'normalized_url' => $normalizedUrl,
            'cluster' => null,
            'cluster_id' => null,
            'cluster_url_count' => 0,
            'canonical_title' => null,
            'top_tag' => null,
            'distribution' => [],
            'secondary_distribution' => [],
            'evidence_verdict' => [
                'direction' => 'insufficient_evidence',
                'label' => '尚無足夠證據評價',
                'supports_weight' => 0.0,
                'refutes_weight' => 0.0,
                'contextual_weight' => 0.0,
                'net_support_weight' => 0.0,
                'rating_count' => 0,
            ],
            'media_context' => $mediaOutlet ? $this->reportStats->mediaContext($mediaOutlet) : null,
            'journalist_context' => [],
            'display_text' => '尚無足夠投票資料',
            'tone' => 'neutral',
            'percentage' => 0.0,
            'total_weight' => 0.0,
            'is_open' => true,
            'voting_closes_at' => null,
            'finalized_at' => null,
            'algorithm_version' => config('truthshield.algorithm_version', 'truthshield-v1'),
            'snapshot' => [
                'availability_status' => 'unknown',
                'last_snapshot_at' => null,
                'archive_url' => null,
                'snapshots_count' => 0,
                'changed_snapshots_count' => 0,
                'pending_change_reports_count' => 0,
                'latest_snapshot' => null,
            ],
        ];
    }

    private function normalizeEmptyStatus(array $status): array
    {
        if (($status['top_tag'] ?? null) === null && empty($status['voting_closes_at']) && empty($status['finalized_at'])) {
            $status['is_open'] = true;
        }

        return $status;
    }

    private function localizeStatusPayload(array $status, string $locale): array
    {
        $status['top_tag'] = $this->localizeTagPayload($status['top_tag'] ?? null, $locale);
        $status['distribution'] = collect($status['distribution'] ?? [])
            ->map(fn (array $row) => [
                ...$row,
                'tag' => $this->localizeTagPayload($row['tag'] ?? null, $locale),
            ])
            ->all();
        $status['secondary_distribution'] = collect($status['secondary_distribution'] ?? [])
            ->map(fn (array $row) => [
                ...$row,
                'tag' => $this->localizeTagPayload($row['tag'] ?? null, $locale),
            ])
            ->all();

        $status['display_text'] = $this->displayText(
            $status['top_tag']['severity'] ?? null,
            (float) ($status['percentage'] ?? 0),
            $status['top_tag']['name'] ?? null,
            $locale,
        );

        return $status;
    }

    private function localizeEvidencePayload(array $evidence, string $locale): array
    {
        return collect($evidence)
            ->map(fn (array $row) => [
                ...$row,
                'tag' => $this->localizeTagPayload($row['tag'] ?? null, $locale),
            ])
            ->all();
    }

    private function localizeTagPayload(mixed $tag, string $locale): ?array
    {
        if (! $tag) {
            return null;
        }

        if ($tag instanceof Tag) {
            return $tag->localizedPayload($locale);
        }

        $payload = is_array($tag) ? $tag : (array) $tag;
        $translation = data_get($payload, "translations.{$locale}", []);

        if (! $translation && isset($payload['slug'])) {
            $fresh = Tag::query()
                ->where('slug', $payload['slug'])
                ->first(['id', 'name', 'slug', 'color', 'severity', 'requires_evidence', 'description', 'translations']);

            if ($fresh) {
                return $fresh->localizedPayload($locale);
            }
        }

        unset($payload['translations']);

        return [
            ...$payload,
            'name' => $translation['name'] ?? $payload['name'] ?? null,
            'description' => $translation['description'] ?? $payload['description'] ?? null,
        ];
    }

    private function displayText(?string $severity, float $percentage, ?string $tagName, string $locale = 'zh-TW'): string
    {
        if (! $tagName) {
            return $locale === 'en' ? 'Not enough voting data yet' : '尚無足夠投票資料';
        }

        if ($severity === 'positive') {
            return $locale === 'en'
                ? '✅ Majority recommendation: '.$tagName
                : '✅ 多數專家推薦：'.$tagName;
        }

        return $locale === 'en'
            ? '⚠️ '.(int) round($percentage).'% users labeled: '.$tagName
            : '⚠️ '.(int) round($percentage).'% 使用者標註：'.$tagName;
    }

    private function secondaryTagDistribution(NewsUrl $newsUrl): array
    {
        $votes = $newsUrl->votes()
            ->whereNotNull('secondary_tag_ids')
            ->get(['secondary_tag_ids', 'weight_score']);
        $weights = [];

        foreach ($votes as $vote) {
            foreach ($vote->secondary_tag_ids ?: [] as $tagId) {
                $weights[(int) $tagId] = ($weights[(int) $tagId] ?? 0) + (float) $vote->weight_score;
            }
        }

        if ($weights === []) {
            return [];
        }

        arsort($weights);
        $tags = Tag::query()
            ->whereIn('id', array_keys($weights))
            ->get(['id', 'name', 'slug', 'color', 'severity', 'requires_evidence', 'description', 'translations'])
            ->keyBy('id');
        $total = array_sum($weights);

        return collect($weights)
            ->map(fn ($weight, $tagId) => [
                'tag' => $tags->get((int) $tagId),
                'weight' => round((float) $weight, 4),
                'percentage' => $total > 0 ? round(((float) $weight / $total) * 100, 2) : 0.0,
            ])
            ->filter(fn ($row) => $row['tag'])
            ->values()
            ->all();
    }

    private function toneFor(?string $severity): string
    {
        return match ($severity) {
            'high' => 'danger',
            'medium' => 'warning',
            'positive' => 'positive',
            default => 'neutral',
        };
    }

    private function statusCacheKey(NewsUrl $newsUrl, string $locale = 'zh-TW'): string
    {
        $version = config('truthshield.status_cache_version', 'v1');
        $locale = $this->normalizeLocale($locale);

        return "news:status:{$version}:{$locale}:{$newsUrl->hash}";
    }

    private function legacyStatusCacheKey(NewsUrl $newsUrl): string
    {
        $version = config('truthshield.status_cache_version', 'v1');

        return "news:status:{$version}:{$newsUrl->hash}";
    }

    private function missingStatusCacheKey(string $hash): string
    {
        $version = config('truthshield.status_cache_version', 'v1');

        return "news:status:missing:{$version}:{$hash}";
    }

    private function normalizeLocale(string $locale): string
    {
        return str_starts_with(strtolower($locale), 'en') ? 'en' : 'zh-TW';
    }

    private function withCacheStatus(array $status, string $cacheStatus): array
    {
        $status['cache_status'] = $cacheStatus;

        return $status;
    }
}
