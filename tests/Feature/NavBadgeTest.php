<?php

use App\Enums\RequestStatus;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\Notification;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

test('the sidebar renders for every role', function (string $helper) {
    $this->actingAs($helper());

    Livewire::test('sidebar-nav')->assertOk();
})->with(['administrator', 'registrarStaff', 'student']);

test('staff see how many requests are waiting', function () {
    DocumentRequest::factory()->count(3)->create(['status' => RequestStatus::Pending]);
    DocumentRequest::factory()->released()->create();

    $this->actingAs(registrarStaff());

    expect(Livewire::test('sidebar-nav')->instance()->badges['pendingRequests'])->toBe(3);
});

test('a zero count renders no badge at all', function () {
    DocumentRequest::factory()->released()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('sidebar-nav')->assertDontSeeHtml('data-flux-navlist-badge>');
});

test('a non-zero count does render a badge', function () {
    DocumentRequest::factory()->create(['status' => RequestStatus::Pending]);

    $this->actingAs(registrarStaff());

    Livewire::test('sidebar-nav')->assertSeeHtml('data-flux-navlist-badge>');
});

test('staff see how many accounts are waiting for review', function () {
    User::factory()->student()->count(2)->create(['status' => UserStatus::Pending]);
    User::factory()->student()->create();

    $this->actingAs(registrarStaff());

    expect(Livewire::test('sidebar-nav')->instance()->badges['pendingAccounts'])->toBe(2);
});

test('staff see today\'s appointment load', function () {
    $todaySlot = TimeSlot::factory()->onDate(CarbonImmutable::today())->startingAt(9)->create();
    $laterSlot = TimeSlot::factory()->onDate(CarbonImmutable::today()->addWeekday())->startingAt(9)->create();

    Appointment::factory()->create([
        'document_request_id' => DocumentRequest::factory()->readyForRelease()->create()->id,
        'time_slot_id' => $todaySlot->id,
    ]);

    Appointment::factory()->create([
        'document_request_id' => DocumentRequest::factory()->readyForRelease()->create()->id,
        'time_slot_id' => $laterSlot->id,
    ]);

    $this->actingAs(registrarStaff());

    expect(Livewire::test('sidebar-nav')->instance()->badges['appointmentsToday'])->toBe(1);
});

test('a student counts only their own open requests', function () {
    $user = student();

    DocumentRequest::factory()->for($user->student)->count(2)->create();
    DocumentRequest::factory()->for($user->student)->released()->create();
    DocumentRequest::factory()->create();

    $this->actingAs($user);

    expect(Livewire::test('sidebar-nav')->instance()->badges['openRequests'])->toBe(2);
});

test('a student never sees the staff counts', function () {
    DocumentRequest::factory()->count(3)->create(['status' => RequestStatus::Pending]);
    User::factory()->student()->create(['status' => UserStatus::Pending]);

    $this->actingAs(student());

    $badges = Livewire::test('sidebar-nav')->instance()->badges;

    expect($badges['pendingRequests'])->toBe(0)
        ->and($badges['pendingAccounts'])->toBe(0);
});

test('a student without a profile still gets a sidebar', function () {
    $this->actingAs(studentWithoutProfile());

    expect(Livewire::test('sidebar-nav')->instance()->badges['openRequests'])->toBe(0);
});

test('the unread notification count reaches the sidebar', function () {
    $user = student();

    Notification::factory()->count(2)->create(['user_id' => $user->id, 'is_read' => false]);
    Notification::factory()->create(['user_id' => $user->id, 'is_read' => true]);

    $this->actingAs($user);

    expect(Livewire::test('sidebar-nav')->instance()->badges['unreadNotifications'])->toBe(2);
});

test('the sidebar is mounted on a real page', function () {
    $this->actingAs(registrarStaff());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSeeHtml('data-test="sidebar-nav"');
});
