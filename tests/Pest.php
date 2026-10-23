<?php

use App\Models\StudentRegistryEntry;
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
 * Create a student account that has no student profile yet, as happens when
 * an administrator creates the account instead of the student registering.
 */
function studentWithoutProfile(): User
{
    return User::factory()->create();
}

/**
 * Create an alumnus account with an attached student profile.
 */
function alumnus(): User
{
    return User::factory()->alumnus()->create();
}

/**
 * Create an entry on the registrar's student roster.
 *
 * Registration is checked against this roster, so a test that registers an
 * account needs a matching unclaimed entry first.
 *
 * @param  array<string, mixed>  $attributes
 */
function registryEntry(array $attributes = []): StudentRegistryEntry
{
    return StudentRegistryEntry::factory()->create($attributes);
}

/**
 * Build a minimal .xlsx workbook and return its contents.
 *
 * Text cells go through the shared string table and whole numbers are
 * written as numeric cells, the way Excel itself saves them. A null cell is
 * left out of the row entirely, as Excel does for empty cells.
 *
 * @param  array<int, array<int, string|int|null>>  $rows
 */
function xlsxWorkbook(array $rows): string
{
    $strings = [];
    $sheetRows = '';

    foreach (array_values($rows) as $rowIndex => $cells) {
        $rowNumber = $rowIndex + 1;
        $sheetRows .= '<row r="'.$rowNumber.'">';

        foreach (array_values($cells) as $columnIndex => $value) {
            if ($value === null) {
                continue;
            }

            $reference = chr(65 + $columnIndex).$rowNumber;

            if (is_int($value)) {
                $sheetRows .= '<c r="'.$reference.'"><v>'.$value.'</v></c>';

                continue;
            }

            $strings[] = htmlspecialchars($value, ENT_XML1);
            $sheetRows .= '<c r="'.$reference.'" t="s"><v>'.(count($strings) - 1).'</v></c>';
        }

        $sheetRows .= '</row>';
    }

    $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $relationships = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    $path = tempnam(sys_get_temp_dir(), 'workbook');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="'.$main.'" xmlns:r="'.$relationships.'"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.$main.'"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="'.$main.'" count="'.count($strings).'" uniqueCount="'.count($strings).'">'.implode('', array_map(fn (string $text): string => '<si><t>'.$text.'</t></si>', $strings)).'</sst>');
    $zip->close();

    $contents = (string) file_get_contents($path);
    unlink($path);

    return $contents;
}
