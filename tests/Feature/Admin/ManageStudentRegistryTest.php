<?php

use App\Models\StudentRegistryEntry;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('an administrator can open the student registry', function () {
    $this->actingAs(administrator());

    $this->get(route('admin.student-registry.index'))->assertOk();
});

test('the student registry is closed to everyone else', function (string $helper) {
    $this->actingAs($helper());

    $this->get(route('admin.student-registry.index'))->assertForbidden();
})->with(['registrarStaff', 'student']);

test('guests are sent to log in', function () {
    $this->get(route('admin.student-registry.index'))->assertRedirect(route('login'));
});

test('an administrator can add a student to the roster', function () {
    $this->actingAs(administrator());

    Livewire::test('pages::admin.student-registry')
        ->call('createEntry')
        ->set('student_number', '2022-10231')
        ->set('name', 'Juan Dela Cruz')
        ->set('course', 'BS Information Technology')
        ->call('saveEntry')
        ->assertHasNoErrors();

    expect(StudentRegistryEntry::query()->where('student_number', '2022-10231')->exists())->toBeTrue();
});

test('a roster entry is stored with an upper-cased student number', function () {
    $this->actingAs(administrator());

    Livewire::test('pages::admin.student-registry')
        ->call('createEntry')
        ->set('student_number', ' a-2021-0007 ')
        ->set('name', 'Ana Lim')
        ->set('course', 'BS Nursing')
        ->call('saveEntry')
        ->assertHasNoErrors();

    expect(StudentRegistryEntry::query()->where('student_number', 'A-2021-0007')->exists())->toBeTrue();
});

test('a student number can only appear on the roster once', function () {
    registryEntry(['student_number' => '2022-10231']);

    $this->actingAs(administrator());

    Livewire::test('pages::admin.student-registry')
        ->call('createEntry')
        ->set('student_number', '2022-10231')
        ->set('name', 'Someone Else')
        ->set('course', 'BS Nursing')
        ->call('saveEntry')
        ->assertHasErrors('student_number');
});

test('an administrator can correct a roster entry', function () {
    $entry = registryEntry(['name' => 'Juan D. Cruz']);

    $this->actingAs(administrator());

    Livewire::test('pages::admin.student-registry')
        ->call('editEntry', $entry->id)
        ->assertSet('name', 'Juan D. Cruz')
        ->set('name', 'Juan Dela Cruz')
        ->call('saveEntry')
        ->assertHasNoErrors();

    expect($entry->refresh()->name)->toBe('Juan Dela Cruz');
});

test('an unclaimed entry can be removed', function () {
    $entry = registryEntry();

    $this->actingAs(administrator());

    Livewire::test('pages::admin.student-registry')
        ->call('deleteEntry', $entry->id);

    expect(StudentRegistryEntry::query()->whereKey($entry->id)->exists())->toBeFalse();
});

test('an entry backing a registered account cannot be removed', function () {
    $entry = registryEntry();
    $entry->forceFill(['claimed_by_user_id' => student()->id, 'claimed_at' => now()])->save();

    $this->actingAs(administrator());

    Livewire::test('pages::admin.student-registry')
        ->call('deleteEntry', $entry->id)
        ->assertForbidden();

    expect(StudentRegistryEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

test('the roster can be filtered by whether an account exists', function () {
    registryEntry(['student_number' => '2022-00001', 'name' => 'Unclaimed Student']);
    registryEntry(['student_number' => '2022-00002', 'name' => 'Registered Student'])
        ->forceFill(['claimed_by_user_id' => student()->id, 'claimed_at' => now()])
        ->save();

    $this->actingAs(administrator());

    Livewire::test('pages::admin.student-registry')
        ->set('claimed', 'unclaimed')
        ->assertSee('Unclaimed Student')
        ->assertDontSee('Registered Student')
        ->set('claimed', 'claimed')
        ->assertSee('Registered Student')
        ->assertDontSee('Unclaimed Student');
});

test('the roster can be searched by number, name, and course', function (string $term) {
    registryEntry(['student_number' => '2022-10231', 'name' => 'Juan Dela Cruz', 'course' => 'BS Information Technology']);
    registryEntry(['student_number' => '2019-55555', 'name' => 'Other Person', 'course' => 'BS Nursing']);

    $this->actingAs(administrator());

    Livewire::test('pages::admin.student-registry')
        ->set('search', $term)
        ->assertSee('Juan Dela Cruz')
        ->assertDontSee('Other Person');
})->with([
    'by number' => '2022-10231',
    'by name' => 'Dela Cruz',
    'by course' => 'Information Technology',
]);

test('an administrator can import a roster export', function () {
    $this->actingAs(administrator());

    $csv = UploadedFile::fake()->createWithContent('roster.csv', <<<'CSV'
    student_number,name,course,year_graduated
    2022-10231,Juan Dela Cruz,BS Information Technology,
    2018-00457,Maria Santos,BS Business Administration,2022
    CSV);

    Livewire::test('pages::admin.student-registry')
        ->set('csv', $csv)
        ->call('importCsv')
        ->assertHasNoErrors();

    expect(StudentRegistryEntry::query()->count())->toBe(2);
});

test('an unreadable import reports the problem instead of failing', function () {
    $this->actingAs(administrator());

    $csv = UploadedFile::fake()->createWithContent('roster.csv', "student_number,course\n2022-10231,BS IT");

    Livewire::test('pages::admin.student-registry')
        ->set('csv', $csv)
        ->call('importCsv')
        ->assertHasErrors('csv');

    expect(StudentRegistryEntry::query()->count())->toBe(0);
});
