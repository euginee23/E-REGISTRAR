<?php

use App\Enums\UserStatus;

test('every status has a label and a badge colour', function (UserStatus $status) {
    expect($status->label())->not->toBeEmpty()
        ->and($status->color())->not->toBeEmpty();
})->with(UserStatus::cases());

test('only an active account may sign in', function (UserStatus $status) {
    expect($status->canSignIn())->toBe($status === UserStatus::Active);
})->with(UserStatus::cases());

test('only a pending account awaits approval', function (UserStatus $status) {
    expect($status->awaitsApproval())->toBe($status === UserStatus::Pending);
})->with(UserStatus::cases());

test('every status can explain itself at the sign-in screen', function (UserStatus $status) {
    expect($status->signInMessage())->not->toBeEmpty();
})->with(UserStatus::cases());

test('a pending account is told to wait rather than to seek help', function () {
    expect(UserStatus::Pending->signInMessage())->toContain('approve')
        ->and(UserStatus::Suspended->signInMessage())->toContain('not active');
});
