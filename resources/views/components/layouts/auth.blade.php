<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Loggerxo' }} · Loggerxo</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="relative flex min-h-full flex-col items-center justify-center overflow-hidden px-4 py-12">

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
