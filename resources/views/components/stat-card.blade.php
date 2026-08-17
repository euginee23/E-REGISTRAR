@props([
    'label',
    'value',
    'icon' => null,
    'hint' => null,
    'href' => null,
])

<flux:card
    :href="$href"
    {{ $attributes->merge(['class' => 'flex flex-col gap-2' . ($href ? ' transition hover:border-zinc-300' : '')]) }}
>
    <div class="flex items-center justify-between gap-2">
        <flux:text size="sm" class="font-medium">{{ $label }}</flux:text>

        @if ($icon)
            <flux:icon :name="$icon" variant="mini" class="text-zinc-400" />
        @endif
    </div>

    <flux:heading size="xl" class="tabular-nums">{{ $value }}</flux:heading>

    @if ($hint)
        <flux:text size="sm" class="text-zinc-500">{{ $hint }}</flux:text>
    @endif
</flux:card>
