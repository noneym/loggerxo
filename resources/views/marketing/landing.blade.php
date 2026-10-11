@php $appUrl = rtrim(config('app.url'), '/'); @endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loggerxo · Open source error tracking for Laravel and the browser</title>
    <meta name="description" content="Open source error tracking, log search and performance monitoring for Laravel and browser apps. Issues grouped like Sentry, events stored in ClickHouse, shipped as one container.">
    <meta property="og:title" content="Loggerxo">
    <meta property="og:description" content="Open source error tracking, log search and performance monitoring for Laravel and browser apps.">
    <meta property="og:image" content="{{ $appUrl }}/images/landing/issue.webp">
    <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
    <link rel="preload" as="image" href="/images/landing/issue.webp" fetchpriority="high">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media (prefers-reduced-motion: no-preference) {
            .lx-rise { opacity: 0; transform: translateY(14px); animation: lx-rise .7s cubic-bezier(.16, 1, .3, 1) forwards; }
            .lx-rise[data-delay="1"] { animation-delay: .08s; }
            .lx-rise[data-delay="2"] { animation-delay: .16s; }
            .lx-rise[data-delay="3"] { animation-delay: .24s; }
            .lx-rise[data-delay="4"] { animation-delay: .36s; }
            @keyframes lx-rise { to { opacity: 1; transform: none; } }
        }
    </style>
</head>
<body class="h-full bg-ink-950 text-ink-100">

<header class="sticky top-0 z-30 border-b border-ink-800/80 bg-ink-950/85 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 lg:px-8">
        <a href="/" class="flex items-center gap-2.5">
            <x-logo class="h-7 w-7" />
            <span class="text-[15px] font-semibold tracking-tight text-white">Loggerxo</span>
        </a>
        <nav class="flex items-center gap-1 text-[13px]">
            <a href="{{ route('docs') }}" class="btn-ghost hidden sm:inline-flex">Docs</a>
            <a href="https://github.com/noneym/loggerxo" class="btn-ghost hidden sm:inline-flex" rel="noopener">GitHub</a>
            <a href="{{ route('login') }}" class="btn-ghost">Sign in</a>
            @if (config('loggerxo.registration_enabled'))<a href="{{ route('register') }}" class="btn-primary">Create account</a>@endif
        </nav>
    </div>
</header>

