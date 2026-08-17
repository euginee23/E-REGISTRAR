<?php

namespace App\Actions\Reports;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Carbon\CarbonImmutable;

class BuildRegistrarSummary
{
    /**
     * Build the headline operational figures for the registrar's office.
     *
     * @return array{
     *     byStatus: array<string, int>,
     *     total: int,
     *     open: int,
     *     overdue: int,
     *     topDocuments: list<array{name: string, total: int}>,
     *     thisMonth: int,
     *     lastMonth: int,
     *     monthDelta: int|null
     * }
     */
    public function __invoke(int $overdueAfterDays = 7): array
    {
        $byStatus = array_fill_keys(array_column(RequestStatus::cases(), 'value'), 0);

        $counts = DocumentRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        foreach ($counts as $status => $total) {
            $byStatus[(string) $status] = (int) $total;
        }

        $open = 0;

        foreach (RequestStatus::open() as $status) {
            $open += $byStatus[$status->value];
        }

        $thisMonthStart = CarbonImmutable::today()->startOfMonth();
        $lastMonthStart = $thisMonthStart->subMonth();

        $thisMonth = DocumentRequest::query()
            ->whereDate('created_at', '>=', $thisMonthStart)
            ->count();

        $lastMonth = DocumentRequest::query()
            ->whereDate('created_at', '>=', $lastMonthStart)
            ->whereDate('created_at', '<', $thisMonthStart)
            ->count();

        return [
            'byStatus' => $byStatus,
            'total' => array_sum($byStatus),
            'open' => $open,
            'overdue' => DocumentRequest::query()
                ->open()
                ->whereDate('created_at', '<', CarbonImmutable::today()->subDays($overdueAfterDays))
                ->count(),
            'topDocuments' => array_values(
                DocumentType::query()
                    ->withCount('documentRequests')
                    ->orderByDesc('document_requests_count')
                    ->take(5)
                    ->get()
                    ->map(fn (DocumentType $type): array => [
                        'name' => $type->name,
                        'total' => (int) $type->document_requests_count,
                    ])
                    ->all(),
            ),
            'thisMonth' => $thisMonth,
            'lastMonth' => $lastMonth,
            'monthDelta' => $lastMonth === 0
                ? null
                : (int) round((($thisMonth - $lastMonth) / $lastMonth) * 100),
        ];
    }
}
