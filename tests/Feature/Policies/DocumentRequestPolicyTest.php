<?php

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\User;

test('a student may view their own request', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->create();

    expect($user->can('view', $request))->toBeTrue();
});

test('a student may not view another student\'s request', function () {
    $mine = student();
    $theirs = student();
    $request = DocumentRequest::factory()->for($theirs->student)->create();

    expect($mine->can('view', $request))->toBeFalse();
});

test('staff may view any request', function (string $helper) {
    $request = DocumentRequest::factory()->create();

    expect($helper()->can('view', $request))->toBeTrue();
})->with(['registrarStaff', 'administrator']);

test('only students with a profile may submit requests', function () {
    expect(student()->can('create', DocumentRequest::class))->toBeTrue()
        ->and(registrarStaff()->can('create', DocumentRequest::class))->toBeFalse()
        ->and(User::factory()->create()->can('create', DocumentRequest::class))->toBeFalse();
});

test('only staff may edit registrar-side fields', function () {
    $request = DocumentRequest::factory()->create();

    expect(registrarStaff()->can('update', $request))->toBeTrue()
        ->and(student()->can('update', $request))->toBeFalse();
});

test('a student may cancel their own request while it is still early', function (RequestStatus $status, bool $expected) {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->create(['status' => $status]);

    expect($user->can('cancel', $request))->toBe($expected);
})->with([
    [RequestStatus::Pending, true],
    [RequestStatus::Processing, true],
    [RequestStatus::ReadyForRelease, false],
    [RequestStatus::Released, false],
    [RequestStatus::Rejected, false],
    [RequestStatus::Cancelled, false],
]);

test('staff may not cancel a request that has already finished', function (RequestStatus $status) {
    $request = DocumentRequest::factory()->create(['status' => $status]);

    expect(registrarStaff()->can('cancel', $request))->toBeFalse();
})->with([
    RequestStatus::Released,
    RequestStatus::Rejected,
    RequestStatus::Cancelled,
]);

test('a transition is refused when the state machine forbids it', function () {
    $request = DocumentRequest::factory()->create(['status' => RequestStatus::Pending]);
    $staff = registrarStaff();

    expect($staff->can('transition', [$request, RequestStatus::Released]))->toBeFalse()
        ->and($staff->can('transition', [$request, RequestStatus::Processing]))->toBeTrue();
});

test('a student cannot drive a staff-only transition on their own request', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->create(['status' => RequestStatus::Pending]);

    expect($user->can('transition', [$request, RequestStatus::Processing]))->toBeFalse()
        ->and($user->can('transition', [$request, RequestStatus::Cancelled]))->toBeTrue();
});

test('a student may only attach requirements while the request is pending', function (RequestStatus $status, bool $expected) {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->create(['status' => $status]);

    expect($user->can('uploadAttachment', $request))->toBe($expected);
})->with([
    [RequestStatus::Pending, true],
    [RequestStatus::Processing, false],
    [RequestStatus::Released, false],
]);

test('a student may book only their own bookable request', function () {
    $user = student();
    $other = student();

    $bookable = DocumentRequest::factory()->for($user->student)->create(['status' => RequestStatus::Processing]);
    $tooEarly = DocumentRequest::factory()->for($user->student)->create(['status' => RequestStatus::Pending]);
    $notMine = DocumentRequest::factory()->for($other->student)->create(['status' => RequestStatus::Processing]);

    expect($user->can('book', $bookable))->toBeTrue()
        ->and($user->can('book', $tooEarly))->toBeFalse()
        ->and($user->can('book', $notMine))->toBeFalse();
});

test('administrators are granted staff abilities by the policy itself', function () {
    // There is deliberately no Gate::before administrator bypass: a blanket
    // pass would also skip the invariants policies encode, such as the
    // last-administrator lockout guard in UserPolicy.
    $request = DocumentRequest::factory()->create(['status' => RequestStatus::Released]);

    expect(administrator()->can('update', $request))->toBeTrue()
        ->and(administrator()->can('view', $request))->toBeTrue();
});
