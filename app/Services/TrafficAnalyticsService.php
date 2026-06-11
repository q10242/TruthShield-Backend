<?php

namespace App\Services;

use App\Models\TrafficDailySummary;
use App\Models\TrafficEvent;
use App\Models\TrafficHourlySummary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrafficAnalyticsService
{
    private const MAX_METADATA_KEYS = 16;

    private static ?bool $trafficEventsTableExists = null;

    public function recordFromRequest(Request $request, array $overrides = []): ?TrafficEvent
    {
        return $this->record([
            'event_type' => $overrides['event_type'] ?? 'api_request',
            'source' => $overrides['source'] ?? 'api',
            'feature' => $overrides['feature'] ?? $this->featureForPath('/'.ltrim($request->path(), '/')),
            'path' => $overrides['path'] ?? $this->safePath('/'.ltrim($request->path(), '/')),
            'method' => $overrides['method'] ?? $request->method(),
            'domain' => $overrides['domain'] ?? $this->domainFromRequest($request),
            'url_hash' => $overrides['url_hash'] ?? $this->hashUrl($request->query('url')),
            'session_hash' => $overrides['session_hash'] ?? $this->sessionHash($request),
            'user_id' => $overrides['user_id'] ?? $request->user()?->id,
            'status_code' => $overrides['status_code'] ?? null,
            'duration_ms' => $overrides['duration_ms'] ?? null,
            'success' => $overrides['success'] ?? true,
            'cache_status' => $overrides['cache_status'] ?? null,
            'locale' => $overrides['locale'] ?? $this->locale($request),
            'sample_rate' => $overrides['sample_rate'] ?? $this->sampleRateForPath('/'.ltrim($request->path(), '/')),
            'metadata' => $overrides['metadata'] ?? [],
        ]);
    }

    public function record(array $payload): ?TrafficEvent
    {
        if (! (bool) config('truthshield_traffic.enabled', true) || ! $this->trafficEventsTableExists()) {
            return null;
        }

        $sampleRate = max(0.0001, min(1.0, (float) ($payload['sample_rate'] ?? 1)));
        if ($sampleRate < 1.0 && mt_rand() / mt_getrandmax() > $sampleRate) {
            return null;
        }

        return TrafficEvent::query()->create([
            'event_type' => $this->string($payload['event_type'] ?? 'event', 80),
            'source' => $this->string($payload['source'] ?? 'web', 40),
            'feature' => $this->nullableString($payload['feature'] ?? null, 80),
            'path' => $this->safePath($payload['path'] ?? null),
            'method' => $this->nullableString($payload['method'] ?? null, 12),
            'domain' => $this->normalizeDomain($payload['domain'] ?? null),
            'url_hash' => $this->nullableString($payload['url_hash'] ?? null, 128),
            'session_hash' => $this->nullableString($payload['session_hash'] ?? null, 128),
            'user_id' => $payload['user_id'] ?? null,
            'status_code' => $payload['status_code'] ?? null,
            'duration_ms' => $payload['duration_ms'] ?? null,
            'success' => (bool) ($payload['success'] ?? true),
            'cache_status' => $this->nullableString($payload['cache_status'] ?? null, 24),
            'locale' => $this->nullableString($payload['locale'] ?? null, 16),
            'sample_rate' => $sampleRate,
            'metadata' => $this->sanitizeMetadata($payload['metadata'] ?? []),
        ]);
    }

    public function aggregate(?Carbon $from = null, ?Carbon $to = null): array
    {
        if (! $this->trafficEventsTableExists()) {
            return ['hourly' => 0, 'daily' => 0];
        }

        $from ??= now()->subDays(2)->startOfDay();
        $to ??= now();

        return [
            'hourly' => $this->aggregateHourly($from, $to),
            'daily' => $this->aggregateDaily($from, $to),
        ];
    }

    public function prune(int $rawDays = 14, int $summaryDays = 400): array
    {
        $events = Schema::hasTable('traffic_events')
            ? TrafficEvent::query()->where('created_at', '<', now()->subDays($rawDays))->delete()
            : 0;
        $hourly = Schema::hasTable('traffic_hourly_summaries')
            ? TrafficHourlySummary::query()->where('bucket_at', '<', now()->subDays($summaryDays))->delete()
            : 0;
        $daily = Schema::hasTable('traffic_daily_summaries')
            ? TrafficDailySummary::query()->where('bucket_date', '<', now()->subDays($summaryDays)->toDateString())->delete()
            : 0;

        return compact('events', 'hourly', 'daily');
    }

    public function publicSummary(): array
    {
        return Cache::store(config('truthshield.status_cache_store'))->remember('traffic:public-summary:v1', now()->addSeconds(30), function (): array {
            $today = now()->toDateString();
            $hour = now()->startOfHour();
            $todayRows = Schema::hasTable('traffic_daily_summaries')
                ? TrafficDailySummary::query()->where('bucket_date', $today)->get()
                : collect();
            $rawToday = Schema::hasTable('traffic_events')
                ? TrafficEvent::query()->where('created_at', '>=', now()->startOfDay())->get()
                : collect();

            $events = $todayRows->isNotEmpty() ? $todayRows : $rawToday;

            $sum = fn (callable $filter): int => (int) $events
                ->filter($filter)
                ->sum(fn ($row) => (int) ($row->estimated_count ?? round(1 / max(0.0001, (float) ($row->sample_rate ?? 1)))));

            $statusEvents = $rawToday->filter(fn ($row) => ($row->feature ?? null) === 'news_status');
            $hits = $this->sumEstimated($statusEvents->filter(fn ($row) => ($row->cache_status ?? null) === 'hit'));
            $misses = $this->sumEstimated($statusEvents->filter(fn ($row) => ($row->cache_status ?? null) === 'miss'));
            $statusTotal = $hits + $misses;

            return [
                'today_api_requests' => $sum(fn ($row) => ($row->source ?? null) === 'api'),
                'today_status_queries' => $sum(fn ($row) => ($row->feature ?? null) === 'news_status'),
                'today_extension_events' => $sum(fn ($row) => ($row->source ?? null) === 'extension'),
                'today_active_extension_clients' => $this->uniqueSessionsForToday('extension'),
                'today_banner_views' => $sum(fn ($row) => ($row->event_type ?? null) === 'banner_view'),
                'today_tooltip_views' => $sum(fn ($row) => ($row->event_type ?? null) === 'tooltip_view'),
                'today_vote_panel_opens' => $sum(fn ($row) => ($row->event_type ?? null) === 'vote_panel_open'),
                'today_votes' => $sum(fn ($row) => ($row->event_type ?? null) === 'vote_completed'),
                'today_evidence_submissions' => $sum(fn ($row) => ($row->event_type ?? null) === 'evidence_submitted'),
                'today_reports' => $sum(fn ($row) => in_array($row->event_type ?? null, ['domain_report_completed', 'evidence_report_completed', 'bug_report_completed'], true)),
                'extension_zip_downloads_today' => $sum(fn ($row) => ($row->event_type ?? null) === 'extension_zip_download'),
                'cache_hit_rate' => $statusTotal > 0 ? round(($hits / $statusTotal) * 100, 2) : null,
                'error_rate' => $this->errorRateForToday($events),
                'current_hour_events' => Schema::hasTable('traffic_events') ? TrafficEvent::query()->where('created_at', '>=', $hour)->count() : 0,
            ];
        });
    }

    public function sessionHash(Request $request): string
    {
        $salt = now()->toDateString().'|'.config('app.key');
        $subject = implode('|', [
            $request->ip(),
            substr((string) $request->userAgent(), 0, 240),
            $request->header('X-TruthShield-Install-Id', ''),
        ]);

        return hash('sha256', $salt.'|'.$subject);
    }

    public function hashUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return hash('sha256', Str::limit($url, 4096, ''));
    }

    public function featureForPath(?string $path): ?string
    {
        $path = '/'.ltrim((string) $path, '/');

        return match (true) {
            str_starts_with($path, '/api/news/status') => 'news_status',
            str_starts_with($path, '/api/vote') => 'vote',
            str_starts_with($path, '/api/evidence/') => 'evidence',
            str_starts_with($path, '/api/news-domain-reports') => 'domain_report',
            str_starts_with($path, '/api/url-classification-reports') => 'url_classification',
            str_starts_with($path, '/api/trusted-source-suggestions') => 'trusted_source_suggestion',
            str_starts_with($path, '/api/bug-reports') => 'bug_report',
            str_starts_with($path, '/api/auth/') => 'auth',
            str_starts_with($path, '/api/extension/') => 'extension',
            str_starts_with($path, '/api/donations') => 'donation',
            str_starts_with($path, '/truthshield-extension.zip') => 'extension_download',
            default => null,
        };
    }

    public function sampleRateForPath(?string $path): float
    {
        $path = '/'.ltrim((string) $path, '/');

        return match (true) {
            str_starts_with($path, '/api/news/status') => (float) config('truthshield_traffic.status_sample_rate', 0.1),
            str_starts_with($path, '/api/system/health') => 0.05,
            str_starts_with($path, '/api/transparency') => 0.2,
            default => 1.0,
        };
    }

    private function aggregateHourly(Carbon $from, Carbon $to): int
    {
        $bucketExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H:00:00', created_at)"
            : "date_trunc('hour', created_at)";

        $rows = TrafficEvent::query()
            ->selectRaw("{$bucketExpression} as bucket_at, event_type, source, feature, domain")
            ->selectRaw('count(*) as events_count')
            ->selectRaw('round(sum(1 / sample_rate)) as estimated_count')
            ->selectRaw('sum(case when success then 1 else 0 end) as success_count')
            ->selectRaw('sum(case when success then 0 else 1 end) as error_count')
            ->selectRaw('count(distinct session_hash) as unique_sessions')
            ->selectRaw('round(avg(duration_ms)) as avg_duration_ms')
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw("{$bucketExpression}, event_type, source, feature, domain")
            ->get();

        foreach ($rows as $row) {
            TrafficHourlySummary::query()->updateOrCreate(
                [
                    'bucket_at' => Carbon::parse($row->bucket_at),
                    'event_type' => $row->event_type,
                    'source' => $row->source,
                    'feature' => $row->feature,
                    'domain' => $row->domain,
                ],
                $this->summaryPayload($row),
            );
        }

        return $rows->count();
    }

    private function aggregateDaily(Carbon $from, Carbon $to): int
    {
        $bucketExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'date(created_at)'
            : 'date(created_at)';

        $rows = TrafficEvent::query()
            ->selectRaw("{$bucketExpression} as bucket_date, event_type, source, feature, domain")
            ->selectRaw('count(*) as events_count')
            ->selectRaw('round(sum(1 / sample_rate)) as estimated_count')
            ->selectRaw('sum(case when success then 1 else 0 end) as success_count')
            ->selectRaw('sum(case when success then 0 else 1 end) as error_count')
            ->selectRaw('count(distinct session_hash) as unique_sessions')
            ->selectRaw('round(avg(duration_ms)) as avg_duration_ms')
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw("{$bucketExpression}, event_type, source, feature, domain")
            ->get();

        foreach ($rows as $row) {
            TrafficDailySummary::query()->updateOrCreate(
                [
                    'bucket_date' => $row->bucket_date,
                    'event_type' => $row->event_type,
                    'source' => $row->source,
                    'feature' => $row->feature,
                    'domain' => $row->domain,
                ],
                $this->summaryPayload($row),
            );
        }

        return $rows->count();
    }

    private function summaryPayload(object $row): array
    {
        return [
            'events_count' => (int) $row->events_count,
            'estimated_count' => (int) $row->estimated_count,
            'success_count' => (int) $row->success_count,
            'error_count' => (int) $row->error_count,
            'unique_sessions' => (int) $row->unique_sessions,
            'avg_duration_ms' => $row->avg_duration_ms !== null ? (int) $row->avg_duration_ms : null,
        ];
    }

    private function uniqueSessionsForToday(string $source): int
    {
        if (! $this->trafficEventsTableExists()) {
            return 0;
        }

        return TrafficEvent::query()
            ->where('source', $source)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotNull('session_hash')
            ->distinct('session_hash')
            ->count('session_hash');
    }

    private function sumEstimated($events): int
    {
        return (int) $events->sum(
            fn ($row) => (int) ($row->estimated_count ?? round(1 / max(0.0001, (float) ($row->sample_rate ?? 1))))
        );
    }

    private function errorRateForToday($events): ?float
    {
        $total = $this->sumEstimated($events);
        if ($total <= 0) {
            return null;
        }

        $errors = $this->sumEstimated($events->filter(fn ($row) => ! (bool) ($row->success ?? true)));

        return round(($errors / $total) * 100, 2);
    }

    private function domainFromRequest(Request $request): ?string
    {
        $url = $request->query('url');
        if (is_string($url)) {
            return $this->normalizeDomain(parse_url($url, PHP_URL_HOST));
        }

        return null;
    }

    private function locale(Request $request): ?string
    {
        $locale = $request->query('locale') ?: $request->header('Accept-Language');

        return is_string($locale) ? Str::limit($locale, 16, '') : null;
    }

    private function safePath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return Str::limit(Str::before($path, '?'), 255, '');
    }

    private function normalizeDomain(mixed $domain): ?string
    {
        if (! is_string($domain) || $domain === '') {
            return null;
        }

        return Str::limit(strtolower($domain), 255, '');
    }

    private function sanitizeMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        return collect($metadata)
            ->reject(fn ($value, $key): bool => in_array(strtolower((string) $key), ['url', 'full_url', 'href', 'location', 'referrer', 'referer'], true))
            ->take(self::MAX_METADATA_KEYS)
            ->mapWithKeys(function ($value, $key): array {
                $safeKey = Str::limit((string) $key, 80, '');
                if (is_scalar($value) || $value === null) {
                    return [$safeKey => is_string($value) ? Str::limit($value, 255, '') : $value];
                }

                return [$safeKey => '[complex]'];
            })
            ->all();
    }

    private function nullableString(mixed $value, int $limit): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Str::limit($value, $limit, '');
    }

    private function string(mixed $value, int $limit): string
    {
        return Str::limit((string) $value, $limit, '');
    }

    private function trafficEventsTableExists(): bool
    {
        return self::$trafficEventsTableExists ??= Schema::hasTable('traffic_events');
    }
}
