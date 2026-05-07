# TruthShield API

Laravel 11 backend for TruthShield: weighted news credibility voting, evidence moderation, extension telemetry, admin management, and ECPay donations.

## Local Commands

```bash
composer install
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

## Operational Commands

```bash
php artisan truthshield:check-production-env
php artisan truthshield:finalize-news --settle
php artisan truthshield:warm-cache
php artisan truthshield:seed-launch-policies
php artisan truthshield:expire-pending-donations --hours=24
```

## Key API Areas

- `/api/news/status`
- `/api/vote`
- `/api/evidence-library`
- `/api/transparency`
- `/api/donations/ecpay`
- `/api/openapi.json`

## Admin

Local admin panel: `/admin`

Default seeded admin: `admin@truthshield.local` / `admin123456`
