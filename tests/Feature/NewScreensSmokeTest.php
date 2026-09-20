<?php

use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;

test('every screen added in this batch renders for the right role', function () {
    $documentRequest = DocumentRequest::factory()->unpaid()->readyForRelease()->create();
    $slot = TimeSlot::factory()->onDate(CarbonImmutable::today()->addWeekday())->startingAt(9)->create();
    $appointment = Appointment::factory()->create([
        'document_request_id' => $documentRequest->id,
        'time_slot_id' => $slot->id,
    ]);

    $studentUser = $documentRequest->student->user;

    // Student-facing.
    $this->actingAs($studentUser);
    $this->get(route('requests.slip', $documentRequest))->assertOk();
    $this->get(route('student.appointments.reschedule', $appointment))->assertOk();
    $this->get(route('student.requests.show', $documentRequest))->assertOk();
    $this->get(route('student.requests.index'))->assertOk();

    // Staff-facing.
    $this->actingAs(registrarStaff());
    $this->get(route('registrar.requests.index'))->assertOk();
    $this->get(route('registrar.requests.show', $documentRequest))->assertOk();
    $this->get(route('registrar.appointments.index'))->assertOk();
    $this->get(route('registrar.appointments.index', ['view' => 'day']))->assertOk();
    $this->get(route('registrar.pending-accounts.index'))->assertOk();
    $this->get(route('requests.slip', $documentRequest))->assertOk();

    // Administrator-only.
    $this->actingAs(administrator());
    $this->get(route('admin.student-registry.index'))->assertOk();
    $this->get(route('admin.users.index'))->assertOk();
    $this->get(route('admin.document-types.index'))->assertOk();
    $this->get(route('dashboard'))->assertOk();
});
