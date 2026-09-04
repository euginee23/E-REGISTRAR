<?php

use App\Models\DocumentRequest;

test('a student without a profile is redirected out of the student area', function (string $route) {
    $this->actingAs(studentWithoutProfile());

    $this->get($route)
        ->assertRedirect(route('student-profile.edit'))
        ->assertSessionHas('status', 'student-profile-required');
})->with(fn () => [
    route('student.requests.index'),
    route('student.requests.create'),
    route('student.appointments.index'),
]);

test('a student without a profile is redirected away from a single request', function () {
    $documentRequest = DocumentRequest::factory()->create();

    $this->actingAs(studentWithoutProfile());

    $this->get(route('student.requests.show', $documentRequest))
        ->assertRedirect(route('student-profile.edit'));
});

test('a student with a profile still reaches the student area', function () {
    $this->actingAs(student());

    $this->get(route('student.requests.index'))->assertOk();
});

test('the dashboard still loads for a student without a profile', function () {
    $this->actingAs(studentWithoutProfile());

    $this->get(route('dashboard'))->assertOk();
});
