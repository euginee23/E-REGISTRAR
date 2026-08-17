<?php

namespace App\Actions\Reports;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportReportToCsv
{
    /**
     * Stream a report table back to the browser as a CSV download.
     *
     * Streaming keeps memory flat regardless of how many rows a report grows
     * to, and CSV avoids pulling in a PDF dependency for something a
     * spreadsheet opens natively.
     *
     * @param  list<string>  $headings
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function __invoke(string $filename, array $headings, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'wb');

            throw_if($handle === false, RuntimeException::class, 'Unable to open the output stream for the CSV export.');

            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
