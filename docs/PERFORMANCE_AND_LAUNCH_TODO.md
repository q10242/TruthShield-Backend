# TruthShield Performance And Launch TODO

## Done In This Pass

- [x] Throttle and deduplicate extension telemetry so hover and skipped-banner events do not write to the API on every DOM interaction.
- [x] Add Redis negative cache for unknown news URLs hit by `/api/news/status`.
- [x] Clear unknown-URL cache when a user creates the first vote for that URL.
- [x] Add a Redis finalization lock so the first post-deadline status read does not trigger duplicate snapshot work.
- [x] Cache lookup endpoints used by the extension: tags and news domains.
- [x] Cache health/admin count widgets with short TTLs.
- [x] Add database indexes for common launch hot paths.
- [x] Prefer persisted evidence quality score for evidence ordering instead of calculating weighted reaction ordering in the read path.

## Next Performance Work

- [ ] Move extension telemetry ingestion to queue or batched client upload.
- [ ] Add frontend URL canonicalization so tracking-query variants share the same hover cache entry before reaching the backend.
- [ ] Add edge/CDN cache headers for anonymous status and lookup endpoints.
- [ ] Add PostgreSQL trigram or full-text indexes for evidence library keyword search.
- [ ] Add load tests for `/api/news/status`, `/api/news/evidence`, `/api/news-domains`, and `/api/extension/events`.
- [ ] Add EXPLAIN-based checks for high-volume admin resources.
- [ ] Move abuse detection from synchronous vote/reaction requests into a lightweight immediate check plus queued batch jobs.
- [ ] Run production queue workers instead of `QUEUE_CONNECTION=sync`.

## Remaining Launch Gaps

- [ ] Browser-test every Filament admin resource for create, edit, delete, approve/reject, and bulk actions.
- [ ] Browser-test extension install on local Chrome against CNA and local demo pages.
- [ ] Verify top banner appears once per article page and performs exactly one status request per URL per page session.
- [ ] Verify tooltip disappears on mouseout and does not expose vote entry.
- [ ] Verify right-click menu is the only voting/status entry beyond the passive banner and tooltip.
- [ ] Verify community-maintained URL classification reports can become domain rules through admin approval.
- [ ] Verify trusted evidence source suggestions can become trusted source rows through admin approval.
- [ ] Add alerting for Redis outage, DB latency, queue lag, and high 429 rate.
- [ ] Add backup restore rehearsal documentation.
- [ ] Add production OAuth credential checklist.
