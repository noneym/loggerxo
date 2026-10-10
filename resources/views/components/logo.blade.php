{{--
    Loggerxo mark: a pair of square brackets with the event inside, like the `[level]` prefix of a log line.
    Brackets follow currentColor so the mark works on any surface; only the inner square carries the accent.
    Usage: <x-logo class="h-7 w-7" />  or  <x-logo class="h-7 w-7" mono />
--}}
@props(['mono' => false])
<svg {{ $attributes->merge(['class' => 'h-7 w-7']) }} viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path d="M11.5 5.5H7.5a2 2 0 0 0-2 2v17a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M20.5 5.5h4a2 2 0 0 1 2 2v17a2 2 0 0 1-2 2h-4" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
    <rect x="12.25" y="12.25" width="7.5" height="7.5" rx="1.6" fill="{{ $mono ? 'currentColor' : 'var(--color-brand-500, #8b5cf6)' }}"/>
</svg>
