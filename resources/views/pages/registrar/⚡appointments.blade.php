<?php

use App\Actions\Appointments\UpdateAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Appointments')] class extends Component {
    #[Url]
    public string $view = 'month';

    #[Url]
    public string $month = '';

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

        if ($this->month === '') {
            $this->month = CarbonImmutable::parse($this->date)->format('Y-m');
        }

        if (! in_array($this->view, ['month', 'day'], true)) {
            $this->view = 'month';
        }
    }

    /**
     * Drop into the day view whenever a specific date is chosen.
     *
     * Picking a date is an unambiguous request for that day, so the view
     * follows the date rather than making the user switch twice.
     */
    public function updatedDate(): void
    {
        $this->view = 'day';
        $this->month = CarbonImmutable::parse($this->date)->format('Y-m');

        unset($this->daySlots, $this->summary, $this->monthGrid, $this->monthSummary);
    }

    /**
     * Get the weeks of the chosen month, each day carrying its bookings.
     *
     * Filled from a single aggregate query: the grid spans five or six weeks
     * and would otherwise issue a query per day.
     *
     * @return array<int, array<int, array{date: string, day: int, inMonth: bool, isToday: bool, total: int, byStatus: array<string, int>}>>
     */
    #[Computed]
    public function monthGrid(): array
    {
        $firstOfMonth = CarbonImmutable::parse($this->month.'-01');
        $start = $firstOfMonth->startOfMonth()->startOfWeek(CarbonInterface::SUNDAY);
        $end = $firstOfMonth->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        $counts = $this->countsBetween($start, $end);

        $today = CarbonImmutable::today()->toDateString();
        $weeks = [];
        $week = [];

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $key = $day->toDateString();
            $byStatus = $counts[$key] ?? [];

            $week[] = [
                'date' => $key,
                'day' => $day->day,
                'inMonth' => $day->month === $firstOfMonth->month,
                'isToday' => $key === $today,
                'total' => array_sum($byStatus),
                'byStatus' => $byStatus,
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    /**
     * Count every student's appointments per day and status across a range.
     *
     * @return array<string, array<string, int>>
     */
    private function countsBetween(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = Appointment::query()
            ->join('time_slots', 'time_slots.id', '=', 'appointments.time_slot_id')
            ->whereBetween('time_slots.slot_date', [$start->toDateString(), $end->toDateString()])
            // Aliased away from "status" and "slot_date" so the model's enum
            // and date casts do not fire on these aggregate rows.
            ->selectRaw('time_slots.slot_date as day, appointments.status as status_value, count(*) as total')
            ->groupBy('time_slots.slot_date', 'appointments.status')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            // SQLite hands back "Y-m-d 00:00:00" for a date column while MySQL
            // returns "Y-m-d"; keying on the raw value would empty the grid
            // under one of the two drivers.
            $key = Str::before((string) $row->day, ' ');

            $counts[$key][(string) $row->status_value] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * Summarise the chosen month for the header.
     *
     * @return array{booked: int, completed: int, noShows: int, busiest: string|null}
     */
    #[Computed]
    public function monthSummary(): array
    {
        $days = collect($this->monthGrid)->flatten(1)->filter(fn (array $day): bool => $day['inMonth']);

        $totalFor = fn (AppointmentStatus $status): int => (int) $days
            ->sum(fn (array $day): int => $day['byStatus'][$status->value] ?? 0);

        $busiest = $days->sortByDesc('total')->first();

        return [
            'booked' => (int) $days->sum(fn (array $day): int => array_sum(array_intersect_key(
                $day['byStatus'],
                array_flip(array_map(fn (AppointmentStatus $s): string => $s->value, AppointmentStatus::occupying())),
            ))),
            'completed' => $totalFor(AppointmentStatus::Completed),
            'noShows' => $totalFor(AppointmentStatus::NoShow),
            'busiest' => ($busiest !== null && $busiest['total'] > 0) ? $busiest['date'] : null,
        ];
    }

    /**
     * Step the grid to another month.
     */
    public function shiftMonth(int $months): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->addMonths($months)->format('Y-m');
        $this->view = 'month';

        unset($this->monthGrid, $this->monthSummary);
    }

    /**
     * Go back to the month grid.
     */
    public function showMonth(): void
    {
        $this->view = 'month';
        $this->month = CarbonImmutable::parse($this->date)->format('Y-m');

        unset($this->monthGrid, $this->monthSummary);
    }

    /**
     * Open one day from the month grid.
     */
    public function showDay(string $date): void
    {
        $this->date = CarbonImmutable::parse($date)->toDateString();
        $this->view = 'day';

        unset($this->daySlots, $this->summary);
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
     * Refresh the day after a child component moves an appointment.
     */
    #[On('appointment-updated')]
    public function refreshDay(): void
    {
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
        :subheading="$view === 'month'
            ? CarbonImmutable::parse($month . '-01')->format('F Y')
            : CarbonImmutable::parse($date)->format('l, F j, Y')"
    >
        @if ($view === 'month')
            <flux:button wire:click="shiftMonth(-1)" size="sm" variant="ghost" icon="chevron-left" :aria-label="__('Previous month')" data-test="previous-month" />
            <flux:button wire:click="today" size="sm" variant="ghost">{{ __('Today') }}</flux:button>
            <flux:button wire:click="shiftMonth(1)" size="sm" variant="ghost" icon="chevron-right" :aria-label="__('Next month')" data-test="next-month" />
        @else
            <flux:button wire:click="shiftDay(-1)" size="sm" variant="ghost" icon="chevron-left" :aria-label="__('Previous day')" />
            <flux:button wire:click="today" size="sm" variant="ghost">{{ __('Today') }}</flux:button>
            <flux:button wire:click="shiftDay(1)" size="sm" variant="ghost" icon="chevron-right" :aria-label="__('Next day')" />
        @endif
    </x-page-heading>

    <div class="flex flex-wrap items-end gap-4">
        <flux:radio.group wire:model.live="view" variant="segmented" :label="__('View')" data-test="view-toggle">
            <flux:radio value="month" :label="__('Month')" />
            <flux:radio value="day" :label="__('Day')" />
        </flux:radio.group>

        <flux:input wire:model.live="date" :label="__('Date')" type="date" class="max-w-44" data-test="date-input" />

        @if ($view === 'month')
            <div class="grid flex-1 gap-3 sm:grid-cols-3">
                <x-stat-card :label="__('Booked this month')" :value="$this->monthSummary['booked']" icon="users" />
                <x-stat-card :label="__('Completed')" :value="$this->monthSummary['completed']" icon="check-badge" />
                <x-stat-card :label="__('No shows')" :value="$this->monthSummary['noShows']" icon="x-circle" />
            </div>
        @else
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
        @endif
    </div>

    @if ($view === 'month')
        <flux:card class="flex flex-col gap-3" data-test="month-calendar">
            <div class="grid grid-cols-7 gap-1 text-center">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                    <flux:text size="sm" class="py-1 font-medium text-zinc-500">{{ __($weekday) }}</flux:text>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-1">
                @foreach ($this->monthGrid as $week)
                    @foreach ($week as $day)
                        <button
                            type="button"
                            wire:key="day-{{ $day['date'] }}"
                            wire:click="showDay('{{ $day['date'] }}')"
                            @class([
                                'flex min-h-20 flex-col gap-1 rounded-lg border p-2 text-start transition',
                                'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800',
                                'opacity-40' => ! $day['inMonth'],
                                'ring-2 ring-accent' => $day['isToday'],
                            ])
                            data-test="calendar-day"
                        >
                            <span class="text-sm font-medium tabular-nums">{{ $day['day'] }}</span>

                            @if ($day['total'] > 0)
                                <span class="text-xs text-zinc-500" data-test="day-count">
                                    {{ trans_choice('{1} :count booking|[2,*] :count bookings', $day['total'], ['count' => $day['total']]) }}
                                </span>

                                <span class="mt-auto flex flex-wrap gap-1">
                                    @foreach ($day['byStatus'] as $status => $count)
                                        @php $case = App\Enums\AppointmentStatus::from($status); @endphp
                                        <span
                                            class="inline-block size-2 rounded-full {{ $case->dotClass() }}"
                                            title="{{ $case->label() }}: {{ $count }}"
                                        ></span>
                                    @endforeach
                                </span>
                            @endif
                        </button>
                    @endforeach
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                @foreach (App\Enums\AppointmentStatus::cases() as $case)
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block size-2 rounded-full {{ $case->dotClass() }}"></span>
                        <flux:text size="sm" class="text-zinc-500">{{ $case->label() }}</flux:text>
                    </span>
                @endforeach
            </div>
        </flux:card>
    @elseif ($this->daySlots->isEmpty())
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
            <div>
                <flux:button wire:click="showMonth" size="sm" variant="ghost" icon="arrow-left" data-test="back-to-month">
                    {{ __('Back to month') }}
                </flux:button>
            </div>

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

                                                <livewire:registrar.reschedule-appointment
                                                    :appointment="$appointment"
                                                    :wire:key="'reschedule-' . $appointment->id . '-' . $appointment->time_slot_id"
                                                />
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
