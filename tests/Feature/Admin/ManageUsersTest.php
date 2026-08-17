<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Livewire\Livewire;

test('only administrators can open the user screen', function () {
    $this->actingAs(administrator());
    $this->get(route('admin.users.index'))->assertOk();

    $this->actingAs(registrarStaff());
    $this->get(route('admin.users.index'))->assertForbidden();

    $this->actingAs(student());
    $this->get(route('admin.users.index'))->assertForbidden();
});

test('an administrator can create a staff account', function () {
    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')
        ->call('createUser')
        ->set('name', 'New Registrar')
        ->set('email', 'new.staff@e-registrar.test')
        ->set('newRole', UserRole::RegistrarStaff->value)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('saveUser')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'new.staff@e-registrar.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::RegistrarStaff)
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->email_verified_at)->not->toBeNull();
});

test('a created account can sign in immediately', function () {
    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')
        ->call('createUser')
        ->set('name', 'New Registrar')
        ->set('email', 'new.staff@e-registrar.test')
        ->set('newRole', UserRole::RegistrarStaff->value)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('saveUser');

    $this->post(route('login.store'), [
        'email' => 'new.staff@e-registrar.test',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticated();

    // Verified straight away, so the dashboard is reachable without the
    // account first confirming an email it never received.
    $this->get(route('dashboard'))->assertOk();
});

test('a new account requires a password', function () {
    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')
        ->call('createUser')
        ->set('name', 'New Registrar')
        ->set('email', 'new.staff@e-registrar.test')
        ->set('password', '')
        ->call('saveUser')
        ->assertHasErrors('password');
});

test('email addresses cannot be duplicated', function () {
    $existing = registrarStaff();

    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')
        ->call('createUser')
        ->set('name', 'Someone Else')
        ->set('email', $existing->email)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('saveUser')
        ->assertHasErrors('email');
});

test('an administrator can edit an account without changing the password', function () {
    $staff = registrarStaff();
    $originalPassword = $staff->password;

    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')
        ->call('editUser', $staff->id)
        ->set('name', 'Renamed Staff')
        ->set('password', '')
        ->call('saveUser')
        ->assertHasNoErrors();

    $staff->refresh();

    expect($staff->name)->toBe('Renamed Staff')
        ->and($staff->password)->toBe($originalPassword);
});

test('an administrator can suspend and reactivate an account', function () {
    $staff = registrarStaff();

    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')
        ->call('toggleStatus', $staff->id);

    expect($staff->fresh()->status)->toBe(UserStatus::Suspended);

    Livewire::test('pages::admin.users')
        ->call('toggleStatus', $staff->id);

    expect($staff->fresh()->status)->toBe(UserStatus::Active);
});

test('an administrator cannot suspend themselves', function () {
    $admin = administrator();
    administrator();

    $this->actingAs($admin);

    Livewire::test('pages::admin.users')
        ->call('toggleStatus', $admin->id)
        ->assertForbidden();

    expect($admin->fresh()->status)->toBe(UserStatus::Active);
});

test('the last active administrator cannot be suspended', function () {
    $admin = administrator();

    $this->actingAs($admin);

    Livewire::test('pages::admin.users')
        ->call('toggleStatus', $admin->id)
        ->assertForbidden();

    expect(User::query()->where('role', UserRole::Administrator)->where('status', UserStatus::Active)->count())
        ->toBe(1);
});

test('an administrator cannot demote themselves', function () {
    $admin = administrator();

    $this->actingAs($admin);

    Livewire::test('pages::admin.users')
        ->call('editUser', $admin->id)
        ->set('newRole', UserRole::RegistrarStaff->value)
        ->call('saveUser')
        ->assertForbidden();

    expect($admin->fresh()->role)->toBe(UserRole::Administrator);
});

test('accounts can be filtered by role', function () {
    $staff = registrarStaff();
    $studentUser = student();

    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')
        ->set('role', UserRole::RegistrarStaff->value)
        ->assertSee($staff->email)
        ->assertDontSee($studentUser->email);
});

test('accounts can be searched by name', function () {
    $wanted = registrarStaff();
    $wanted->update(['name' => 'Distinctive Person']);
    $other = registrarStaff();

    $this->actingAs(administrator());

    Livewire::test('pages::admin.users')
        ->set('search', 'Distinctive')
        ->assertSee($wanted->email)
        ->assertDontSee($other->email);
});

test('a suspended staff member is turned away at the door', function () {
    $staff = registrarStaff();

    $this->actingAs(administrator());
    Livewire::test('pages::admin.users')->call('toggleStatus', $staff->id);

    $this->actingAs($staff->fresh());
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
