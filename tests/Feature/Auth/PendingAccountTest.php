<?php

use App\Enums\UserStatus;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('a pending account cannot sign in', function () {
    $user = User::factory()->student()->create([
        'email' => 'pending@example.com',
        'status' => UserStatus::Pending,
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the pending account is told it is waiting for approval, not that it is broken', function () {
    $user = User::factory()->student()->create(['status' => UserStatus::Pending]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertInvalid(['email' => 'waiting for the registrar to approve']);
});

test('a suspended account keeps the generic message', function () {
    $user = User::factory()->student()->create(['status' => UserStatus::Suspended]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertInvalid(['email' => 'not active']);
});

test('wrong credentials never reveal the account status', function () {
    $user = User::factory()->student()->create(['status' => UserStatus::Pending]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'the-wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    expect($response->baseResponse->getSession()->get('errors')->first('email'))
        ->not->toContain('approve');

    $this->assertGuest();
});

test('an active account still signs in normally', function () {
    $user = User::factory()->student()->create(['status' => UserStatus::Active]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('a pending session is bounced from the dashboard', function () {
    $user = User::factory()->student()->create(['status' => UserStatus::Pending]);

    $this->actingAs($user);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('registering flashes the awaiting-approval notice to the login screen', function () {
    registryEntry(['student_number' => '2022-10231', 'name' => 'John Doe']);

    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'student_number' => '2022-10231',
        'course' => 'BS Information Technology',
        'enrollment_status' => 'enrolled',
        'contact_number' => '09171234567',
    ]);

    expect(session('status'))->toContain('approve');

    $this->get(route('login'))->assertSee('approve', escape: false);
});