<main>
    {{-- Hero: asymmetric split, real product screenshot --}}
    <section class="relative overflow-hidden">
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-5 pb-20 pt-14 lg:grid-cols-12 lg:gap-6 lg:px-8 lg:pb-28 lg:pt-20">
            <div class="min-w-0 lg:col-span-6">
                <p class="lx-rise text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-300">Open source, MIT licensed</p>
                <h1 class="lx-rise mt-5 text-4xl font-semibold leading-[1.05] tracking-tight text-white md:text-5xl lg:text-[2.9rem] xl:text-[3.1rem]" data-delay="1">Error tracking for Laravel and the browser.</h1>
                <p class="lx-rise mt-6 max-w-[42ch] text-[17px] leading-relaxed text-ink-300" data-delay="2">Loggerxo groups exceptions into issues, streams logs and console output, and shows which routes are slow.</p>
                <div class="lx-rise mt-8 flex flex-wrap items-center gap-3" data-delay="3">
                    @if (config('loggerxo.registration_enabled'))
                        <a href="{{ route('register') }}" class="btn-primary px-5 py-2.5 text-sm">Create account</a>
                    @endif
                    <a href="https://github.com/noneym/loggerxo" class="btn-secondary px-5 py-2.5 text-sm" rel="noopener"><x-phosphor-github-logo class="h-4 w-4" /> View on GitHub</a>
                </div>
            </div>
            <div class="lx-rise min-w-0 lg:col-span-6" data-delay="4">
                <div class="relative lg:-mr-24 xl:-mr-32">
                    <div class="overflow-hidden rounded-xl border border-ink-700 bg-ink-900 shadow-[0_30px_80px_-30px_rgba(0,0,0,.8)]">
                        <div class="flex h-9 items-center gap-1.5 border-b border-ink-800 px-3">
                            <span class="h-2.5 w-2.5 rounded-full bg-ink-600"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-ink-600"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-ink-600"></span>
                            <span class="ml-3 rounded-md bg-ink-850 px-2 py-0.5 font-mono text-[11px] text-ink-400">loggerxo.com/app</span>
                        </div>
                        <img src="/images/landing/issue.webp" width="1440" height="840" alt="Loggerxo issue page showing a grouped RuntimeException with event timeline, stack trace and tags" class="block w-full" fetchpriority="high" decoding="async">
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Integrations: logos only --}}
    <section class="border-y border-ink-800/80 bg-ink-900/40">
        <div class="mx-auto max-w-7xl px-5 py-10 lg:px-8">
            <p class="text-center text-[13px] text-ink-400">Works with what you already run</p>
            <ul class="mt-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-6 text-ink-400">
                @foreach (['laravel' => 'Laravel', 'php' => 'PHP', 'javascript' => 'JavaScript', 'clickhouse' => 'ClickHouse', 'mariadb' => 'MariaDB', 'redis' => 'Redis', 'docker' => 'Docker', 'telegram' => 'Telegram', 'github' => 'GitHub'] as $slug => $name)
                    <li><x-dynamic-component :component="'logo-'.$slug" class="h-7 w-7 fill-current" role="img" aria-label="{{ $name }}" /></li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Three surfaces: bento with exactly three cells --}}
    <section class="mx-auto max-w-7xl px-5 py-24 lg:px-8 lg:py-32">
        <h2 class="max-w-[22ch] text-3xl font-semibold leading-tight tracking-tight text-white md:text-4xl">Three views, one timeline behind them.</h2>
        <p class="mt-4 max-w-[60ch] text-[15px] leading-relaxed text-ink-400">Every event is stored once in ClickHouse. Issues, logs and performance are different windows onto the same data, so you can jump from a slow request to the error it caused.</p>

        <div class="mt-12 grid gap-4 lg:grid-cols-5">
            <article class="group overflow-hidden rounded-xl border border-ink-700/80 bg-ink-900 lg:col-span-3">
                <div class="flex items-start gap-3 p-6">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-500/10 text-brand-300"><x-phosphor-bug class="h-5 w-5" /></span>
                    <div>
                        <h3 class="text-[17px] font-semibold text-white">Issues</h3>
                        <p class="mt-1 text-[14px] leading-relaxed text-ink-400">Exceptions and repeated log problems are fingerprinted into one issue each, with stack trace, code context, breadcrumbs, request and user. Resolve, ignore, assign. Get told when it comes back.</p>
                    </div>
                </div>
                <div class="relative ml-6 h-[300px] overflow-hidden rounded-tl-lg border-l border-t border-ink-700 md:h-[380px]">
                    <img src="/images/landing/issues.webp" width="1192" height="840" alt="Issue list with levels, sparklines and event counts" loading="lazy" decoding="async" class="block h-full w-full object-cover object-left-top transition duration-500 ease-out group-hover:scale-[1.015]">
                </div>
            </article>
            <article class="group overflow-hidden rounded-xl border border-ink-700/80 bg-ink-900 lg:col-span-2">
                <div class="flex items-start gap-3 p-6">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-500/10 text-brand-300"><x-phosphor-list-magnifying-glass class="h-5 w-5" /></span>
                    <div>
                        <h3 class="text-[17px] font-semibold text-white">Logs and console</h3>
                        <p class="mt-1 text-[14px] leading-relaxed text-ink-400">Every Laravel log level and every browser console error, searchable by text, user, URL or tag. Live tail while you deploy.</p>
                    </div>
                </div>
                <div class="relative ml-6 h-[300px] overflow-hidden rounded-tl-lg border-l border-t border-ink-700 md:h-[380px]">
                    <img src="/images/landing/logs.webp" width="1192" height="840" alt="Log explorer with level filters and search" loading="lazy" decoding="async" class="block h-full w-[160%] max-w-none object-cover object-left-top transition duration-500 ease-out group-hover:scale-[1.015]">
                </div>
            </article>
            <article class="group overflow-hidden rounded-xl border border-ink-700/80 bg-ink-900 lg:col-span-5">
                <div class="grid lg:grid-cols-5">
                    <div class="flex items-start gap-3 p-6 lg:col-span-2">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-500/10 text-brand-300"><x-phosphor-gauge class="h-5 w-5" /></span>
                        <div>
                            <h3 class="text-[17px] font-semibold text-white">Performance</h3>
                            <p class="mt-1 text-[14px] leading-relaxed text-ink-400">p50 and p95 per route, the queries behind a slow request as a waterfall, and web vitals measured on real page loads.</p>
                        </div>
                    </div>
                    <div class="relative ml-6 h-[260px] overflow-hidden rounded-tl-lg border-l border-t border-ink-700 md:h-[340px] lg:col-span-3 lg:ml-0 lg:mt-6">
                        <img src="/images/landing/performance.webp" width="1192" height="840" alt="Transaction detail with latency chart and span waterfall" loading="lazy" decoding="async" class="block h-full w-full object-cover object-left-top transition duration-500 ease-out group-hover:scale-[1.015]">
                    </div>
                </div>
            </article>
        </div>
    </section>

    {{-- Install --}}
    <section class="border-t border-ink-800/80 bg-ink-900/40">
        <div class="mx-auto max-w-7xl px-5 py-24 lg:px-8 lg:py-32">
            <h2 class="max-w-[24ch] text-3xl font-semibold leading-tight tracking-tight text-white md:text-4xl">Two lines for Laravel. One tag for the browser.</h2>
            <p class="mt-4 max-w-[60ch] text-[15px] leading-relaxed text-ink-400">Exceptions, log lines and request timings are captured automatically once the key is set. Anything else can post plain JSON to the HTTP API.</p>
            <div class="mt-12 grid gap-6 lg:grid-cols-2">
                <div class="min-w-0">
                    <div class="mb-3 flex items-center gap-2 text-[13px] font-medium text-ink-200"><x-phosphor-terminal-window class="h-4 w-4 text-brand-300" /> Laravel</div>
                    <x-code class="text-[13px]">{{ 'composer require noneym/loggerxo-laravel

# .env
LOGGERXO_DSN=https://lx_your_key@loggerxo.com
LOG_STACK=single,loggerxo' }}</x-code>
                </div>
                <div class="min-w-0">
                    <div class="mb-3 flex items-center gap-2 text-[13px] font-medium text-ink-200"><x-phosphor-browser class="h-4 w-4 text-brand-300" /> Browser</div>
                    <x-code class="text-[13px]">{!! e('<script src="'.$appUrl.'/sdk/loggerxo.js"
        data-key="lx_your_key"
        data-env="production"></script>') !!}</x-code>
                </div>
            </div>
        </div>
    </section>

    {{-- Facts row, no cards --}}
    <section class="mx-auto max-w-7xl px-5 py-24 lg:px-8 lg:py-32">
        <h2 class="max-w-[24ch] text-3xl font-semibold leading-tight tracking-tight text-white md:text-4xl">Built to be run, not rented.</h2>
        <div class="mt-12 grid gap-10 md:grid-cols-2 lg:grid-cols-4 lg:gap-0 lg:divide-x lg:divide-ink-800">
            @foreach ([
                ['bell-ringing', 'Alerts', 'E-mail and Telegram for new issues, regressions, spikes and critical logs. Per-issue cooldowns so a storm is one message.'],
                ['database', 'Storage', 'Events live in ClickHouse, issues and teams in MariaDB, the ingest queue in Redis. Retention is a number you set per project.'],
                ['shipping-container', 'Deploy', 'One Dockerfile runs the web app, ingest workers, alert queue and scheduler. Configure everything with environment variables.'],
                ['lock-key', 'Your data', 'Stack traces, request bodies and user identifiers never leave your servers. Sensitive keys are masked twice: in the SDK and on ingest.'],
            ] as [$icon, $title, $body])
                <div class="lg:px-8 lg:first:pl-0 lg:last:pr-0">
                    <x-dynamic-component :component="'phosphor-'.$icon" class="h-6 w-6 text-brand-300" />
                    <h3 class="mt-4 text-[16px] font-semibold text-white">{{ $title }}</h3>
                    <p class="mt-2 text-[14px] leading-relaxed text-ink-400">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="border-t border-ink-800/80">
        <div class="mx-auto max-w-3xl px-5 py-24 text-center lg:py-32">
            <h2 class="text-3xl font-semibold leading-tight tracking-tight text-white md:text-4xl">Read the source, run a project, send the first event.</h2>
            <p class="mx-auto mt-4 max-w-[52ch] text-[15px] leading-relaxed text-ink-400">The Laravel package, the browser script and the HTTP API are documented on one page.</p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                @if (config('loggerxo.registration_enabled'))<a href="{{ route('register') }}" class="btn-primary px-5 py-2.5 text-sm">Create account</a>@endif
                <a href="{{ route('docs') }}" class="btn-secondary px-5 py-2.5 text-sm">Read the docs <x-phosphor-arrow-right class="h-4 w-4" /></a>
            </div>
        </div>
    </section>
</main>

<footer class="border-t border-ink-800/80">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-8 text-[13px] text-ink-500 lg:px-8">
        <div class="flex items-center gap-2"><x-logo class="h-5 w-5" /><span>Loggerxo, MIT licensed</span></div>
        <nav class="flex flex-wrap gap-6">
            <a href="{{ route('docs') }}" class="hover:text-ink-200">Documentation</a>
            <a href="https://github.com/noneym/loggerxo" class="hover:text-ink-200" rel="noopener">Source</a>
            <a href="https://github.com/noneym/loggerxo-laravel" class="hover:text-ink-200" rel="noopener">Laravel package</a>
            <a href="{{ route('login') }}" class="hover:text-ink-200">Sign in</a>
        </nav>
    </div>
</footer>
</body>
</html>
