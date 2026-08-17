<?php

use App\Models\Notification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('every role can open their notifications', function (string $helper) {
    $this->actingAs($helper());

    $this->get(route('notifications.index'))->assertOk();
})->with(['student', 'registrarStaff', 'administrator']);

test('guests are redirected away from notifications', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
});

test('a user sees only their own notifications', function () {
    $mine = student();
    $theirs = student();

    Notification::factory()->create(['user_id' => $mine->id, 'message' => 'Message meant for me']);
    Notification::factory()->create(['user_id' => $theirs->id, 'message' => 'Message meant for them']);

    $this->actingAs($mine);

    $this->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Message meant for me')
        ->assertDontSee('Message meant for them');
});

test('a single notification can be marked as read', function () {
    $user = student();
    $notification = Notification::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::notifications')
        ->call('markAsRead', $notification->id)
        ->assertHasNoErrors();

    expect($notification->fresh()->is_read)->toBeTrue();
});

test('a user cannot mark someone else\'s notification as read', function () {
    $notification = Notification::factory()->create(['user_id' => student()->id]);

    $this->actingAs(student());

    // The lookup is scoped to the signed-in user, so another user's
    // notification simply is not found - it resolves to a 404 over HTTP.
    expect(fn () => Livewire::test('pages::notifications')->call('markAsRead', $notification->id))
        ->toThrow(ModelNotFoundException::class);

    expect($notification->fresh()->is_read)->toBeFalse();
});

test('all notifications can be marked as read at once', function () {
    $user = student();
    Notification::factory()->count(4)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::notifications')
        ->call('markAllAsRead')
        ->assertHasNoErrors();

    expect($user->unreadNotificationsCount())->toBe(0);
});

test('the unread filter hides notifications already opened', function () {
    $user = student();

    Notification::factory()->create(['user_id' => $user->id, 'message' => 'Still unread here']);
    Notification::factory()->read()->create(['user_id' => $user->id, 'message' => 'Already opened this']);

    $this->actingAs($user);

    Livewire::test('pages::notifications')
        ->set('unreadOnly', true)
        ->assertSee('Still unread here')
        ->assertDontSee('Already opened this');
});

test('the unread count is shown when notifications are waiting', function () {
    $user = student();
    Notification::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::notifications')
        ->assertSet('unreadOnly', false)
        ->assertSee('3 unread');
});

test('the notification bell reports the unread count', function () {
    $user = student();
    Notification::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('notification-bell')
        ->assertOk()
        ->assertSee('2');
});

test('the bell caps the badge at nine plus', function () {
    $user = student();
    Notification::factory()->count(12)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('notification-bell')->assertSee('9+');
});

test('the bell shows nothing to do when everything is read', function () {
    $user = student();
    Notification::factory()->count(2)->read()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('notification-bell')
        ->assertSee('You are all caught up.')
        ->assertDontSee('data-test="notification-count"', escape: false);
});

test('the bell refreshes when the page marks everything read', function () {
    $user = student();
    Notification::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $bell = Livewire::test('notification-bell');
    expect($user->unreadNotificationsCount())->toBe(3);

    $user->markAllNotificationsRead();

    $bell->dispatch('notifications-read')
        ->assertSee('You are all caught up.');
});
