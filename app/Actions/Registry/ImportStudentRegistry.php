<?php

namespace App\Actions\Registry;

use App\Models\StudentRegistryEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class ImportStudentRegistry
{
    /**
     * The columns a registry export must provide, in any order.
     *
     * @var array<int, string>
     */
    private const REQUIRED_HEADERS = ['student_number', 'name', 'course'];

    /**
     * Other headings the registrar's own spreadsheets use for each column.
     *
     * Headings are compared after being lower-cased and having punctuation
     * and spacing collapsed to underscores, so "ID NO." arrives as "id_no".
     *
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'id' => 'student_number',
        'id_no' => 'student_number',
        'id_num' => 'student_number',
        'id_number' => 'student_number',
        'school_id' => 'student_number',
        'stud_no' => 'student_number',
        'student_no' => 'student_number',
        'student_id' => 'student_number',
        'student_id_no' => 'student_number',
        'student_id_number' => 'student_number',
        'full_name' => 'name',
        'student_name' => 'name',
        'complete_name' => 'name',
        'program' => 'course',
        'degree' => 'course',
        'graduation_year' => 'year_graduated',
        'year_of_graduation' => 'year_graduated',
    ];

    /**
     * Create the action.
     */
    public function __construct(private ReadSpreadsheetRows $readSpreadsheetRows) {}

    /**
     * Load a roster export into the student registry.
     *
     * The export may be a CSV file or an Excel workbook; only the first sheet
     * of a workbook is read. Columns other than the ones the registry keeps,
     * such as a print checklist, are ignored.
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
                $name = Str::squish($validated['name']);
                $course = Str::squish($validated['course']);

                $entry = StudentRegistryEntry::query()
                    ->where('student_number', $studentNumber)
                    ->first();

                if ($entry === null) {
                    StudentRegistryEntry::create([
                        'student_number' => $studentNumber,
                        'name' => $name,
                        'course' => $course,
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
                    'name' => $name,
                    'course' => $course,
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
     * The first row that is not blank is taken as the header row.
     *
     * @return array<int, array<string, string|null>>
     */
    private function readRows(string $absolutePath): array
    {
        $maxRows = (int) config('registrar.registry.max_import_rows');
        $columns = null;
        $rows = [];

        foreach (($this->readSpreadsheetRows)($absolutePath) as $line => $record) {
            if ($this->isBlank($record)) {
                continue;
            }

            if ($columns === null) {
                $columns = $this->columns($record);

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

        if ($columns === null) {
            throw new RuntimeException(__('The file is empty.'));
        }

        return $rows;
    }

    /**
     * Map the header row onto registry columns, keyed by column position.
     *
     * Columns with no heading are dropped, and a heading that repeats one
     * already seen is ignored, so the first matching column is the one used.
     *
     * @param  array<int, string|null>  $header
     * @return array<int, string>
     */
    private function columns(array $header): array
    {
        $columns = [];

        foreach ($header as $index => $heading) {
            $column = $this->normaliseHeading((string) $heading);

            if ($column === '' || in_array($column, $columns, true)) {
                continue;
            }

            $columns[$index] = $column;
        }

        $missing = array_diff(self::REQUIRED_HEADERS, $columns);

        if ($missing !== []) {
            throw new RuntimeException(__('The file is missing the :columns column(s). Expected a header row with: ID number, name, course, and optionally year graduated.', [
                'columns' => implode(', ', $missing),
            ]));
        }

        return $columns;
    }

    /**
     * Reduce a heading to the registry column it names.
     */
    private function normaliseHeading(string $heading): string
    {
        // A byte order mark from a spreadsheet export would otherwise hide
        // the first column name.
        $heading = str_replace("\u{FEFF}", '', $heading);
        $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii($heading))), '_');

        return self::HEADER_ALIASES[$key] ?? $key;
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
