<?php

use App\Actions\Fortify\CreateNewUser;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\StudentRegistryEntry;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());

    // Registration is checked against the registrar's roster, so every valid
    // payload needs the matching entry to exist and be unclaimed.
    $this->registryEntry = registryEntry([
        'student_number' => '2022-10231',
        'name' => 'John Doe',
    ]);
});

/**
 * Build a valid registration payload, overriding any field.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'student_number' => '2022-10231',
        'course' => 'BS Information Technology',
        'enrollment_status' => EnrollmentStatus::Enrolled->value,
        'contact_number' => '09171234567',
        ...$overrides,
    ];
}

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), registrationPayload());

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('login', absolute: false));

    // Registering no longer signs the student in: the account waits for the
    // registrar to review it first.
    $this->assertGuest();
    $response->assertSessionHas('status');
});

test('registering creates a pending student account with a profile', function () {
    $this->post(route('register.store'), registrationPayload());

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Student)
        ->and($user->status)->toBe(UserStatus::Pending)
        ->and($user->student)->not->toBeNull()
        ->and($user->student->course)->toBe('BS Information Technology')
        ->and($user->student->student_number)->toBe('2022-10231')
        ->and($user->student->enrollment_status)->toBe(EnrollmentStatus::Enrolled);
});

test('registration cannot be used to create a privileged account', function (string $role) {
    $this->post(route('register.store'), registrationPayload([
        'role' => $role,
        'status' => UserStatus::Suspended->value,
    ]));

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Student)
        ->and($user->status)->toBe(UserStatus::Pending);
})->with([
    UserRole::Administrator->value,
    UserRole::RegistrarStaff->value,
]);

test('alumni must supply the year they graduated', function () {
    $response = $this->post(route('register.store'), registrationPayload([
        'enrollment_status' => EnrollmentStatus::Alumnus->value,
        'year_graduated' => null,
    ]));

    $response->assertSessionHasErrors('year_graduated');
    $this->assertGuest();
});

test('alumni registrations record the graduation year', function () {
    $this->post(route('register.store'), registrationPayload([
        'enrollment_status' => EnrollmentStatus::Alumnus->value,
        'year_graduated' => 2022,
    ]));

    $student = User::query()->where('email', 'test@example.com')->firstOrFail()->student;

    expect($student->enrollment_status)->toBe(EnrollmentStatus::Alumnus)
        ->and($student->year_graduated)->toBe(2022);
});

test('an enrolled student never stores a graduation year', function () {
    $this->post(route('register.store'), registrationPayload([
        'enrollment_status' => EnrollmentStatus::Enrolled->value,
        'year_graduated' => 2022,
    ]));

    $student = User::query()->where('email', 'test@example.com')->firstOrFail()->student;

    expect($student->year_graduated)->toBeNull();
});

test('the student profile fields are required', function (string $field) {
    $response = $this->post(route('register.store'), registrationPayload([$field => null]));

    $response->assertSessionHasErrors($field);
    expect(User::query()->count())->toBe(0);
})->with(['student_number', 'course', 'enrollment_status', 'contact_number']);

test('student numbers cannot be claimed twice', function () {
    student()->student->update(['student_number' => '2022-10231']);

    $response = $this->post(route('register.store'), registrationPayload([
        'student_number' => '2022-10231',
    ]));

    $response->assertSessionHasErrors('student_number');
});

test('a failed registration creates no orphaned user', function () {
    $this->post(route('register.store'), registrationPayload(['course' => null]));

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('a student number the registrar has no record of is refused', function () {
    $response = $this->post(route('register.store'), registrationPayload([
        'student_number' => '1999-99999',
    ]));

    $response->assertSessionHasErrors('student_number');
    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('a student number cannot be claimed under somebody else\'s name', function () {
    $response = $this->post(route('register.store'), registrationPayload([
        'name' => 'Impostor Cruz',
    ]));

    $response->assertSessionHasErrors('student_number');
    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('a registry entry that already has an account cannot be registered again', function () {
    $this->registryEntry->forceFill([
        'claimed_by_user_id' => student()->id,
        'claimed_at' => now(),
    ])->save();

    $response = $this->post(route('register.store'), registrationPayload());

    $response->assertSessionHasErrors('student_number');
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('registering claims the roster entry for the new account', function () {
    $this->post(route('register.store'), registrationPayload());

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();
    $entry = $this->registryEntry->refresh();

    expect($entry->claimed_by_user_id)->toBe($user->id)
        ->and($entry->claimed_at)->not->toBeNull();
});

test('a failed registration leaves the roster entry unclaimed', function () {
    $this->post(route('register.store'), registrationPayload(['course' => null]));

    expect($this->registryEntry->refresh()->isClaimed())->toBeFalse();
});

test('the name is matched against the roster regardless of word order and casing', function (string $name) {
    registryEntry(['student_number' => '2020-00500', 'name' => 'Dela Cruz, Juan']);

    $response = $this->post(route('register.store'), registrationPayload([
        'name' => $name,
        'student_number' => '2020-00500',
        'email' => 'juan@example.com',
    ]));

    $response->assertSessionHasNoErrors();
    expect(User::query()->where('email', 'juan@example.com')->exists())->toBeTrue();
})->with([
    'same words, different order' => 'Juan Dela Cruz',
    'different casing' => 'juan dela cruz',
    'with a middle name the roster omits' => 'Juan Pablo Dela Cruz',
]);

test('a student number is matched after trimming and upper-casing', function () {
    registryEntry(['student_number' => 'A-2021-0007', 'name' => 'Ana Lim']);

    $response = $this->post(route('register.store'), registrationPayload([
        'name' => 'Ana Lim',
        'student_number' => '  a-2021-0007  ',
        'email' => 'ana@example.com',
    ]));

    $response->assertSessionHasNoErrors();

    $student = User::query()->where('email', 'ana@example.com')->firstOrFail()->student;

    expect($student->student_number)->toBe('A-2021-0007');
});

test('two registrations cannot both claim the same roster entry', function () {
    // Driven through the action rather than the route: the register route is
    // guest-only, so a second request would be redirected rather than
    // validated, and the guard being tested here would never run.
    $createNewUser = app(CreateNewUser::class);

    $createNewUser->create(registrationPayload());

    expect(fn () => $createNewUser->create(registrationPayload(['email' => 'second@example.com'])))
        ->toThrow(ValidationException::class);

    expect(User::query()->where('email', 'second@example.com')->exists())->toBeFalse()
        ->and(StudentRegistryEntry::query()->claimed()->count())->toBe(1);
});
