<?php

namespace App\Actions\Requests;

use App\Models\DocumentRequest;
use Carbon\CarbonImmutable;

class GenerateReferenceNumber
{
    /**
     * Generate the next tracking reference number for a document request.
     *
     * Reference numbers read as {prefix}-{year}-{sequence}, e.g.
     * "REG-2026-000137". The sequence restarts each calendar year, which
     * mirrors how the registrar's office files its records and makes the
     * number self-dating.
     *
     * The unique index on document_requests.reference_no is the real
     * guarantee of uniqueness; the row lock here keeps concurrent submitters
     * from deriving the same sequence in the first place. Callers wrap this in
     * a retrying transaction so a lost race simply re-derives the next number.
     */
    public function __invoke(?CarbonImmutable $submittedAt = null): string
    {
        $submittedAt ??= CarbonImmutable::now();
        $year = $submittedAt->year;

        $sequence = DocumentRequest::query()
            ->whereYear('created_at', $year)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf(
            '%s-%d-%s',
            config('registrar.reference.prefix'),
            $year,
            str_pad((string) $sequence, (int) config('registrar.reference.pad'), '0', STR_PAD_LEFT),
        );
    }
}
