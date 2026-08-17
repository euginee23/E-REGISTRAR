<?php

namespace App\Policies;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Determine whether the user may list appointments.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user may view the appointment.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->isStaff() || $this->owns($user, $appointment);
    }

    /**
     * Determine whether the user may move the appointment to another slot.
     */
    public function reschedule(User $user, Appointment $appointment): bool
    {
        if ($appointment->status->isFinished()) {
            return false;
        }

        return $user->isStaff() || $this->owns($user, $appointment);
    }

    /**
     * Determine whether the user may call off the appointment.
     */
    public function cancel(User $user, Appointment $appointment): bool
    {
        if ($appointment->status->isFinished()) {
            return false;
        }

        return $user->isStaff() || $this->owns($user, $appointment);
    }

    /**
     * Determine whether the user may confirm the appointment.
     */
    public function confirm(User $user, Appointment $appointment): bool
    {
        return $user->isStaff() && $appointment->status === AppointmentStatus::Scheduled;
    }

    /**
     * Determine whether the user may close out the appointment.
     *
     * Marking attendance is a front-desk action, so it is staff only.
     */
    public function complete(User $user, Appointment $appointment): bool
    {
        return $user->isStaff() && ! $appointment->status->isFinished();
    }

    /**
     * Determine whether the appointment belongs to the user.
     */
    private function owns(User $user, Appointment $appointment): bool
    {
        return $user->student !== null
            && $user->student->id === $appointment->documentRequest->student_id;
    }
}
