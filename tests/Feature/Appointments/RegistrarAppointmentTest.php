<?php

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\UpdateAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Models\DocumentRequest;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Book an appointment into a fresh slot on the given day.
 */
function bookedAppointment(?CarbonImmutable $date = null, int $hour = 9)
{
    $slot = TimeSlot::factory()
        ->onDate($date ?? CarbonImmutable::today()->addWeekday())
        ->startingAt($hour)
        ->create(['capacity' => 5]);

    return app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);
}

test('staff can open the appointment calendar', function (string $helper) {
    $this->actingAs($helper());

    $this->get(route('registrar.appointments.index'))->assertOk();
})->with(['registrarStaff', 'administrator']);

test('students cannot open the registrar calendar', function () {
    $this->actingAs(student());

    $this->get(route('registrar.appointments.index'))->assertForbidden();
});

test('the calendar shows the chosen day\'s bookings', function () {
    $date = CarbonImmutable::today()->addWeekday();
    $appointment = bookedAppointment($date);

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.appointments')
        ->set('date', $date->toDateString())
        ->assertSee($appointment->documentRequest->reference_no);
});

test('the calendar hides bookings from other days', function () {
    $date = CarbonImmutable::today()->addWeekday();
    $appointment = bookedAppointment($date);

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.appointments')
        ->set('date', $date->addWeek()->toDateString())
        ->assertDontSee($appointment->documentRequest->reference_no);
});

test('staff can confirm an appointment', function () {
    $staff = registrarStaff();
    $appointment = bookedAppointment();

    $this->actingAs($staff);

    Livewire::test('pages::registrar.appointments')
        ->set('date', $appointment->timeSlot->slot_date->toDateString())
        ->call('setStatus', $appointment->id, AppointmentStatus::Confirmed->value)
        ->assertHasNoErrors();

    $appointment->refresh();

    expect($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->confirmed_by_user_id)->toBe($staff->id)
        ->and($appointment->confirmed_at)->not->toBeNull();
});

test('staff can mark a document claimed', function () {
    $appointment = bookedAppointment();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.appointments')
        ->set('date', $appointment->timeSlot->slot_date->toDateString())
        ->call('setStatus', $appointment->id, AppointmentStatus::Completed->value);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed)
        ->and($appointment->fresh()->completed_at)->not->toBeNull();
});

test('staff can record a no-show', function () {
    $appointment = bookedAppointment();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.appointments')
        ->set('date', $appointment->timeSlot->slot_date->toDateString())
        ->call('setStatus', $appointment->id, AppointmentStatus::NoShow->value);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::NoShow)
        ->and($appointment->timeSlot->fresh()->booked_count)->toBe(0);
});

test('every staff action notifies the student', function (AppointmentStatus $status) {
    $user = student();
    $slot = TimeSlot::factory()->onDate(CarbonImmutable::today()->addWeekday())->create();
    $appointment = app(BookAppointment::class)(
        DocumentRequest::factory()->for($user->student)->processing()->create(),
        $slot,
    );

    $user->markAllNotificationsRead();

    app(UpdateAppointmentStatus::class)($appointment, $status, registrarStaff());

    expect($user->unreadNotificationsCount())->toBe(1);
})->with([
    AppointmentStatus::Confirmed,
    AppointmentStatus::Completed,
    AppointmentStatus::Cancelled,
    AppointmentStatus::NoShow,
]);

test('a finished appointment cannot be confirmed again', function () {
    $appointment = bookedAppointment();

    app(UpdateAppointmentStatus::class)(
        $appointment,
        AppointmentStatus::Completed,
        registrarStaff(),
    );

    expect(registrarStaff()->can('confirm', $appointment->fresh()))->toBeFalse()
        ->and(registrarStaff()->can('cancel', $appointment->fresh()))->toBeFalse();
});

test('the day summary counts capacity and attendance', function () {
    $date = CarbonImmutable::today()->addWeekday();
    $appointment = bookedAppointment($date, hour: 9);

    app(UpdateAppointmentStatus::class)(
        $appointment,
        AppointmentStatus::Completed,
        registrarStaff(),
    );

    $this->actingAs(registrarStaff());

    $summary = Livewire::test('pages::registrar.appointments')
        ->set('date', $date->toDateString())
        ->instance()
        ->summary;

    expect($summary['slots'])->toBe(1)
        ->and($summary['capacity'])->toBe(5)
        ->and($summary['booked'])->toBe(1)
        ->and($summary['completed'])->toBe(1)
        ->and($summary['noShows'])->toBe(0);
});

test('the calendar can be stepped between days', function () {
    $this->actingAs(registrarStaff());

    $today = CarbonImmutable::today();

    Livewire::test('pages::registrar.appointments')
        ->call('shiftDay', 1)
        ->assertSet('date', $today->addDay()->toDateString())
        ->call('shiftDay', -2)
        ->assertSet('date', $today->subDay()->toDateString())
        ->call('today')
        ->assertSet('date', $today->toDateString());
});

test('a student can cancel their own appointment from their list', function () {
    $user = student();
    $slot = TimeSlot::factory()->onDate(CarbonImmutable::today()->addWeekday())->create();
    $appointment = app(BookAppointment::class)(
        DocumentRequest::factory()->for($user->student)->processing()->create(),
        $slot,
    );

    $this->actingAs($user);

    Livewire::test('pages::student.appointments')
        ->call('cancelAppointment', $appointment->id)
        ->assertHasNoErrors();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and($slot->fresh()->booked_count)->toBe(0);
});

test('a student cannot cancel someone else\'s appointment', function () {
    $appointment = bookedAppointment();

    $this->actingAs(student());

    Livewire::test('pages::student.appointments')
        ->call('cancelAppointment', $appointment->id)
        ->assertForbidden();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

test('the student appointment list shows requests ready to schedule', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->processing()->create();

    $this->actingAs($user);

    $this->get(route('student.appointments.index'))
        ->assertOk()
        ->assertSee('Ready to schedule');
});
