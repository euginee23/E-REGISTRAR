<?php

namespace App\Actions\Registry;

use Generator;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class ReadSpreadsheetRows
{
    /**
     * The namespace every SpreadsheetML part writes its elements in.
     */
    private const MAIN_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * The namespace of the workbook's relationship attributes.
     */
    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * The namespace of the package relationship parts.
     */
    private const PACKAGE_RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * The largest uncompressed part read out of a workbook, in bytes.
     *
     * An upload is capped at a couple of megabytes, but a zip can expand far
     * beyond that, so each part is checked before it is pulled into memory.
     */
    private const MAX_PART_BYTES = 50 * 1024 * 1024;

    /**
     * Read the rows of a CSV file or the first sheet of an Excel workbook.
     *
     * The format is taken from the file's contents rather than its name,
     * because uploads are stored under temporary names. Each row is keyed by
     * its line or row number, so a problem can be reported against the row
     * the registrar sees in their spreadsheet.
     *
     * @return Generator<int, array<int, string|null>>
     */
    public function __invoke(string $absolutePath): Generator
    {
        $signature = @file_get_contents($absolutePath, length: 8);

        if ($signature === false) {
            throw new RuntimeException(__('The uploaded file could not be read.'));
        }

        if (str_starts_with($signature, "\xD0\xCF\x11\xE0")) {
            throw new RuntimeException(__('Old Excel files (.xls) cannot be read. Open the file in Excel and save it as .xlsx or .csv, then import that.'));
        }

        return str_starts_with($signature, "PK\x03\x04")
            ? $this->workbookRows($absolutePath)
            : $this->csvRows($absolutePath);
    }

    /**
     * Read the rows of a CSV file.
     *
     * @return Generator<int, array<int, string|null>>
     */
    private function csvRows(string $absolutePath): Generator
    {
        $handle = @fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new RuntimeException(__('The uploaded file could not be read.'));
        }

        try {
            $line = 0;

            while (($record = fgetcsv($handle, escape: '')) !== false) {
                $line++;

                yield $line => $record;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Read the rows of the first sheet of an .xlsx workbook.
     *
     * @return Generator<int, array<int, string|null>>
     */
    private function workbookRows(string $absolutePath): Generator
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException(__('The uploaded file could not be read.'));
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheet = $this->part($zip, $this->firstSheetPath($zip));

            if ($sheet === null) {
                throw new RuntimeException(__('The workbook has no sheets to import.'));
            }

            $worksheet = $sheet->children(self::MAIN_NAMESPACE);

            if (! isset($worksheet->sheetData)) {
                return;
            }

            $rowNumber = 0;

            foreach ($worksheet->sheetData->children(self::MAIN_NAMESPACE)->row as $row) {
                $declaredNumber = (int) $row->attributes()['r'];
                $rowNumber = $declaredNumber > 0 ? $declaredNumber : $rowNumber + 1;

                yield $rowNumber => $this->rowValues($row, $sharedStrings);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Read the cells of one sheet row into a list ordered by column.
     *
     * Empty cells are often left out of the file entirely, so each cell is
     * placed by its column letter rather than by its position in the row.
     *
     * @param  array<int, string>  $sharedStrings
     * @return array<int, string|null>
     */
    private function rowValues(SimpleXMLElement $row, array $sharedStrings): array
    {
        $values = [];
        $nextColumn = 0;

        foreach ($row->children(self::MAIN_NAMESPACE)->c as $cell) {
            $attributes = $cell->attributes();
            $reference = (string) $attributes['r'];
            $column = $reference === '' ? $nextColumn : $this->columnIndex($reference);
            $nextColumn = $column + 1;

            $values[$column] = $this->cellValue($cell, (string) $attributes['t'], $sharedStrings);
        }

        if ($values === []) {
            return [];
        }

        $row = array_fill(0, max(array_keys($values)) + 1, null);

        return array_replace($row, $values);
    }

    /**
     * Read a single cell as the text the spreadsheet displays for it.
     *
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, string $type, array $sharedStrings): ?string
    {
        $children = $cell->children(self::MAIN_NAMESPACE);

        if ($type === 'inlineStr') {
            return $this->richText($children->is);
        }

        if (! isset($children->v)) {
            return null;
        }

        $value = (string) $children->v;

        return match ($type) {
            's' => $sharedStrings[(int) $value] ?? null,
            'b' => $value === '1' ? 'TRUE' : 'FALSE',
            'str', 'e' => $value,
            default => $this->numberAsText($value),
        };
    }

    /**
     * Write a stored number the way the spreadsheet shows it.
     *
     * Excel stores 2023 as "2023" but may store a computed whole number as
     * "2023.0" or "2.023E3"; both should import as the student number or
     * year the registrar typed.
     */
    private function numberAsText(string $value): string
    {
        if (! is_numeric($value)) {
            return $value;
        }

        $number = (float) $value;

        if (floor($number) === $number && abs($number) < 1e15) {
            return sprintf('%.0f', $number);
        }

        return $value;
    }

    /**
     * Turn a cell reference such as "AB12" into a zero-based column index.
     */
    private function columnIndex(string $reference): int
    {
        $letters = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $reference));
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }

    /**
     * Read the workbook's table of shared strings.
     *
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $table = $this->part($zip, 'xl/sharedStrings.xml');

        if ($table === null) {
            return [];
        }

        $strings = [];

        foreach ($table->children(self::MAIN_NAMESPACE)->si as $item) {
            $strings[] = $this->richText($item);
        }

        return $strings;
    }

    /**
     * Join the text of a string item, including each formatted run.
     *
     * Phonetic guides are skipped; they are reading aids, not the text.
     */
    private function richText(SimpleXMLElement $item): string
    {
        $children = $item->children(self::MAIN_NAMESPACE);

        if (isset($children->t)) {
            return (string) $children->t;
        }

        $text = '';

        foreach ($children->r as $run) {
            $text .= (string) $run->children(self::MAIN_NAMESPACE)->t;
        }

        return $text;
    }

    /**
     * Find the path of the first sheet in the workbook's tab order.
     */
    private function firstSheetPath(ZipArchive $zip): string
    {
        $fallback = 'xl/worksheets/sheet1.xml';

        $workbook = $this->part($zip, 'xl/workbook.xml');
        $relationships = $this->part($zip, 'xl/_rels/workbook.xml.rels');

        if ($workbook === null || $relationships === null) {
            return $fallback;
        }

        $sheet = $workbook->children(self::MAIN_NAMESPACE)->sheets->children(self::MAIN_NAMESPACE)->sheet[0] ?? null;

        if ($sheet === null) {
            return $fallback;
        }

        $relationshipId = (string) $sheet->attributes(self::RELATIONSHIP_NAMESPACE)['id'];

        foreach ($relationships->children(self::PACKAGE_RELATIONSHIP_NAMESPACE)->Relationship as $relationship) {
            $attributes = $relationship->attributes();

            if ((string) $attributes['Id'] !== $relationshipId) {
                continue;
            }

            $target = (string) $attributes['Target'];

            return str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'xl/'.$target;
        }

        return $fallback;
    }

    /**
     * Read and parse one XML part of the workbook, if it exists.
     */
    private function part(ZipArchive $zip, string $name): ?SimpleXMLElement
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            return null;
        }

        if ($stat['size'] > self::MAX_PART_BYTES) {
            throw new RuntimeException(__('The workbook is too large to import. Split it and import in batches.'));
        }

        $contents = $zip->getFromName($name);

        if ($contents === false) {
            throw new RuntimeException(__('The uploaded file could not be read.'));
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($contents, options: LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false) {
            throw new RuntimeException(__('The workbook could not be read. Save it again as .xlsx or .csv and retry.'));
        }

        return $xml;
    }
}
