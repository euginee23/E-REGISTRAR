<?php

use App\Actions\Notifications\SendNotification;
use App\Actions\Requests\TransitionRequestStatus;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\Notification;
use App\Models\User;

test('a notification records its recipient, type, and unread state', function () {
    $user = student();

    app(SendNotification::class)($user, NotificationType::RequestSubmitted, 'Your request was submitted.');

    $notification = Notification::query()->firstOrFail();

    expect($notification->user_id)->toBe($user->id)
        ->and($notification->type)->toBe(NotificationType::RequestSubmitted)
        ->and($notification->message)->toBe('Your request was submitted.')
        ->and($notification->is_read)->toBeFalse();
});

test('a notification can be sent to several recipients at once', function () {
    $staff = registrarStaff();
    $admin = administrator();

    app(SendNotification::class)(
        User::query()->whereIn('id', [$staff->id, $admin->id])->get(),
        NotificationType::RequestReceived,
        'A new request arrived.',
    );

    expect(Notification::query()->count())->toBe(2);
});

test('a status change notification links back to the request', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->pending()->create();

    app(TransitionRequestStatus::class)(
        documentRequest: $request,
        to: RequestStatus::Processing,
        actor: registrarStaff(),
    );

    expect($user->registrarNotifications()->first()->url)
        ->toBe(route('student.requests.show', $request));
});

test('the notification message names the document and reference', function () {
    $user = student();
    $staff = registrarStaff();
    $request = DocumentRequest::factory()->for($user->student)->processing()->create();

    app(TransitionRequestStatus::class)(
        documentRequest: $request,
        to: RequestStatus::ReadyForRelease,
        actor: $staff,
    );

    $message = $user->registrarNotifications()->first()->message;

    expect($message)->toContain($request->reference_no)
        ->and($message)->toContain($request->display_name);
});

test('each staff step in the lifecycle notifies the student separately', function () {
    $user = student();
    $staff = registrarStaff();
    $request = DocumentRequest::factory()->for($user->student)->pending()->create();

    $transition = app(TransitionRequestStatus::class);

    $transition($request, RequestStatus::Processing, $staff);
    $transition($request, RequestStatus::ReadyForRelease, $staff);
    $transition($request, RequestStatus::Released, $staff);

    expect($user->registrarNotifications()->count())->toBe(3)
        ->and($user->registrarNotifications()->pluck('type')->all())->toEqualCanonicalizing([
            NotificationType::RequestStatusChanged,
            NotificationType::RequestStatusChanged,
            NotificationType::RequestStatusChanged,
        ]);
});

test('marking a notification as read is idempotent', function () {
    $notification = Notification::factory()->create();

    expect($notification->markAsRead())->toBeTrue()
        ->and($notification->fresh()->is_read)->toBeTrue()
        ->and($notification->fresh()->markAsRead())->toBeFalse();
});

test('the unread scope excludes notifications already opened', function () {
    $user = student();

    Notification::factory()->count(3)->create(['user_id' => $user->id]);
    Notification::factory()->count(2)->read()->create(['user_id' => $user->id]);

    expect($user->unreadNotificationsCount())->toBe(3);
});

test('marking all as read clears the unread count', function () {
    $user = student();
    Notification::factory()->count(4)->create(['user_id' => $user->id]);

    expect($user->markAllNotificationsRead())->toBe(4)
        ->and($user->unreadNotificationsCount())->toBe(0);
});

test('marking all as read only touches your own notifications', function () {
    $mine = student();
    $theirs = student();

    Notification::factory()->count(2)->create(['user_id' => $mine->id]);
    Notification::factory()->count(3)->create(['user_id' => $theirs->id]);

    $mine->markAllNotificationsRead();

    expect($theirs->unreadNotificationsCount())->toBe(3);
});

test('deleting a user removes their notifications', function () {
    $user = student();
    Notification::factory()->count(3)->create(['user_id' => $user->id]);

    $user->delete();

    expect(Notification::query()->count())->toBe(0);
});
