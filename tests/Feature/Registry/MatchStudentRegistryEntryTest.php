<?php

use App\Actions\Registry\MatchStudentRegistryEntry;
use App\Exceptions\StudentRegistryMismatchException;

beforeEach(function () {
    $this->match = app(MatchStudentRegistryEntry::class);
});

test('an exact student number and name find the roster entry', function () {
    $entry = registryEntry(['student_number' => '2022-10231', 'name' => 'Juan Dela Cruz']);

    expect(($this->match)('2022-10231', 'Juan Dela Cruz')->id)->toBe($entry->id);
});

test('a name written in a different word order still matches', function () {
    registryEntry(['student_number' => '2022-10231', 'name' => 'Dela Cruz, Juan']);

    expect(($this->match)('2022-10231', 'Juan Dela Cruz'))->not->toBeNull();
});

test('a middle name the roster omits does not block the match', function () {
    registryEntry(['student_number' => '2022-10231', 'name' => 'Juan Dela Cruz']);

    expect(($this->match)('2022-10231', 'Juan Pablo Dela Cruz'))->not->toBeNull();
});

test('casing and punctuation are ignored when matching a name', function (string $submitted) {
    registryEntry(['student_number' => '2022-10231', 'name' => "Ma. Teresa O'Brien-Santos"]);

    expect(($this->match)('2022-10231', $submitted))->not->toBeNull();
})->with([
    'lower case' => "ma teresa o'brien santos",
    'separators swapped' => 'Ma Teresa O-Brien Santos',
    'extra spacing' => "  Ma.   Teresa   O'Brien-Santos  ",
]);

test('fusing two words of a name is not accepted as a match', function () {
    // Deliberately strict: the words of the submitted name must be the words
    // the roster holds. A student who mistypes a hyphenated name is told so
    // and the registrar can correct the roster, which is safer than loosening
    // the check that keeps one student off another's number.
    registryEntry(['student_number' => '2022-10231', 'name' => "Teresa O'Brien-Santos"]);

    expect(fn () => ($this->match)('2022-10231', 'Teresa OBrienSantos'))
        ->toThrow(StudentRegistryMismatchException::class);
});

test('a student number is matched after trimming and upper-casing', function () {
    $entry = registryEntry(['student_number' => 'A-2021-0007', 'name' => 'Ana Lim']);

    expect(($this->match)('  a-2021-0007 ', 'Ana Lim')->id)->toBe($entry->id);
});

test('a student number the roster does not hold is rejected', function () {
    expect(fn () => ($this->match)('1999-99999', 'Juan Dela Cruz'))
        ->toThrow(StudentRegistryMismatchException::class);
});

test('a name that does not belong to the number is rejected', function () {
    registryEntry(['student_number' => '2022-10231', 'name' => 'Maria Dela Cruz']);

    expect(fn () => ($this->match)('2022-10231', 'Juan Dela Cruz'))
        ->toThrow(StudentRegistryMismatchException::class);
});

test('a partial name is not enough to claim a number', function () {
    registryEntry(['student_number' => '2022-10231', 'name' => 'Juan Dela Cruz']);

    expect(fn () => ($this->match)('2022-10231', 'Juan'))
        ->toThrow(StudentRegistryMismatchException::class);
});

test('an entry that already has an account is rejected', function () {
    registryEntry(['student_number' => '2022-10231', 'name' => 'Juan Dela Cruz'])
        ->forceFill(['claimed_by_user_id' => student()->id, 'claimed_at' => now()])
        ->save();

    expect(fn () => ($this->match)('2022-10231', 'Juan Dela Cruz'))
        ->toThrow(StudentRegistryMismatchException::class);
});

test('a blank name never matches', function () {
    registryEntry(['student_number' => '2022-10231', 'name' => 'Juan Dela Cruz']);

    expect(fn () => ($this->match)('2022-10231', '   '))
        ->toThrow(StudentRegistryMismatchException::class);
});

test('the rejection message never reveals the name on the roster', function () {
    registryEntry(['student_number' => '2022-10231', 'name' => 'Maria Dela Cruz']);

    try {
        ($this->match)('2022-10231', 'Juan Dela Cruz');
    } catch (StudentRegistryMismatchException $exception) {
        expect($exception->getMessage())->not->toContain('Maria');

        return;
    }

    $this->fail('The mismatch was not rejected.');
});
