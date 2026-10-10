@php $appUrl = rtrim(config('app.url'), '/'); @endphp
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentation · Loggerxo</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<header class="sticky top-0 z-20 border-b border-ink-800 bg-ink-950/80 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
        <a href="/" class="flex items-center gap-2.5"><x-logo class="h-7 w-7" /><span class="font-semibold tracking-tight text-white">Loggerxo</span><span class="text-ink-500">/ docs</span></a>
        <nav class="flex items-center gap-2 text-[13px]">@auth<a href="{{ route('app.dashboard') }}" class="btn-primary btn-sm">Open app</a>@else<a href="{{ route('login') }}" class="btn-secondary btn-sm">Sign in</a>@endauth</nav>
    </div>
</header>

<div class="mx-auto grid max-w-6xl gap-10 px-6 py-10 lg:grid-cols-[200px_1fr]">
    <aside class="hidden lg:block">
        <nav class="sticky top-20 space-y-1 text-[13px]">
            @foreach (['concepts' => 'Concepts', 'laravel' => 'Laravel SDK', 'browser' => 'Browser SDK', 'http' => 'HTTP API', 'alerts' => 'Alerts', 'search' => 'Search syntax', 'selfhost' => 'Deploy'] as $id => $label)
                <a href="#{{ $id }}" class="block rounded-md px-2.5 py-1.5 text-ink-400 hover:bg-ink-800 hover:text-ink-100">{{ $label }}</a>
            @endforeach
        </nav>
    </aside>

    <article class="prose-lx max-w-3xl space-y-12 text-[14.5px] leading-relaxed text-ink-300 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-white [&_h3]:mt-6 [&_h3]:text-[15px] [&_h3]:font-semibold [&_h3]:text-ink-100 [&_p]:mt-3 [&_code]:rounded [&_code]:bg-ink-800 [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[12.5px] [&_code]:text-brand-200 [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_pre_code]:text-ink-200 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5 [&_table]:mt-3 [&_table]:w-full [&_table]:text-[13px] [&_th]:py-1.5 [&_th]:text-left [&_th]:text-ink-400 [&_td]:border-t [&_td]:border-ink-800 [&_td]:py-1.5 [&_td]:align-top [&_td]:pr-4">
        <section id="concepts">
            <h2>Concepts</h2>
            <p><strong class="text-ink-100">Organizations</strong> own <strong class="text-ink-100">projects</strong>; a project is one application and has one public key (<code>lx_…</code>). SDKs send <strong class="text-ink-100">events</strong> to the project. Events are one of four types:</p>
            <table>
                <thead><tr><th>Type</th><th>What it is</th><th>Grouped into issues?</th></tr></thead>
                <tbody>
                <tr><td><code>error</code></td><td>An exception with stack trace</td><td>Yes, by exception type + message + in-app frames</td></tr>
                <tr><td><code>log</code></td><td>A log line (debug … emergency) from the server</td><td>Yes, by level + logger + normalized message</td></tr>
                <tr><td><code>console</code></td><td>Browser console output, failed resources, HTTP ≥ 500 from fetch/XHR</td><td>Yes, same as log</td></tr>
                <tr><td><code>transaction</code></td><td>A timed operation: HTTP request, page load, job, command, with spans</td><td>No, shown under Performance</td></tr>
                </tbody>
            </table>
            <p>Issues live in MariaDB (status, assignee, counts). Raw events live in ClickHouse for the project’s retention window (default {{ config('loggerxo.default_retention_days') }} days). Numbers, UUIDs, e-mails, dates and quoted strings are normalized before grouping so <code>Order 4412 not found</code> and <code>Order 9981 not found</code> become one issue. Send a custom <code>fingerprint</code> array to control grouping yourself.</p>
        </section>

        <section id="laravel">
            <h2>Laravel SDK</h2>
            <p>Requires PHP 8.2+ and Laravel 10, 11 or 12.</p>
            <x-code>{{ 'composer config repositories.loggerxo vcs https://github.com/noneym/loggerxo-laravel
composer require noneym/loggerxo-laravel:dev-main' }}</x-code>
            <x-code>{{ 'LOGGERXO_DSN=https://lx_your_key@loggerxo.com
LOGGERXO_ENVIRONMENT=${APP_ENV}
LOGGERXO_RELEASE=2026.10.1

# log channel: add "loggerxo" to your stack to ship log lines
LOG_STACK=single,loggerxo
LOGGERXO_LOG_LEVEL=debug        # minimum level sent as log events
LOGGERXO_TRACING=true           # request / job / command transactions
LOGGERXO_TRACES_SAMPLE_RATE=1.0 # 0.0 – 1.0
LOGGERXO_SEND_DEFAULT_PII=false # include user e-mail / IP
LOGGERXO_QUEUE=false            # true = send via your queue instead of at request end' }}</x-code>
            <h3>What is captured automatically</h3>
            <ul>
                <li>Every reported exception (via the exception handler) with source-code context, request data, authenticated user, and breadcrumbs (queries, log lines, dispatched jobs, HTTP client calls).</li>
                <li>Log lines through the <code>loggerxo</code> Monolog channel, including context arrays.</li>
                <li>Transactions for HTTP requests (<code>GET /orders/{order}</code>), queue jobs and artisan commands, with DB query spans, status codes, memory and query counts.</li>
            </ul>
            <h3>Manual API</h3>
            <x-code>{{ 'use Noneym\\Loggerxo\\Facades\\Loggerxo;

Loggerxo::captureException($e, [\'tags\' => [\'module\' => \'billing\']]);
Loggerxo::captureMessage(\'Payout batch finished\', \'info\', [\'context\' => [\'count\' => 42]]);
Loggerxo::setUser([\'id\' => $user->id, \'email\' => $user->email]);
Loggerxo::setTag(\'tenant\', $tenant->slug);
Loggerxo::addBreadcrumb(\'cache\', \'warm products\', [\'took_ms\' => 12]);
Loggerxo::flush(); // force send now (e.g. before exit in a long-running script)' }}</x-code>
            <p>Sensitive keys (<code>password</code>, <code>token</code>, <code>authorization</code>, cookies, …) are masked both in the SDK and again on the server.</p>
        </section>

        <section id="browser">
            <h2>Browser SDK</h2>
            <x-code>{!! e('<script src="'.$appUrl.'/sdk/loggerxo.js" data-key="lx_your_key" data-env="production" data-release="1.0.0"></script>') !!}</x-code>
            <p>Captures <code>window.onerror</code>, unhandled promise rejections, <code>console.error</code>/<code>console.warn</code> (configurable), failed resources, fetch/XHR failures and ≥ 500 responses, plus a page-load transaction with web vitals. Breadcrumbs: clicks, navigation, console, HTTP.</p>
            <x-code>{{ 'Loggerxo.init({
  key: \'lx_your_key\',
  endpoint: \''.$appUrl.'\',
  environment: \'production\',
  release: \'1.0.0\',
  captureConsole: [\'error\', \'warn\'],   // [] to disable
  captureHttpErrors: true,
  httpErrorStatus: 500,
  captureResourceErrors: true,
  tracing: true,
  sampleRate: 1,
  maxEventsPerMinute: 60,
  ignoreErrors: [/ResizeObserver loop/, \'Script error.\'],
  ignoreUrls: [/extensions\\//],
  beforeSend: (event) => event,          // return null to drop
  user: { id: \'42\' },
  tags: { plan: \'pro\' },
});' }}</x-code>
            <p>Data attributes map to options: <code>data-key</code>, <code>data-endpoint</code>, <code>data-env</code>, <code>data-release</code>, <code>data-console="error,warn"</code>, <code>data-tracing="false"</code>, <code>data-debug="true"</code>.</p>
            <p>Restrict which sites may use the key under <em>Project → Settings → Allowed browser origins</em>.</p>
        </section>

        <section id="http">
            <h2>HTTP API</h2>
            <p>Anything that can POST JSON can send events. Authenticate with the project key in <code>X-Loggerxo-Key</code> (or <code>Authorization: Bearer</code>, or <code>?key=</code>). Optional <code>Content-Encoding: gzip</code>.</p>
            <x-code>{{ 'POST '.$appUrl.'/api/v1/envelope        # server-side events
POST '.$appUrl.'/api/v1/browser?key=…   # browser events (CORS, text/plain allowed)
GET  '.$appUrl.'/api/v1/health' }}</x-code>
            <x-code>{{ '{
  "sdk": {"name": "my-app", "version": "1.0"},
  "environment": "production",
  "release": "2026.10.1",
  "events": [ { "type": "log", "level": "warning", "message": "Disk at 91%", "logger": "ops",
                "tags": {"host": "web-1"}, "context": {"free_gb": 4.2} } ]
}' }}</x-code>
            <table>
                <thead><tr><th>Field</th><th>Notes</th></tr></thead>
                <tbody>
                <tr><td><code>type</code></td><td><code>error</code>, <code>log</code>, <code>console</code>, <code>transaction</code>. Defaults to <code>error</code> if <code>exception</code> is present, else <code>log</code>.</td></tr>
                <tr><td><code>level</code></td><td><code>debug</code>, <code>info</code>, <code>notice</code>, <code>warning</code>, <code>error</code>, <code>critical</code>, <code>alert</code>, <code>emergency</code> (aliases like <code>warn</code>/<code>fatal</code> accepted).</td></tr>
                <tr><td><code>timestamp</code></td><td>ISO-8601 or unix seconds/milliseconds. Clamped to the last 30 days.</td></tr>
                <tr><td><code>exception</code></td><td><code>{type, value, module?, code?, frames: [{file, line, col?, function, class?, in_app, context?: {pre[], line, post[]}}], chain?: [...]}</code>. Sentry-style <code>{values: [...]}</code> is accepted too.</td></tr>
                <tr><td><code>request</code></td><td><code>{url, method, headers, query, data, ip, route}</code>; sensitive headers and fields are masked.</td></tr>
                <tr><td><code>user</code></td><td><code>{id, email, username, name, ip}</code></td></tr>
                <tr><td><code>tags</code></td><td>Flat string map, max 50 keys. Searchable with <code>tag:key=value</code>.</td></tr>
                <tr><td><code>context</code></td><td>Any JSON, shown on the event (truncated at 20 KB).</td></tr>
                <tr><td><code>breadcrumbs</code></td><td><code>[{timestamp, type, category, level, message, data}]</code>, last 50 kept.</td></tr>
                <tr><td><code>fingerprint</code></td><td>Array of strings to override grouping.</td></tr>
                <tr><td>transaction fields</td><td><code>name</code>, <code>op</code>, <code>http_method</code>, <code>status_code</code>, <code>duration_ms</code>, <code>measurements</code> (numeric map), <code>spans</code> (<code>[{op, description, start_ms, duration_ms, data?}]</code>).</td></tr>
                </tbody>
            </table>
            <p>Limits: 500 events / request (50 for the browser endpoint), 2 MB body, 256 KB per event, project rate limit (default {{ number_format(config('loggerxo.ingest.rate_limit_per_minute')) }} events/min, 429 + <code>Retry-After</code> when exceeded). Response: <code>202 {"accepted": n, "dropped": n}</code>.</p>
        </section>

        <section id="alerts">
            <h2>Alerts</h2>
            <p>Under <em>Project → Settings → Alerts</em> create channels (e-mail recipients or a Telegram chat) and rules. Rule triggers:</p>
            <ul>
                <li><strong class="text-ink-100">New issue</strong> – the first time a fingerprint is seen.</li>
                <li><strong class="text-ink-100">Regression</strong> – a resolved issue happens again.</li>
                <li><strong class="text-ink-100">Frequency</strong> – more than N events for one issue within M minutes.</li>
                <li><strong class="text-ink-100">Log level</strong> – any event at or above a level (e.g. <code>critical</code>), useful for ops logs that are not exceptions.</li>
            </ul>
            <p>Each rule has a per-issue cooldown and can be limited to environments and event types. Telegram: create a bot with @BotFather, add it to a group, and use the group’s chat id. The server can also hold one global bot token (<code>TELEGRAM_BOT_TOKEN</code>) so projects only need a chat id.</p>
        </section>

        <section id="search">
            <h2>Search syntax</h2>
            <table>
                <thead><tr><th>Query</th><th>Matches</th></tr></thead>
                <tbody>
                <tr><td><code>timeout</code></td><td>message, exception value, URL or transaction containing “timeout”</td></tr>
                <tr><td><code>url:/checkout</code></td><td>URL contains /checkout</td></tr>
                <tr><td><code>user:jane@example.com</code></td><td>events for that user (also matches id / IP in the user filter)</td></tr>
                <tr><td><code>tag:module=orders</code></td><td>exact tag match</td></tr>
                <tr><td><code>exception:TypeError</code></td><td>exception type contains TypeError</td></tr>
                <tr><td><code>transaction:orders</code>, <code>server:web-1</code>, <code>release:2026.10</code>, <code>logger:payments</code></td><td>field contains value</td></tr>
                </tbody>
            </table>
        </section>

        <section id="selfhost">
            <h2>Deploy your own instance</h2>
            <p>Loggerxo is open source (MIT). The repository ships a multi-stage <code>Dockerfile</code> (FrankenPHP + supervisord running the web server, ingest workers, queue worker and scheduler). Point it at MariaDB, Redis and ClickHouse via environment variables; migrations run on boot.</p>
            <x-code>{{ 'APP_KEY=base64:…            APP_URL=https://loggerxo.example.com
DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD
REDIS_HOST / REDIS_PORT / REDIS_PASSWORD
CLICKHOUSE_HOST / CLICKHOUSE_PORT (HTTP, 8123) / CLICKHOUSE_DB / CLICKHOUSE_USER / CLICKHOUSE_PASSWORD
MAIL_MAILER=cloudflare + CLOUDFLARE_EMAIL_ACCOUNT_ID + CLOUDFLARE_EMAIL_API_TOKEN   (or MAIL_MAILER=smtp …)
MAIL_FROM_ADDRESS=no-reply@your-verified-domain
INGEST_WORKERS=2   DEFAULT_RETENTION_DAYS=30   REGISTRATION_ENABLED=true   SUPER_ADMIN_EMAILS=you@example.com' }}</x-code>
            <p>Useful commands: <code>php artisan loggerxo:clickhouse-migrate</code>, <code>php artisan loggerxo:ingest</code>, <code>php artisan loggerxo:prune</code>. The admin page (super admins only) shows ingest backlog, dead letters and ClickHouse disk usage.</p>
        </section>
    </article>
</div>
</body>
</html>
