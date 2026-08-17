@props([
    'heading',
    'subheading' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-start justify-between gap-4']) }}>
    <div class="flex flex-col gap-1">
        <flux:heading size="lg">{{ $heading }}</flux:heading>

        @if ($subheading)
            <flux:subheading>{{ $subheading }}</flux:subheading>
        @endif
    </div>

    @if (trim($slot) !== '')
        <div class="flex items-center gap-2">{{ $slot }}</div>
    @endif
</div>
