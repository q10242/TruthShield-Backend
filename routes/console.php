<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\DetectAbuseClustersJob;
use App\Jobs\FinalizeNewsUrlJob;
use App\Jobs\SnapshotEvidenceJob;
use App\Models\AbuseCluster;
use App\Models\AbuseEvent;
use App\Models\AccountEdge;
use App\Models\AccountSignal;
use App\Models\Evidence;
use App\Models\EvidenceSnapshot;
use App\Models\Donation;
use App\Models\ExtensionSelectorCheck;
use App\Models\OperationalEvent;
use App\Models\NewsUrl;
use App\Models\NewsDomain;
use App\Models\RateLimitPolicy;
use App\Models\TrustedEvidenceSource;
use App\Models\Vote;
use App\Services\AlgorithmVersionService;
use App\Services\EvidenceSyncService;
use App\Services\NewsAggregationService;
use App\Services\TrustScoreService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('truthshield:finalize-news {--settle}', function (NewsAggregationService $aggregation, TrustScoreService $trustScores) {
    $count = 0;
    $settled = 0;

    NewsUrl::query()
        ->whereNotNull('voting_closes_at')
        ->where('voting_closes_at', '<=', now())
        ->whereNull('finalized_at')
        ->chunkById(100, function ($newsUrls) use ($aggregation, $trustScores, &$count, &$settled): void {
            foreach ($newsUrls as $newsUrl) {
                FinalizeNewsUrlJob::dispatchSync($newsUrl->id, (bool) $this->option('settle'));
                $count++;
                $settled += $this->option('settle') ? 1 : 0;
            }
        });

    $this->info("Finalized {$count} news URLs; settled {$settled} trust score rows.");
})->purpose('Finalize expired TruthShield news URLs and optionally settle TrustScore.');

Artisan::command('truthshield:warm-cache', function (NewsAggregationService $aggregation) {
    $count = 0;

    NewsUrl::query()
        ->latest()
        ->limit(500)
        ->get()
        ->each(function (NewsUrl $newsUrl) use ($aggregation, &$count): void {
            $aggregation->statusForFingerprint([
                'hash' => $newsUrl->hash,
                'normalized_url' => $newsUrl->normalized_url,
            ]);
            $count++;
        });

    $this->info("Warmed {$count} status cache entries.");
})->purpose('Warm status cache for recently seen TruthShield URLs.');

Artisan::command('truthshield:ensure-algorithm-version', function (AlgorithmVersionService $versions) {
    $version = $versions->ensureCurrent();
    $this->info("Algorithm version ready: {$version->version}");
})->purpose('Ensure current TruthShield algorithm version metadata exists.');

Artisan::command('truthshield:sync-evidence {--limit=500}', function (EvidenceSyncService $sync) {
    $count = 0;
    Vote::query()
        ->whereNotNull('evidence_url')
        ->latest()
        ->limit((int) $this->option('limit'))
        ->get()
        ->each(function (Vote $vote) use ($sync, &$count): void {
            $sync->syncFromVote($vote);
            $count++;
        });

    $this->info("Synced {$count} evidence rows.");
})->purpose('Backfill and refresh primary evidence rows from votes.');

Artisan::command('truthshield:snapshot-evidence {--limit=100}', function () {
    $count = 0;
    Evidence::query()
        ->whereIn('snapshot_status', ['pending', 'failed'])
        ->latest()
        ->limit((int) $this->option('limit'))
        ->get()
        ->each(function (Evidence $evidence) use (&$count): void {
            SnapshotEvidenceJob::dispatchSync($evidence->id);
            $count++;
        });

    $this->info("Processed {$count} evidence snapshots.");
})->purpose('Create evidence snapshot records and mark snapshot status.');

Artisan::command('truthshield:detect-abuse-clusters', function () {
    DetectAbuseClustersJob::dispatchSync();
    $this->info('Abuse cluster detection job completed.');
})->purpose('Aggregate recent abuse events into reviewable clusters.');

