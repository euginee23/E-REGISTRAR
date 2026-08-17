<?php

namespace App\Actions\Reports;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Carbon\CarbonImmutable;

class BuildRequestVolumeReport
{
    /**
     * Count requests per document type, broken down by status.
     *
     * @return array{
     *     rows: list<array{type: string, statuses: array<string, int>, total: int}>,
     *     totals: array<string, int>,
     *     grandTotal: int
     * }
     */
    public function __invoke(?CarbonImmutable $from = null, ?CarbonImmutable $until = null): array
    {
        $counts = DocumentRequest::query()
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($until, fn ($query) => $query->whereDate('created_at', '<=', $until))
            ->selectRaw('document_type_id, status, count(*) as total')
            ->groupBy('document_type_id', 'status')
            ->get()
            ->groupBy('document_type_id');

        $types = DocumentType::query()->orderBy('name')->get();

        $rows = [];
        $totals = array_fill_keys(array_column(RequestStatus::cases(), 'value'), 0);
        $grandTotal = 0;

        foreach ($types as $type) {
            $statuses = array_fill_keys(array_column(RequestStatus::cases(), 'value'), 0);

            foreach ($counts->get($type->id, collect()) as $row) {
                // The model casts status to an enum, which cannot be an array key.
                $key = $row->status->value;

                $statuses[$key] = (int) $row->total;
                $totals[$key] += (int) $row->total;
            }

            $rowTotal = array_sum($statuses);
            $grandTotal += $rowTotal;

            $rows[] = [
                'type' => $type->name,
                'statuses' => $statuses,
                'total' => $rowTotal,
            ];
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'grandTotal' => $grandTotal,
        ];
    }
}
