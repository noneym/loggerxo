@props(['label', 'value', 'tone' => 'sky', 'icon' => null, 'suffix' => '', 'hint' => null])
@php
    $tones = [
        'red' => 'bg-red-500/10 text-red-300',
        'amber' => 'bg-amber-500/10 text-amber-300',
        'sky' => 'bg-sky-500/10 text-sky-300',
        'emerald' => 'bg-emerald-500/10 text-emerald-300',
        'violet' => 'bg-brand-500/10 text-brand-300',
        'zinc' => 'bg-ink-700/60 text-ink-300',
    ];
    $display = is_numeric($value) ? number_format((float) $value) : $value;
@endphp
<div {{ $attributes->merge(['class' => 'card flex items-center gap-3 px-4 py-3.5']) }}>
    @if ($icon)
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['sky'] }}"><x-icon :name="$icon" class="h-4.5 w-4.5" /></span>
    @endif
    <div class="min-w-0">
        <div class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">{{ $label }}</div>
        <div class="truncate text-xl font-semibold tracking-tight text-white">{{ $display }}<span class="ml-0.5 text-sm font-normal text-ink-400">{{ $suffix }}</span></div>
        @if ($hint) <div class="text-[11px] text-ink-500">{{ $hint }}</div> @endif
    </div>
</div>
