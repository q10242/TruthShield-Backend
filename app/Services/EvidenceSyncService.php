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
        $trustedBonus = $vote->evidence_safety === 'trusted' ? 10 : 0;
        $quality = round(max(0, min(100, 50 + (($helpful - $unhelpful) * 5) + $trustedBonus + ((float) $vote->weight_score * 2))), 2);

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

        return 'https://web.archive.org/save/' . $url;
    }
}
