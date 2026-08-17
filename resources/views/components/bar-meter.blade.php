@props([
    'label',
    'value',
    'max',
    'suffix' => null,
])

@php
    $percentage = $max > 0 ? min(100, (int) round(($value / $max) * 100)) : 0;
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5']) }}>
    <div class="flex items-baseline justify-between gap-4">
        <flux:text size="sm" class="truncate">{{ $label }}</flux:text>
        <flux:text size="sm" class="shrink-0 font-medium tabular-nums">
            {{ $value }}{{ $suffix }}
        </flux:text>
    </div>

    <div
        class="h-2 w-full overflow-hidden rounded-full bg-zinc-100"
        role="meter"
        aria-valuenow="{{ $value }}"
        aria-valuemin="0"
        aria-valuemax="{{ $max }}"
        aria-label="{{ $label }}"
    >
        <div class="h-full rounded-full bg-accent" style="width: {{ $percentage }}%"></div>
    </div>
</div>
