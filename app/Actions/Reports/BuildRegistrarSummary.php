<?php

namespace App\Actions\Reports;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class BuildRegistrarSummary
{
    /**
     * Build the headline operational figures for the registrar's office.
     *
     * Every figure counts the requests submitted within the window, so the
     * summary can be read for a single day as easily as for a term. The
     * window is compared with the one of equal length just before it - a
     * single day against the day before, a week against the week before.
     *
     * @return array{
     *     byStatus: array<string, int>,
     *     total: int,
     *     open: int,
     *     overdue: int,
     *     topDocuments: list<array{name: string, total: int}>,
     *     previousFrom: string|null,
     *     previousUntil: string|null,
     *     previousTotal: int|null,
     *     delta: int|null
     * }
     */
    public function __invoke(
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $until = null,
        int $overdueAfterDays = 7,
    ): array {
        $byStatus = array_fill_keys(array_column(RequestStatus::cases(), 'value'), 0);

        $counts = $this->within(DocumentRequest::query(), $from, $until)
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

        $total = array_sum($byStatus);

        [$previousFrom, $previousUntil] = $this->previousWindow($from, $until);

        $previousTotal = $previousFrom === null
            ? null
            : $this->within(DocumentRequest::query(), $previousFrom, $previousUntil)->count();

        return [
            'byStatus' => $byStatus,
            'total' => $total,
            'open' => $open,
            'overdue' => $this->within(DocumentRequest::query(), $from, $until)
                ->open()
                ->whereDate('created_at', '<', CarbonImmutable::today()->subDays($overdueAfterDays))
                ->count(),
            'topDocuments' => $this->topDocuments($from, $until),
            'previousFrom' => $previousFrom?->toDateString(),
            'previousUntil' => $previousUntil?->toDateString(),
            'previousTotal' => $previousTotal,
            'delta' => $previousTotal === null || $previousTotal === 0
                ? null
                : (int) round((($total - $previousTotal) / $previousTotal) * 100),
        ];
    }

    /**
     * Get the five most requested documents in the window.
     *
     * @return list<array{name: string, total: int}>
     */
    private function topDocuments(?CarbonImmutable $from, ?CarbonImmutable $until): array
    {
        return array_values(
            DocumentType::query()
                ->withCount(['documentRequests' => fn (Builder $query) => $this->within($query, $from, $until)])
                ->orderByDesc('document_requests_count')
                ->take(5)
                ->get()
                ->filter(fn (DocumentType $type): bool => $type->document_requests_count > 0)
                ->map(fn (DocumentType $type): array => [
                    'name' => $type->name,
                    'total' => (int) $type->document_requests_count,
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * Get the window of equal length that ends the day before this one.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function previousWindow(?CarbonImmutable $from, ?CarbonImmutable $until): array
    {
        if ($from === null || $until === null || $until->lessThan($from)) {
            return [null, null];
        }

        $days = (int) $from->startOfDay()->diffInDays($until->startOfDay()) + 1;
        $previousUntil = $from->startOfDay()->subDay();

        return [$previousUntil->subDays($days - 1), $previousUntil];
    }

    /**
     * Narrow a request query to those submitted within the window.
     *
     * @param  Builder<DocumentRequest>  $query
     * @return Builder<DocumentRequest>
     */
    private function within(Builder $query, ?CarbonImmutable $from, ?CarbonImmutable $until): Builder
    {
        if ($from !== null) {
            $query->whereDate('document_requests.created_at', '>=', $from);
        }

        if ($until !== null) {
            $query->whereDate('document_requests.created_at', '<=', $until);
        }

        return $query;
    }
}
