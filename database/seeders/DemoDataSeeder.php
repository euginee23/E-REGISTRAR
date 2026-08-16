<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\RequestStatusHistory;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed a realistic spread of requests and appointments.
     *
     * Dashboards and reports are only meaningful with data behind them, so
     * this fills every status with a plausible volume for demonstration.
     */
    public function run(): void
    {
        $students = Student::query()->count() >= 8
            ? Student::query()->get()
            : User::factory()->count(8)->student()->create()->map(fn (User $user) => $user->student)->flatten();

        $documentTypes = DocumentType::query()->active()->get();
        $staff = User::query()->where('role', 'registrar_staff')->first()
            ?? User::factory()->registrarStaff()->create();

        $distribution = [
            RequestStatus::Pending->value => 10,
            RequestStatus::Processing->value => 8,
            RequestStatus::ReadyForRelease->value => 6,
            RequestStatus::Released->value => 12,
            RequestStatus::Rejected->value => 2,
            RequestStatus::Cancelled->value => 2,
        ];

        foreach ($distribution as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->createRequest(
                    RequestStatus::from($status),
                    $students->random(),
                    $documentTypes->random(),
                    $staff,
                );
            }
        }

        $this->bookAppointments();
    }

    /**
     * Create a single demo request with a matching status history.
     */
    private function createRequest(
        RequestStatus $status,
        Student $student,
        DocumentType $documentType,
        User $staff,
    ): void {
        $submittedAt = CarbonImmutable::now()->subDays(fake()->numberBetween(1, 45));

        $request = DocumentRequest::factory()
            ->for($student)
            ->for($documentType)
            ->create([
                'status' => $status,
                'other_document_name' => $documentType->requires_custom_name ? 'Certificate of Units Earned' : null,
                'processed_by_user_id' => $status === RequestStatus::Pending ? null : $staff->id,
                'ready_at' => in_array($status, [RequestStatus::ReadyForRelease, RequestStatus::Released], true)
                    ? $submittedAt->addWeekdays($documentType->processing_days)
                    : null,
                'released_at' => $status === RequestStatus::Released
                    ? $submittedAt->addWeekdays($documentType->processing_days + 1)
                    : null,
                'remarks' => $status === RequestStatus::Rejected ? 'Incomplete supporting requirements.' : null,
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ]);

        $this->recordHistory($request, $status, $staff, $submittedAt);
    }

    /**
     * Write the status trail that led the request to its current status.
     */
    private function recordHistory(
        DocumentRequest $request,
        RequestStatus $status,
        User $staff,
        CarbonImmutable $submittedAt,
    ): void {
        $trail = match ($status) {
            RequestStatus::Pending => [RequestStatus::Pending],
            RequestStatus::Processing => [RequestStatus::Pending, RequestStatus::Processing],
            RequestStatus::ReadyForRelease => [RequestStatus::Pending, RequestStatus::Processing, RequestStatus::ReadyForRelease],
            RequestStatus::Released => [RequestStatus::Pending, RequestStatus::Processing, RequestStatus::ReadyForRelease, RequestStatus::Released],
            RequestStatus::Rejected => [RequestStatus::Pending, RequestStatus::Rejected],
            RequestStatus::Cancelled => [RequestStatus::Pending, RequestStatus::Cancelled],
        };

        $previous = null;

        foreach ($trail as $index => $step) {
            RequestStatusHistory::factory()->create([
                'document_request_id' => $request->id,
                'from_status' => $previous,
                'to_status' => $step,
                'changed_by_user_id' => $previous === null ? $request->student->user_id : $staff->id,
                'created_at' => $submittedAt->addDays($index),
                'updated_at' => $submittedAt->addDays($index),
            ]);

            $previous = $step;
        }
    }

    /**
     * Book claiming appointments for a portion of the claimable requests.
     */
    private function bookAppointments(): void
    {
        $slots = TimeSlot::query()->available()->orderBy('slot_date')->orderBy('start_time')->take(20)->get();

        if ($slots->isEmpty()) {
            return;
        }

        $claimable = DocumentRequest::query()
            ->whereIn('status', [RequestStatus::Processing, RequestStatus::ReadyForRelease])
            ->doesntHave('appointment')
            ->take(10)
            ->get();

        foreach ($claimable as $index => $request) {
            $slot = $slots[$index % $slots->count()];

            if ($slot->isFull()) {
                continue;
            }

            Appointment::factory()->create([
                'document_request_id' => $request->id,
                'time_slot_id' => $slot->id,
                'status' => AppointmentStatus::Scheduled,
            ]);

            $slot->increment('booked_count');
        }
    }
}
