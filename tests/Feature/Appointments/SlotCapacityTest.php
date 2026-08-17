<?php

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\UpdateAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotFullyBookedException;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;

/**
 * Create a slot that is genuinely open for booking.
 */
function openSlot(int $capacity = 1, int $hour = 9): TimeSlot
{
    return TimeSlot::factory()
        ->onDate(CarbonImmutable::today()->addWeekday())
        ->startingAt($hour)
        ->create(['capacity' => $capacity]);
}

test('the last seat can only be taken once', function () {
    $slot = openSlot(capacity: 1);

    app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);

    expect(fn () => app(BookAppointment::class)(
        DocumentRequest::factory()->processing()->create(),
        $slot->fresh(),
    ))->toThrow(SlotFullyBookedException::class);

    expect($slot->fresh()->booked_count)->toBe(1)
        ->and($slot->fresh()->isFull())->toBeTrue();
});

test('cancelling an appointment frees the seat again', function () {
    $slot = openSlot(capacity: 1);
    $appointment = app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);

    expect($slot->fresh()->booked_count)->toBe(1);

    app(UpdateAppointmentStatus::class)($appointment, AppointmentStatus::Cancelled, registrarStaff());

    expect($slot->fresh()->booked_count)->toBe(0)
        ->and($slot->fresh()->isFull())->toBeFalse();

    // The freed seat is genuinely bookable by someone else.
    app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot->fresh());

    expect($slot->fresh()->booked_count)->toBe(1);
});

test('a no-show frees the seat', function () {
    $slot = openSlot(capacity: 2);
    $appointment = app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);

    app(UpdateAppointmentStatus::class)($appointment, AppointmentStatus::NoShow, registrarStaff());

    expect($slot->fresh()->booked_count)->toBe(0);
});

test('confirming and completing keep the seat taken', function (AppointmentStatus $status) {
    $slot = openSlot(capacity: 2);
    $appointment = app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);

    app(UpdateAppointmentStatus::class)($appointment, $status, registrarStaff());

    expect($slot->fresh()->booked_count)->toBe(1);
})->with([AppointmentStatus::Confirmed, AppointmentStatus::Completed]);

test('reinstating a cancelled appointment retakes a seat', function () {
    $slot = openSlot(capacity: 2);
    $appointment = app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);

    $update = app(UpdateAppointmentStatus::class);

    $update($appointment, AppointmentStatus::Cancelled, registrarStaff());
    expect($slot->fresh()->booked_count)->toBe(0);

    $update($appointment, AppointmentStatus::Scheduled, registrarStaff());
    expect($slot->fresh()->booked_count)->toBe(1);
});

test('setting the same status twice does not double count', function () {
    $slot = openSlot(capacity: 3);
    $appointment = app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);

    $update = app(UpdateAppointmentStatus::class);

    $update($appointment, AppointmentStatus::Confirmed, registrarStaff());
    $update($appointment, AppointmentStatus::Confirmed, registrarStaff());

    expect($slot->fresh()->booked_count)->toBe(1);
});

test('the counter never goes negative', function () {
    $slot = openSlot(capacity: 2);
    $appointment = app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);

    // Force the counter out of step, as a bad manual edit might.
    $slot->forceFill(['booked_count' => 0])->save();

    app(UpdateAppointmentStatus::class)($appointment, AppointmentStatus::Cancelled, registrarStaff());

    expect($slot->fresh()->booked_count)->toBe(0);
});

test('the recount command reports and repairs drift', function () {
    $slot = openSlot(capacity: 5);
    app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);
    app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot->fresh());

    $slot->forceFill(['booked_count' => 99])->save();

    $this->artisan('slots:recount')
        ->expectsOutputToContain('stored 99, actual 2')
        ->assertSuccessful();

    expect($slot->fresh()->booked_count)->toBe(2);
});

test('the recount command leaves accurate counters alone', function () {
    $slot = openSlot(capacity: 5);
    app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);

    $this->artisan('slots:recount')
        ->expectsOutputToContain('All slot booking counters are accurate.')
        ->assertSuccessful();

    expect($slot->fresh()->booked_count)->toBe(1);
});

test('a dry run reports drift without changing anything', function () {
    $slot = openSlot(capacity: 5);
    $slot->forceFill(['booked_count' => 4])->save();

    $this->artisan('slots:recount', ['--dry-run' => true])->assertSuccessful();

    expect($slot->fresh()->booked_count)->toBe(4);
});

test('the recount ignores cancelled and missed appointments', function () {
    $slot = openSlot(capacity: 10);

    Appointment::factory()->count(2)->for($slot)->create(['status' => AppointmentStatus::Scheduled]);
    Appointment::factory()->for($slot)->create(['status' => AppointmentStatus::Completed]);
    Appointment::factory()->for($slot)->create(['status' => AppointmentStatus::Cancelled]);
    Appointment::factory()->for($slot)->create(['status' => AppointmentStatus::NoShow]);

    $this->artisan('slots:recount')->assertSuccessful();

    expect($slot->fresh()->booked_count)->toBe(3);
});
