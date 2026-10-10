# Loggerxo — single-container image (FrankenPHP web server + ingest workers + queue + scheduler via supervisord).
# Build on dockxo / any Docker host; configure everything through environment variables (see .env.example).
FROM dunglas/frankenphp:1-php8.3-alpine AS base

ENV APP_ENV=production \
    APP_DEBUG=false \
    SERVER_NAME=":80"

RUN apk add --no-cache bash curl icu-dev libzip-dev oniguruma-dev supervisor tzdata \
    && install-php-extensions pdo_mysql intl zip bcmath opcache pcntl redis \
    && apk del icu-dev libzip-dev oniguruma-dev

RUN printf "memory_limit=512M\nupload_max_filesize=10M\npost_max_size=12M\nmax_execution_time=60\nexpose_php=Off\n" > /usr/local/etc/php/conf.d/app.ini \
    && printf "opcache.enable=1\nopcache.enable_cli=1\nopcache.memory_consumption=192\nopcache.max_accelerated_files=20000\nopcache.validate_timestamps=0\n" > /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# ---------------------------------------------------------------- vendor
FROM base AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

# ---------------------------------------------------------------- assets
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.js ./
COPY app/Livewire ./app/Livewire
COPY --from=vendor /app/vendor/livewire/livewire/src ./vendor/livewire/livewire/src
RUN npm run build

# ---------------------------------------------------------------- app
FROM base
WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /build/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
