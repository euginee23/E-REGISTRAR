<?php

use App\Actions\Appointments\BookAppointment;
use App\Exceptions\SlotFullyBookedException;
use App\Exceptions\SlotNotBookableException;
use App\Models\DocumentRequest;
use App\Models\TimeSlot;
use App\Rules\SlotIsBookable;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Book an appointment')] class extends Component {
    public DocumentRequest $documentRequest;

    public string $date = '';
    public ?int $selectedSlotId = null;

    /**
     * Mount the component.
     */
    public function mount(DocumentRequest $documentRequest): void
    {
        Gate::authorize('book', $documentRequest);

        $this->documentRequest = $documentRequest->load('documentType');
        $this->date = $this->minDate;
    }

    /**
     * Get the earliest date the document could be collected.
     */
    #[Computed]
    public function minDate(): string
    {
        $earliest = $this->documentRequest->earliestClaimDate();
        $today = CarbonImmutable::today();

        return $earliest->greaterThan($today)
            ? $earliest->toDateString()
            : $today->toDateString();
    }

    /**
     * Get the latest date the office accepts bookings for.
     */
    #[Computed]
    public function maxDate(): string
    {
        return CarbonImmutable::today()
            ->addDays((int) config('registrar.booking.max_days_ahead'))
            ->toDateString();
    }

    /**
     * Get the bookable slots on the chosen date.
     *
     * Slots only ever exist on days the office is open, so a weekend simply
     * yields nothing - which is what the empty state explains.
     *
     * @return Collection<int, TimeSlot>
     */
    #[Computed]
    public function availableSlots(): Collection
    {
        if ($this->date === '') {
            return new Collection;
        }

        return TimeSlot::query()
            ->onDate(CarbonImmutable::parse($this->date))
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Clear the chosen slot whenever the date changes.
     */
    public function updatedDate(): void
    {
        $this->selectedSlotId = null;
    }

    /**
     * Choose a slot to book.
     */
    public function selectSlot(int $slotId): void
    {
        $this->selectedSlotId = $slotId;
    }

    /**
     * Reserve the chosen slot.
     */
    public function book(BookAppointment $bookAppointment): void
    {
        Gate::authorize('book', $this->documentRequest);

        $this->validate(
            ['selectedSlotId' => ['required', new SlotIsBookable]],
            ['selectedSlotId.required' => __('Choose a time slot first.')],
        );

        try {
            $bookAppointment(
                $this->documentRequest,
                TimeSlot::query()->findOrFail($this->selectedSlotId),
            );
        } catch (SlotFullyBookedException|SlotNotBookableException $e) {
            // Someone took the last seat between rendering and submitting.
            unset($this->availableSlots);
            $this->selectedSlotId = null;

            $this->addError('selectedSlotId', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Appointment booked.'));

        $this->redirectRoute('student.appointments.index', navigate: true);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Book a claiming appointment')"
        :subheading="__('Reserve a time to collect :document (:reference).', [
            'document' => $documentRequest->display_name,
            'reference' => $documentRequest->reference_no,
        ])"
    >
        <flux:button :href="route('student.requests.show', $documentRequest)" variant="ghost" size="sm" wire:navigate>
            {{ __('Back to request') }}
        </flux:button>
    </x-page-heading>

    <flux:card class="flex flex-col gap-6">
        <flux:input
            wire:model.live="date"
            :label="__('Preferred date')"
            :description="__('The registrar\'s office is open Monday to Friday, 8:00 AM to 5:00 PM.')"
            type="date"
            :min="$this->minDate"
            :max="$this->maxDate"
            class="max-w-xs"
            data-test="date-input"
        />

        <div class="flex flex-col gap-3">
            <flux:heading size="sm">{{ __('Available time slots') }}</flux:heading>

            @if ($this->availableSlots->isEmpty())
                <x-empty-state
                    icon="calendar"
                    :heading="__('No slots on this date')"
                    :description="__('The registrar\'s office is open Monday to Friday, 8:00 AM to 5:00 PM. Try another date.')"
                    data-test="no-slots"
                />
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-test="slot-grid">
                    @foreach ($this->availableSlots as $slot)
                        <flux:button
                            type="button"
                            wire:key="slot-{{ $slot->id }}"
                            wire:click="selectSlot({{ $slot->id }})"
                            :variant="$selectedSlotId === $slot->id ? 'primary' : 'outline'"
                            :disabled="$slot->isFull() || ! $slot->startsAt()->isFuture()"
                            class="flex-col items-start gap-1 py-3"
                            data-test="slot-option"
                        >
                            <span class="font-medium">{{ $slot->label }}</span>
                            <span class="text-xs opacity-75">
                                @if ($slot->isFull())
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

            <flux:error name="selectedSlotId" />
        </div>

        <div class="flex items-center gap-3">
            <flux:button
                wire:click="book"
                variant="primary"
                :disabled="$selectedSlotId === null"
                data-test="confirm-booking"
            >
                {{ __('Confirm booking') }}
            </flux:button>

            <flux:button :href="route('student.requests.show', $documentRequest)" variant="ghost" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </flux:card>
</div>
