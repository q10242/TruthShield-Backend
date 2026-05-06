<?php

namespace App\Jobs;

use App\Models\NewsUrl;
use App\Services\NewsAggregationService;
use App\Services\TrustScoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizeNewsUrlJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $newsUrlId, public bool $settle = true) {}

    public function handle(NewsAggregationService $aggregation, TrustScoreService $trustScores): void
    {
        $newsUrl = NewsUrl::query()->find($this->newsUrlId);
        if (! $newsUrl || ($newsUrl->voting_closes_at && $newsUrl->voting_closes_at->isFuture())) {
            return;
        }

        $aggregation->finalizeNewsUrl($newsUrl);

        if ($this->settle) {
            $trustScores->settleNewsUrl($newsUrl->refresh());
        }
    }
}
