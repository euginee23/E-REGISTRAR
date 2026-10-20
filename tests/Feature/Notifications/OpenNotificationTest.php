<?php

use App\Actions\Requests\SubmitDocumentRequest;
use App\Enums\NotificationType;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\Notification;
use Livewire\Livewire;

test('opening a notification marks it read and goes to its page', function () {
    $user = student();
    $notification = Notification::factory()->create([
        'user_id' => $user->id,
        'url' => route('student.requests.index'),
        'is_read' => false,
    ]);

    $this->actingAs($user)
        ->get(route('notifications.open', $notification))
        ->assertRedirect('/student/requests');

    expect($notification->fresh()->is_read)->toBeTrue();
});

test('a notification stored with another host still lands on this site', function () {
    $user = student();
    $notification = Notification::factory()->create([
        'user_id' => $user->id,
        'url' => 'http://localhost/student/appointments?date=2026-10-20',
    ]);

    $this->actingAs($user)
        ->get(route('notifications.open', $notification))
        ->assertRedirect('/student/appointments?date=2026-10-20');
});

test('a notification without a destination opens the notifications page', function () {
    $user = student();
    $notification = Notification::factory()->create(['user_id' => $user->id, 'url' => null]);

    $this->actingAs($user)
        ->get(route('notifications.open', $notification))
        ->assertRedirect(route('notifications.index'));
});

test('a guest is sent to log in first', function () {
    $notification = Notification::factory()->create(['user_id' => student()->id]);

    $this->get(route('notifications.open', $notification))
        ->assertRedirect(route('login'));
});

test('someone else\'s notification is refused with an offer to switch accounts', function () {
    $notification = Notification::factory()->create([
        'user_id' => student()->id,
        'url' => route('student.appointments.index'),
        'is_read' => false,
    ]);

    $this->actingAs(registrarStaff())
        ->get(route('notifications.open', $notification))
        ->assertForbidden()
        ->assertSeeHtml('data-test="switch-account-button"')
        ->assertSee('Registrar Staff');

    expect($notification->fresh()->is_read)->toBeFalse();
});

test('a student link opened while signed in as the registrar explains itself', function () {
    $this->actingAs(registrarStaff())
        ->get(route('student.appointments.index'))
        ->assertForbidden()
        ->assertSee('You don&#039;t have access to this page', escape: false)
        ->assertSeeHtml('value="/student/appointments"');
});

test('switching accounts signs out and returns to the link after logging in', function () {
    $student = student();

    $this->actingAs(registrarStaff())
        ->post(route('switch-account'), ['continue' => '/student/appointments'])
        ->assertRedirect(route('login'));

    $this->assertGuest();

    $this->post(route('login.store'), ['email' => $student->email, 'password' => 'password'])
        ->assertRedirect(url('/student/appointments'));
});

test('switching accounts never redirects off-site', function (string $continue) {
    $this->actingAs(registrarStaff())
        ->post(route('switch-account'), ['continue' => $continue])
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBeNull();
})->with(['https://evil.example', '//evil.example', '/\\evil.example', '']);

test('the registrar\'s new request notification opens the request', function () {
    $staff = registrarStaff();

    $documentRequest = app(SubmitDocumentRequest::class)(
        student: student()->student,
        documentType: DocumentType::factory()->create(),
        purpose: 'Employment requirement',
    );

    $notification = Notification::query()
        ->where('user_id', $staff->id)
        ->where('type', NotificationType::RequestReceived)
        ->firstOrFail();

    expect($notification->url)->toBe(route('registrar.requests.show', $documentRequest));

    $this->actingAs($staff)
        ->get(route('notifications.open', $notification))
        ->assertRedirect('/registrar/requests/'.$documentRequest->getRouteKey());
});

test('the bell links each notification through the open route', function () {
    $user = registrarStaff();
    $notification = Notification::factory()->create([
        'user_id' => $user->id,
        'url' => route('registrar.requests.show', DocumentRequest::factory()->create()),
        'is_read' => false,
    ]);

    $this->actingAs($user);

    Livewire::test('notification-bell')
        ->assertSeeHtml('href="'.route('notifications.open', $notification).'"');
});