Artisan::command('truthshield:build-account-graph', function () {
    $edges = 0;

    AccountSignal::query()
        ->where('created_at', '>=', now()->subDays(7))
        ->whereNotNull('user_id')
        ->selectRaw('signal_type, signal_hash, count(distinct user_id) as user_count')
        ->groupBy('signal_type', 'signal_hash')
        ->havingRaw('count(distinct user_id) >= 2')
        ->get()
        ->each(function ($signal) use (&$edges): void {
            $userIds = AccountSignal::query()
                ->where('signal_type', $signal->signal_type)
                ->where('signal_hash', $signal->signal_hash)
                ->distinct()
                ->pluck('user_id')
                ->filter()
                ->values();

            for ($i = 0; $i < $userIds->count(); $i++) {
                for ($j = $i + 1; $j < $userIds->count(); $j++) {
                    $edgeType = match ($signal->signal_type) {
                        'ip_hash' => 'shared_ip',
                        'user_agent_hash' => 'shared_user_agent',
                        default => 'shared_' . $signal->signal_type,
                    };

                    AccountEdge::query()->updateOrCreate(
                        [
                            'source_user_id' => min($userIds[$i], $userIds[$j]),
                            'target_user_id' => max($userIds[$i], $userIds[$j]),
                            'edge_type' => $edgeType,
                        ],
                        [
                            'score' => min(100, (int) $signal->user_count * 10),
                            'metadata' => ['signal_hash' => $signal->signal_hash, 'window' => '7d'],
                        ],
                    );
                    $edges++;
                }
            }
        });

    $this->info("Built {$edges} account graph edges.");
})->purpose('Build account graph edges from shared signals.');

Artisan::command('truthshield:record-operational-heartbeat {type=queue_worker}', function () {
    OperationalEvent::query()->create([
        'type' => $this->argument('type'),
        'status' => 'ok',
        'metadata' => ['recorded_at' => now()->toJSON()],
    ]);

    $this->info('Heartbeat recorded.');
})->purpose('Record operational heartbeat for health checks.');

Artisan::command('truthshield:check-production-env', function () {
    $required = [
        'APP_KEY',
        'APP_URL',
        'FRONTEND_URL',
        'DB_HOST',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'REDIS_HOST',
        'ECPAY_MERCHANT_ID',
        'ECPAY_HASH_KEY',
        'ECPAY_HASH_IV',
        'ECPAY_API_BASE_URL',
        'ECPAY_WEB_BASE_URL',
    ];
    $missing = array_values(array_filter($required, fn (string $key) => blank(env($key))));

    if ($missing !== []) {
        $this->error('Missing production env: ' . implode(', ', $missing));
        return 1;
    }

    if ((bool) config('truthshield.dev_login_enabled')) {
        $this->warn('TRUTHSHIELD_DEV_LOGIN_ENABLED is enabled. Disable it before production launch.');
    }

    $this->info('Production environment checklist passed.');
    return 0;
})->purpose('Validate required TruthShield production environment variables.');

Artisan::command('truthshield:expire-pending-donations {--hours=24}', function () {
    $hours = max(1, (int) $this->option('hours'));
    $count = Donation::query()
        ->where('status', 'pending')
        ->where('created_at', '<=', now()->subHours($hours))
        ->update(['status' => 'expired']);

    $this->info("Expired {$count} pending donation orders older than {$hours} hours.");
})->purpose('Expire stale pending donation orders.');

Artisan::command('truthshield:seed-launch-policies', function () {
    $policies = [
        ['name' => 'hover', 'scope' => 'public_status', 'max_attempts' => 600, 'decay_seconds' => 60, 'low_trust_multiplier' => 1],
        ['name' => 'vote', 'scope' => 'authenticated_write', 'max_attempts' => 30, 'decay_seconds' => 60, 'low_trust_multiplier' => 0.5],
        ['name' => 'reaction', 'scope' => 'authenticated_write', 'max_attempts' => 60, 'decay_seconds' => 60, 'low_trust_multiplier' => 0.5],
        ['name' => 'report', 'scope' => 'authenticated_moderation', 'max_attempts' => 10, 'decay_seconds' => 60, 'low_trust_multiplier' => 0.25],
    ];

    foreach ($policies as $policy) {
        RateLimitPolicy::query()->updateOrCreate(['name' => $policy['name']], $policy + ['is_active' => true]);
    }

    foreach (config('truthshield.trusted_evidence_hosts', []) as $host) {
        TrustedEvidenceSource::query()->updateOrCreate(
            ['host' => $host],
            ['source_type' => 'configured', 'trust_bonus' => 10, 'is_active' => true],
        );
    }

    foreach (config('truthshield.cloud_drive_evidence_hosts', []) as $host) {
        TrustedEvidenceSource::query()->updateOrCreate(
            ['host' => $host],
            [
                'source_type' => 'cloud_drive',
                'trust_bonus' => 6,
                'is_active' => true,
                'notes' => '外部雲端硬碟證據來源；TruthShield 只保存連結，不代管圖片。',
            ],
        );
    }

    $this->info('Launch policies seeded.');
})->purpose('Seed launch rate-limit policies and trusted evidence sources.');

