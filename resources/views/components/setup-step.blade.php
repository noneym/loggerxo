@props(['n', 'title'])
<div class="flex gap-4">
    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-600/20 text-[12px] font-bold text-brand-300">{{ $n }}</span>
    <div class="min-w-0 flex-1 space-y-2">
        <div class="text-[14px] font-semibold text-ink-100">{{ $title }}</div>
        {{ $slot }}
    </div>
</div>
