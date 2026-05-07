# TruthShield Deployment

Target production layout:

- Cloud Run: Laravel API, Filament admin, public API endpoints.
- Static host: Vue web app and extension download page.
- Shared machine: Laravel queue worker and scheduler cron.
- Shared services: one PostgreSQL and one Redis used by API and worker.

## Required External Values

- GCP project id and Artifact Registry region.
- Production API domain, for example `https://api.truthshield.example`.
- Production web domain, for example `https://truthshield.example`.
- PostgreSQL host, database, username, password.
- Redis host, port, and password if enabled.
- One shared `APP_KEY`; API and worker must use the same value.
- OAuth client id/secret for Facebook, Google, GitHub.
- ECPay production merchant id, hash key, hash IV.
- Chrome extension id after publishing, or unpacked id for testing.
- Mail provider if user-facing email is enabled.
- Turnstile keys if challenge mode is enabled.

## API On Cloud Run

1. Create a production env file:

```bash
cp deploy/cloudrun-api.env.example.yaml deploy/cloudrun-api.env.yaml
```

2. Fill all placeholder values. Keep `APP_ENV=production`, `APP_DEBUG=false`, and `TRUTHSHIELD_DEV_LOGIN_ENABLED=false`.

3. Deploy:

```bash
PROJECT_ID=your-gcp-project \
REGION=asia-east1 \
SERVICE=truth-shield-api \
./deploy/cloudrun-api.sh
```

If using Cloud SQL, also set:

```bash
CLOUDSQL_INSTANCE=project:region:instance ./deploy/cloudrun-api.sh
```

Run migrations from the shared worker machine or a one-off Cloud Run job. Do not rely on Cloud Run request startup for migrations.

## Shared Worker And Scheduler Machine

1. Install PHP 8.2+, Composer, cron, systemd, and PostgreSQL client tools.
2. Clone the backend repository to `/var/www/truthshield/current/truth-shield-api`.
3. Install unit files:

```bash
cd /var/www/truthshield/current/truth-shield-api
./deploy/install-shared-worker-units.sh
```

4. Fill `/path/to/worker.env`. It must match the API env for DB, Redis, `APP_KEY`, `APP_URL`, and `FRONTEND_URL`.

5. Deploy/update backend worker code:

```bash
APP_DIR=/var/www/truthshield/current/truth-shield-api \
./deploy/shared-worker-update.sh
```

6. Confirm:

```bash
systemctl status truthshield-worker
tail -f /var/log/truthshield-schedule.log
```

The scheduler cron runs `php artisan schedule:run` every minute. The Laravel schedule already controls task cadence and overlapping locks.

## Frontend

Build the site:

```bash
cd truth-shield-web
VITE_API_BASE_URL=https://api.truthshield.example npm run build
```

Deploy `truth-shield-web/dist` to the static host.

Package the extension:

```bash
cd truth-shield-web
npm run package:extension
```

Before packaging, set extension options to production origins or update the default origins in the extension if you want production defaults.

## First Production Run

Run these once after database credentials are set:

```bash
php artisan migrate --force
php artisan db:seed --class=TagSeeder --force
php artisan truthshield:seed-launch-policies
php artisan truthshield:ensure-algorithm-version
php artisan truthshield:check-production-env
```

Then verify:

```bash
curl https://api.truthshield.example/api/system/health
curl https://api.truthshield.example/api/tags?locale=en
curl https://api.truthshield.example/api/news/status?url=https%3A%2F%2Fwww.cna.com.tw%2Fnews%2Faipl%2F202605060001.aspx
```

## Rollback

- Cloud Run: roll traffic back to the previous revision.
- Worker: `git checkout` the previous commit in `truth-shield-api`, run Composer, clear/cache config, and restart `truthshield-worker`.
- Database: migrations are forward-only unless a backup restore is explicitly planned.

## Production Guardrails

- Keep dev login disabled.
- Keep API and worker code versions aligned.
- Monitor `/api/system/health`, queue failures, expired unfinalized news, Redis availability, and schedule log freshness.
- Run PostgreSQL backup rehearsal before public launch.
