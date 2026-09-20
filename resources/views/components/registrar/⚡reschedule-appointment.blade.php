<?php

use App\Actions\Appointments\RescheduleAppointment;
use App\Exceptions\SlotFullyBookedException;
use App\Exceptions\SlotNotBookableException;
use App\Models\Appointment;
use App\Models\TimeSlot;
use App\Rules\SlotIsBookable;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Appointment $appointment;

    public string $date = '';

    public ?int $selectedSlotId = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->date = CarbonImmutable::today()->toDateString();
    }

    /**
     * Get the name of this appointment's modal.
     */
    #[Computed]
    public function modalName(): string
    {
        return 'reschedule-appointment-'.$this->appointment->id;
    }

    /**
     * Determine whether this appointment may still be moved.
     */
    #[Computed]
    public function canReschedule(): bool
    {
        return Auth::user()->can('reschedule', $this->appointment);
    }

    /**
     * Get the latest date the office accepts bookings for.
     */
    #[Computed]
    public function maxDate(): string
    {
        return TimeSlot::bookingHorizon()->toDateString();
    }

    /**
     * Get the slots on the chosen date.
     *
     * @return Collection<int, TimeSlot>
     */
    #[Computed]
    public function availableSlots(): Collection
    {
        return TimeSlot::offeredOn($this->date);
    }

    /**
     * Clear the chosen slot whenever the date changes.
     */
    public function updatedDate(): void
    {
        $this->selectedSlotId = null;
    }

    /**
     * Choose a slot.
     */
    public function selectSlot(int $slotId): void
    {
        $this->selectedSlotId = $slotId;
    }

    /**
     * Move the appointment to the chosen slot on the student's behalf.
     */
    public function reschedule(RescheduleAppointment $rescheduleAppointment): void
    {
        Gate::authorize('reschedule', $this->appointment);

        $this->validate(
            ['selectedSlotId' => ['required', new SlotIsBookable]],
            ['selectedSlotId.required' => __('Choose a new time slot first.')],
        );

        try {
            $rescheduleAppointment(
                $this->appointment,
                TimeSlot::query()->findOrFail($this->selectedSlotId),
                Auth::user(),
            );
        } catch (SlotFullyBookedException|SlotNotBookableException $e) {
            unset($this->availableSlots);
            $this->selectedSlotId = null;

            $this->addError('selectedSlotId', $e->getMessage());

            return;
        }

        $this->appointment->refresh();
        $this->reset('selectedSlotId');

        Flux::modal($this->modalName)->close();
        Flux::toast(variant: 'success', text: __('Appointment moved.'));

        $this->dispatch('appointment-updated');
    }
}; ?>

<div>
    @if ($this->canReschedule)
        <flux:modal.trigger :name="$this->modalName">
            <flux:button size="xs" variant="subtle" data-test="reschedule-trigger">
                {{ __('Reschedule') }}
            </flux:button>
        </flux:modal.trigger>

        <flux:modal :name="$this->modalName" class="min-w-[30rem]">
            <form wire:submit="reschedule" class="flex flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <flux:heading size="lg">{{ __('Move this appointment') }}</flux:heading>
                    <flux:text>
                        {{ __(':student is booked for :date at :time.', [
                            'student' => $appointment->documentRequest->student->user->name,
                            'date' => $appointment->timeSlot->slot_date->format('F j, Y'),
                            'time' => $appointment->timeSlot->label,
                        ]) }}
                    </flux:text>
                </div>

                <flux:input
                    wire:model.live="date"
                    :label="__('New date')"
                    type="date"
                    :max="$this->maxDate"
                    class="max-w-xs"
                    data-test="reschedule-date-input"
                />

                <div class="flex flex-col gap-3">
                    <x-appointment-slots
                        :slots="$this->availableSlots"
                        :selected-slot-id="$selectedSlotId"
                        :current-slot-id="$appointment->time_slot_id"
                    />

                    <flux:error name="selectedSlotId" />
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button
                        type="submit"
                        variant="primary"
                        :disabled="$selectedSlotId === null"
                        data-test="confirm-reschedule"
                    >
                        {{ __('Move appointment') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
