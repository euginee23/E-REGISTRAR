<?php

namespace App\Actions\Slots;

use App\Models\TimeSlot;
use Carbon\CarbonImmutable;

class GenerateTimeSlots
{
    /**
     * Generate appointment time slots across a range of dates.
     *
     * Slots are only ever created on the days the registrar's office is open,
     * which is what keeps a student from booking a weekend: the date picker
     * simply returns nothing for a closed day. Existing slots are updated in
     * place rather than duplicated, so re-running this is safe.
     *
     * @return int The number of slots created or refreshed.
     */
    public function __invoke(
        CarbonImmutable $from,
        CarbonImmutable $until,
        ?string $opensAt = null,
        ?string $closesAt = null,
        ?int $slotMinutes = null,
        ?int $capacity = null,
    ): int {
        $opensAt ??= (string) config('registrar.office.opens_at');
        $closesAt ??= (string) config('registrar.office.closes_at');
        $slotMinutes ??= (int) config('registrar.office.slot_minutes');
        $capacity ??= (int) config('registrar.office.default_capacity');

        /** @var array<int, int> $openDays */
        $openDays = config('registrar.office.open_days');

        $rows = [];
        $now = CarbonImmutable::now();

        for ($date = $from->startOfDay(); $date->lessThanOrEqualTo($until); $date = $date->addDay()) {
            if (! in_array($date->dayOfWeekIso, $openDays, strict: true)) {
                continue;
            }

            $opens = $date->setTimeFromTimeString($opensAt);
            $closes = $date->setTimeFromTimeString($closesAt);

            for ($start = $opens; $start->addMinutes($slotMinutes)->lessThanOrEqualTo($closes); $start = $start->addMinutes($slotMinutes)) {
                $rows[] = [
                    'slot_date' => $date->toDateString(),
                    'start_time' => $start->format('H:i:s'),
                    'end_time' => $start->addMinutes($slotMinutes)->format('H:i:s'),
                    'capacity' => $capacity,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows === []) {
            return 0;
        }

        // booked_count is deliberately excluded from the update columns so
        // regenerating a range never discards existing bookings.
        TimeSlot::query()->upsert(
            $rows,
            uniqueBy: ['slot_date', 'start_time'],
            update: ['end_time', 'capacity', 'is_active', 'updated_at'],
        );

        return count($rows);
    }
}
