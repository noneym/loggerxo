@props(['type'])
@php
    $cls = match ($type) {
        'error' => 'border-rose-500/30 bg-rose-500/10 text-rose-300',
        'log' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
        'console' => 'border-brand-500/30 bg-brand-500/10 text-brand-300',
        'transaction' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
        default => 'border-ink-600 bg-ink-800 text-ink-300',
    };
@endphp
<span {{ $attributes->merge(['class' => 'badge '.$cls]) }}>{{ $type }}</span>
