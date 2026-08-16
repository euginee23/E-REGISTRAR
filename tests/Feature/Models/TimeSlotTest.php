<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;

test('a slot composes its start and end moments from its date and times', function () {
    $slot = TimeSlot::factory()->create([
        'slot_date' => '2026-09-01',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);

    expect($slot->startsAt()->toDateTimeString())->toBe('2026-09-01 08:00:00')
        ->and($slot->endsAt()->toDateTimeString())->toBe('2026-09-01 09:00:00')
        ->and($slot->startsAt()->getTimezone()->getName())->toBe('Asia/Manila');
});

test('a slot renders a readable label', function () {
    $slot = TimeSlot::factory()->create([
        'start_time' => '13:00:00',
        'end_time' => '14:00:00',
    ]);

    expect($slot->label)->toBe('1:00 PM - 2:00 PM');
});

test('remaining capacity reflects the bookings taken', function () {
    $slot = TimeSlot::factory()->create(['capacity' => 5, 'booked_count' => 2]);

    expect($slot->remaining_capacity)->toBe(3)
        ->and($slot->isFull())->toBeFalse();
});

test('remaining capacity never falls below zero', function () {
    $slot = TimeSlot::factory()->create(['capacity' => 2, 'booked_count' => 5]);

    expect($slot->remaining_capacity)->toBe(0)
        ->and($slot->isFull())->toBeTrue();
});

test('a full slot is not bookable', function () {
    $slot = TimeSlot::factory()->full()->create();

    expect($slot->isBookable())->toBeFalse();
});

test('a past slot is not bookable', function () {
    $slot = TimeSlot::factory()->past()->create();

    expect($slot->isBookable())->toBeFalse();
});

test('a closed slot is not bookable', function () {
    $slot = TimeSlot::factory()->inactive()->create();

    expect($slot->isBookable())->toBeFalse();
});

test('an open future slot with seats is bookable', function () {
    $slot = TimeSlot::factory()->onDate(CarbonImmutable::now()->addWeek())->create();

    expect($slot->isBookable())->toBeTrue();
});

test('recounting bookings ignores cancelled and no-show appointments', function () {
    $slot = TimeSlot::factory()->create(['capacity' => 10]);

    Appointment::factory()->count(2)->for($slot)->create(['status' => AppointmentStatus::Scheduled]);
    Appointment::factory()->for($slot)->create(['status' => AppointmentStatus::Confirmed]);
    Appointment::factory()->for($slot)->create(['status' => AppointmentStatus::Completed]);
    Appointment::factory()->for($slot)->create(['status' => AppointmentStatus::Cancelled]);
    Appointment::factory()->for($slot)->create(['status' => AppointmentStatus::NoShow]);

    expect($slot->recountBookings())->toBe(4);
});

test('the onDate scope returns only active slots for that day', function () {
    $date = CarbonImmutable::now()->addWeek()->startOfDay();

    TimeSlot::factory()->onDate($date)->startingAt(8)->create();
    TimeSlot::factory()->onDate($date)->startingAt(9)->create();
    TimeSlot::factory()->onDate($date)->startingAt(10)->inactive()->create();
    TimeSlot::factory()->onDate($date->addDay())->startingAt(8)->create();

    expect(TimeSlot::query()->onDate($date)->count())->toBe(2);
});

test('the available scope excludes full slots', function () {
    TimeSlot::factory()->startingAt(9)->create(['capacity' => 5, 'booked_count' => 1]);
    TimeSlot::factory()->startingAt(10)->create(['capacity' => 5, 'booked_count' => 5]);

    expect(TimeSlot::query()->available()->count())->toBe(1);
});
