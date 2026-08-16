<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Confirmed => __('Confirmed'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
            self::NoShow => __('No show'),
        };
    }

    /**
     * Get the Flux badge colour representing the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'amber',
            self::Confirmed => 'blue',
            self::Completed => 'green',
            self::Cancelled => 'zinc',
            self::NoShow => 'red',
        };
    }

    /**
     * Determine whether an appointment in this status consumes a slot seat.
     *
     * This is the single definition of "booked" in the system. Booking,
     * cancellation, rescheduling, and the slot recount command all derive
     * TimeSlot::$booked_count from it.
     */
    public function occupiesCapacity(): bool
    {
        return match ($this) {
            self::Scheduled, self::Confirmed, self::Completed => true,
            self::Cancelled, self::NoShow => false,
        };
    }

    /**
     * Get the statuses that still consume a seat in a time slot.
     *
     * @return array<int, self>
     */
    public static function occupying(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => $status->occupiesCapacity(),
        ));
    }
}
