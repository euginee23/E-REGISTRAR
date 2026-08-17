<?php

use App\Actions\Appointments\UpdateAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Appointments')] class extends Component {
    #[Url]
    public string $date = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        if ($this->date === '') {
            $this->date = CarbonImmutable::today()->toDateString();
        }
    }

    /**
     * Get the slots for the chosen day with their appointments attached.
     *
     * @return Collection<int, TimeSlot>
     */
    #[Computed]
    public function daySlots(): Collection
    {
        return TimeSlot::query()
            ->whereDate('slot_date', $this->date)
            ->with(['appointments.documentRequest.student.user', 'appointments.documentRequest.documentType'])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Summarise the day for the header.
     *
     * @return array{slots: int, capacity: int, booked: int, completed: int, noShows: int}
     */
    #[Computed]
    public function summary(): array
    {
        $appointments = $this->daySlots->flatMap->appointments;

        return [
            'slots' => $this->daySlots->count(),
            'capacity' => (int) $this->daySlots->sum('capacity'),
            'booked' => (int) $this->daySlots->sum('booked_count'),
            'completed' => $appointments->where('status', AppointmentStatus::Completed)->count(),
            'noShows' => $appointments->where('status', AppointmentStatus::NoShow)->count(),
        ];
    }

    /**
     * Step the calendar to another day.
     */
    public function shiftDay(int $days): void
    {
        $this->date = CarbonImmutable::parse($this->date)->addDays($days)->toDateString();

        unset($this->daySlots, $this->summary);
    }

    /**
     * Jump the calendar back to today.
     */
    public function today(): void
    {
        $this->date = CarbonImmutable::today()->toDateString();

        unset($this->daySlots, $this->summary);
    }

    /**
     * Move an appointment to another status.
     */
    public function setStatus(
        int $appointmentId,
        string $status,
        UpdateAppointmentStatus $updateAppointmentStatus,
    ): void {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $to = AppointmentStatus::from($status);

        Gate::authorize(match ($to) {
            AppointmentStatus::Confirmed => 'confirm',
            AppointmentStatus::Cancelled => 'cancel',
            default => 'complete',
        }, $appointment);

        $updateAppointmentStatus($appointment, $to, Auth::user());

        unset($this->daySlots, $this->summary);

        Flux::toast(variant: 'success', text: __('Appointment marked as :status.', ['status' => $to->label()]));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Appointment calendar')"
        :subheading="CarbonImmutable::parse($date)->format('l, F j, Y')"
    >
        <flux:button wire:click="shiftDay(-1)" size="sm" variant="ghost" icon="chevron-left" :aria-label="__('Previous day')" />
        <flux:button wire:click="today" size="sm" variant="ghost">{{ __('Today') }}</flux:button>
        <flux:button wire:click="shiftDay(1)" size="sm" variant="ghost" icon="chevron-right" :aria-label="__('Next day')" />
    </x-page-heading>

    <div class="flex flex-wrap items-end gap-4">
        <flux:input wire:model.live="date" :label="__('Date')" type="date" class="max-w-44" data-test="date-input" />

        <div class="grid flex-1 gap-3 sm:grid-cols-4">
            <x-stat-card :label="__('Slots')" :value="$this->summary['slots']" icon="clock" />
            <x-stat-card
                :label="__('Booked')"
                :value="$this->summary['booked'] . ' / ' . $this->summary['capacity']"
                icon="users"
            />
            <x-stat-card :label="__('Completed')" :value="$this->summary['completed']" icon="check-badge" />
            <x-stat-card :label="__('No shows')" :value="$this->summary['noShows']" icon="x-circle" />
        </div>
    </div>

    @if ($this->daySlots->isEmpty())
        <x-empty-state
            icon="calendar"
            :heading="__('No slots on this date')"
            :description="__('The office is closed, or no slots have been opened for this day yet.')"
        >
            <flux:button :href="route('registrar.time-slots.index')" size="sm" wire:navigate>
                {{ __('Manage time slots') }}
            </flux:button>
        </x-empty-state>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($this->daySlots as $slot)
                <flux:card wire:key="slot-{{ $slot->id }}" class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <flux:heading size="sm">{{ $slot->label }}</flux:heading>

                            <flux:badge :color="$slot->isFull() ? 'red' : 'zinc'" size="sm">
                                {{ __(':booked of :capacity booked', [
                                    'booked' => $slot->booked_count,
                                    'capacity' => $slot->capacity,
                                ]) }}
                            </flux:badge>

                            @unless ($slot->is_active)
                                <flux:badge color="amber" size="sm">{{ __('Closed') }}</flux:badge>
                            @endunless
                        </div>
                    </div>

                    @if ($slot->appointments->isEmpty())
                        <flux:text size="sm" class="text-zinc-500">{{ __('Nobody booked this slot.') }}</flux:text>
                    @else
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Student') }}</flux:table.column>
                                <flux:table.column>{{ __('Document') }}</flux:table.column>
                                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                                <flux:table.column>{{ __('Status') }}</flux:table.column>
                                <flux:table.column />
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($slot->appointments as $appointment)
                                    <flux:table.row wire:key="appointment-{{ $appointment->id }}">
                                        <flux:table.cell>{{ $appointment->documentRequest->student->user->name }}</flux:table.cell>
                                        <flux:table.cell>{{ $appointment->documentRequest->display_name }}</flux:table.cell>
                                        <flux:table.cell class="font-mono text-xs">
                                            {{ $appointment->documentRequest->reference_no }}
                                        </flux:table.cell>
                                        <flux:table.cell><x-status-badge :status="$appointment->status" /></flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex justify-end gap-1">
                                                @can('confirm', $appointment)
                                                    <flux:button
                                                        wire:click="setStatus({{ $appointment->id }}, 'confirmed')"
                                                        size="xs"
                                                        variant="ghost"
                                                        data-test="confirm-appointment"
                                                    >
                                                        {{ __('Confirm') }}
                                                    </flux:button>
                                                @endcan

                                                @can('complete', $appointment)
                                                    <flux:button
                                                        wire:click="setStatus({{ $appointment->id }}, 'completed')"
                                                        size="xs"
                                                        variant="ghost"
                                                        data-test="complete-appointment"
                                                    >
                                                        {{ __('Claimed') }}
                                                    </flux:button>

                                                    <flux:button
                                                        wire:click="setStatus({{ $appointment->id }}, 'no_show')"
                                                        size="xs"
                                                        variant="subtle"
                                                        data-test="no-show-appointment"
                                                    >
                                                        {{ __('No show') }}
                                                    </flux:button>
                                                @endcan
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
