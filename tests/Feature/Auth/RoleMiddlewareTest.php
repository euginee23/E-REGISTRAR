<?php

test('the student profile screen is restricted to students', function (string $helper, int $expected) {
    $this->actingAs($helper());

    $this->get(route('student-profile.edit'))->assertStatus($expected);
})->with([
    ['student', 200],
    ['alumnus', 200],
    ['registrarStaff', 403],
    ['administrator', 403],
]);

test('administrators are not handed staff-only role routes automatically', function () {
    // Gate::before grants administrators every policy check, but route-level
    // role restrictions stay explicit so areas remain separable.
    $this->actingAs(administrator());

    $this->get(route('student-profile.edit'))->assertForbidden();
});

test('guests are redirected away from role-restricted routes', function () {
    $this->get(route('student-profile.edit'))->assertRedirect(route('login'));
});

test('every role reaches the shared dashboard', function (string $helper) {
    $this->actingAs($helper());

    $this->get(route('dashboard'))->assertOk();
})->with(['student', 'alumnus', 'registrarStaff', 'administrator']);

test('every role reaches its own account settings', function (string $helper) {
    $this->actingAs($helper());

    $this->get(route('profile.edit'))->assertOk();
})->with(['student', 'registrarStaff', 'administrator']);
