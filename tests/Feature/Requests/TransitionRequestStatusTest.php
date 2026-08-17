<?php

use App\Actions\Requests\TransitionRequestStatus;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use Illuminate\Validation\UnauthorizedException;

/**
 * Apply a transition as the given actor.
 */
function transition(DocumentRequest $request, RequestStatus $to, $actor, ?string $remarks = null): DocumentRequest
{
    return app(TransitionRequestStatus::class)(
        documentRequest: $request,
        to: $to,
        actor: $actor,
        remarks: $remarks,
    );
}

test('staff can apply every legal transition', function (RequestStatus $from, RequestStatus $to) {
    $request = DocumentRequest::factory()->create(['status' => $from]);

    transition($request, $to, registrarStaff());

    expect($request->fresh()->status)->toBe($to);
})->with(function () {
    foreach (RequestStatus::cases() as $from) {
        foreach ($from->allowedTransitions() as $to) {
            // Student-only cancellation is covered separately.
            if ($to !== RequestStatus::Cancelled) {
                yield [$from, $to];
            }
        }
    }
});

test('an illegal transition is refused', function (RequestStatus $from, RequestStatus $to) {
    $request = DocumentRequest::factory()->create(['status' => $from]);

    expect(fn () => transition($request, $to, registrarStaff()))
        ->toThrow(UnauthorizedException::class);

    expect($request->fresh()->status)->toBe($from);
})->with(function () {
    foreach (RequestStatus::cases() as $from) {
        foreach (RequestStatus::cases() as $to) {
            if (! $from->canTransitionTo($to)) {
                yield [$from, $to];
            }
        }
    }
});

test('a transition records who made it and when', function () {
    $staff = registrarStaff();
    $request = DocumentRequest::factory()->pending()->create();

    transition($request, RequestStatus::Processing, $staff, 'Verified the requirements.');

    $request->refresh();
    $history = $request->statusHistories->last();

    expect($request->processed_by_user_id)->toBe($staff->id)
        ->and($request->remarks)->toBe('Verified the requirements.')
        ->and($history->from_status)->toBe(RequestStatus::Pending)
        ->and($history->to_status)->toBe(RequestStatus::Processing)
        ->and($history->changed_by_user_id)->toBe($staff->id)
        ->and($history->remarks)->toBe('Verified the requirements.');
});

test('moving to ready for release stamps the ready timestamp', function () {
    $request = DocumentRequest::factory()->processing()->create(['ready_at' => null]);

    transition($request, RequestStatus::ReadyForRelease, registrarStaff());

    expect($request->fresh()->ready_at)->not->toBeNull()
        ->and($request->fresh()->released_at)->toBeNull();
});

test('releasing stamps the released timestamp', function () {
    $request = DocumentRequest::factory()->readyForRelease()->create(['released_at' => null]);

    transition($request, RequestStatus::Released, registrarStaff());

    expect($request->fresh()->released_at)->not->toBeNull();
});

test('a full lifecycle leaves a complete audit trail', function () {
    $staff = registrarStaff();
    $request = DocumentRequest::factory()->pending()->create();

    transition($request, RequestStatus::Processing, $staff);
    transition($request, RequestStatus::ReadyForRelease, $staff);
    transition($request, RequestStatus::Released, $staff);

    $trail = $request->fresh()->statusHistories->pluck('to_status')->all();

    expect($trail)->toBe([
        RequestStatus::Processing,
        RequestStatus::ReadyForRelease,
        RequestStatus::Released,
    ]);
});

test('a student cannot drive a staff transition', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->pending()->create();

    expect(fn () => transition($request, RequestStatus::Processing, $user))
        ->toThrow(UnauthorizedException::class);
});

test('a student does not become the processor of their own request', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->pending()->create();

    transition($request, RequestStatus::Cancelled, $user);

    expect($request->fresh()->processed_by_user_id)->toBeNull();
});

test('every staff transition notifies the requesting student', function (RequestStatus $from, RequestStatus $to) {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->create(['status' => $from]);

    transition($request, $to, registrarStaff());

    expect($user->registrarNotifications()->count())->toBe(1);
})->with([
    [RequestStatus::Pending, RequestStatus::Processing],
    [RequestStatus::Processing, RequestStatus::ReadyForRelease],
    [RequestStatus::ReadyForRelease, RequestStatus::Released],
    [RequestStatus::Pending, RequestStatus::Rejected],
]);

test('a rejection is notified as a rejection', function () {
    $user = student();
    $request = DocumentRequest::factory()->for($user->student)->pending()->create();

    transition($request, RequestStatus::Rejected, registrarStaff(), 'Incomplete requirements.');

    expect($user->registrarNotifications()->first()->type)
        ->toBe(NotificationType::RequestRejected);
});

test('a failed transition writes no history', function () {
    $request = DocumentRequest::factory()->released()->create();
    $before = $request->statusHistories()->count();

    try {
        transition($request, RequestStatus::Pending, registrarStaff());
    } catch (UnauthorizedException) {
        // Expected.
    }

    expect($request->statusHistories()->count())->toBe($before);
});
