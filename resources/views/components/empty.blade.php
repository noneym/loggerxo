@props(['icon' => 'inbox', 'title', 'body' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-xl border border-ink-700 bg-ink-850 text-ink-400"><x-icon :name="$icon" class="h-5 w-5" /></span>
    <h3 class="mt-4 text-[15px] font-semibold text-ink-100">{{ $title }}</h3>
    @if ($body) <p class="mt-1 max-w-sm text-[13px] text-ink-400">{{ $body }}</p> @endif
    @if (trim($slot)) <div class="mt-5">{{ $slot }}</div> @endif
</div>
