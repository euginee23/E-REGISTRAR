<?php

use App\Actions\Appointments\BookAppointment;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\Notification;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Book a named student onto a fresh future slot, through the real action so
 * the slot counters start out consistent.
 */
function appointmentFor(User $user, int $hour = 9): Appointment
{
    $slot = TimeSlot::factory()
        ->onDate(CarbonImmutable::today()->addWeekday())
        ->startingAt($hour)
        ->create(['capacity' => 5]);

    $documentRequest = DocumentRequest::factory()->for($user->student)->readyForRelease()->create();

    return app(BookAppointment::class)($documentRequest, $slot);
}

/**
 * Open another slot on the same day as an existing one.
 */
function alternativeSlot(TimeSlot $existing, int $hour = 14): TimeSlot
{
    return TimeSlot::factory()
        ->onDate($existing->slot_date)
        ->startingAt($hour)
        ->create(['capacity' => 5]);
}

test('a student can move their own appointment', function () {
    $user = student();
    $appointment = appointmentFor($user);
    $originalSlot = $appointment->timeSlot;
    $target = alternativeSlot($originalSlot);

    $this->actingAs($user);

    Livewire::test('pages::student.reschedule-appointment', ['appointment' => $appointment])
        ->set('date', $target->slot_date->toDateString())
        ->call('selectSlot', $target->id)
        ->call('reschedule')
        ->assertHasNoErrors()
        ->assertRedirect(route('student.appointments.index'));

    expect($appointment->refresh()->time_slot_id)->toBe($target->id)
        ->and($originalSlot->refresh()->booked_count)->toBe(0)
        ->and($target->refresh()->booked_count)->toBe(1);
});

test('rescheduling keeps the one appointment per request rule', function () {
    $user = student();
    $appointment = appointmentFor($user);
    $target = alternativeSlot($appointment->timeSlot);

    $this->actingAs($user);

    Livewire::test('pages::student.reschedule-appointment', ['appointment' => $appointment])
        ->set('date', $target->slot_date->toDateString())
        ->call('selectSlot', $target->id)
        ->call('reschedule');

    expect(Appointment::query()->where('document_request_id', $appointment->document_request_id)->count())->toBe(1);
});

test('a student cannot open the reschedule screen for someone else', function () {
    $appointment = appointmentFor(student());

    $this->actingAs(student());

    $this->get(route('student.appointments.reschedule', $appointment))->assertForbidden();
});

test('a finished appointment can no longer be moved', function (AppointmentStatus $status) {
    $user = student();
    $appointment = appointmentFor($user);
    $appointment->forceFill(['status' => $status])->save();

    $this->actingAs($user);

    $this->get(route('student.appointments.reschedule', $appointment))->assertForbidden();
})->with([
    AppointmentStatus::Completed,
    AppointmentStatus::Cancelled,
    AppointmentStatus::NoShow,
]);

test('the current slot is offered but cannot be picked', function () {
    $user = student();
    $appointment = appointmentFor($user);
    alternativeSlot($appointment->timeSlot);

    $this->actingAs($user);

    Livewire::test('pages::student.reschedule-appointment', ['appointment' => $appointment])
        ->set('date', $appointment->timeSlot->slot_date->toDateString())
        ->assertSee('Current time');
});

test('losing the last seat mid-submit shows a friendly error', function () {
    $user = student();
    $appointment = appointmentFor($user);
    $target = alternativeSlot($appointment->timeSlot);
    $target->forceFill(['capacity' => 1])->save();

    $this->actingAs($user);

    $component = Livewire::test('pages::student.reschedule-appointment', ['appointment' => $appointment])
        ->set('date', $target->slot_date->toDateString())
        ->call('selectSlot', $target->id);

    // Somebody else takes the last seat before the form is submitted.
    $target->forceFill(['booked_count' => 1])->save();

    $component->call('reschedule')->assertHasErrors('selectedSlotId');

    expect($appointment->refresh()->time_slot_id)->not->toBe($target->id);
});

test('the reschedule link is offered on the student appointments screen', function () {
    $user = student();
    appointmentFor($user);

    $this->actingAs($user);

    $this->get(route('student.appointments.index'))
        ->assertOk()
        ->assertSeeHtml('data-test="reschedule-appointment-link"');
});

test('staff can move an appointment from the calendar', function () {
    $appointment = appointmentFor(student());
    $originalSlot = $appointment->timeSlot;
    $target = alternativeSlot($originalSlot);

    $this->actingAs(registrarStaff());

    Livewire::test('registrar.reschedule-appointment', ['appointment' => $appointment])
        ->set('date', $target->slot_date->toDateString())
        ->call('selectSlot', $target->id)
        ->call('reschedule')
        ->assertHasNoErrors()
        ->assertDispatched('appointment-updated');

    expect($appointment->refresh()->time_slot_id)->toBe($target->id)
        ->and($originalSlot->refresh()->booked_count)->toBe(0);
});

test('a staff reschedule tells the student exactly once', function () {
    $appointment = appointmentFor(student());
    $target = alternativeSlot($appointment->timeSlot);
    $studentUser = $appointment->documentRequest->student->user;

    Notification::query()->where('user_id', $studentUser->id)->delete();

    $this->actingAs(registrarStaff());

    Livewire::test('registrar.reschedule-appointment', ['appointment' => $appointment])
        ->set('date', $target->slot_date->toDateString())
        ->call('selectSlot', $target->id)
        ->call('reschedule');

    expect(Notification::query()->where('user_id', $studentUser->id)->count())->toBe(1);
});

test('the reschedule trigger is on the registrar calendar', function () {
    $appointment = appointmentFor(student());

    $this->actingAs(registrarStaff());

    $this->get(route('registrar.appointments.index', [
        'view' => 'day',
        'date' => $appointment->timeSlot->slot_date->toDateString(),
    ]))
        ->assertOk()
        ->assertSeeHtml('data-test="reschedule-trigger"');
});

test('a student cannot drive the staff reschedule component', function () {
    $appointment = appointmentFor(student());
    $target = alternativeSlot($appointment->timeSlot);

    $this->actingAs(student());

    Livewire::test('registrar.reschedule-appointment', ['appointment' => $appointment])
        ->set('date', $target->slot_date->toDateString())
        ->call('selectSlot', $target->id)
        ->call('reschedule')
        ->assertForbidden();

    expect($appointment->refresh()->time_slot_id)->not->toBe($target->id);
});

test('choosing no slot is refused', function () {
    $user = student();
    $appointment = appointmentFor($user);

    $this->actingAs($user);

    Livewire::test('pages::student.reschedule-appointment', ['appointment' => $appointment])
        ->call('reschedule')
        ->assertHasErrors('selectedSlotId');
});
