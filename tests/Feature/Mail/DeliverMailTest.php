<?php

use App\Actions\Mail\DeliverMail;
use App\Mail\TestMail;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;

test('email is sent immediately by default', function () {
    Mail::fake();

    $delivered = app(DeliverMail::class)('someone@example.com', new TestMail('Admin'));

    expect($delivered)->toBeTrue();
    Mail::assertSent(TestMail::class, fn ($mail) => $mail->hasTo('someone@example.com'));
    Mail::assertNothingQueued();
});

test('email reaches the transport without a queue worker', function () {
    Queue::fake();

    app(DeliverMail::class)('someone@example.com', new TestMail('Admin'));

    Queue::assertNothingPushed();
    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(1);
});

test('email is queued on the configured queue when queued delivery is enabled', function () {
    Queue::fake();
    config(['registrar.mail.queued' => true, 'registrar.mail.queue' => 'emails']);

    app(DeliverMail::class)('someone@example.com', new TestMail('Admin'));

    Queue::assertPushedOn('emails', SendQueuedMailable::class);
    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(0);
});

test('a failed send is reported instead of thrown', function () {
    Exceptions::fake();
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
    ]);
    Mail::purge('smtp');

    $delivered = app(DeliverMail::class)('someone@example.com', new TestMail('Admin'));

    expect($delivered)->toBeFalse();
    Exceptions::assertReported(TransportException::class);
});
