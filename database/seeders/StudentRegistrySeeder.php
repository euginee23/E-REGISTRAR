<?php

namespace Database\Seeders;

use App\Models\StudentRegistryEntry;
use Illuminate\Database\Seeder;

class StudentRegistrySeeder extends Seeder
{
    /**
     * Seed the roster registration is checked against.
     *
     * The first two entries back the demo student accounts; the rest give the
     * registrar something to search, edit and register against without
     * needing a CSV import first.
     *
     * @var array<int, array{student_number: string, name: string, course: string, year_graduated: int|null}>
     */
    private const ENTRIES = [
        ['student_number' => '2022-10231', 'name' => 'Juan Dela Cruz', 'course' => 'BS Information Technology', 'year_graduated' => null],
        ['student_number' => '2018-00457', 'name' => 'Maria Santos', 'course' => 'BS Business Administration', 'year_graduated' => 2022],
        ['student_number' => '2023-11002', 'name' => 'Angelo Reyes', 'course' => 'BS Nursing', 'year_graduated' => null],
        ['student_number' => '2023-11045', 'name' => 'Bea Mendoza', 'course' => 'BS Education', 'year_graduated' => null],
        ['student_number' => '2021-09876', 'name' => 'Carlo Villanueva', 'course' => 'BS Accountancy', 'year_graduated' => null],
        ['student_number' => '2019-00123', 'name' => 'Divina Lim', 'course' => 'BS Psychology', 'year_graduated' => 2023],
    ];

    /**
     * Seed the student registry.
     *
     * Claim details are deliberately left out of the update payload so
     * re-running the seeder never detaches an account from its entry.
     */
    public function run(): void
    {
        foreach (self::ENTRIES as $entry) {
            StudentRegistryEntry::query()->updateOrCreate(
                ['student_number' => $entry['student_number']],
                [
                    'name' => $entry['name'],
                    'course' => $entry['course'],
                    'year_graduated' => $entry['year_graduated'],
                ],
            );
        }
    }
}
