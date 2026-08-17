<?php

use App\Enums\EnrollmentStatus;
use Livewire\Livewire;

test('the student profile screen renders with the current details', function () {
    $user = student();
    $user->student->update([
        'course' => 'BS Information Technology',
        'contact_number' => '09171234567',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::settings.student-profile')
        ->assertOk()
        ->assertSet('course', 'BS Information Technology')
        ->assertSet('contact_number', '09171234567');
});

test('a student can update their profile', function () {
    $user = student();
    $this->actingAs($user);

    Livewire::test('pages::settings.student-profile')
        ->set('course', 'BS Computer Science')
        ->set('contact_number', '09998887777')
        ->set('student_number', '2023-00042')
        ->call('updateStudentProfile')
        ->assertHasNoErrors();

    $student = $user->student->fresh();

    expect($student->course)->toBe('BS Computer Science')
        ->and($student->contact_number)->toBe('09998887777')
        ->and($student->student_number)->toBe('2023-00042');
});

test('switching to alumnus requires a graduation year', function () {
    $this->actingAs(student());

    Livewire::test('pages::settings.student-profile')
        ->set('enrollment_status', EnrollmentStatus::Alumnus->value)
        ->set('year_graduated', null)
        ->call('updateStudentProfile')
        ->assertHasErrors('year_graduated');
});

test('switching back to enrolled clears the graduation year', function () {
    $user = alumnus();
    $this->actingAs($user);

    Livewire::test('pages::settings.student-profile')
        ->set('enrollment_status', EnrollmentStatus::Enrolled->value)
        ->call('updateStudentProfile')
        ->assertHasNoErrors();

    expect($user->student->fresh()->year_graduated)->toBeNull();
});

test('a student keeps their own student number when saving', function () {
    $user = student();
    $user->student->update(['student_number' => '2022-10231']);

    $this->actingAs($user);

    Livewire::test('pages::settings.student-profile')
        ->set('student_number', '2022-10231')
        ->call('updateStudentProfile')
        ->assertHasNoErrors();
});

test('a student cannot take another student\'s number', function () {
    student()->student->update(['student_number' => '2022-10231']);

    $this->actingAs(student());

    Livewire::test('pages::settings.student-profile')
        ->set('student_number', '2022-10231')
        ->call('updateStudentProfile')
        ->assertHasErrors('student_number');
});

test('the course and contact number are required', function (string $field) {
    $this->actingAs(student());

    Livewire::test('pages::settings.student-profile')
        ->set($field, '')
        ->call('updateStudentProfile')
        ->assertHasErrors($field);
})->with(['course', 'contact_number']);

test('staff never see the student profile link in settings', function () {
    $this->actingAs(registrarStaff());

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee(route('student-profile.edit'));
});

test('students see the student profile link in settings', function () {
    $this->actingAs(student());

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee(route('student-profile.edit'));
});
