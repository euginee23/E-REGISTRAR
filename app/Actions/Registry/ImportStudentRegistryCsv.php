<?php

namespace App\Actions\Registry;

use App\Models\StudentRegistryEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class ImportStudentRegistryCsv
{
    /**
     * The columns a registry export must provide, in any order.
     *
     * @var array<int, string>
     */
    private const REQUIRED_HEADERS = ['student_number', 'name', 'course'];

    /**
     * Load a roster export into the student registry.
     *
     * Rows already claimed by an account are reported as skipped rather than
     * rewritten: the roster is the record registration was checked against,
     * so a later import must never silently move an existing account onto
     * different details.
     *
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function __invoke(string $absolutePath): array
    {
        $rows = $this->readRows($absolutePath);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rows, &$imported, &$updated, &$skipped, &$errors): void {
            foreach ($rows as $line => $row) {
                $validator = Validator::make($row, [
                    'student_number' => ['required', 'string', 'max:32'],
                    'name' => ['required', 'string', 'max:255'],
                    'course' => ['required', 'string', 'max:150'],
                    'year_graduated' => ['nullable', 'integer', 'min:1950', 'max:'.date('Y')],
                ]);

                if ($validator->fails()) {
                    $skipped++;
                    $errors[] = __('Row :line: :message', [
                        'line' => $line,
                        'message' => (string) $validator->errors()->first(),
                    ]);

                    continue;
                }

                $validated = $validator->validated();
                $studentNumber = Str::upper(trim((string) $validated['student_number']));

                $entry = StudentRegistryEntry::query()
                    ->where('student_number', $studentNumber)
                    ->first();

                if ($entry === null) {
                    StudentRegistryEntry::create([
                        'student_number' => $studentNumber,
                        'name' => $validated['name'],
                        'course' => $validated['course'],
                        'year_graduated' => $validated['year_graduated'] ?? null,
                    ]);

                    $imported++;

                    continue;
                }

                if ($entry->isClaimed()) {
                    $skipped++;
                    $errors[] = __('Row :line: :number already has a registered account and was left untouched.', [
                        'line' => $line,
                        'number' => $studentNumber,
                    ]);

                    continue;
                }

                $entry->update([
                    'name' => $validated['name'],
                    'course' => $validated['course'],
                    'year_graduated' => $validated['year_graduated'] ?? null,
                ]);

                $updated++;
            }
        });

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Read the file into header-keyed rows, keyed by their line number.
     *
     * @return array<int, array<string, string|null>>
     */
    private function readRows(string $absolutePath): array
    {
        $handle = @fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new RuntimeException(__('The uploaded file could not be read.'));
        }

        try {
            $header = fgetcsv($handle, escape: '');

            if ($header === false) {
                throw new RuntimeException(__('The file is empty.'));
            }

            $columns = array_map(
                fn ($column): string => Str::snake(Str::squish(Str::lower((string) $column))),
                $header,
            );

            // A byte order mark from a spreadsheet export would otherwise
            // hide the first column name.
            $columns[0] = ltrim($columns[0], "\u{FEFF}");

            $missing = array_diff(self::REQUIRED_HEADERS, $columns);

            if ($missing !== []) {
                throw new RuntimeException(__('The file is missing the :columns column(s). Expected a header row of: :expected.', [
                    'columns' => implode(', ', $missing),
                    'expected' => implode(', ', [...self::REQUIRED_HEADERS, 'year_graduated']),
                ]));
            }

            $maxRows = (int) config('registrar.registry.max_import_rows');
            $rows = [];
            $line = 1;

            while (($record = fgetcsv($handle, escape: '')) !== false) {
                $line++;

                if ($record === [null] || $this->isBlank($record)) {
                    continue;
                }

                if (count($rows) >= $maxRows) {
                    throw new RuntimeException(__('The file holds more than :max rows. Split it and import in batches.', [
                        'max' => $maxRows,
                    ]));
                }

                $row = [];

                foreach ($columns as $index => $column) {
                    $value = $record[$index] ?? null;
                    $value = is_string($value) ? trim($value) : $value;
                    $row[$column] = ($value === '' ? null : $value);
                }

                $rows[$line] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Determine whether a parsed record holds nothing but empty cells.
     *
     * @param  array<int, string|null>  $record
     */
    private function isBlank(array $record): bool
    {
        foreach ($record as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }
}
