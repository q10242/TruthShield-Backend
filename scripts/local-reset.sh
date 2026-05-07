#!/usr/bin/env sh
set -eu

php artisan migrate:fresh --seed
php artisan truthshield:ensure-algorithm-version
php artisan truthshield:seed-launch-policies
php artisan truthshield:warm-cache
php artisan truthshield:record-operational-heartbeat
