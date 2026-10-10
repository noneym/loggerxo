<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Loggerxo' }} · Loggerxo</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="relative flex min-h-full flex-col items-center justify-center overflow-hidden px-4 py-12">
    <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(60%_50%_at_50%_0%,rgba(124,58,237,.18),transparent_70%)]"></div>
    <div class="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(rgba(255,255,255,.025)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.025)_1px,transparent_1px)] bg-[size:48px_48px] [mask-image:radial-gradient(ellipse_at_center,black_30%,transparent_75%)]"></div>

    <a href="{{ route('home') }}" class="mb-8 flex items-center gap-2.5">
        <x-logo class="h-8 w-8" />
        <span class="text-lg font-semibold tracking-tight text-white">Loggerxo</span>
    </a>

    <div class="w-full max-w-[400px]">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-[13px] text-emerald-200">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-[13px] text-red-200">{{ session('error') }}</div>
        @endif
        {{ $slot }}
    </div>

    <p class="mt-10 text-xs text-ink-500">© {{ date('Y') }} Loggerxo · <a href="{{ route('docs') }}" class="hover:text-ink-300">Docs</a></p>
</div>
</body>
</html>
