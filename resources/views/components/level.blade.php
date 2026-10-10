@props(['level'])
@php
    $l = \App\Support\Levels::normalize($level);
    $cls = match ($l) {
        'emergency', 'alert', 'critical' => 'border-rose-500/40 bg-rose-500/15 text-rose-300',
        'error' => 'border-red-500/40 bg-red-500/15 text-red-300',
        'warning' => 'border-amber-500/40 bg-amber-500/15 text-amber-300',
        'notice' => 'border-sky-500/40 bg-sky-500/15 text-sky-300',
        'info' => 'border-blue-500/40 bg-blue-500/15 text-blue-300',
        default => 'border-ink-600 bg-ink-800 text-ink-300',
    };
@endphp
<span {{ $attributes->merge(['class' => 'badge uppercase '.$cls]) }}>{{ $l }}</span>
