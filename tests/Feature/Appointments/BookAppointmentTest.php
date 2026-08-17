<?php

use App\Actions\Appointments\BookAppointment;
use App\Enums\AppointmentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\SlotFullyBookedException;
use App\Exceptions\SlotNotBookableException;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

/**
 * Create a slot that is genuinely open for booking.
 */
function bookableSlot(int $capacity = 5, int $hour = 9): TimeSlot
{
    return TimeSlot::factory()
        ->onDate(CarbonImmutable::today()->addWeekday())
        ->startingAt($hour)
        ->create(['capacity' => $capacity]);
}

test('a student can book an open slot', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->processing()->create();
    $slot = bookableSlot();

    app(BookAppointment::class)($request, $slot);

    expect(Appointment::query()->count())->toBe(1)
        ->and($request->fresh()->appointment->time_slot_id)->toBe($slot->id)
        ->and($request->fresh()->appointment->status)->toBe(AppointmentStatus::Scheduled);
});

test('booking takes a seat in the slot', function () {
    $request = DocumentRequest::factory()->processing()->create();
    $slot = bookableSlot(capacity: 5);

    app(BookAppointment::class)($request, $slot);

    expect($slot->fresh()->booked_count)->toBe(1)
        ->and($slot->fresh()->remaining_capacity)->toBe(4);
});

test('a fully booked slot refuses another booking', function () {
    $slot = bookableSlot(capacity: 1);

    app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);

    expect(fn () => app(BookAppointment::class)(
        DocumentRequest::factory()->processing()->create(),
        $slot->fresh(),
    ))->toThrow(SlotFullyBookedException::class);

    expect($slot->fresh()->booked_count)->toBe(1)
        ->and(Appointment::query()->count())->toBe(1);
});

test('a slot in the past cannot be booked', function () {
    $slot = TimeSlot::factory()->past()->create();

    expect(fn () => app(BookAppointment::class)(
        DocumentRequest::factory()->processing()->create(),
        $slot,
    ))->toThrow(SlotNotBookableException::class);
});

test('a closed slot cannot be booked', function () {
    $slot = TimeSlot::factory()
        ->onDate(CarbonImmutable::today()->addWeekday())
        ->inactive()
        ->create();

    expect(fn () => app(BookAppointment::class)(
        DocumentRequest::factory()->processing()->create(),
        $slot,
    ))->toThrow(SlotNotBookableException::class);
});

test('one request can never hold two appointments', function () {
    $request = DocumentRequest::factory()->processing()->create();

    app(BookAppointment::class)($request, bookableSlot(hour: 9));

    expect(fn () => app(BookAppointment::class)($request, bookableSlot(hour: 10)))
        ->toThrow(QueryException::class);

    expect(Appointment::query()->count())->toBe(1);
});

test('booking notifies the student and the registrar', function () {
    $user = student();
    $staff = registrarStaff();
    $request = DocumentRequest::factory()->for($user->student)->processing()->create();

    app(BookAppointment::class)($request, bookableSlot());

    expect($user->unreadNotificationsCount())->toBe(1)
        ->and($staff->unreadNotificationsCount())->toBe(1);
});

test('the booking screen only opens for a bookable request', function (RequestStatus $status, int $expected) {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->create(['status' => $status]);

    $this->actingAs($user);

    $this->get(route('student.appointments.book', $request))->assertStatus($expected);
})->with([
    [RequestStatus::Pending, 403],
    [RequestStatus::Processing, 200],
    [RequestStatus::ReadyForRelease, 200],
    [RequestStatus::Released, 403],
    [RequestStatus::Cancelled, 403],
]);

test('a student cannot book against someone else\'s request', function () {
    $request = DocumentRequest::factory()->for(student()->student)->processing()->create();

    $this->actingAs(student());

    $this->get(route('student.appointments.book', $request))->assertForbidden();
});

test('a request that already has an appointment cannot be booked again', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->processing()->create();
    Appointment::factory()->for($request)->create();

    $this->actingAs($user);

    $this->get(route('student.appointments.book', $request))->assertForbidden();
});

test('a student can book through the booking screen', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->processing()->create();
    $slot = bookableSlot();

    $this->actingAs($user);

    Livewire::test('pages::student.book-appointment', ['documentRequest' => $request])
        ->set('date', $slot->slot_date->toDateString())
        ->call('selectSlot', $slot->id)
        ->call('book')
        ->assertHasNoErrors();

    expect($slot->fresh()->booked_count)->toBe(1);
});

test('a slot must be chosen before booking', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->processing()->create();

    $this->actingAs($user);

    Livewire::test('pages::student.book-appointment', ['documentRequest' => $request])
        ->call('book')
        ->assertHasErrors('selectedSlotId');
});

test('losing the last seat mid-booking shows a friendly message', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->processing()->create();
    $slot = bookableSlot(capacity: 1);

    $this->actingAs($user);

    $component = Livewire::test('pages::student.book-appointment', ['documentRequest' => $request])
        ->set('date', $slot->slot_date->toDateString())
        ->call('selectSlot', $slot->id);

    // Another student takes the final seat before this one submits.
    app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot->fresh());

    $component->call('book')->assertHasErrors('selectedSlotId');

    expect($slot->fresh()->booked_count)->toBe(1);
});

test('a weekend date offers no slots', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->processing()->create();

    $saturday = CarbonImmutable::today()->next('Saturday');

    $this->actingAs($user);

    Livewire::test('pages::student.book-appointment', ['documentRequest' => $request])
        ->set('date', $saturday->toDateString())
        ->assertSee('open Monday to Friday');
});

test('the booking screen refuses a slot beyond the booking horizon', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->processing()->create();

    $tooFar = TimeSlot::factory()
        ->onDate(CarbonImmutable::today()->addDays((int) config('registrar.booking.max_days_ahead') + 10))
        ->create();

    $this->actingAs($user);

    Livewire::test('pages::student.book-appointment', ['documentRequest' => $request])
        ->call('selectSlot', $tooFar->id)
        ->call('book')
        ->assertHasErrors('selectedSlotId');
});
