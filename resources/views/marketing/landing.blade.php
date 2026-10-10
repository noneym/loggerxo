<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loggerxo · Errors, logs and performance for your apps</title>
    <meta name="description" content="Self-hosted error tracking, log search and performance monitoring for Laravel and browser apps. Sentry-style issues, ClickHouse speed, one Docker container.">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(70%_50%_at_50%_-10%,rgba(124,58,237,.25),transparent_70%)]"></div>
    <div class="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(rgba(255,255,255,.025)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.025)_1px,transparent_1px)] bg-[size:56px_56px] [mask-image:radial-gradient(ellipse_at_top,black_20%,transparent_70%)]"></div>

    <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
        <a href="/" class="flex items-center gap-2.5"><x-logo class="h-8 w-8" /><span class="text-lg font-semibold tracking-tight text-white">Loggerxo</span></a>
        <nav class="flex items-center gap-2 text-[13px]">
            <a href="{{ route('docs') }}" class="btn-ghost">Docs</a>
            <a href="{{ route('login') }}" class="btn-ghost">Sign in</a>
            @if (config('loggerxo.registration_enabled'))<a href="{{ route('register') }}" class="btn-primary">Get started</a>@endif
        </nav>
    </header>

    <section class="mx-auto max-w-6xl px-6 pb-16 pt-14 md:pt-24">
        <div class="max-w-3xl">
            <span class="badge border-brand-500/40 bg-brand-500/10 text-brand-200">Self-hosted · Laravel · Browser</span>
            <h1 class="mt-5 text-4xl font-extrabold leading-[1.05] tracking-tight text-white md:text-6xl">Every error, log line and slow request. One place.</h1>
            <p class="mt-6 max-w-2xl text-lg text-ink-300">Loggerxo groups exceptions into issues, streams your Laravel logs and browser console into a searchable timeline, and shows which routes are slow. Backed by ClickHouse, shipped as a single container.</p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                @if (config('loggerxo.registration_enabled'))<a href="{{ route('register') }}" class="btn-primary px-5 py-2.5 text-sm">Create free account</a>@endif
                <a href="{{ route('docs') }}" class="btn-secondary px-5 py-2.5 text-sm">Read the docs</a>
            </div>
        </div>

        <div class="mt-14 grid gap-4 md:grid-cols-2">
            <div class="card p-5">
                <div class="mb-3 flex items-center gap-2 text-[13px] font-semibold text-ink-200"><x-icon name="terminal" class="text-brand-300" /> Laravel: two lines in .env</div>
                <pre class="code-block">composer require noneym/loggerxo-laravel
LOGGERXO_DSN=https://lx_your_key@loggerxo.com</pre>
            </div>
            <div class="card p-5">
                <div class="mb-3 flex items-center gap-2 text-[13px] font-semibold text-ink-200"><x-icon name="globe" class="text-brand-300" /> Browser: one script tag</div>
                <pre class="code-block">&lt;script src="https://loggerxo.com/sdk/loggerxo.js"
        data-key="lx_your_key" data-env="production"&gt;&lt;/script&gt;</pre>
            </div>
        </div>
    </section>

    <section class="border-t border-ink-800 bg-ink-900/40">
        <div class="mx-auto grid max-w-6xl gap-8 px-6 py-16 md:grid-cols-3">
            @foreach ([
                ['bug', 'Issues, not noise', 'Exceptions and repeated log problems are fingerprinted into issues with stack traces, breadcrumbs, request and user context. Resolve, ignore, assign, and get told when something regresses.'],
                ['list', 'Logs & console', 'Every Laravel log level and every browser console.error/warn, searchable by text, user, URL, tag or logger. Live tail when you are deploying.'],
                ['gauge', 'Performance', 'p50/p95 per route, slow DB queries, span waterfalls, and web vitals (LCP, FCP, CLS) from real users. No sampling vendor lock-in.'],
                ['bell', 'Alerts that matter', 'E-mail and Telegram notifications for new issues, regressions, spikes and critical logs, with per-issue cooldowns.'],
                ['users', 'Teams', 'Organizations, projects, roles and invitations. Super-admin view for the people who run the server.'],
                ['layers', 'Built to self-host', 'Laravel + Livewire app, MariaDB for metadata, Redis for the ingest queue, ClickHouse for events. One Dockerfile, environment variables for everything.'],
            ] as [$icon, $title, $body])
                <div>
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-600/15 text-brand-300"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <h3 class="mt-4 text-[15px] font-semibold text-white">{{ $title }}</h3>
                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-ink-400">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <footer class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-8 text-xs text-ink-500">
        <span>© {{ date('Y') }} Loggerxo</span>
        <span class="flex gap-4"><a href="{{ route('docs') }}" class="hover:text-ink-300">Documentation</a><a href="https://github.com/noneym/loggerxo" class="hover:text-ink-300">GitHub</a></span>
    </footer>
</div>
</body>
</html>
