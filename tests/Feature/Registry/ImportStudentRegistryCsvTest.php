<?php

use App\Actions\Registry\ImportStudentRegistryCsv;
use App\Models\StudentRegistryEntry;

/**
 * Write a CSV to a temporary file and return its path.
 */
function registryCsv(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'registry').'.csv';
    file_put_contents($path, $contents);

    return $path;
}

beforeEach(function () {
    $this->import = app(ImportStudentRegistryCsv::class);
});

test('a roster export is loaded into the registry', function () {
    $result = ($this->import)(registryCsv(<<<'CSV'
    student_number,name,course,year_graduated
    2022-10231,Juan Dela Cruz,BS Information Technology,
    2018-00457,Maria Santos,BS Business Administration,2022
    CSV));

    expect($result['imported'])->toBe(2)
        ->and($result['updated'])->toBe(0)
        ->and($result['skipped'])->toBe(0)
        ->and($result['errors'])->toBe([]);

    $entry = StudentRegistryEntry::query()->where('student_number', '2018-00457')->firstOrFail();

    expect($entry->name)->toBe('Maria Santos')
        ->and($entry->year_graduated)->toBe(2022);
});

test('an existing unclaimed number is corrected rather than duplicated', function () {
    registryEntry(['student_number' => '2022-10231', 'name' => 'Juan D. Cruz', 'course' => 'BS Nursing']);

    $result = ($this->import)(registryCsv(<<<'CSV'
    student_number,name,course,year_graduated
    2022-10231,Juan Dela Cruz,BS Information Technology,
    CSV));

    expect($result['updated'])->toBe(1)
        ->and($result['imported'])->toBe(0)
        ->and(StudentRegistryEntry::query()->count())->toBe(1);

    $entry = StudentRegistryEntry::query()->firstOrFail();

    expect($entry->name)->toBe('Juan Dela Cruz')
        ->and($entry->course)->toBe('BS Information Technology');
});

test('an entry that already has an account is left untouched', function () {
    $user = student();
    $entry = registryEntry(['student_number' => '2022-10231', 'name' => 'Juan Dela Cruz']);
    $entry->forceFill(['claimed_by_user_id' => $user->id, 'claimed_at' => now()])->save();

    $result = ($this->import)(registryCsv(<<<'CSV'
    student_number,name,course,year_graduated
    2022-10231,Someone Else,BS Nursing,
    CSV));

    expect($result['skipped'])->toBe(1)
        ->and($result['errors'])->toHaveCount(1);

    $entry->refresh();

    expect($entry->name)->toBe('Juan Dela Cruz')
        ->and($entry->claimed_by_user_id)->toBe($user->id);
});

test('the header row is matched regardless of casing and spacing', function () {
    $result = ($this->import)(registryCsv(<<<'CSV'
    Student Number, Name , COURSE ,Year Graduated
    2022-10231,Juan Dela Cruz,BS Information Technology,
    CSV));

    expect($result['imported'])->toBe(1);
});

test('a file missing a required column is rejected outright', function () {
    expect(fn () => ($this->import)(registryCsv(<<<'CSV'
    student_number,course
    2022-10231,BS Information Technology
    CSV)))->toThrow(RuntimeException::class);

    expect(StudentRegistryEntry::query()->count())->toBe(0);
});

test('an invalid row is reported and the rest still import', function () {
    $result = ($this->import)(registryCsv(<<<'CSV'
    student_number,name,course,year_graduated
    2022-10231,Juan Dela Cruz,BS Information Technology,
    ,Missing Number,BS Nursing,
    2023-11002,Angelo Reyes,BS Nursing,1800
    CSV));

    expect($result['imported'])->toBe(1)
        ->and($result['skipped'])->toBe(2)
        ->and($result['errors'])->toHaveCount(2);

    expect(StudentRegistryEntry::query()->count())->toBe(1);
});

test('student numbers are stored upper-cased and trimmed', function () {
    ($this->import)(registryCsv(<<<'CSV'
    student_number,name,course,year_graduated
    " a-2021-0007 ",Ana Lim,BS Nursing,
    CSV));

    expect(StudentRegistryEntry::query()->where('student_number', 'A-2021-0007')->exists())->toBeTrue();
});

test('blank lines are ignored', function () {
    $result = ($this->import)(registryCsv(
        "student_number,name,course,year_graduated\n2022-10231,Juan Dela Cruz,BS IT,\n\n,,,\n"
    ));

    expect($result['imported'])->toBe(1)
        ->and($result['skipped'])->toBe(0);
});

test('a file larger than the configured cap is refused', function () {
    config(['registrar.registry.max_import_rows' => 2]);

    $rows = collect(range(1, 5))
        ->map(fn (int $index) => "2022-1000{$index},Student {$index},BS IT,")
        ->implode("\n");

    expect(fn () => ($this->import)(registryCsv("student_number,name,course,year_graduated\n".$rows)))
        ->toThrow(RuntimeException::class);

    expect(StudentRegistryEntry::query()->count())->toBe(0);
});
