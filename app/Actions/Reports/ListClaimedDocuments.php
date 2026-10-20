<?php

namespace App\Actions\Reports;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use Carbon\CarbonImmutable;

class ListClaimedDocuments
{
    /**
     * List who claimed which document, and when, within a window.
     *
     * A document counts as claimed on the day it was released, not the day it
     * was requested, so the window is matched against released_at. Ordered
     * oldest first so the list reads as a log of the counter's day.
     *
     * @return list<array{
     *     id: int,
     *     released_at: string,
     *     student: string,
     *     student_number: string,
     *     course: string,
     *     document: string,
     *     reference: string,
     *     copies: int,
     *     released_by: string|null
     * }>
     */
    public function __invoke(?CarbonImmutable $from = null, ?CarbonImmutable $until = null): array
    {
        return array_values(DocumentRequest::query()
            ->where('status', RequestStatus::Released)
            ->whereNotNull('released_at')
            ->when($from, fn ($query) => $query->whereDate('released_at', '>=', $from))
            ->when($until, fn ($query) => $query->whereDate('released_at', '<=', $until))
            ->with(['student.user', 'documentType', 'processedBy'])
            ->orderBy('released_at')
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentRequest $request): array => [
                'id' => $request->id,
                'released_at' => (string) $request->released_at?->format('Y-m-d H:i'),
                'student' => $request->student->user->name,
                'student_number' => $request->student->student_number,
                'course' => $request->student->course,
                'document' => $request->display_name,
                'reference' => $request->reference_no,
                'copies' => $request->copies,
                'released_by' => $request->processedBy?->name,
            ])
            ->all());
    }
}
