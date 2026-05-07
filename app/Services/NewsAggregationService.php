<?php

namespace App\Services;

use App\Models\NewsUrl;
use App\Models\Vote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NewsAggregationService
{
    public function __construct(private readonly NewsSnapshotService $snapshots)
    {
    }

    public function statusForFingerprint(array $fingerprint): array
    {
        $missingCacheKey = $this->missingStatusCacheKey($fingerprint['hash']);
        $cachedMissing = Cache::store(config('truthshield.status_cache_store'))->get($missingCacheKey);

        if (is_array($cachedMissing)) {
            return $cachedMissing;
        }

        $newsUrl = NewsUrl::query()->where('hash', $fingerprint['hash'])->first();

        if (! $newsUrl) {
            $empty = $this->emptyStatus($fingerprint['hash'], $fingerprint['normalized_url']);
            Cache::store(config('truthshield.status_cache_store'))->put($missingCacheKey, $empty, now()->addMinutes(2));

            return $empty;
        }

        $this->ensureVotingWindow($newsUrl);

        if (! $this->isOpen($newsUrl)) {
            return $this->withCurrentSnapshot($newsUrl, $this->finalize($newsUrl)['status']);
        }

        $cacheKey = $this->statusCacheKey($newsUrl);
        $ttl = now()->addSeconds(max(1, min(600, now()->diffInSeconds($newsUrl->voting_closes_at, false))));

        return Cache::store(config('truthshield.status_cache_store'))->remember($cacheKey, $ttl, fn () => $this->buildStatusPayload($newsUrl));
    }

    public function evidenceForFingerprint(array $fingerprint): array
    {
        $newsUrl = NewsUrl::query()->where('hash', $fingerprint['hash'])->first();

        if (! $newsUrl) {
            return [];
        }

        $this->ensureVotingWindow($newsUrl);

        if (! $this->isOpen($newsUrl)) {
            return $this->finalize($newsUrl)['evidence'];
        }

        return $this->buildEvidencePayload($newsUrl);
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
        Cache::store(config('truthshield.status_cache_store'))->forget($this->statusCacheKey($newsUrl));
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
        $status['snapshot'] = $this->snapshots->statusPayload($newsUrl);

        return $status;
    }

    private function buildStatusPayload(NewsUrl $newsUrl, ?Carbon $finalizedAt = null): array
    {
        $rows = $newsUrl->votes()
            ->select('tag_id', DB::raw('SUM(weight_score) as total_weight'))
            ->with('tag:id,name,slug,color,severity,requires_evidence')
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

        return [
            'url_hash' => $newsUrl->hash,
            'normalized_url' => $newsUrl->normalized_url,
            'top_tag' => $top?->tag,
            'distribution' => $distribution,
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
            ->with(['tag:id,name,slug,color,severity', 'user:id,name,trust_score', 'evidence:id,vote_id,archive_url,preview_url,quality_score,snapshot_status'])
            ->withSum(['reactions as helpful_weight' => fn ($query) => $query->where('helpful', true)], 'weight_score')
            ->withSum(['reactions as unhelpful_weight' => fn ($query) => $query->where('helpful', false)], 'weight_score')
            ->withCount(['reactions as helpful_count' => fn ($query) => $query->where('helpful', true)])
            ->withCount(['reactions as unhelpful_count' => fn ($query) => $query->where('helpful', false)])
            ->orderByDesc(
                \App\Models\Evidence::query()
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
                    'name' => $vote->user?->name,
                    'trust_score' => round((float) $vote->user?->trust_score, 2),
                ],
                'vote_weight' => round((float) $vote->weight_score, 4),
                'helpful_count' => $vote->helpful_count,
                'unhelpful_count' => $vote->unhelpful_count,
                'helpful_weight' => round((float) ($vote->helpful_weight ?? 0), 4),
                'unhelpful_weight' => round((float) ($vote->unhelpful_weight ?? 0), 4),
                'net_helpful_weight' => round((float) ($vote->helpful_weight ?? 0) - (float) ($vote->unhelpful_weight ?? 0), 4),
            ])
            ->values()
            ->all();
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
        return [
            'url_hash' => $hash,
            'normalized_url' => $normalizedUrl,
            'top_tag' => null,
            'distribution' => [],
            'display_text' => '尚無足夠投票資料',
            'tone' => 'neutral',
            'percentage' => 0.0,
            'total_weight' => 0.0,
            'is_open' => false,
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

    private function displayText(?string $severity, float $percentage, ?string $tagName): string
    {
        if (! $tagName) {
            return '尚無足夠投票資料';
        }

        if ($severity === 'positive') {
            return '✅ 多數專家推薦：' . $tagName;
        }

        return '⚠️ ' . (int) round($percentage) . '% 使用者標註：' . $tagName;
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

    private function statusCacheKey(NewsUrl $newsUrl): string
    {
        $version = config('truthshield.status_cache_version', 'v1');

        return "news:status:{$version}:{$newsUrl->hash}";
    }

    private function missingStatusCacheKey(string $hash): string
    {
        $version = config('truthshield.status_cache_version', 'v1');

        return "news:status:missing:{$version}:{$hash}";
    }
}
