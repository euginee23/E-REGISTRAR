<?php

namespace App\Rules;

use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SlotIsBookable implements ValidationRule
{
    /**
     * Validate that a chosen slot can still be booked.
     *
     * This exists so the form can explain the problem in plain words. It is
     * not the guarantee - BookAppointment re-checks under a row lock, which
     * is what actually prevents overbooking.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail(__('Choose an available time slot.'));

            return;
        }

        $slot = TimeSlot::query()->whereKey((int) $value)->first();

        if ($slot === null) {
            $fail(__('Choose an available time slot.'));

            return;
        }

        if (! $slot->is_active) {
            $fail(__('That time slot has been closed by the registrar.'));

            return;
        }

        if (! $slot->startsAt()->isFuture()) {
            $fail(__('That time slot has already passed.'));

            return;
        }

        if ($slot->isFull()) {
            $fail(__('That time slot is fully booked. Please choose another.'));

            return;
        }

        $maxDate = CarbonImmutable::today()->addDays((int) config('registrar.booking.max_days_ahead'));

        if ($slot->slot_date->greaterThan($maxDate)) {
            $fail(__('Appointments can only be booked up to :days days ahead.', [
                'days' => config('registrar.booking.max_days_ahead'),
            ]));
        }
    }
}
