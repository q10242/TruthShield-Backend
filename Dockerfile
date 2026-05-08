FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    unzip \
  && docker-php-ext-install intl mbstring pcntl pdo_pgsql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize \
  && php artisan filament:assets \
  && php artisan filament:optimize \
  && mkdir -p storage/logs bootstrap/cache \
  && chmod -R ug+rw storage bootstrap/cache

EXPOSE 8080

CMD ["sh", "-lc", "php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
