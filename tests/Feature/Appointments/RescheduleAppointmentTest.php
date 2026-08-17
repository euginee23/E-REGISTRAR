<?php

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\RescheduleAppointment;
use App\Actions\Appointments\UpdateAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotFullyBookedException;
use App\Exceptions\SlotNotBookableException;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;

/**
 * Create a slot that is genuinely open for booking.
 */
function slotAt(int $hour, int $capacity = 5): TimeSlot
{
    return TimeSlot::factory()
        ->onDate(CarbonImmutable::today()->addWeekday())
        ->startingAt($hour)
        ->create(['capacity' => $capacity]);
}

test('rescheduling moves the seat between slots', function () {
    $from = slotAt(9);
    $to = slotAt(11);

    $appointment = app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $from);

    app(RescheduleAppointment::class)($appointment, $to, registrarStaff());

    expect($from->fresh()->booked_count)->toBe(0)
        ->and($to->fresh()->booked_count)->toBe(1)
        ->and($appointment->fresh()->time_slot_id)->toBe($to->id);
});

test('rescheduling updates the appointment in place rather than duplicating it', function () {
    $appointment = app(BookAppointment::class)(
        DocumentRequest::factory()->processing()->create(),
        slotAt(9),
    );

    app(RescheduleAppointment::class)($appointment, slotAt(11), registrarStaff());

    expect(Appointment::query()->count())->toBe(1);
});

test('rescheduling resets confirmation and the reminder stamp', function () {
    $appointment = app(BookAppointment::class)(
        DocumentRequest::factory()->processing()->create(),
        slotAt(9),
    );

    $appointment->forceFill([
        'status' => AppointmentStatus::Confirmed,
        'confirmed_at' => now(),
        'reminder_sent_at' => now(),
    ])->save();

    app(RescheduleAppointment::class)($appointment, slotAt(11), registrarStaff());

    $appointment->refresh();

    expect($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->confirmed_at)->toBeNull()
        ->and($appointment->reminder_sent_at)->toBeNull();
});

test('rescheduling into a full slot is refused and changes nothing', function () {
    $from = slotAt(9);
    $full = slotAt(11, capacity: 1);

    app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $full);
    $appointment = app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $from);

    expect(fn () => app(RescheduleAppointment::class)($appointment, $full->fresh(), registrarStaff()))
        ->toThrow(SlotFullyBookedException::class);

    expect($appointment->fresh()->time_slot_id)->toBe($from->id)
        ->and($from->fresh()->booked_count)->toBe(1)
        ->and($full->fresh()->booked_count)->toBe(1);
});

test('rescheduling into a past slot is refused', function () {
    $appointment = app(BookAppointment::class)(
        DocumentRequest::factory()->processing()->create(),
        slotAt(9),
    );

    expect(fn () => app(RescheduleAppointment::class)(
        $appointment,
        TimeSlot::factory()->past()->create(),
        registrarStaff(),
    ))->toThrow(SlotNotBookableException::class);
});

test('rescheduling into a closed slot is refused', function () {
    $appointment = app(BookAppointment::class)(
        DocumentRequest::factory()->processing()->create(),
        slotAt(9),
    );

    $closed = TimeSlot::factory()
        ->onDate(CarbonImmutable::today()->addWeekday())
        ->startingAt(14)
        ->inactive()
        ->create();

    expect(fn () => app(RescheduleAppointment::class)($appointment, $closed, registrarStaff()))
        ->toThrow(SlotNotBookableException::class);
});

test('rescheduling to the same slot is a no-op', function () {
    $slot = slotAt(9);
    $appointment = app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);

    app(RescheduleAppointment::class)($appointment, $slot->fresh(), registrarStaff());

    expect($slot->fresh()->booked_count)->toBe(1);
});

test('rescheduling a cancelled appointment does not double count the seat', function () {
    $from = slotAt(9);
    $to = slotAt(11);

    $appointment = app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $from);

    app(UpdateAppointmentStatus::class)(
        $appointment,
        AppointmentStatus::Cancelled,
        registrarStaff(),
    );

    expect($from->fresh()->booked_count)->toBe(0);

    app(RescheduleAppointment::class)($appointment, $to, registrarStaff());

    // The appointment becomes live again, so the new slot must count it -
    // otherwise the seat is occupied but invisible to the capacity check.
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and($from->fresh()->booked_count)->toBe(0)
        ->and($to->fresh()->booked_count)->toBe(1);
});

test('rescheduling notifies the student when staff move them', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->processing()->create();
    $appointment = app(BookAppointment::class)($request, slotAt(9));

    $user->markAllNotificationsRead();

    app(RescheduleAppointment::class)($appointment, slotAt(11), registrarStaff());

    expect($user->unreadNotificationsCount())->toBe(1);
});
