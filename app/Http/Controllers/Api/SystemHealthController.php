<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountEdge;
use App\Models\AbuseEvent;
use App\Models\ApiClient;
use App\Models\BugReport;
use App\Models\Donation;
use App\Models\EvidenceReport;
use App\Models\ExtensionSelectorCheck;
use App\Models\ExtensionEvent;
use App\Models\NewsDomainReport;
use App\Models\NewsUrl;
use App\Models\OperationalEvent;
use App\Models\RateLimitPolicy;
use App\Models\TrustedEvidenceSource;
use App\Models\UserDataRequest;
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
            'system:health:metrics:v2',
            now()->addSeconds(15),
            function (): array {
                $latestHeartbeat = OperationalEvent::query()
                    ->where('type', 'queue_worker')
                    ->latest()
                    ->first();
                $latestScheduleHeartbeat = OperationalEvent::query()
                    ->where('type', 'scheduler')
                    ->latest()
                    ->first();
                $queueHealthy = $this->heartbeatHealthy($latestHeartbeat, 10);
                $schedulerHealthy = $this->heartbeatHealthy($latestScheduleHeartbeat, 3);

                return [
                    'queue' => [
                        'healthy' => $queueHealthy,
                        'pending_jobs' => $this->tableCount('jobs'),
                        'failed_jobs' => $this->tableCount('failed_jobs'),
                        'latest_worker_heartbeat_at' => $latestHeartbeat?->created_at?->toJSON(),
                    ],
                    'scheduler' => [
                        'healthy' => $schedulerHealthy,
                        'latest_scheduler_heartbeat_at' => $latestScheduleHeartbeat?->created_at?->toJSON(),
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
                        'pending_donations' => Donation::query()->where('status', Donation::STATUS_PENDING)->count(),
                        'paid_donations_24h' => Donation::query()->where('status', Donation::STATUS_PAID)->where('paid_at', '>=', now()->subDay())->count(),
                        'pending_user_data_requests' => UserDataRequest::query()->where('status', 'pending')->count(),
                        'open_bug_reports' => BugReport::query()->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                        'open_security_reports' => BugReport::query()->where('report_type', 'security')->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                        'critical_bug_reports' => BugReport::query()->where('severity', 'critical')->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                    ],
                ];
            },
        );

        $queueHealthy = (bool) $metrics['queue']['healthy'];
        $schedulerHealthy = (bool) $metrics['scheduler']['healthy'];
        $governancePressure = (int) (
            ($metrics['counts']['pending_domain_reports'] ?? 0)
            + ($metrics['counts']['pending_evidence_reports'] ?? 0)
            + (($metrics['counts']['open_abuse_events'] ?? 0) * 2)
            + ($metrics['counts']['pending_user_data_requests'] ?? 0)
            + ($metrics['counts']['open_bug_reports'] ?? 0)
            + (($metrics['counts']['critical_bug_reports'] ?? 0) * 3)
        );
        $degradedReasons = [];
        if (! $database) $degradedReasons[] = 'database';
        if (! $cache) $degradedReasons[] = 'cache';
        if (! $queueHealthy) $degradedReasons[] = 'queue';
        if (! $schedulerHealthy) $degradedReasons[] = 'scheduler';

        return response()->json([
            'ok' => $database && $cache && $queueHealthy && $schedulerHealthy,
            'database' => $database,
            'cache' => $cache,
            'degraded_reasons' => $degradedReasons,
            'queue' => [
                'connection' => config('queue.default'),
                ...$metrics['queue'],
            ],
            'scheduler' => $metrics['scheduler'],
            'mail' => [
                'enabled' => (bool) config('truthshield.email_enabled', true),
                'mailer' => config('mail.default'),
                'from_address_configured' => filled(config('mail.from.address')),
                'limits' => config('truthshield.email_limits', []),
            ],
            'counts' => $metrics['counts'],
            'governance_pressure_score' => min(100, $governancePressure * 10),
            'thresholds' => [
                'min_read_seconds_before_vote' => (int) config('truthshield.min_read_seconds_before_vote', 15),
                'evidence_reaction_min_trust_score' => (float) config('truthshield.evidence_reaction_min_trust_score', 0.5),
                'algorithm_version' => config('truthshield.algorithm_version', 'truthshield-v1'),
                'status_cache_version' => config('truthshield.status_cache_version', 'v1'),
            ],
            'timestamp' => now()->toJSON(),
        ], $database && $cache && $queueHealthy && $schedulerHealthy ? 200 : 503);
    }

    private function tableCount(string $table): int
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    private function heartbeatHealthy(?OperationalEvent $event, int $freshMinutes): bool
    {
        if (! $event) {
            return config('app.env') !== 'production';
        }

        return $event->created_at->gte(now()->subMinutes($freshMinutes));
    }
}
