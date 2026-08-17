<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs(student());

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('each role sees its own dashboard', function (string $helper, string $marker) {
    $this->actingAs($helper());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee($marker, escape: false);
})->with([
    ['student', 'data-test="student-dashboard"'],
    ['registrarStaff', 'data-test="registrar-dashboard"'],
    ['administrator', 'data-test="admin-dashboard"'],
]);

test('a role never sees another role\'s dashboard', function () {
    $this->actingAs(student());

    $this->get(route('dashboard'))
        ->assertDontSee('data-test="admin-dashboard"', escape: false)
        ->assertDontSee('data-test="registrar-dashboard"', escape: false);
});

test('a student without a profile is prompted to complete it instead of erroring', function () {
    // Registration always creates a profile, but an administrator can create
    // a student account without one.
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Complete your student profile');
});
