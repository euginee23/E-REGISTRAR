<?php

use App\Actions\Appointments\UpdateAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My appointments')] class extends Component {
    public ?int $cancelling = null;

    /**
     * Get the student's appointments, soonest first.
     *
     * @return Collection<int, Appointment>
     */
    #[Computed]
    public function appointments(): Collection
    {
        return Appointment::query()
            ->whereHas('documentRequest', fn ($request) => $request->forStudent(Auth::user()->student))
            ->with(['timeSlot', 'documentRequest.documentType'])
            ->join('time_slots', 'time_slots.id', '=', 'appointments.time_slot_id')
            ->orderBy('time_slots.slot_date')
            ->orderBy('time_slots.start_time')
            ->select('appointments.*')
            ->get();
    }

    /**
     * Get the requests that are ready to be scheduled.
     *
     * @return Collection<int, App\Models\DocumentRequest>
     */
    #[Computed]
    public function bookable(): Collection
    {
        return App\Models\DocumentRequest::query()
            ->forStudent(Auth::user()->student)
            ->whereIn('status', [App\Enums\RequestStatus::Processing, App\Enums\RequestStatus::ReadyForRelease])
            ->doesntHave('appointment')
            ->with('documentType')
            ->get();
    }

    /**
     * Cancel one of the student's appointments.
     */
    public function cancelAppointment(int $appointmentId, UpdateAppointmentStatus $updateAppointmentStatus): void
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);

        Gate::authorize('cancel', $appointment);

        $updateAppointmentStatus(
            appointment: $appointment,
            to: AppointmentStatus::Cancelled,
            actor: Auth::user(),
            reason: __('Cancelled by the student.'),
        );

        unset($this->appointments, $this->bookable);

        Flux::modal('cancel-appointment-'.$appointmentId)->close();
        Flux::toast(variant: 'success', text: __('Appointment cancelled.'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('My appointments')"
        :subheading="__('Reserved times for collecting your documents.')"
    />

    @if ($this->bookable->isNotEmpty())
        <flux:callout icon="calendar-days">
            <flux:callout.heading>{{ __('Ready to schedule') }}</flux:callout.heading>
            <flux:callout.text>
                {{ trans_choice(
                    '{1} :count request is ready for you to book a claiming time.|[2,*] :count requests are ready for you to book a claiming time.',
                    $this->bookable->count(),
                    ['count' => $this->bookable->count()],
                ) }}
            </flux:callout.text>

            <x-slot name="actions">
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->bookable as $request)
                        <flux:button
                            :href="route('student.appointments.book', $request)"
                            size="sm"
                            wire:key="bookable-{{ $request->id }}"
                            wire:navigate
                        >
                            {{ __('Book for :document', ['document' => $request->display_name]) }}
                        </flux:button>
                    @endforeach
                </div>
            </x-slot>
        </flux:callout>
    @endif

    @if ($this->appointments->isEmpty())
        <x-empty-state
            icon="calendar-days"
            :heading="__('No appointments yet')"
            :description="__('Once a request is being processed you can reserve a time to collect it.')"
        />
    @else
        <div class="flex flex-col gap-3">
            @foreach ($this->appointments as $appointment)
                <flux:card wire:key="appointment-{{ $appointment->id }}" class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="sm">
                                {{ $appointment->timeSlot->slot_date->format('l, F j, Y') }}
                            </flux:heading>
                            <x-status-badge :status="$appointment->status" />
                        </div>

                        <flux:text size="sm">{{ $appointment->timeSlot->label }}</flux:text>

                        <flux:text size="sm" class="text-zinc-500">
                            {{ $appointment->documentRequest->display_name }}
                            &middot;
                            <span class="font-mono text-xs">{{ $appointment->documentRequest->reference_no }}</span>
                        </flux:text>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button
                            :href="route('student.requests.show', $appointment->documentRequest)"
                            size="xs"
                            variant="ghost"
                            wire:navigate
                        >
                            {{ __('View request') }}
                        </flux:button>

                        @can('cancel', $appointment)
                            <flux:modal.trigger :name="'cancel-appointment-' . $appointment->id">
                                <flux:button size="xs" variant="subtle" data-test="cancel-appointment-trigger">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </flux:modal.trigger>

                            <flux:modal :name="'cancel-appointment-' . $appointment->id" class="min-w-[22rem]">
                                <div class="flex flex-col gap-6">
                                    <div class="flex flex-col gap-2">
                                        <flux:heading size="lg">{{ __('Cancel this appointment?') }}</flux:heading>
                                        <flux:text>
                                            {{ __('Your reserved time on :date will be released for other students.', [
                                                'date' => $appointment->timeSlot->slot_date->format('F j, Y'),
                                            ]) }}
                                        </flux:text>
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <flux:modal.close>
                                            <flux:button variant="ghost">{{ __('Keep it') }}</flux:button>
                                        </flux:modal.close>

                                        <flux:button
                                            variant="danger"
                                            wire:click="cancelAppointment({{ $appointment->id }})"
                                            data-test="confirm-cancel-appointment"
                                        >
                                            {{ __('Cancel appointment') }}
                                        </flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        @endcan
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
