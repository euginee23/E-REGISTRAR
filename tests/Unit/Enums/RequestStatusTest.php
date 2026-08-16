<?php

use App\Enums\RequestStatus;

test('every status has a label and a colour', function (RequestStatus $status) {
    expect($status->label())->not->toBeEmpty()
        ->and($status->color())->not->toBeEmpty();
})->with(RequestStatus::cases());

test('pending requests may be approved, rejected, or cancelled', function () {
    expect(RequestStatus::Pending->allowedTransitions())->toEqualCanonicalizing([
        RequestStatus::Processing,
        RequestStatus::Rejected,
        RequestStatus::Cancelled,
    ]);
});

test('processing requests may be readied, rejected, or cancelled', function () {
    expect(RequestStatus::Processing->allowedTransitions())->toEqualCanonicalizing([
        RequestStatus::ReadyForRelease,
        RequestStatus::Rejected,
        RequestStatus::Cancelled,
    ]);
});

test('ready requests may only be released or cancelled', function () {
    expect(RequestStatus::ReadyForRelease->allowedTransitions())->toEqualCanonicalizing([
        RequestStatus::Released,
        RequestStatus::Cancelled,
    ]);
});

test('terminal statuses allow no further transitions', function (RequestStatus $status) {
    expect($status->allowedTransitions())->toBe([])
        ->and($status->isTerminal())->toBeTrue();
})->with([
    RequestStatus::Released,
    RequestStatus::Rejected,
    RequestStatus::Cancelled,
]);

test('open statuses are not terminal', function (RequestStatus $status) {
    expect($status->isTerminal())->toBeFalse();
})->with([
    RequestStatus::Pending,
    RequestStatus::Processing,
    RequestStatus::ReadyForRelease,
]);

test('canTransitionTo agrees with the allowed transition list', function (RequestStatus $from, RequestStatus $to) {
    expect($from->canTransitionTo($to))
        ->toBe(in_array($to, $from->allowedTransitions(), strict: true));
})->with(RequestStatus::cases())->with(RequestStatus::cases());

test('a status can never transition to itself', function (RequestStatus $status) {
    expect($status->canTransitionTo($status))->toBeFalse();
})->with(RequestStatus::cases());

test('a request cannot skip straight from pending to released', function () {
    expect(RequestStatus::Pending->canTransitionTo(RequestStatus::Released))->toBeFalse();
});

test('only pending and processing requests may be cancelled by the student', function (RequestStatus $status, bool $expected) {
    expect($status->isCancellableByStudent())->toBe($expected);
})->with([
    [RequestStatus::Pending, true],
    [RequestStatus::Processing, true],
    [RequestStatus::ReadyForRelease, false],
    [RequestStatus::Released, false],
    [RequestStatus::Rejected, false],
    [RequestStatus::Cancelled, false],
]);

test('appointments may only be booked while processing or ready for release', function (RequestStatus $status, bool $expected) {
    expect($status->isBookable())->toBe($expected);
})->with([
    [RequestStatus::Pending, false],
    [RequestStatus::Processing, true],
    [RequestStatus::ReadyForRelease, true],
    [RequestStatus::Released, false],
    [RequestStatus::Rejected, false],
    [RequestStatus::Cancelled, false],
]);

test('open statuses are the ones still in the registrar queue', function () {
    expect(RequestStatus::open())->toBe([
        RequestStatus::Pending,
        RequestStatus::Processing,
        RequestStatus::ReadyForRelease,
    ]);
});
