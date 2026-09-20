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
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reschedule appointment')] class extends Component {
    public Appointment $appointment;

    public string $date = '';

    public ?int $selectedSlotId = null;

    /**
     * Mount the component.
     */
    public function mount(Appointment $appointment): void
    {
        Gate::authorize('reschedule', $appointment);

        $this->appointment = $appointment->load(['timeSlot', 'documentRequest.documentType']);
        $this->date = $this->minDate;
    }

    /**
     * Get the earliest date the document could be collected.
     *
     * The processing window still applies: moving an appointment must not
     * land it before the document could possibly be ready.
     */
    #[Computed]
    public function minDate(): string
    {
        $earliest = $this->appointment->documentRequest->earliestClaimDate();
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
     * Move the appointment to the chosen slot.
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
            // Someone took the last seat between rendering and submitting.
            unset($this->availableSlots);
            $this->selectedSlotId = null;

            $this->addError('selectedSlotId', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Appointment moved.'));

        $this->redirectRoute('student.appointments.index', navigate: true);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Reschedule your appointment')"
        :subheading="__('Currently :date at :time for :document.', [
            'date' => $appointment->timeSlot->slot_date->format('F j, Y'),
            'time' => $appointment->timeSlot->label,
            'document' => $appointment->documentRequest->display_name,
        ])"
    >
        <flux:button :href="route('student.appointments.index')" variant="ghost" size="sm" wire:navigate>
            {{ __('Back to appointments') }}
        </flux:button>
    </x-page-heading>

    <flux:card class="flex flex-col gap-6">
        <flux:input
            wire:model.live="date"
            :label="__('New date')"
            :description="__('The registrar\'s office is open Monday to Friday, 8:00 AM to 5:00 PM.')"
            type="date"
            :min="$this->minDate"
            :max="$this->maxDate"
            class="max-w-xs"
            data-test="date-input"
        />

        <div class="flex flex-col gap-3">
            <flux:heading size="sm">{{ __('Available time slots') }}</flux:heading>

            <x-appointment-slots
                :slots="$this->availableSlots"
                :selected-slot-id="$selectedSlotId"
                :current-slot-id="$appointment->time_slot_id"
            />

            <flux:error name="selectedSlotId" />
        </div>

        <div class="flex items-center gap-3">
            <flux:button
                wire:click="reschedule"
                variant="primary"
                :disabled="$selectedSlotId === null"
                data-test="confirm-reschedule"
            >
                {{ __('Move appointment') }}
            </flux:button>

            <flux:button :href="route('student.appointments.index')" variant="ghost" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </flux:card>
</div>
