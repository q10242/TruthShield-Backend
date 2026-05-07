#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/truthshield/current/truth-shield-api}"
ENV_FILE="${ENV_FILE:-/path/to/worker.env}"
BRANCH="${BRANCH:-main}"

if [[ ! -d "${APP_DIR}/.git" && ! -f "${APP_DIR}/artisan" ]]; then
  echo "APP_DIR must point to the deployed truth-shield-api directory: ${APP_DIR}" >&2
  exit 1
fi

cd "${APP_DIR}"

if [[ -d .git ]]; then
  git fetch --prune origin
  git checkout "${BRANCH}"
  git pull --ff-only origin "${BRANCH}"
fi

if [[ ! -f .env ]]; then
  ln -s "${ENV_FILE}" .env
fi

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=TagSeeder --force
php artisan truthshield:seed-launch-policies
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo systemctl daemon-reload
sudo systemctl enable --now truthshield-worker.service
sudo systemctl restart truthshield-worker.service

echo "Worker updated at ${APP_DIR}"
