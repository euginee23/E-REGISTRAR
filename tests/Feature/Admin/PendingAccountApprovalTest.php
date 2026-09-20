<?php

use App\Actions\Users\ApproveStudentAccount;
use App\Enums\UserStatus;
use App\Mail\AccountApprovedMail;
use App\Mail\AccountRejectedMail;
use App\Models\StudentRegistryEntry;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\UnauthorizedException;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
});

/**
 * Create a student account waiting on review, with its roster entry claimed.
 */
function pendingAccount(array $attributes = []): User
{
    $user = User::factory()->student()->create([
        'status' => UserStatus::Pending,
        ...$attributes,
    ]);

    StudentRegistryEntry::factory()->create([
        'student_number' => $user->student->student_number,
        'name' => $user->name,
        'claimed_by_user_id' => $user->id,
        'claimed_at' => now(),
    ]);

    return $user;
}

test('registrar staff can open the pending accounts screen', function (string $helper) {
    $this->actingAs($helper());

    $this->get(route('registrar.pending-accounts.index'))->assertOk();
})->with(['registrarStaff', 'administrator']);

test('students cannot open the pending accounts screen', function () {
    $this->actingAs(student());

    $this->get(route('registrar.pending-accounts.index'))->assertForbidden();
});

test('only accounts awaiting review are listed', function () {
    $pending = pendingAccount(['name' => 'Waiting Student']);
    $active = User::factory()->student()->create(['name' => 'Existing Student']);

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.pending-accounts')
        ->assertSee($pending->name)
        ->assertDontSee($active->name);
});

test('the reviewer sees the submitted details beside the registry record', function () {
    $user = pendingAccount();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.pending-accounts')
        ->assertSee($user->email)
        ->assertSee($user->student->student_number)
        ->assertSeeHtml('data-test="registry-record"');
});

test('approving an account lets the student sign in', function () {
    $user = pendingAccount();
    $staff = registrarStaff();

    $this->actingAs($staff);

    Livewire::test('pages::registrar.pending-accounts')
        ->call('approve', $user->id);

    $user->refresh();

    expect($user->status)->toBe(UserStatus::Active)
        ->and($user->approved_by_user_id)->toBe($staff->id)
        ->and($user->approved_at)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();

    Mail::assertQueued(AccountApprovedMail::class, fn ($mail) => $mail->hasTo($user->email));
});

test('an approved student can actually log in afterwards', function () {
    $user = pendingAccount();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.pending-accounts')->call('approve', $user->id);

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user->fresh());
});

test('turning an account away requires a reason', function () {
    $user = pendingAccount();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.pending-accounts')
        ->call('startRejection', $user->id)
        ->set('reason', '')
        ->call('reject')
        ->assertHasErrors('reason');

    expect($user->refresh()->status)->toBe(UserStatus::Pending);
});

test('turning an account away records the reason and emails the student', function () {
    $user = pendingAccount();
    $staff = registrarStaff();

    $this->actingAs($staff);

    Livewire::test('pages::registrar.pending-accounts')
        ->call('startRejection', $user->id)
        ->set('reason', 'The student number belongs to a different person.')
        ->call('reject')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->status)->toBe(UserStatus::Inactive)
        ->and($user->rejection_reason)->toBe('The student number belongs to a different person.')
        ->and($user->approved_by_user_id)->toBe($staff->id);

    Mail::assertQueued(AccountRejectedMail::class, fn ($mail) => $mail->hasTo($user->email));
});

test('turning an account away frees its roster entry for a fresh registration', function () {
    $user = pendingAccount();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.pending-accounts')
        ->call('startRejection', $user->id)
        ->set('reason', 'Mistyped name.')
        ->call('reject');

    $entry = StudentRegistryEntry::query()
        ->where('student_number', $user->student->student_number)
        ->firstOrFail();

    expect($entry->isClaimed())->toBeFalse()
        ->and($entry->claimed_at)->toBeNull();
});

test('an account that is not pending cannot be approved', function () {
    $user = User::factory()->student()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.pending-accounts')
        ->call('approve', $user->id)
        ->assertForbidden();
});

test('a student cannot approve their own account through the action', function () {
    $user = pendingAccount();

    expect(fn () => app(ApproveStudentAccount::class)($user, $user))
        ->toThrow(UnauthorizedException::class);

    expect($user->refresh()->status)->toBe(UserStatus::Pending);
});

test('the pending list can be searched by student number', function () {
    $wanted = pendingAccount(['name' => 'Wanted Student']);
    pendingAccount(['name' => 'Other Student']);

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.pending-accounts')
        ->set('search', $wanted->student->student_number)
        ->assertSee('Wanted Student')
        ->assertDontSee('Other Student');
});

test('a pending account cannot be activated from the user management screen', function () {
    $user = pendingAccount();

    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')
        ->call('toggleStatus', $user->id)
        ->assertForbidden();

    expect($user->refresh()->status)->toBe(UserStatus::Pending);
});
