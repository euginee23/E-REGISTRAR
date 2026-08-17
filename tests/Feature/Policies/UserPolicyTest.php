<?php

use App\Enums\UserStatus;
use App\Models\User;

test('only administrators may manage accounts', function () {
    expect(administrator()->can('viewAny', User::class))->toBeTrue()
        ->and(registrarStaff()->can('viewAny', User::class))->toBeFalse()
        ->and(student()->can('viewAny', User::class))->toBeFalse();
});

test('only administrators may create accounts', function () {
    expect(administrator()->can('create', User::class))->toBeTrue()
        ->and(registrarStaff()->can('create', User::class))->toBeFalse();
});

test('an administrator cannot change their own status or role', function () {
    $admin = administrator();
    administrator();

    expect($admin->can('changeStatus', $admin))->toBeFalse()
        ->and($admin->can('changeRole', $admin))->toBeFalse()
        ->and($admin->can('delete', $admin))->toBeFalse();
});

test('the last active administrator cannot be disabled', function () {
    $admin = administrator();
    $other = administrator();

    // Two administrators exist, so either may be changed.
    expect($admin->can('changeStatus', $other))->toBeTrue();

    $other->update(['status' => UserStatus::Suspended]);

    // Only one active administrator is left, and it is the actor themselves.
    expect($admin->can('changeStatus', $admin))->toBeFalse();
});

test('an administrator may manage staff and student accounts', function (string $helper) {
    $admin = administrator();

    expect($admin->can('changeStatus', $helper()))->toBeTrue()
        ->and($admin->can('changeRole', $helper()))->toBeTrue()
        ->and($admin->can('delete', $helper()))->toBeTrue();
})->with(['registrarStaff', 'student']);

test('a user may view their own account', function () {
    $user = student();

    expect($user->can('view', $user))->toBeTrue();
});
