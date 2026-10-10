<?php

namespace Database\Seeders;

use App\Actions\Slots\GenerateTimeSlots;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class DemoAppointmentSeeder extends Seeder
{
    /**
     * Fill the current month's calendar with sample claiming appointments.
     *
     * Bookings are anchored to today rather than to the earliest open slot,
     * so the calendar always has something to show whenever the demo is
     * seeded: finished outcomes on the days already gone, upcoming ones on
     * today and the days ahead. Re-running is safe; a month that already has
     * bookings is left alone.
     */
    public function run(): void
    {
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth()->startOfDay();

        $alreadyBooked = Appointment::query()
            ->whereHas('timeSlot', fn ($query) => $query->whereBetween('slot_date', [$monthStart->toDateString(), $monthEnd->toDateString()]))
            ->exists();

        if ($alreadyBooked) {
            return;
        }

        app(GenerateTimeSlots::class)($monthStart, $monthEnd);

        $students = Student::query()->get();

        if ($students->isEmpty()) {
            $students = User::factory()->count(8)->student()->create()->map(fn (User $user) => $user->student);
        }

        $documentTypes = DocumentType::query()->active()->get();

        if ($documentTypes->isEmpty()) {
            $documentTypes = DocumentType::factory()->count(3)->create();
        }

        $staff = User::query()->where('role', 'registrar_staff')->first()
            ?? User::factory()->registrarStaff()->create();

        $slotsByDay = TimeSlot::query()
            ->whereBetween('slot_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->available()
            ->get()
            ->groupBy(fn (TimeSlot $slot): string => $slot->slot_date->toDateString());

        foreach ($slotsByDay as $date => $slots) {
            $isPast = CarbonImmutable::parse($date)->lessThan($today);

            foreach ($slots->shuffle()->take(fake()->numberBetween(1, 3)) as $slot) {
                $this->book($slot, $this->pickStatus($isPast), $students, $documentTypes, $staff);
            }
        }
    }

    /**
     * Pick a plausible status for a booking on a past or upcoming day.
     */
    private function pickStatus(bool $isPast): AppointmentStatus
    {
        return $isPast
            ? fake()->randomElement([
                AppointmentStatus::Completed,
                AppointmentStatus::Completed,
                AppointmentStatus::Completed,
                AppointmentStatus::NoShow,
                AppointmentStatus::Cancelled,
            ])
            : fake()->randomElement([
                AppointmentStatus::Scheduled,
                AppointmentStatus::Scheduled,
                AppointmentStatus::Confirmed,
            ]);
    }

    /**
     * Book one slot for a fresh claimable request.
     *
     * A request carries at most one appointment, so every booking gets its
     * own request rather than borrowing one the main demo seeder created.
     *
     * @param  Collection<int, Student>|\Illuminate\Support\Collection<int, Student>  $students
     * @param  Collection<int, DocumentType>  $documentTypes
     */
    private function book(
        TimeSlot $slot,
        AppointmentStatus $status,
        Collection|\Illuminate\Support\Collection $students,
        Collection $documentTypes,
        User $staff,
    ): void {
        $startsAt = $slot->slot_date->setTimeFromTimeString($slot->start_time);
        $documentType = $documentTypes->random();
        $isClaimed = $status === AppointmentStatus::Completed;

        $request = DocumentRequest::factory()
            ->for($students->random())
            ->for($documentType)
            ->{$isClaimed ? 'released' : 'readyForRelease'}()
            ->create([
                'other_document_name' => $documentType->requires_custom_name ? 'Certificate of Units Earned' : null,
                'processed_by_user_id' => $staff->id,
                'ready_at' => $startsAt->subWeekdays(2),
                'released_at' => $isClaimed ? $startsAt : null,
                'created_at' => $startsAt->subWeekdays(5),
                'updated_at' => $startsAt->subWeekdays(2),
            ]);

        Appointment::factory()->create([
            'document_request_id' => $request->id,
            'time_slot_id' => $slot->id,
            'status' => $status,
            'confirmed_at' => $status === AppointmentStatus::Confirmed ? $startsAt->subDay() : null,
            'confirmed_by_user_id' => $status === AppointmentStatus::Confirmed ? $staff->id : null,
            'completed_at' => $isClaimed ? $startsAt->addMinutes(15) : null,
            'cancelled_at' => $status === AppointmentStatus::Cancelled ? $startsAt->subDay() : null,
            'cancellation_reason' => $status === AppointmentStatus::Cancelled ? 'Student asked to move the claim.' : null,
        ]);

        if ($status->occupiesCapacity()) {
            $slot->increment('booked_count');
        }
    }
}
