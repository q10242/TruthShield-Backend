<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\Vote;

class EvidenceSyncService
{
    public function syncFromVote(Vote $vote, ?array $inspection = null): ?Evidence
    {
        if (! $vote->evidence_url) {
            $vote->evidence()?->delete();
            return null;
        }

        $helpful = (float) $vote->reactions()->where('helpful', true)->sum('weight_score');
        $unhelpful = (float) $vote->reactions()->where('helpful', false)->sum('weight_score');
        $credibility = (float) $vote->reactions()->whereNotNull('credibility')->avg('credibility');
        $relevance = (float) $vote->reactions()->whereNotNull('relevance')->avg('relevance');
        $directionWeights = [
            'supports' => (float) $vote->reactions()->where('direction', 'supports')->sum('weight_score'),
            'refutes' => (float) $vote->reactions()->where('direction', 'refutes')->sum('weight_score'),
            'contextual' => (float) $vote->reactions()->where('direction', 'contextual')->sum('weight_score'),
        ];
        $ratingBonus = (($credibility ?: 3.0) - 3.0) * 4 + (($relevance ?: 3.0) - 3.0) * 4;
        $trustedBonus = $vote->evidence_safety === 'trusted' ? 10 : 0;
        $quality = round(max(0, min(100, 50 + (($helpful - $unhelpful) * 5) + $ratingBonus + $trustedBonus + ((float) $vote->weight_score * 2))), 2);

        return Evidence::query()->updateOrCreate(
            ['vote_id' => $vote->id],
            [
                'news_url_id' => $vote->news_url_id,
                'user_id' => $vote->user_id,
                'url' => $vote->evidence_url,
                'host' => $vote->evidence_host,
                'type' => $vote->evidence_type,
                'safety' => $vote->evidence_safety ?: 'unknown',
                'snapshot_status' => 'pending',
                'archive_url' => $this->archiveUrl($vote->evidence_url),
                'preview_url' => $inspection['preview_url'] ?? null,
                'quality_score' => $quality,
                'metadata' => [
                    'source' => 'vote_sync',
                    'helpful_weight' => $helpful,
                    'unhelpful_weight' => $unhelpful,
                    'avg_credibility' => $credibility ?: null,
                    'avg_relevance' => $relevance ?: null,
                    'direction_weights' => $directionWeights,
                ],
            ],
        );
    }

    private function archiveUrl(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (in_array($host, ['archive.ph', 'web.archive.org'], true)) {
            return $url;
        }

        foreach (config('truthshield.cloud_drive_evidence_hosts', []) as $allowedHost) {
            $allowedHost = strtolower((string) $allowedHost);

            if ($host === $allowedHost || str_ends_with($host, ".{$allowedHost}")) {
                return null;
            }
        }

        return 'https://web.archive.org/save/' . $url;
    }
}
