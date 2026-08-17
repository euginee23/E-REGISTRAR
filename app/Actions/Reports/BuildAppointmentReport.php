<?php

namespace App\Actions\Reports;

use App\Enums\AppointmentStatus;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;

class BuildAppointmentReport
{
    /**
     * Summarise slot usage and attendance for each day in the range.
     *
     * @return array{
     *     rows: list<array{
     *         date: string, slots: int, capacity: int, booked: int,
     *         completed: int, noShows: int, cancelled: int, utilisation: int
     *     }>,
     *     totals: array{slots: int, capacity: int, booked: int, completed: int, noShows: int, cancelled: int},
     *     utilisation: int
     * }
     */
    public function __invoke(?CarbonImmutable $from = null, ?CarbonImmutable $until = null): array
    {
        $slots = TimeSlot::query()
            ->when($from, fn ($query) => $query->whereDate('slot_date', '>=', $from))
            ->when($until, fn ($query) => $query->whereDate('slot_date', '<=', $until))
            ->withCount([
                'appointments as completed_total' => fn ($query) => $query->where('status', AppointmentStatus::Completed),
                'appointments as no_show_total' => fn ($query) => $query->where('status', AppointmentStatus::NoShow),
                'appointments as cancelled_total' => fn ($query) => $query->where('status', AppointmentStatus::Cancelled),
            ])
            ->orderBy('slot_date')
            ->get();

        $rows = [];
        $totals = ['slots' => 0, 'capacity' => 0, 'booked' => 0, 'completed' => 0, 'noShows' => 0, 'cancelled' => 0];

        foreach ($slots->groupBy(fn (TimeSlot $slot): string => $slot->slot_date->toDateString()) as $date => $group) {
            $capacity = (int) $group->sum('capacity');
            $booked = (int) $group->sum('booked_count');
            $completed = (int) $group->sum('completed_total');
            $noShows = (int) $group->sum('no_show_total');
            $cancelled = (int) $group->sum('cancelled_total');

            $rows[] = [
                'date' => (string) $date,
                'slots' => $group->count(),
                'capacity' => $capacity,
                'booked' => $booked,
                'completed' => $completed,
                'noShows' => $noShows,
                'cancelled' => $cancelled,
                'utilisation' => $capacity > 0 ? (int) round(($booked / $capacity) * 100) : 0,
            ];

            $totals['slots'] += $group->count();
            $totals['capacity'] += $capacity;
            $totals['booked'] += $booked;
            $totals['completed'] += $completed;
            $totals['noShows'] += $noShows;
            $totals['cancelled'] += $cancelled;
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'utilisation' => $totals['capacity'] > 0
                ? (int) round(($totals['booked'] / $totals['capacity']) * 100)
                : 0,
        ];
    }
}
