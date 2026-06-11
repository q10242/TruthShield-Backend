<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbuseEvent;
use App\Models\AccountEdge;
use App\Models\ApiClient;
use App\Models\BugReport;
use App\Models\Donation;
use App\Models\Evidence;
use App\Models\EvidenceReport;
use App\Models\ExtensionEvent;
use App\Models\ExtensionSelectorCheck;
use App\Models\NewsDomainReport;
use App\Models\NewsUrl;
use App\Models\OperationalEvent;
use App\Models\RateLimitPolicy;
use App\Models\TrafficEvent;
use App\Models\TrustedEvidenceSource;
use App\Models\UserDataRequest;
use App\Services\TrafficAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemHealthController extends Controller
{
    public function show(TrafficAnalyticsService $traffic): JsonResponse
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

        $metricsAvailable = true;
        try {
            $metrics = Cache::store(config('truthshield.status_cache_store'))->remember(
                'system:health:metrics:v3',
                now()->addSeconds(15),
                fn (): array => $this->buildMetrics(),
            );
        } catch (\Throwable $exception) {
            $metricsAvailable = false;
            $metrics = $this->fallbackMetrics();
            Log::warning('system_health.metrics_failed', ['message' => $exception->getMessage()]);
        }

        $trafficAvailable = true;
        try {
            $trafficSummary = $traffic->publicSummary();
        } catch (\Throwable $exception) {
            $trafficAvailable = false;
            $trafficSummary = ['available' => false];
            Log::warning('system_health.traffic_failed', ['message' => $exception->getMessage()]);
        }

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
        if (! $database) {
            $degradedReasons[] = 'database';
        }
        if (! $cache) {
            $degradedReasons[] = 'cache';
        }
        if (! $queueHealthy) {
            $degradedReasons[] = 'queue';
        }
        if (! $schedulerHealthy) {
            $degradedReasons[] = 'scheduler';
        }
        if (! $metricsAvailable) {
            $degradedReasons[] = 'metrics';
        }
        if (! $trafficAvailable) {
            $degradedReasons[] = 'traffic';
        }

        $healthy = $database && $cache && $queueHealthy && $schedulerHealthy && $metricsAvailable && $trafficAvailable;

        return response()->json([
            'ok' => $healthy,
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
            'traffic' => $trafficSummary,
            'governance_pressure_score' => min(100, $governancePressure * 10),
            'thresholds' => [
                'min_read_seconds_before_vote' => (int) config('truthshield.min_read_seconds_before_vote', 15),
                'evidence_reaction_min_trust_score' => (float) config('truthshield.evidence_reaction_min_trust_score', 0.5),
                'algorithm_version' => config('truthshield.algorithm_version', 'truthshield-v1'),
                'status_cache_version' => config('truthshield.status_cache_version', 'v1'),
            ],
            'timestamp' => now()->toJSON(),
        ], $healthy ? 200 : 503);
    }

    private function buildMetrics(): array
    {
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
                'selector_failures_24h' => ExtensionSelectorCheck::query()->actionableFailures()->where('checked_at', '>=', now()->subDay())->count(),
                'active_trusted_evidence_sources' => TrustedEvidenceSource::query()->where('is_active', true)->count(),
                'active_rate_limit_policies' => RateLimitPolicy::query()->where('is_active', true)->count(),
                'pending_evidence_snapshots' => Evidence::query()->where('snapshot_status', 'pending')->count(),
                'pending_donations' => Donation::query()->where('status', Donation::STATUS_PENDING)->count(),
                'paid_donations_24h' => Donation::query()->where('status', Donation::STATUS_PAID)->where('paid_at', '>=', now()->subDay())->count(),
                'pending_user_data_requests' => UserDataRequest::query()->where('status', 'pending')->count(),
                'open_bug_reports' => BugReport::query()->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                'open_security_reports' => BugReport::query()->where('report_type', 'security')->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                'critical_bug_reports' => BugReport::query()->where('severity', 'critical')->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                'traffic_events_24h' => $this->tableCount('traffic_events')
                    ? TrafficEvent::query()->where('created_at', '>=', now()->subDay())->count()
                    : 0,
            ],
        ];
    }

    private function fallbackMetrics(): array
    {
        return [
            'queue' => [
                'healthy' => false,
                'pending_jobs' => null,
                'failed_jobs' => null,
                'latest_worker_heartbeat_at' => null,
            ],
            'scheduler' => [
                'healthy' => false,
                'latest_scheduler_heartbeat_at' => null,
            ],
            'counts' => [
                'expired_unfinalized_news' => 0,
                'pending_domain_reports' => 0,
                'pending_evidence_reports' => 0,
                'open_abuse_events' => 0,
                'extension_failures_24h' => 0,
                'high_risk_account_edges' => 0,
                'active_api_clients' => 0,
                'operational_events_24h' => 0,
                'selector_failures_24h' => 0,
                'active_trusted_evidence_sources' => 0,
                'active_rate_limit_policies' => 0,
                'pending_evidence_snapshots' => 0,
                'pending_donations' => 0,
                'paid_donations_24h' => 0,
                'pending_user_data_requests' => 0,
                'open_bug_reports' => 0,
                'open_security_reports' => 0,
                'critical_bug_reports' => 0,
                'traffic_events_24h' => 0,
            ],
        ];
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
