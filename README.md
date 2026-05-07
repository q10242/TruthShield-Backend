# TruthShield API

TruthShield is an open-source news credibility system built around weighted community review, evidence-first voting, transparent moderation, and browser-extension delivery.

This repository contains the Laravel backend: API, admin panel, reputation engine, evidence pipeline, anti-abuse logic, governance records, donation backend, exports, and operational tooling.

> TruthShield does not delete news, block readers, or decide truth by authority. It shows weighted community signals beside news content and makes the reasoning auditable.

## What It Does

- Normalizes news URLs and stores stable fingerprints.
- Returns low-latency article status for browser-extension tooltips and banners.
- Lets authenticated users vote with trust-weighted scores.
- Requires evidence for negative labels and supports external image hosts, cloud drives, YouTube, archive links, related reports, and official records.
- Freezes each article result after a 72-hour voting window.
- Tracks evidence usefulness and feeds it into future trust-score settlement.
- Detects suspicious coordinated behavior and lowers abusive account weight.
- Provides a Filament admin panel for data review, moderation, trust adjustment, evidence reports, domain coverage, YouTube channels, donations, and operational health.
- Publishes transparency, leaderboard, evidence-library, OpenAPI, export, and readiness endpoints.

## Product Principles

- **No censorship:** external news remains readable at the original site.
- **Evidence first:** negative claims require a public evidence URL and short explanation.
- **One person, one article entry:** each user has one vote/evidence record per article, editable before the cutoff.
- **Weighted trust:** vote impact is `trust_score * identity_multiplier * abuse_multiplier`.
- **Frozen results:** finalized articles read from snapshots instead of dynamic recomputation.
- **Transparent governance:** moderation, appeals, automation, and trust actions leave records.

## Architecture

```mermaid
flowchart LR
    Extension["Chrome Extension"] -->|status / telemetry| API["Laravel API"]
    Web["Vue Truth Hub"] -->|auth / vote / evidence| API
    API --> PG["PostgreSQL"]
    API --> Redis["Redis Cache"]
    API --> Queue["Queue Worker"]
    Scheduler["Laravel Scheduler"] --> Queue
    Queue --> Settlement["Finalization / Trust Settlement / Abuse Detection"]
    Admin["Filament Admin"] --> API
```

## Tech Stack

- Laravel 11
- Laravel Sanctum
- PostgreSQL
- Redis
- Filament admin panel
- Database queue workers
- Docker-first local development

## Key API Areas

| Area | Endpoint |
| --- | --- |
| Article status | `GET /api/news/status?url=...` |
| Vote with evidence | `POST /api/vote` |
| Evidence list | `GET /api/news/evidence?url=...` |
| Evidence reaction/report | `POST /api/evidence/{vote}/reaction`, `POST /api/evidence/{vote}/report` |
| Tags | `GET /api/tags?locale=zh-TW` |
| News domains | `GET /api/news-domains` |
| Transparency | `GET /api/transparency` |
| Evidence library | `GET /api/evidence-library` |
| Profile | `GET /api/me/profile` |
| OpenAPI | `GET /api/openapi.json` |
| Health | `GET /api/system/health` |
| Donations | `POST /api/donations/ecpay` |

## Local Development

If this repository is checked out inside the full TruthShield workspace, start all local services from the workspace root:

```bash
docker compose up --build
```

Default local services:

- API: `http://127.0.0.1:18080`
- Admin: `http://127.0.0.1:18080/admin`
- Web app: `http://127.0.0.1:15173`

Backend-only setup:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

Local seeded admin:

```text
admin@truthshield.local / admin123456
```

This account is for local development only. Create a separate production admin with the command below.

Create a production admin:

```bash
php artisan truthshield:bootstrap-admin \
  --email=admin@example.com \
  --name="TruthShield Admin" \
  --password='use-a-long-random-password'
```

## Operations

Common commands:

```bash
php artisan truthshield:preflight-production
php artisan truthshield:check-production-env
php artisan truthshield:finalize-news --settle
php artisan truthshield:warm-cache
php artisan truthshield:seed-launch-policies
php artisan truthshield:expire-pending-donations --hours=24
php artisan truthshield:stress-status --requests=1000
php artisan truthshield:explain-hot-queries
```

Smoke test an API deployment:

```bash
BASE_URL=https://api.truthshield.example ./deploy/smoke-test.sh
```

Queue worker:

```bash
php artisan queue:work database --sleep=1 --tries=3 --timeout=90
```

Scheduler:

```bash
php artisan schedule:run
```

In production, the API can run on Cloud Run while a shared worker machine runs queue workers and scheduler cron.

## Production Dependencies

Required:

- PostgreSQL
- Redis
- HTTPS API origin
- HTTPS frontend origin
- Queue worker host
- Scheduler host or cron
- Secure `APP_KEY`
- Production admin account

Optional for launch:

- Facebook / Google / GitHub OAuth credentials
- ECPay merchant credentials
- Mail provider credentials
- External evidence upload provider
- Chrome Web Store listing

The system can deploy without optional keys by keeping dev login disabled in production and leaving optional integrations off.

## Evidence Safety

TruthShield does not host evidence images or documents. Evidence is stored as external URLs plus metadata, safety status, preview information, trust-source classification, and quality score.

The backend blocks private and localhost evidence URLs to reduce SSRF risk.

## Testing

```bash
php artisan test
```

Targeted examples:

```bash
php artisan test --filter=UrlFingerprintServiceTest
php artisan test --filter=health_and_transparency_include_operational_defense_fields
```

## Related Repository

- TruthShield Web and Chrome extension: `truth-shield-web`

## Status

The project is in deployable beta form. Core local functionality is implemented; production readiness depends on infrastructure, domain, OAuth/payment/email decisions, and real-world extension compatibility testing.
