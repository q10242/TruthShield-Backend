<?php

namespace App\Jobs;

use App\Models\AbuseCluster;
use App\Models\AbuseEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DetectAbuseClustersJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        AbuseEvent::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('news_url_id, type, severity, count(*) as event_count, count(distinct user_id) as user_count')
            ->groupBy('news_url_id', 'type', 'severity')
            ->havingRaw('count(*) >= 3')
            ->get()
            ->each(function ($row): void {
                AbuseCluster::query()->firstOrCreate(
                    [
                        'news_url_id' => $row->news_url_id,
                        'type' => $row->type,
                        'severity' => $row->severity,
                    ],
                    [
                        'user_count' => (int) $row->user_count,
                        'event_count' => (int) $row->event_count,
                        'metadata' => ['window' => '24h', 'source' => 'job'],
                    ],
                );
            });
    }
}
