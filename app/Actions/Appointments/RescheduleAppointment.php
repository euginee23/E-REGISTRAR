<?php

namespace App\Actions\Appointments;

use App\Actions\Notifications\SendNotification;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationType;
use App\Exceptions\SlotFullyBookedException;
use App\Exceptions\SlotNotBookableException;
use App\Models\Appointment;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RescheduleAppointment
{
    public function __construct(private SendNotification $sendNotification) {}

    /**
     * Move an existing appointment to a different time slot.
     *
     * Both slots are locked in a stable order before either counter moves, so
     * two students swapping slots at the same moment cannot deadlock. The
     * appointment row is updated in place rather than replaced, which keeps
     * the one-appointment-per-request guarantee intact.
     *
     * @throws SlotFullyBookedException
     * @throws SlotNotBookableException
     */
    public function __invoke(Appointment $appointment, TimeSlot $newSlot, User $actor): Appointment
    {
        if ($appointment->time_slot_id === $newSlot->getKey()) {
            return $appointment;
        }

        DB::transaction(function () use ($appointment, $newSlot): void {
            $ids = [$appointment->time_slot_id, $newSlot->getKey()];
            sort($ids);

            /** @var Collection<int, TimeSlot> $locked */
            $locked = TimeSlot::query()->whereKey($ids)->lockForUpdate()->get()->keyBy('id');

            $target = $locked[$newSlot->getKey()];
            $current = $locked[$appointment->time_slot_id];

            throw_unless($target->is_active && $target->startsAt()->isFuture(), SlotNotBookableException::make());
            throw_if($target->booked_count >= $target->capacity, SlotFullyBookedException::make());

            // The appointment always lands back in Scheduled, so the target
            // slot always gains a seat. The source only gives one up if it
            // was actually holding one - a cancelled appointment is not.
            if ($appointment->status->occupiesCapacity()) {
                $current->decrement('booked_count', $current->booked_count > 0 ? 1 : 0);
            }

            $target->increment('booked_count');

            $appointment->forceFill([
                'time_slot_id' => $target->id,
                'status' => AppointmentStatus::Scheduled,
                'confirmed_at' => null,
                'confirmed_by_user_id' => null,
                'reminder_sent_at' => null,
            ])->save();
        }, attempts: 3);

        $appointment->load('timeSlot');

        $this->notify($appointment, $actor);

        return $appointment;
    }

    /**
     * Tell the student where their appointment moved to.
     */
    private function notify(Appointment $appointment, User $actor): void
    {
        $student = $appointment->documentRequest->student->user;

        if ($student->is($actor)) {
            return;
        }

        ($this->sendNotification)(
            $student,
            NotificationType::AppointmentBooked,
            __('Your appointment was moved to :date, :time.', [
                'date' => $appointment->timeSlot->slot_date->format('F j, Y'),
                'time' => $appointment->timeSlot->label,
            ]),
            route('student.appointments.index'),
        );
    }
}
