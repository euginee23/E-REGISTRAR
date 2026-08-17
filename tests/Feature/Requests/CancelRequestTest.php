<?php

use App\Actions\Requests\TransitionRequestStatus;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use Illuminate\Validation\UnauthorizedException;
use Livewire\Livewire;

test('a student can cancel a request that has not been finished', function (RequestStatus $status) {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->create(['status' => $status]);

    $this->actingAs($user);

    Livewire::test('pages::student.show-request', ['documentRequest' => $request])
        ->call('cancelRequest')
        ->assertHasNoErrors();

    expect($request->fresh()->status)->toBe(RequestStatus::Cancelled);
})->with([RequestStatus::Pending, RequestStatus::Processing]);

test('cancelling writes a status history entry', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->pending()->create();

    $this->actingAs($user);

    Livewire::test('pages::student.show-request', ['documentRequest' => $request])
        ->call('cancelRequest');

    $history = $request->fresh()->statusHistories->last();

    expect($history->from_status)->toBe(RequestStatus::Pending)
        ->and($history->to_status)->toBe(RequestStatus::Cancelled)
        ->and($history->changed_by_user_id)->toBe($user->id);
});

test('a student cannot cancel once the document is ready or finished', function (RequestStatus $status) {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->create(['status' => $status]);

    $this->actingAs($user);

    Livewire::test('pages::student.show-request', ['documentRequest' => $request])
        ->assertDontSee('data-test="cancel-request-trigger"', escape: false)
        ->call('cancelRequest')
        ->assertForbidden();

    expect($request->fresh()->status)->toBe($status);
})->with([
    RequestStatus::ReadyForRelease,
    RequestStatus::Released,
    RequestStatus::Rejected,
]);

test('a student cannot cancel someone else\'s request', function () {
    $request = DocumentRequest::factory()->for(student()->student)->pending()->create();

    $this->actingAs(student());

    Livewire::test('pages::student.show-request', ['documentRequest' => $request])
        ->assertForbidden();
});

test('the cancel action refuses an illegal transition outright', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->released()->create();

    expect(fn () => app(TransitionRequestStatus::class)(
        documentRequest: $request,
        to: RequestStatus::Cancelled,
        actor: $user,
    ))->toThrow(UnauthorizedException::class);

    expect($request->fresh()->status)->toBe(RequestStatus::Released);
});

test('cancelling your own request does not notify you about it', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->pending()->create();

    app(TransitionRequestStatus::class)(
        documentRequest: $request,
        to: RequestStatus::Cancelled,
        actor: $user,
    );

    expect($user->registrarNotifications()->count())->toBe(0);
});

test('staff cancelling a request notifies the student', function () {
    $user = student();
    $staff = registrarStaff();
    $request = DocumentRequest::factory()->for($user->student)->pending()->create();

    app(TransitionRequestStatus::class)(
        documentRequest: $request,
        to: RequestStatus::Cancelled,
        actor: $staff,
    );

    expect($user->registrarNotifications()->count())->toBe(1);
});
