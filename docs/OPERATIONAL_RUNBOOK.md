# TruthShield Operational Runbook

## Health Checks

Run:

```bash
curl http://127.0.0.1:18080/api/system/health
```

Check:

- `ok`: overall system health.
- `database`: PostgreSQL connectivity.
- `cache`: Redis connectivity.
- `queue.healthy`: worker heartbeat freshness.
- `counts.expired_unfinalized_news`: should not grow continuously.
- `counts.pending_evidence_snapshots`: should drain over time.

## Alerts To Configure

- Redis unavailable for 2 consecutive checks.
- PostgreSQL unavailable for 2 consecutive checks.
- Queue heartbeat older than 10 minutes.
- Failed jobs greater than 0.
- Expired unfinalized news count increasing for more than 30 minutes.
- HTTP 429 rate above expected baseline.
- Extension failure events rising above normal baseline.

## Queue Operations

```bash
php artisan queue:work database --sleep=1 --tries=3 --timeout=90
php artisan queue:failed
php artisan truthshield:record-operational-heartbeat
```

Scheduled work expected:

- Finalize expired news.
- Settle trust score after finalization.
- Snapshot evidence metadata.
- Detect abuse clusters.
- Build account graph.
- Refresh evidence quality.

## Load Tests

```bash
php artisan truthshield:stress-http-launch --requests=50 --base-url=http://127.0.0.1:18080
php artisan truthshield:stress-status --requests=1000
php artisan truthshield:explain-hot-queries
```

Record p95/p99 latency before each beta release.

## Backup And Restore Rehearsal

1. Run `scripts/backup-postgres.sh`.
2. Restore into a temporary local database with `scripts/restore-postgres.sh`.
3. Run migrations.
4. Open `/api/system/health`.
5. Compare row counts for users, news URLs, votes, evidence, and badges.

## Incident Notes

- If status API is slow, check Redis first, then `news:status:*` cache hit behavior.
- If vote API is slow, check queue and abuse detection jobs.
- If extension events spike, verify telemetry batch endpoint and rate limits.
