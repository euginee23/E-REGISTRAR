<?php

use App\Enums\PaymentStatus;

test('every payment status has a label and a badge colour', function (PaymentStatus $status) {
    expect($status->label())->not->toBeEmpty()
        ->and($status->color())->not->toBeEmpty();
})->with(PaymentStatus::cases());

test('only an outstanding fee is unsettled', function (PaymentStatus $status) {
    expect($status->isSettled())->toBe($status !== PaymentStatus::Unpaid)
        ->and($status->isOutstanding())->toBe($status === PaymentStatus::Unpaid);
})->with(PaymentStatus::cases());

test('a waived fee counts as settled', function () {
    expect(PaymentStatus::Waived->isSettled())->toBeTrue()
        ->and(PaymentStatus::NotRequired->isSettled())->toBeTrue();
});
