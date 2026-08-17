<?php

use App\Actions\Slots\GenerateTimeSlots;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

test('slots are only opened on weekdays', function () {
    $monday = CarbonImmutable::parse('2026-09-07');

    app(GenerateTimeSlots::class)($monday, $monday->addDays(6));

    $days = TimeSlot::query()->get()->map(fn (TimeSlot $slot) => $slot->slot_date->dayOfWeekIso)->unique()->sort()->values();

    expect($days->all())->toBe([1, 2, 3, 4, 5]);
});

test('a full working day yields one slot per hour of office time', function () {
    $monday = CarbonImmutable::parse('2026-09-07');

    app(GenerateTimeSlots::class)($monday, $monday);

    // 08:00 to 17:00 in one-hour slots.
    expect(TimeSlot::query()->count())->toBe(9)
        ->and(TimeSlot::query()->min('start_time'))->toBe('08:00:00')
        ->and(TimeSlot::query()->max('end_time'))->toBe('17:00:00');
});

test('generated slots carry an end time', function () {
    $monday = CarbonImmutable::parse('2026-09-07');

    app(GenerateTimeSlots::class)($monday, $monday);

    $first = TimeSlot::query()->orderBy('start_time')->firstOrFail();

    expect($first->start_time)->toBe('08:00:00')
        ->and($first->end_time)->toBe('09:00:00')
        ->and($first->label)->toBe('8:00 AM - 9:00 AM');
});

test('generating twice does not duplicate slots', function () {
    $monday = CarbonImmutable::parse('2026-09-07');

    app(GenerateTimeSlots::class)($monday, $monday->addDays(4));
    $first = TimeSlot::query()->count();

    app(GenerateTimeSlots::class)($monday, $monday->addDays(4));

    expect(TimeSlot::query()->count())->toBe($first);
});

test('regenerating never discards existing bookings', function () {
    $monday = CarbonImmutable::parse('2026-09-07');

    app(GenerateTimeSlots::class)($monday, $monday);

    $slot = TimeSlot::query()->orderBy('start_time')->firstOrFail();
    $slot->forceFill(['booked_count' => 3])->save();

    app(GenerateTimeSlots::class)($monday, $monday);

    expect($slot->fresh()->booked_count)->toBe(3);
});

test('regenerating updates the capacity of existing slots', function () {
    $monday = CarbonImmutable::parse('2026-09-07');

    app(GenerateTimeSlots::class)($monday, $monday, capacity: 5);
    app(GenerateTimeSlots::class)($monday, $monday, capacity: 12);

    expect(TimeSlot::query()->distinct()->pluck('capacity')->all())->toBe([12]);
});

test('a weekend-only range opens nothing', function () {
    $saturday = CarbonImmutable::parse('2026-09-12');

    $created = app(GenerateTimeSlots::class)($saturday, $saturday->addDay());

    expect($created)->toBe(0)
        ->and(TimeSlot::query()->count())->toBe(0);
});

test('custom office hours are respected', function () {
    $monday = CarbonImmutable::parse('2026-09-07');

    app(GenerateTimeSlots::class)($monday, $monday, opensAt: '09:00', closesAt: '12:00', slotMinutes: 90);

    expect(TimeSlot::query()->count())->toBe(2)
        ->and(TimeSlot::query()->orderBy('start_time')->pluck('start_time')->all())
        ->toBe(['09:00:00', '10:30:00']);
});

test('a partial trailing slot is never created', function () {
    $monday = CarbonImmutable::parse('2026-09-07');

    // 08:00 to 11:00 in 50-minute slots fits three, with 30 minutes left over
    // that must not become a short fourth slot running past closing time.
    app(GenerateTimeSlots::class)($monday, $monday, opensAt: '08:00', closesAt: '11:00', slotMinutes: 50);

    expect(TimeSlot::query()->count())->toBe(3)
        ->and(TimeSlot::query()->max('end_time'))->toBe('10:30:00');
});

test('staff can open slots through the management screen', function () {
    $this->actingAs(registrarStaff());

    $monday = CarbonImmutable::parse('2026-09-07');

    Livewire::test('pages::registrar.time-slots')
        ->set('generateFrom', $monday->toDateString())
        ->set('generateUntil', $monday->addDays(4)->toDateString())
        ->set('generateCapacity', 6)
        ->call('generateSlots')
        ->assertHasNoErrors();

    expect(TimeSlot::query()->count())->toBe(45)
        ->and(TimeSlot::query()->distinct()->pluck('capacity')->all())->toBe([6]);
});

test('the end of the range cannot precede the start', function () {
    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.time-slots')
        ->set('generateFrom', '2026-09-10')
        ->set('generateUntil', '2026-09-07')
        ->call('generateSlots')
        ->assertHasErrors('generateUntil');
});

test('students cannot manage time slots', function () {
    $this->actingAs(student());

    $this->get(route('registrar.time-slots.index'))->assertForbidden();
});

test('staff can close and reopen a slot', function () {
    $slot = TimeSlot::factory()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.time-slots')
        ->set('date', $slot->slot_date->toDateString())
        ->call('toggleSlot', $slot->id);

    expect($slot->fresh()->is_active)->toBeFalse();

    Livewire::test('pages::registrar.time-slots')
        ->set('date', $slot->slot_date->toDateString())
        ->call('toggleSlot', $slot->id);

    expect($slot->fresh()->is_active)->toBeTrue();
});

test('capacity cannot be cut below the bookings already taken', function () {
    $slot = TimeSlot::factory()->create(['capacity' => 5, 'booked_count' => 3]);

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.time-slots')
        ->set('date', $slot->slot_date->toDateString())
        ->call('updateCapacity', $slot->id, 2);

    expect($slot->fresh()->capacity)->toBe(5);
});

test('capacity can be raised', function () {
    $slot = TimeSlot::factory()->create(['capacity' => 5, 'booked_count' => 3]);

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.time-slots')
        ->set('date', $slot->slot_date->toDateString())
        ->call('updateCapacity', $slot->id, 8);

    expect($slot->fresh()->capacity)->toBe(8);
});
