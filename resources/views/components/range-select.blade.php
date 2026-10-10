<select {{ $attributes->merge(['class' => 'select w-auto py-1.5 text-xs']) }}>
    @foreach (\App\Services\EventRepository::rangeOptions() as $key => $label)
        <option value="{{ $key }}">{{ $label }}</option>
    @endforeach
</select>
