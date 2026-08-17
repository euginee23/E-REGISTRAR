<?php

namespace App\Policies;

use App\Models\TimeSlot;
use App\Models\User;

class TimeSlotPolicy
{
    /**
     * Determine whether the user may browse the appointment calendar.
     *
     * Students need to see open slots in order to book one.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user may view the slot.
     */
    public function view(User $user, TimeSlot $timeSlot): bool
    {
        return true;
    }

    /**
     * Determine whether the user may open new slots.
     */
    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    /**
     * Determine whether the user may change a slot's capacity or hours.
     */
    public function update(User $user, TimeSlot $timeSlot): bool
    {
        return $user->isStaff();
    }

    /**
     * Determine whether the user may remove the slot.
     *
     * A slot with bookings is closed rather than deleted so the appointments
     * pointing at it keep their history.
     */
    public function delete(User $user, TimeSlot $timeSlot): bool
    {
        return $user->isStaff() && $timeSlot->booked_count === 0;
    }
}