Artisan::command('truthshield:check-extension-selectors', function () {
    $count = 0;

    NewsDomain::query()->where('is_active', true)->get()->each(function (NewsDomain $domain) use (&$count): void {
        $selectors = array_filter([$domain->article_selector, $domain->title_selector, $domain->content_selector]);

        foreach (['article_selector' => $domain->article_selector, 'title_selector' => $domain->title_selector, 'content_selector' => $domain->content_selector] as $type => $selector) {
            ExtensionSelectorCheck::query()->create([
                'news_domain_id' => $domain->id,
                'domain' => $domain->domain,
                'check_type' => $type,
                'selector' => $selector,
                'success' => filled($selector) || $type === 'article_selector',
                'checked_at' => now(),
                'metadata' => ['configured_selector_count' => count($selectors), 'mode' => 'static_config_check'],
            ]);
            $count++;
        }
    });

    $this->info("Recorded {$count} selector checks.");
})->purpose('Record static extension selector compatibility checks.');

Artisan::command('truthshield:import-selector-fixtures {path=database/fixtures/extension_selectors.json}', function () {
    $path = base_path($this->argument('path'));
    if (! File::exists($path)) {
        $this->error("Fixture file not found: {$path}");
        return 1;
    }

    $rows = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
    $count = 0;

    foreach ($rows as $row) {
        NewsDomain::query()->updateOrCreate(
            ['domain' => strtolower($row['domain'])],
            [
                'is_active' => true,
                'article_selector' => $row['article_selector'] ?? null,
                'title_selector' => $row['title_selector'] ?? null,
                'content_selector' => $row['content_selector'] ?? null,
                'priority' => $row['priority'] ?? 0,
            ],
        );
        $count++;
    }

    $this->info("Imported {$count} extension selector fixtures.");
    return 0;
})->purpose('Import extension selector fixtures for mainstream news domains.');

Artisan::command('truthshield:refresh-evidence-quality {--limit=500}', function (EvidenceSyncService $sync) {
    $count = 0;

    Vote::query()
        ->whereNotNull('evidence_url')
        ->latest()
        ->limit((int) $this->option('limit'))
        ->get()
        ->each(function (Vote $vote) use ($sync, &$count): void {
            $sync->syncFromVote($vote);
            $count++;
        });

    $this->info("Refreshed {$count} evidence quality scores.");
})->purpose('Refresh evidence quality scores from weighted reactions.');

Artisan::command('truthshield:stress-status {url?} {--requests=200}', function (NewsAggregationService $aggregation, \App\Services\UrlFingerprintService $fingerprints) {
    $url = $this->argument('url') ?: 'https://www.cna.com.tw/news/aipl/202605060001.aspx';
    $requests = max(1, (int) $this->option('requests'));
    $fingerprint = $fingerprints->fingerprint($url);
    $started = microtime(true);

    for ($i = 0; $i < $requests; $i++) {
        $aggregation->statusForFingerprint($fingerprint);
    }

    $elapsedMs = round((microtime(true) - $started) * 1000, 2);
    $this->info(json_encode([
        'url' => $url,
        'requests' => $requests,
        'elapsed_ms' => $elapsedMs,
        'avg_ms' => round($elapsedMs / $requests, 3),
        'cache_store' => config('truthshield.status_cache_store'),
        'cache_version' => config('truthshield.status_cache_version'),
    ], JSON_PRETTY_PRINT));
})->purpose('Run a local status endpoint aggregation stress loop.');

Artisan::command('truthshield:stress-http-status {url?} {--requests=50} {--base-url=}', function () {
    $baseUrl = rtrim($this->option('base-url') ?: config('app.url'), '/');
    $url = $this->argument('url') ?: 'https://www.cna.com.tw/news/aipl/202605060001.aspx';
    $requests = max(1, (int) $this->option('requests'));
    $latencies = [];
    $failures = 0;

    for ($i = 0; $i < $requests; $i++) {
        $started = microtime(true);
        try {
            $response = Http::timeout(5)->acceptJson()->get($baseUrl . '/api/news/status', ['url' => $url]);
            if (! $response->ok()) {
                $failures++;
            }
        } catch (\Throwable) {
            $failures++;
        }
        $latencies[] = (microtime(true) - $started) * 1000;
    }

    sort($latencies);
    $percentile = fn (float $p) => round($latencies[(int) min(count($latencies) - 1, floor((count($latencies) - 1) * $p))], 2);

    $this->info(json_encode([
        'base_url' => $baseUrl,
        'url' => $url,
        'requests' => $requests,
        'failures' => $failures,
        'p50_ms' => $percentile(0.50),
        'p95_ms' => $percentile(0.95),
        'p99_ms' => $percentile(0.99),
    ], JSON_PRETTY_PRINT));
})->purpose('Run real HTTP status endpoint latency smoke test.');

