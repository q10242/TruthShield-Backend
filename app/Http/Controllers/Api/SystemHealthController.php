<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountEdge;
use App\Models\AbuseEvent;
use App\Models\ApiClient;
use App\Models\EvidenceReport;
use App\Models\ExtensionSelectorCheck;
use App\Models\ExtensionEvent;
use App\Models\NewsDomainReport;
use App\Models\NewsUrl;
use App\Models\OperationalEvent;
use App\Models\RateLimitPolicy;
use App\Models\TrustedEvidenceSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
    public function show(): JsonResponse
    {
        $database = true;
        $cache = true;

        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $database = false;
        }

        try {
            Cache::store(config('truthshield.status_cache_store'))->put('truthshield:health', 'ok', 30);
            $cache = Cache::store(config('truthshield.status_cache_store'))->get('truthshield:health') === 'ok';
        } catch (\Throwable) {
            $cache = false;
        }

        $metrics = Cache::store(config('truthshield.status_cache_store'))->remember(
            'system:health:metrics:v1',
            now()->addSeconds(15),
            function (): array {
                $latestHeartbeat = OperationalEvent::query()
                    ->where('type', 'queue_worker')
                    ->latest()
                    ->first();
                $queueHealthy = ! $latestHeartbeat || $latestHeartbeat->created_at->gte(now()->subMinutes(10));

                return [
                    'queue' => [
                        'healthy' => $queueHealthy,
                        'pending_jobs' => $this->tableCount('jobs'),
                        'failed_jobs' => $this->tableCount('failed_jobs'),
                        'latest_worker_heartbeat_at' => $latestHeartbeat?->created_at?->toJSON(),
                    ],
                    'counts' => [
                        'expired_unfinalized_news' => NewsUrl::query()
                            ->whereNotNull('voting_closes_at')
                            ->where('voting_closes_at', '<=', now())
                            ->whereNull('finalized_at')
                            ->count(),
                        'pending_domain_reports' => NewsDomainReport::query()->where('status', 'pending')->count(),
                        'pending_evidence_reports' => EvidenceReport::query()->where('status', 'pending')->count(),
                        'open_abuse_events' => AbuseEvent::query()->where('reviewed', false)->count(),
                        'extension_failures_24h' => ExtensionEvent::query()->where('success', false)->where('created_at', '>=', now()->subDay())->count(),
                        'high_risk_account_edges' => AccountEdge::query()->where('score', '>=', 50)->count(),
                        'active_api_clients' => ApiClient::query()->where('status', 'active')->count(),
                        'operational_events_24h' => OperationalEvent::query()->where('created_at', '>=', now()->subDay())->count(),
                        'selector_failures_24h' => ExtensionSelectorCheck::query()->where('success', false)->where('checked_at', '>=', now()->subDay())->count(),
                        'active_trusted_evidence_sources' => TrustedEvidenceSource::query()->where('is_active', true)->count(),
                        'active_rate_limit_policies' => RateLimitPolicy::query()->where('is_active', true)->count(),
                        'pending_evidence_snapshots' => \App\Models\Evidence::query()->where('snapshot_status', 'pending')->count(),
                    ],
                ];
            },
        );

        $queueHealthy = (bool) $metrics['queue']['healthy'];

        return response()->json([
            'ok' => $database && $cache && $queueHealthy,
            'database' => $database,
            'cache' => $cache,
            'queue' => [
                'connection' => config('queue.default'),
                ...$metrics['queue'],
            ],
            'counts' => $metrics['counts'],
            'thresholds' => [
                'min_read_seconds_before_vote' => (int) config('truthshield.min_read_seconds_before_vote', 15),
                'evidence_reaction_min_trust_score' => (float) config('truthshield.evidence_reaction_min_trust_score', 0.5),
                'algorithm_version' => config('truthshield.algorithm_version', 'truthshield-v1'),
                'status_cache_version' => config('truthshield.status_cache_version', 'v1'),
            ],
            'timestamp' => now()->toJSON(),
        ], $database && $cache && $queueHealthy ? 200 : 503);
    }

    private function tableCount(string $table): int
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }
}
