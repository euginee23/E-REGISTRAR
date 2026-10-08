<?php

use App\Mail\TestMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('administrators can open the mail settings page', function () {
    $this->actingAs(administrator())
        ->get(route('mail-settings.edit'))
        ->assertOk()
        ->assertSee('Send test email');
});

test('staff and students cannot open the mail settings page', function (User $user) {
    $this->actingAs($user)
        ->get(route('mail-settings.edit'))
        ->assertForbidden();
})->with([
    'registrar staff' => fn () => registrarStaff(),
    'student' => fn () => student(),
]);

test('the recipient defaults to the administrator\'s own address', function () {
    $admin = administrator();

    Livewire::actingAs($admin)
        ->test('pages::settings.mail')
        ->assertSet('recipient', $admin->email);
});

test('a test email is sent immediately to the given recipient', function () {
    Mail::fake();
    config(['registrar.mail.queued' => true]);

    Livewire::actingAs(administrator())
        ->test('pages::settings.mail')
        ->set('recipient', 'inbox@example.com')
        ->call('sendTestEmail')
        ->assertHasNoErrors()
        ->assertSet('succeeded', true);

    Mail::assertSent(TestMail::class, fn ($mail) => $mail->hasTo('inbox@example.com'));
    Mail::assertNothingQueued();
});

test('the recipient must be a valid email address', function () {
    Mail::fake();

    Livewire::actingAs(administrator())
        ->test('pages::settings.mail')
        ->set('recipient', 'not-an-email')
        ->call('sendTestEmail')
        ->assertHasErrors(['recipient' => 'email']);

    Mail::assertNothingSent();
});

test('a mail server failure is shown to the administrator', function () {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
    ]);
    Mail::purge('smtp');

    Livewire::actingAs(administrator())
        ->test('pages::settings.mail')
        ->set('recipient', 'inbox@example.com')
        ->call('sendTestEmail')
        ->assertSet('succeeded', false)
        ->assertSee('The test email could not be sent');
});

test('the page warns when the mailer does not deliver email', function () {
    config(['mail.default' => 'log']);

    Livewire::actingAs(administrator())
        ->test('pages::settings.mail')
        ->assertSee('Email is not being delivered');
});

test('staff cannot send a test email', function () {
    Mail::fake();

    Livewire::actingAs(registrarStaff())
        ->test('pages::settings.mail')
        ->assertForbidden();

    Mail::assertNothingSent();
});

test('test emails are rate limited', function () {
    Mail::fake();

    $component = Livewire::actingAs(administrator())->test('pages::settings.mail');

    foreach (range(1, 5) as $attempt) {
        $component->call('sendTestEmail')->assertHasNoErrors();
    }

    $component->call('sendTestEmail')->assertHasErrors('recipient');

    Mail::assertSentCount(5);
});