Artisan::command('truthshield:stress-http-launch {url?} {--requests=20} {--base-url=}', function () {
    $baseUrl = rtrim($this->option('base-url') ?: config('app.url'), '/');
    $url = $this->argument('url') ?: 'https://www.cna.com.tw/news/aipl/202605060001.aspx';
    $endpoints = [
        'status' => ['/api/news/status', ['url' => $url]],
        'evidence' => ['/api/news/evidence', ['url' => $url]],
        'domains' => ['/api/news-domains', []],
        'extension-events' => ['/api/extension/events/batch', null],
    ];
    $requests = max(1, (int) $this->option('requests'));
    $summary = [];

    foreach ($endpoints as $name => [$path, $query]) {
        $latencies = [];
        $failures = 0;

        for ($i = 0; $i < $requests; $i++) {
            $started = microtime(true);
            try {
                $response = $query === null
                    ? Http::timeout(5)->acceptJson()->post($baseUrl . $path, [
                        'events' => [[
                            'domain' => 'load-test.local',
                            'event_type' => 'load_test',
                            'success' => true,
                            'metadata' => ['iteration' => $i],
                        ]],
                    ])
                    : Http::timeout(5)->acceptJson()->get($baseUrl . $path, $query);

                if (! $response->successful()) {
                    $failures++;
                }
            } catch (\Throwable) {
                $failures++;
            }

            $latencies[] = (microtime(true) - $started) * 1000;
        }

        sort($latencies);
        $percentile = fn (float $p) => round($latencies[(int) min(count($latencies) - 1, floor((count($latencies) - 1) * $p))], 2);
        $summary[$name] = [
            'requests' => $requests,
            'failures' => $failures,
            'p50_ms' => $percentile(0.50),
            'p95_ms' => $percentile(0.95),
            'p99_ms' => $percentile(0.99),
        ];
    }

    $this->info(json_encode([
        'base_url' => $baseUrl,
        'url' => $url,
        'summary' => $summary,
    ], JSON_PRETTY_PRINT));
})->purpose('Run launch hot endpoint HTTP latency smoke tests.');

Artisan::command('truthshield:explain-hot-queries', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->warn('EXPLAIN checks are only available on PostgreSQL.');
        return 0;
    }

    $queries = [
        'status_aggregation' => <<<'SQL'
EXPLAIN (FORMAT JSON)
SELECT tag_id, SUM(weight_score) AS total_weight
FROM votes
WHERE news_url_id = (SELECT id FROM news_urls ORDER BY id DESC LIMIT 1)
GROUP BY tag_id
ORDER BY total_weight DESC
SQL,
        'evidence_library_search' => <<<'SQL'
EXPLAIN (FORMAT JSON)
SELECT votes.id
FROM votes
WHERE hidden = false
  AND evidence_url IS NOT NULL
  AND (evidence_note ILIKE '%test%' OR evidence_url ILIKE '%test%')
ORDER BY votes.created_at DESC
LIMIT 50
SQL,
        'extension_coverage' => <<<'SQL'
EXPLAIN (FORMAT JSON)
SELECT domain, event_type, count(*) AS total
FROM extension_events
WHERE created_at >= NOW() - INTERVAL '7 days'
GROUP BY domain, event_type
ORDER BY domain
SQL,
    ];

    foreach ($queries as $name => $sql) {
        $this->line("## {$name}");
        $plan = DB::selectOne($sql);
        $this->line(json_encode($plan, JSON_PRETTY_PRINT));
    }

    return 0;
})->purpose('Run PostgreSQL EXPLAIN plans for launch hot queries.');

Artisan::command('truthshield:backup-postgres {path?}', function () {
    $path = $this->argument('path') ?: storage_path('app/backups/truthshield-' . now()->format('Ymd-His') . '.sql');
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }

    $this->info("Backup target: {$path}");
    $this->warn('Run pg_dump with the configured database credentials in production; local command intentionally does not embed secrets.');
})->purpose('Prepare a PostgreSQL backup target path for operational runbooks.');

Schedule::command('truthshield:finalize-news --settle')->everyFifteenMinutes();
Schedule::command('truthshield:warm-cache')->hourly();
Schedule::command('truthshield:ensure-algorithm-version')->daily();
Schedule::command('truthshield:sync-evidence --limit=500')->hourly();
Schedule::command('truthshield:snapshot-evidence --limit=100')->everyThirtyMinutes();
Schedule::command('truthshield:detect-abuse-clusters')->hourly();
Schedule::command('truthshield:build-account-graph')->hourly();
Schedule::command('truthshield:record-operational-heartbeat')->everyFiveMinutes();
Schedule::command('truthshield:seed-launch-policies')->daily();
Schedule::command('truthshield:check-extension-selectors')->daily();
Schedule::command('truthshield:refresh-evidence-quality --limit=500')->hourly();
