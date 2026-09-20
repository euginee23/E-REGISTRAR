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

test('registrar staff may approve and reject a pending account', function (string $ability) {
    $pending = User::factory()->student()->create(['status' => UserStatus::Pending]);

    expect(registrarStaff()->can($ability, $pending))->toBeTrue()
        ->and(administrator()->can($ability, $pending))->toBeTrue()
        ->and(student()->can($ability, $pending))->toBeFalse();
})->with(['approve', 'reject']);

test('an account that is not pending can be neither approved nor rejected', function (string $ability) {
    $active = User::factory()->student()->create(['status' => UserStatus::Active]);

    expect(registrarStaff()->can($ability, $active))->toBeFalse();
})->with(['approve', 'reject']);

test('nobody may approve their own pending account', function () {
    $staff = User::factory()->registrarStaff()->create(['status' => UserStatus::Pending]);

    expect($staff->can('approve', $staff))->toBeFalse();
});

test('a pending account cannot have its status flipped directly', function () {
    $pending = User::factory()->student()->create(['status' => UserStatus::Pending]);

    expect(administrator()->can('changeStatus', $pending))->toBeFalse();
});

test('only an administrator may send a password reset link', function () {
    $target = User::factory()->student()->create();

    expect(administrator()->can('resetPassword', $target))->toBeTrue()
        ->and(registrarStaff()->can('resetPassword', $target))->toBeFalse()
        ->and(student()->can('resetPassword', $target))->toBeFalse();
});
