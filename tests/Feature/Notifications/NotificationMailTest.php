<?php

use App\Actions\Notifications\SendNotification;
use App\Actions\Requests\TransitionRequestStatus;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Mail\RegistrarNotificationMail;
use App\Models\DocumentRequest;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->send = app(SendNotification::class);
});

test('a notification is stored and its email queued', function () {
    $user = student();

    ($this->send)($user, NotificationType::RequestSubmitted, 'Your request was received.');

    expect(Notification::query()->where('user_id', $user->id)->count())->toBe(1);

    Mail::assertQueued(RegistrarNotificationMail::class, fn ($mail) => $mail->hasTo($user->email));
});

test('every recipient gets their own email', function () {
    $recipients = [student(), registrarStaff(), administrator()];

    ($this->send)($recipients, NotificationType::RequestReceived, 'A new request is waiting.');

    Mail::assertQueued(RegistrarNotificationMail::class, 3);
    expect(Notification::query()->count())->toBe(3);
});

test('the email carries the same message and link as the stored notification', function () {
    $user = student();

    ($this->send)($user, NotificationType::RequestStatusChanged, 'Your request is ready.', '/student/requests');

    Mail::assertQueued(RegistrarNotificationMail::class, function ($mail) {
        return $mail->body === 'Your request is ready.'
            && $mail->url === '/student/requests'
            && $mail->type === NotificationType::RequestStatusChanged;
    });
});

test('the rendered email shows the message and the subject names the event', function () {
    $user = student();

    ($this->send)($user, NotificationType::AppointmentReminder, 'Your appointment is tomorrow.', '/student/appointments');

    Mail::assertQueued(RegistrarNotificationMail::class, function (RegistrarNotificationMail $mail) {
        $mail->assertSeeInHtml('Your appointment is tomorrow.');
        $mail->assertHasSubject(NotificationType::AppointmentReminder->label());

        return true;
    });
});

test('recipients who share a message still each receive their own copy', function () {
    $first = student();
    $second = student();

    ($this->send)([$first, $second], NotificationType::RequestReceived, 'A new request is waiting.');

    Mail::assertQueued(RegistrarNotificationMail::class, fn ($mail) => $mail->hasTo($first->email));
    Mail::assertQueued(RegistrarNotificationMail::class, fn ($mail) => $mail->hasTo($second->email));
});

test('turning the mail mirror off still records the in-app notification', function () {
    config(['registrar.notifications.mail' => false]);

    $user = student();

    ($this->send)($user, NotificationType::RequestSubmitted, 'Your request was received.');

    expect(Notification::query()->where('user_id', $user->id)->count())->toBe(1);

    Mail::assertNothingQueued();
});

test('recipients passed as a generator are both stored and mailed', function () {
    $users = [student(), registrarStaff()];

    $generator = (function () use ($users) {
        foreach ($users as $user) {
            yield $user;
        }
    })();

    ($this->send)($generator, NotificationType::RequestReceived, 'A new request is waiting.');

    expect(Notification::query()->count())->toBe(2);
    Mail::assertQueued(RegistrarNotificationMail::class, 2);
});

test('a request status change emails the student', function () {
    $documentRequest = DocumentRequest::factory()->create(['status' => RequestStatus::Pending]);
    $staff = registrarStaff();

    app(TransitionRequestStatus::class)($documentRequest, RequestStatus::Processing, $staff);

    Mail::assertQueued(
        RegistrarNotificationMail::class,
        fn ($mail) => $mail->hasTo($documentRequest->student->user->email),
    );
});

test('the queued email is placed on the configured queue', function () {
    config(['registrar.notifications.queue' => 'mail-low']);

    ($this->send)(student(), NotificationType::RequestSubmitted, 'Your request was received.');

    Mail::assertQueued(RegistrarNotificationMail::class, fn ($mail) => $mail->queue === 'mail-low');
});
