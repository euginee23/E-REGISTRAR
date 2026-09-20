@props([
    'slots',
    'selectedSlotId' => null,
    'select' => 'selectSlot',
    'currentSlotId' => null,
])

@if ($slots->isEmpty())
    <x-empty-state
        icon="calendar"
        :heading="__('No slots on this date')"
        :description="__('The registrar\'s office is open Monday to Friday, 8:00 AM to 5:00 PM. Try another date.')"
        data-test="no-slots"
    />
@else
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-test="slot-grid">
        @foreach ($slots as $slot)
            @php
                $isCurrent = $currentSlotId !== null && (int) $currentSlotId === $slot->id;
            @endphp

            <flux:button
                type="button"
                wire:key="slot-{{ $slot->id }}"
                wire:click="{{ $select }}({{ $slot->id }})"
                :variant="$selectedSlotId === $slot->id ? 'primary' : 'outline'"
                :disabled="$isCurrent || $slot->isFull() || ! $slot->startsAt()->isFuture()"
                class="flex-col items-start gap-1 py-3"
                data-test="slot-option"
            >
                <span class="font-medium">{{ $slot->label }}</span>
                <span class="text-xs opacity-75">
                    @if ($isCurrent)
                        {{ __('Current time') }}
                    @elseif ($slot->isFull())
                        {{ __('Fully booked') }}
                    @elseif (! $slot->startsAt()->isFuture())
                        {{ __('Passed') }}
                    @else
                        {{ __(':count of :capacity left', [
                            'count' => $slot->remaining_capacity,
                            'capacity' => $slot->capacity,
                        ]) }}
                    @endif
                </span>
            </flux:button>
        @endforeach
    </div>
@endif
