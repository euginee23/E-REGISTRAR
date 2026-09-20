<?php

use App\Actions\Users\SendPasswordResetLink;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\UnauthorizedException;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());

    Notification::fake();
});

test('an administrator can email a reset link to an account', function () {
    $user = student();

    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')
        ->call('sendResetLink', $user->id);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('registrar staff cannot send reset links', function () {
    $user = student();
    $staff = registrarStaff();

    expect(fn () => app(SendPasswordResetLink::class)($user, $staff))
        ->toThrow(UnauthorizedException::class);

    Notification::assertNothingSent();
});

test('an administrator cannot send a reset link to themselves', function () {
    $admin = administrator();

    expect(fn () => app(SendPasswordResetLink::class)($admin, $admin))
        ->toThrow(UnauthorizedException::class);
});

test('the emailed token actually lets the student set a new password', function () {
    $user = student();

    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')->call('sendResetLink', $user->id);

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    auth()->logout();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasNoErrors();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'a-brand-new-password',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user->fresh());
});

test('a reset does not let a suspended account back in', function () {
    $user = User::factory()->student()->create(['status' => UserStatus::Suspended]);

    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')->call('sendResetLink', $user->id);

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    auth()->logout();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'a-brand-new-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a pending account cannot be let in through a password reset either', function () {
    $user = User::factory()->student()->create(['status' => UserStatus::Pending]);

    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')->call('sendResetLink', $user->id);

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    auth()->logout();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'a-brand-new-password',
    ])->assertInvalid(['email' => 'waiting for the registrar to approve']);
});

test('a second link in quick succession reports the throttle instead of resending', function () {
    $user = student();
    $admin = administrator();

    $action = app(SendPasswordResetLink::class);

    expect($action($user, $admin))->toBe(Password::RESET_LINK_SENT)
        ->and($action($user, $admin))->toBe(Password::RESET_THROTTLED);
});

test('the reset link button is offered for other accounts but never for oneself', function () {
    student();

    $admin = administrator();
    $this->actingAs($admin);

    Livewire::test('pages::admin.users')
        ->assertSeeHtml('data-test="send-reset-link"');

    // With only the administrator's own row on the page, the button is gone:
    // an administrator cannot start a reset against themselves.
    Livewire::test('pages::admin.users')
        ->set('search', $admin->email)
        ->assertDontSeeHtml('data-test="send-reset-link"');
});

test('registrar staff cannot reach the account management screen at all', function () {
    $this->actingAs(registrarStaff());

    $this->get(route('admin.users.index'))->assertForbidden();
});
