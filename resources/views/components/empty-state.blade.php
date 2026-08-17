@props([
    'icon' => 'inbox',
    'heading',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-200 px-6 py-12 text-center']) }}>
    <flux:icon :name="$icon" class="size-8 text-zinc-300" />

    <div class="flex flex-col gap-1">
        <flux:heading size="sm">{{ $heading }}</flux:heading>

        @if ($description)
            <flux:text size="sm" class="max-w-sm text-zinc-500">{{ $description }}</flux:text>
        @endif
    </div>

    @if (trim($slot) !== '')
        <div class="mt-2">{{ $slot }}</div>
    @endif
</div>
