<?php

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
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
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('registering creates an active student account with a profile', function () {
    $this->post(route('register.store'), registrationPayload([
        'student_number' => '2022-10231',
    ]));

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Student)
        ->and($user->status)->toBe(UserStatus::Active)
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
        ->and($user->status)->toBe(UserStatus::Active);
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
})->with(['course', 'enrollment_status', 'contact_number']);

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
