<?php

use App\Enums\AppointmentStatus;

test('every status has a label and a colour', function (AppointmentStatus $status) {
    expect($status->label())->not->toBeEmpty()
        ->and($status->color())->not->toBeEmpty();
})->with(AppointmentStatus::cases());

test('live appointments occupy a seat in the slot', function (AppointmentStatus $status, bool $expected) {
    expect($status->occupiesCapacity())->toBe($expected);
})->with([
    [AppointmentStatus::Scheduled, true],
    [AppointmentStatus::Confirmed, true],
    [AppointmentStatus::Completed, true],
    [AppointmentStatus::Cancelled, false],
    [AppointmentStatus::NoShow, false],
]);

test('the occupying list matches the occupiesCapacity rule', function () {
    expect(AppointmentStatus::occupying())->toBe([
        AppointmentStatus::Scheduled,
        AppointmentStatus::Confirmed,
        AppointmentStatus::Completed,
    ]);
});

test('cancelling or missing an appointment frees the seat', function (AppointmentStatus $status) {
    expect(AppointmentStatus::occupying())->not->toContain($status);
})->with([
    AppointmentStatus::Cancelled,
    AppointmentStatus::NoShow,
]);

test('every status has a calendar dot colour', function (AppointmentStatus $status) {
    expect($status->dotClass())->toStartWith('bg-');
})->with(AppointmentStatus::cases());

test('the dot colours are distinct per status', function () {
    $classes = array_map(fn (AppointmentStatus $s): string => $s->dotClass(), AppointmentStatus::cases());

    expect($classes)->toBe(array_unique($classes));
});
