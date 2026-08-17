<?php

use App\Actions\Slots\GenerateTimeSlots;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Time slots')] class extends Component {
    #[Url]
    public string $date = '';

    public string $generateFrom = '';
    public string $generateUntil = '';
    public int $generateCapacity = 5;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        Gate::authorize('create', TimeSlot::class);

        if ($this->date === '') {
            $this->date = CarbonImmutable::today()->toDateString();
        }

        $this->generateFrom = CarbonImmutable::today()->toDateString();
        $this->generateUntil = CarbonImmutable::today()->addDays(30)->toDateString();
        $this->generateCapacity = (int) config('registrar.office.default_capacity');
    }

    /**
     * Get the slots on the chosen day.
     *
     * @return Collection<int, TimeSlot>
     */
    #[Computed]
    public function daySlots(): Collection
    {
        return TimeSlot::query()
            ->whereDate('slot_date', $this->date)
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Open or close a single slot.
     */
    public function toggleSlot(int $slotId): void
    {
        $slot = TimeSlot::query()->findOrFail($slotId);

        Gate::authorize('update', $slot);

        $slot->forceFill(['is_active' => ! $slot->is_active])->save();

        unset($this->daySlots);

        Flux::toast(variant: 'success', text: $slot->is_active
            ? __('Slot reopened.')
            : __('Slot closed.'));
    }

    /**
     * Change how many students a slot can take.
     */
    public function updateCapacity(int $slotId, int $capacity): void
    {
        $slot = TimeSlot::query()->findOrFail($slotId);

        Gate::authorize('update', $slot);

        if ($capacity < $slot->booked_count) {
            Flux::toast(variant: 'danger', text: __('Capacity cannot be lower than the :count booking(s) already taken.', [
                'count' => $slot->booked_count,
            ]));

            return;
        }

        $slot->forceFill(['capacity' => max(1, $capacity)])->save();

        unset($this->daySlots);

        Flux::toast(variant: 'success', text: __('Capacity updated.'));
    }

    /**
     * Open slots across a range of dates.
     */
    public function generateSlots(GenerateTimeSlots $generateTimeSlots): void
    {
        Gate::authorize('create', TimeSlot::class);

        $validated = $this->validate([
            'generateFrom' => ['required', 'date'],
            'generateUntil' => ['required', 'date', 'after_or_equal:generateFrom'],
            'generateCapacity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $created = $generateTimeSlots(
            CarbonImmutable::parse($validated['generateFrom']),
            CarbonImmutable::parse($validated['generateUntil']),
            capacity: $validated['generateCapacity'],
        );

        unset($this->daySlots);

        Flux::modal('generate-slots')->close();
        Flux::toast(variant: 'success', text: trans_choice(
            '{0} No weekdays fell in that range.|{1} :count slot opened.|[2,*] :count slots opened.',
            $created,
            ['count' => $created],
        ));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Time slots')"
        :subheading="__('Open, close, and size the appointment slots students can book.')"
    >
        <flux:modal.trigger name="generate-slots">
            <flux:button variant="primary" size="sm" icon="plus" data-test="generate-slots-trigger">
                {{ __('Open slots') }}
            </flux:button>
        </flux:modal.trigger>
    </x-page-heading>

    <flux:input wire:model.live="date" :label="__('Date')" type="date" class="max-w-44" data-test="date-input" />

    @if ($this->daySlots->isEmpty())
        <x-empty-state
            icon="clock"
            :heading="__('No slots on this date')"
            :description="__('Weekends are never given slots. Use \'Open slots\' to add them for a range of weekdays.')"
        />
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Booked') }}</flux:table.column>
                <flux:table.column>{{ __('Capacity') }}</flux:table.column>
                <flux:table.column>{{ __('State') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->daySlots as $slot)
                    <flux:table.row wire:key="slot-{{ $slot->id }}">
                        <flux:table.cell>{{ $slot->label }}</flux:table.cell>
                        <flux:table.cell>{{ $slot->booked_count }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-1">
                                <flux:button
                                    wire:click="updateCapacity({{ $slot->id }}, {{ $slot->capacity - 1 }})"
                                    size="xs"
                                    variant="subtle"
                                    icon="minus"
                                    :aria-label="__('Reduce capacity')"
                                    data-test="decrease-capacity"
                                />
                                <span class="w-8 text-center tabular-nums">{{ $slot->capacity }}</span>
                                <flux:button
                                    wire:click="updateCapacity({{ $slot->id }}, {{ $slot->capacity + 1 }})"
                                    size="xs"
                                    variant="subtle"
                                    icon="plus"
                                    :aria-label="__('Increase capacity')"
                                    data-test="increase-capacity"
                                />
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$slot->is_active ? 'green' : 'zinc'" size="sm">
                                {{ $slot->is_active ? __('Open') : __('Closed') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                wire:click="toggleSlot({{ $slot->id }})"
                                size="xs"
                                variant="ghost"
                                data-test="toggle-slot"
                            >
                                {{ $slot->is_active ? __('Close') : __('Reopen') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="generate-slots" class="min-w-[26rem]">
        <form wire:submit="generateSlots" class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <flux:heading size="lg">{{ __('Open appointment slots') }}</flux:heading>
                <flux:text>
                    {{ __('Slots are created for weekdays only, from :opens to :closes. Existing slots keep their bookings.', [
                        'opens' => config('registrar.office.opens_at'),
                        'closes' => config('registrar.office.closes_at'),
                    ]) }}
                </flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="generateFrom" :label="__('From')" type="date" required data-test="generate-from" />
                <flux:input wire:model="generateUntil" :label="__('Until')" type="date" required data-test="generate-until" />
            </div>

            <flux:input
                wire:model="generateCapacity"
                :label="__('Students per slot')"
                type="number"
                min="1"
                max="100"
                required
                data-test="generate-capacity"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" data-test="confirm-generate-slots">
                    {{ __('Open slots') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
