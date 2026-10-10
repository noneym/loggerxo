# Loggerxo

Self-hosted error tracking, log search and performance monitoring for Laravel and browser apps. Sentry-style issues, ClickHouse speed, one Docker container.

- **Issues** – exceptions and repeated log problems fingerprinted into groups with stack traces, breadcrumbs, request/user context; resolve / ignore / assign; regression detection.
- **Logs & console** – every Laravel log level and browser `console.error/warn`, searchable (`url:`, `user:`, `tag:k=v`, `exception:` …), live tail.
- **Performance** – p50/p95 per route, slow queries, span waterfalls, web vitals.
- **Alerts** – e-mail (Cloudflare Email Service or SMTP) and Telegram; triggers: new issue, regression, frequency spike, log level.
- **Teams** – organizations, projects, roles, invitations, super-admin page.

## Architecture

| Store | Used for |
| --- | --- |
| MariaDB | users, organizations, projects, issues, alert rules, sessions |
| Redis | ingest buffer (`lx:ingest` list), cache, queue, rate limits |
| ClickHouse | raw events (`lx_events`) and transactions (`lx_transactions`), HTTP interface |

```
SDK ──POST /api/v1/envelope──▶ Laravel API ──RPUSH──▶ Redis ──▶ loggerxo:ingest worker ──▶ ClickHouse (bulk insert)
                                                                     │
                                                                     ├─▶ MariaDB issues (upsert by fingerprint)
                                                                     └─▶ alerts queue ──▶ e-mail / Telegram
```

Stack: Laravel 12, Livewire 3, Tailwind 4, FrankenPHP, supervisord.

## Run locally

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# fill DB_*, REDIS_*, CLICKHOUSE_*, MAIL_* in .env
php artisan migrate
php artisan loggerxo:clickhouse-migrate
npm run build        # or: npm run dev
php artisan serve
php artisan loggerxo:ingest     # ingest worker (separate terminal)
php artisan queue:work redis --queue=alerts,default
```

Add your e-mail to `SUPER_ADMIN_EMAILS` before registering to get the `/admin` page.

## Deploy (Docker / dockxo)

Build the `Dockerfile`; the container runs the web server, `INGEST_WORKERS` ingest processes, a queue worker and the scheduler. On boot it caches config/routes and runs both MariaDB and ClickHouse migrations (`RUN_MIGRATIONS=false` to skip). Required env: `APP_KEY`, `APP_URL`, `DB_*`, `REDIS_*`, `CLICKHOUSE_*`, `MAIL_*`. Health check: `GET /up`.

## SDKs

- **Laravel**: [`sdk/laravel`](sdk/laravel) → published as `noneym/loggerxo-laravel`. `composer require noneym/loggerxo-laravel`, set `LOGGERXO_DSN=https://lx_key@loggerxo.com`, optionally add `loggerxo` to `LOG_STACK`.
- **Browser**: served by the app at `/sdk/loggerxo.js` (source: `public/sdk/loggerxo.js`). `<script src="https://loggerxo.com/sdk/loggerxo.js" data-key="lx_key"></script>`.
- **Anything else**: plain HTTP, documented at `/docs` (`/api/v1/envelope`).

## Useful commands

| Command | Purpose |
| --- | --- |
| `loggerxo:ingest [--once] [--max-time=3600]` | Redis → ClickHouse worker |
| `loggerxo:clickhouse-migrate [--fresh]` | create ClickHouse tables |
| `loggerxo:prune` | delete events past each project's retention (scheduled nightly) |

## Tests

```bash
php artisan test
```
