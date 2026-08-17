<?php

use App\Enums\UserStatus;
use App\Models\User;

test('an active user reaches the dashboard', function () {
    $this->actingAs(student());

    $this->get(route('dashboard'))->assertOk();
});

test('a deactivated user is signed out mid-session', function (UserStatus $status) {
    $user = User::factory()->student()->create(['status' => $status]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
})->with([
    UserStatus::Suspended,
    UserStatus::Inactive,
]);

test('a deactivated user cannot reach the settings screens either', function () {
    $user = User::factory()->student()->create(['status' => UserStatus::Suspended]);

    $this->actingAs($user);

    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('guests are unaffected by the account status check', function () {
    $this->get(route('login'))->assertOk();
});
