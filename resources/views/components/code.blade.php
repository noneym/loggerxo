@php $code = trim($slot); @endphp
<div class="relative group">
    <pre {{ $attributes->merge(['class' => 'code-block pr-16']) }}>{{ $code }}</pre>
    <button type="button" onclick="lxCopy(this.previousElementSibling.textContent, this)" class="absolute right-2 top-2 rounded-md border border-ink-700 bg-ink-900 px-2 py-1 text-[11px] text-ink-400 opacity-0 transition group-hover:opacity-100 hover:text-ink-100">Copy</button>
</div>
