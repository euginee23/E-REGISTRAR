<?php

namespace App\Actions\Appointments;

use App\Actions\Notifications\SendNotification;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Exceptions\SlotFullyBookedException;
use App\Exceptions\SlotNotBookableException;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BookAppointment
{
    public function __construct(private SendNotification $sendNotification) {}

    /**
     * Reserve a claiming slot for a document request.
     *
     * The row lock is what actually prevents overbooking: SELECT ... FOR
     * UPDATE serialises every concurrent booker on the slot row, so the
     * read-check-write below is atomic. The unique index on
     * appointments.document_request_id independently guarantees one
     * appointment per request.
     *
     * @throws SlotFullyBookedException
     * @throws SlotNotBookableException
     */
    public function __invoke(DocumentRequest $documentRequest, TimeSlot $timeSlot): Appointment
    {
        $appointment = DB::transaction(function () use ($documentRequest, $timeSlot): Appointment {
            $slot = TimeSlot::query()->whereKey($timeSlot->getKey())->lockForUpdate()->firstOrFail();

            throw_unless($slot->is_active && $slot->startsAt()->isFuture(), SlotNotBookableException::make());
            throw_if($slot->booked_count >= $slot->capacity, SlotFullyBookedException::make());

            $appointment = Appointment::create([
                'document_request_id' => $documentRequest->id,
                'time_slot_id' => $slot->id,
                'status' => AppointmentStatus::Scheduled,
            ]);

            $slot->increment('booked_count');

            return $appointment;
        }, attempts: 3);

        $this->notify($documentRequest, $appointment->load('timeSlot'));

        return $appointment;
    }

    /**
     * Confirm the booking to the student and alert the registrar's office.
     */
    private function notify(DocumentRequest $documentRequest, Appointment $appointment): void
    {
        $slot = $appointment->timeSlot;

        ($this->sendNotification)(
            $documentRequest->student->user,
            NotificationType::AppointmentBooked,
            __('Your appointment to claim :document is set for :date, :time.', [
                'document' => $documentRequest->display_name,
                'date' => $slot->slot_date->format('F j, Y'),
                'time' => $slot->label,
            ]),
            route('student.appointments.index'),
        );

        ($this->sendNotification)(
            $this->registrarPersonnel(),
            NotificationType::AppointmentBooked,
            __(':student booked :date, :time to claim :reference.', [
                'student' => $documentRequest->student->user->name,
                'date' => $slot->slot_date->format('M j'),
                'time' => $slot->label,
                'reference' => $documentRequest->reference_no,
            ]),
            route('registrar.appointments.index'),
        );
    }

    /**
     * Get the accounts that staff the registrar's office.
     *
     * @return Collection<int, User>
     */
    private function registrarPersonnel(): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::Administrator, UserRole::RegistrarStaff])
            ->get();
    }
}
