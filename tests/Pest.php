<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Enum tests are pure logic but their labels resolve through the translator,
// so they need a booted application - though not a database.
pest()->extend(TestCase::class)->in('Unit/Enums');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create an administrator account.
 */
function administrator(): User
{
    return User::factory()->administrator()->create();
}

/**
 * Create a registrar staff account.
 */
function registrarStaff(): User
{
    return User::factory()->registrarStaff()->create();
}

/**
 * Create a student account with an attached student profile.
 */
function student(): User
{
    return User::factory()->student()->create();
}

/**
 * Create an alumnus account with an attached student profile.
 */
function alumnus(): User
{
    return User::factory()->alumnus()->create();
}
