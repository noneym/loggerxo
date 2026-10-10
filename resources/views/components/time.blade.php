@props(['value'])
@php
    $dt = $value instanceof \DateTimeInterface ? \Carbon\CarbonImmutable::instance($value) : \Carbon\CarbonImmutable::parse((string) $value, 'UTC');
@endphp
<time datetime="{{ $dt->toIso8601String() }}" title="{{ $dt->format('Y-m-d H:i:s') }} UTC" data-relative {{ $attributes }}>{{ $dt->diffForHumans(short: true) }}</time>
