<?php

namespace App\Services;

use App\Models\CommunityTask;
use App\Models\NewsCluster;
use App\Models\NewsUrl;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class NewsClusterService
{
    public function __construct(private readonly UrlFingerprintService $fingerprints) {}

    public function attach(NewsUrl $newsUrl, array $metadata = []): ?NewsCluster
    {
        $signals = $this->signalsFor($newsUrl, $metadata);

        if (! $signals['canonical_hash'] && ! $signals['content_hash'] && ! $signals['title_key']) {
            return null;
        }

        $cluster = $this->findExactCluster($signals)
            ?? NewsCluster::query()->create($this->clusterAttributes($signals, $metadata));

        $newsUrl->forceFill(['news_cluster_id' => $cluster->id])->save();

        $this->raiseLowConfidenceTask($newsUrl, $cluster, $signals);

        $cluster->forceFill([
            'canonical_hash' => $cluster->canonical_hash ?: $signals['canonical_hash'],
            'content_hash' => $cluster->content_hash ?: $signals['content_hash'],
            'source_host' => $cluster->source_host ?: $signals['source_host'],
            'title_key' => $cluster->title_key ?: $signals['title_key'],
            'canonical_title' => $cluster->canonical_title ?: $signals['canonical_title'],
            'url_count' => $cluster->newsUrls()->count(),
            'metadata' => [
                ...($cluster->metadata ?? []),
                'last_match_strategy' => $this->matchStrategy($signals, $cluster),
                'last_source' => Arr::get($metadata, 'source'),
            ],
            'last_matched_at' => now(),
        ])->save();

        return $cluster->refresh();
    }

    private function findExactCluster(array $signals): ?NewsCluster
    {
        if ($signals['canonical_hash']) {
            $cluster = NewsCluster::query()->where('canonical_hash', $signals['canonical_hash'])->first();
            if ($cluster) {
                return $cluster;
            }
        }

        if ($signals['content_hash']) {
            $cluster = NewsCluster::query()->where('content_hash', $signals['content_hash'])->first();
            if ($cluster) {
                return $cluster;
            }
        }

        if ($signals['source_host'] && $signals['title_key']) {
            return NewsCluster::query()
                ->where('source_host', $signals['source_host'])
                ->where('title_key', $signals['title_key'])
                ->first();
        }

        return null;
    }

    private function signalsFor(NewsUrl $newsUrl, array $metadata): array
    {
        $canonicalUrl = $metadata['canonical_url'] ?? $newsUrl->canonical_url;
        $canonicalHash = null;

        if ($canonicalUrl) {
            try {
                $canonicalHash = $this->fingerprints->fingerprint((string) $canonicalUrl)['hash'];
            } catch (InvalidArgumentException) {
                $canonicalHash = null;
            }
        }

        $contentHash = $this->normalizeContentHash($metadata['content_hash'] ?? $newsUrl->content_hash);
        $title = trim((string) ($metadata['title_snapshot'] ?? $metadata['title'] ?? $newsUrl->title_snapshot ?? ''));
        $sourceUrl = (string) ($canonicalUrl ?: $newsUrl->normalized_url ?: $newsUrl->original_url);

        return [
            'canonical_hash' => $canonicalHash,
            'content_hash' => $contentHash,
            'source_host' => $this->hostForUrl($sourceUrl),
            'title_key' => $this->titleKey($title),
            'canonical_title' => $title !== '' ? Str::limit($title, 255, '') : null,
        ];
    }

    private function clusterAttributes(array $signals, array $metadata): array
    {
        return [
            'canonical_hash' => $signals['canonical_hash'],
            'content_hash' => $signals['content_hash'],
            'source_host' => $signals['source_host'],
            'title_key' => $signals['title_key'],
            'canonical_title' => $signals['canonical_title'],
            'url_count' => 0,
            'metadata' => [
                'created_from' => Arr::get($metadata, 'source', 'unknown'),
                'created_by' => 'news_cluster_service',
            ],
            'last_matched_at' => now(),
        ];
    }

    private function raiseLowConfidenceTask(NewsUrl $newsUrl, NewsCluster $matchedCluster, array $signals): void
    {
        if (! $signals['title_key']) {
            return;
        }

        $candidateIds = NewsCluster::query()
            ->where('title_key', $signals['title_key'])
            ->where('id', '!=', $matchedCluster->id)
            ->when($signals['source_host'], fn ($query, string $host) => $query->where('source_host', '!=', $host))
            ->pluck('id')
            ->all();

        if ($candidateIds === []) {
            return;
        }

        $subjectKey = 'news-cluster-review:' . sha1($signals['title_key'] . ':' . implode(',', $candidateIds) . ':' . $newsUrl->id);

        CommunityTask::query()->updateOrCreate(
            ['type' => 'news_cluster_review', 'subject_key' => $subjectKey],
            [
                'subject_type' => 'news_cluster',
                'subject_id' => null,
                'title' => '疑似同篇新聞需要人工確認',
                'description' => '不同來源出現相近標題，請確認是否為同一篇新聞或入口轉載內容。',
                'priority' => 48,
                'status' => 'open',
                'action_url' => $newsUrl->normalized_url,
                'metrics' => [
                    'news_url_id' => $newsUrl->id,
                    'matched_cluster_id' => $matchedCluster->id,
                    'candidate_cluster_ids' => $candidateIds,
                    'title_key' => $signals['title_key'],
                    'source_host' => $signals['source_host'],
                ],
                'generation_snapshot' => [
                    'reason' => 'low_confidence_news_cluster_match',
                    'generated_at' => now()->toJSON(),
                ],
                'expires_at' => now()->addDays(14),
            ],
        );
    }

    private function matchStrategy(array $signals, NewsCluster $cluster): string
    {
        return match (true) {
            $signals['canonical_hash'] && $cluster->canonical_hash === $signals['canonical_hash'] => 'canonical_url_exact',
            $signals['content_hash'] && $cluster->content_hash === $signals['content_hash'] => 'content_hash_exact',
            $signals['source_host'] && $signals['title_key']
                && $cluster->source_host === $signals['source_host']
                && $cluster->title_key === $signals['title_key'] => 'source_host_title_key_exact',
            default => 'new_cluster',
        };
    }

    private function normalizeContentHash(mixed $hash): ?string
    {
        $hash = Str::lower(trim((string) $hash));

        return preg_match('/^[a-f0-9]{16,64}$/', $hash) === 1 ? $hash : null;
    }

    private function hostForUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host !== '' ? preg_replace('/^www\./', '', $host) : null;
    }

    private function titleKey(string $title): ?string
    {
        $key = Str::lower($title);
        $key = preg_replace('/\s+/u', '', $key) ?? '';
        $key = preg_replace('/[[:punct:]\p{P}\p{S}]+/u', '', $key) ?? '';

        return $key !== '' ? Str::limit($key, 180, '') : null;
    }
}
