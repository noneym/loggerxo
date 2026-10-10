@props(['data'])
@if (is_array($data))
    <dl class="divide-y divide-ink-800 overflow-hidden rounded-lg border border-ink-800 text-[12.5px]">
        @foreach ($data as $k => $v)
            <div class="grid grid-cols-[minmax(7rem,30%)_1fr] gap-3 px-3 py-1.5">
                <dt class="truncate font-mono text-ink-400" title="{{ $k }}">{{ $k }}</dt>
                <dd class="min-w-0 break-words font-mono text-ink-100">
                    @if (is_array($v))
                        <pre class="whitespace-pre-wrap text-[11.5px] text-ink-300">{{ json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    @elseif (is_bool($v))
                        {{ $v ? 'true' : 'false' }}
                    @elseif ($v === null)
                        <span class="text-ink-500">null</span>
                    @else
                        {{ $v }}
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
@else
    <pre class="code-block">{{ $data }}</pre>
@endif
