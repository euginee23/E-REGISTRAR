<?php

use App\Actions\Reports\BuildNavBadgeCounts;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->counts = app(BuildNavBadgeCounts::class);
});

test('the counts are cached per user', function () {
    $staff = registrarStaff();

    DocumentRequest::factory()->create(['status' => RequestStatus::Pending]);

    expect(($this->counts)($staff)['pendingRequests'])->toBe(1)
        ->and(Cache::has('nav-badges:'.$staff->id))->toBeTrue();

    // A second request arriving is not reflected until the cache expires,
    // which is the trade this cache deliberately makes.
    DocumentRequest::factory()->create(['status' => RequestStatus::Pending]);

    expect(($this->counts)($staff)['pendingRequests'])->toBe(1);
});

test('each user gets their own cached counts', function () {
    $student = student();
    $staff = registrarStaff();

    DocumentRequest::factory()->for($student->student)->create();

    expect(($this->counts)($student)['openRequests'])->toBe(1)
        ->and(($this->counts)($staff)['openRequests'])->toBe(0)
        ->and(($this->counts)($staff)['pendingRequests'])->toBe(1);
});

test('the payload always carries every key', function (string $helper) {
    $badges = ($this->counts)($helper());

    expect(array_keys($badges))->toEqualCanonicalizing([
        'pendingRequests',
        'appointmentsToday',
        'pendingAccounts',
        'openRequests',
        'unreadNotifications',
    ]);
})->with(['administrator', 'registrarStaff', 'student']);

test('cancelled and finished requests never count as open for a student', function () {
    $user = student();

    DocumentRequest::factory()->for($user->student)->create();
    DocumentRequest::factory()->for($user->student)->cancelled()->create();
    DocumentRequest::factory()->for($user->student)->rejected()->create();
    DocumentRequest::factory()->for($user->student)->released()->create();

    expect(($this->counts)($user)['openRequests'])->toBe(1);
});
