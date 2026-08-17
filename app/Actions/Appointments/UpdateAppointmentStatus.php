<?php

namespace App\Actions\Appointments;

use App\Actions\Notifications\SendNotification;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationType;
use App\Models\Appointment;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAppointmentStatus
{
    public function __construct(private SendNotification $sendNotification) {}

    /**
     * Move an appointment to a new status, keeping slot capacity in step.
     *
     * Confirming, completing, cancelling, and marking a no-show all run
     * through here so the slot's booked_count can never drift from what
     * AppointmentStatus::occupiesCapacity() says is true.
     */
    public function __invoke(
        Appointment $appointment,
        AppointmentStatus $to,
        User $actor,
        ?string $reason = null,
    ): Appointment {
        $from = $appointment->status;

        if ($from === $to) {
            return $appointment;
        }

        DB::transaction(function () use ($appointment, $from, $to, $actor, $reason): void {
            // Lock the slot before touching the counter so a concurrent
            // booking cannot interleave with this adjustment.
            $slot = TimeSlot::query()->whereKey($appointment->time_slot_id)->lockForUpdate()->firstOrFail();

            $appointment->status = $to;

            match ($to) {
                AppointmentStatus::Confirmed => $appointment->forceFill([
                    'confirmed_at' => now(),
                    'confirmed_by_user_id' => $actor->id,
                ]),
                AppointmentStatus::Completed => $appointment->forceFill(['completed_at' => now()]),
                AppointmentStatus::Cancelled => $appointment->forceFill([
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]),
                default => $appointment,
            };

            $appointment->save();

            $this->adjustCapacity($slot, $from, $to);
        }, attempts: 3);

        $this->notifyStudent($appointment, $to, $actor);

        return $appointment;
    }

    /**
     * Add or release a seat when the status crosses the occupancy boundary.
     */
    private function adjustCapacity(TimeSlot $slot, AppointmentStatus $from, AppointmentStatus $to): void
    {
        $was = $from->occupiesCapacity();
        $now = $to->occupiesCapacity();

        if ($was === $now) {
            return;
        }

        if ($now) {
            $slot->increment('booked_count');

            return;
        }

        // Guard against ever driving the counter negative.
        $slot->decrement('booked_count', $slot->booked_count > 0 ? 1 : 0);
    }

    /**
     * Tell the student what happened, unless they did it themselves.
     */
    private function notifyStudent(Appointment $appointment, AppointmentStatus $to, User $actor): void
    {
        $documentRequest = $appointment->documentRequest;
        $student = $documentRequest->student->user;

        if ($student->is($actor)) {
            return;
        }

        $type = match ($to) {
            AppointmentStatus::Confirmed => NotificationType::AppointmentConfirmed,
            AppointmentStatus::Cancelled => NotificationType::AppointmentCancelled,
            default => NotificationType::AppointmentBooked,
        };

        $slot = $appointment->timeSlot;

        $message = match ($to) {
            AppointmentStatus::Confirmed => __('Your appointment on :date at :time is confirmed.', [
                'date' => $slot->slot_date->format('F j, Y'),
                'time' => $slot->label,
            ]),
            AppointmentStatus::Cancelled => __('Your appointment on :date was cancelled. :reason', [
                'date' => $slot->slot_date->format('F j, Y'),
                'reason' => $appointment->cancellation_reason ?? '',
            ]),
            AppointmentStatus::Completed => __('Your :document has been claimed. Thank you.', [
                'document' => $documentRequest->display_name,
            ]),
            AppointmentStatus::NoShow => __('You missed your appointment on :date. Please book another slot.', [
                'date' => $slot->slot_date->format('F j, Y'),
            ]),
            default => __('Your appointment status changed to :status.', ['status' => $to->label()]),
        };

        ($this->sendNotification)($student, $type, trim($message), route('student.appointments.index'));
    }
}
