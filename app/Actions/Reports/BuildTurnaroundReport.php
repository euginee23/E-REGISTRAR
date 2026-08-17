<?php

namespace App\Actions\Reports;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BuildTurnaroundReport
{
    /**
     * Measure how long released documents took, per document type.
     *
     * Durations are computed in PHP rather than SQL on purpose: TIMESTAMPDIFF
     * is MySQL-only and julianday is SQLite-only, and this project migrates on
     * MySQL but tests on SQLite. At registrar volumes the cost is irrelevant.
     *
     * Reported durations are calendar days - the wait the student experienced.
     * The target comparison uses working days, because that is the unit
     * document_types.processing_days is expressed in.
     *
     * @return array{
     *     rows: list<array{
     *         type: string, released: int, average: float|null, median: float|null,
     *         fastest: float|null, slowest: float|null, sla: int, met: int, missed: int
     *     }>,
     *     overallAverage: float|null,
     *     totalReleased: int,
     *     metRate: int|null
     * }
     */
    public function __invoke(?CarbonImmutable $from = null, ?CarbonImmutable $until = null): array
    {
        $released = DocumentRequest::query()
            ->withStatus(RequestStatus::Released)
            ->whereNotNull('released_at')
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($until, fn ($query) => $query->whereDate('created_at', '<=', $until))
            ->with('documentType')
            ->get(['id', 'document_type_id', 'created_at', 'released_at']);

        $rows = [];
        $allDurations = [];
        $totalMet = 0;
        $totalReleased = $released->count();

        foreach ($released->groupBy('document_type_id') as $group) {
            /** @var DocumentRequest $first */
            $first = $group->first();
            $type = $first->documentType;
            $sla = $type->processing_days;

            // Calendar days are what the student actually waited; working days
            // are what the target is expressed in. Comparing the wait against
            // a working-day target would count every weekend as a failure.
            $durations = $group
                ->map(fn (DocumentRequest $request): float => round(
                    $request->created_at->diffInSeconds($request->released_at) / 86400,
                    1,
                ))
                ->sort()
                ->values();

            $met = $group
                ->filter(fn (DocumentRequest $request): bool => $request->created_at->diffInWeekdays($request->released_at) <= $sla)
                ->count();

            $totalMet += $met;

            $allDurations = [...$allDurations, ...$durations->all()];

            $rows[] = [
                'type' => $type->name,
                'released' => $durations->count(),
                'average' => round((float) $durations->avg(), 1),
                'median' => $this->median($durations),
                'fastest' => (float) $durations->first(),
                'slowest' => (float) $durations->last(),
                'sla' => $sla,
                'met' => $met,
                'missed' => $durations->count() - $met,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['released'] <=> $a['released']);

        return [
            'rows' => $rows,
            'overallAverage' => $allDurations === [] ? null : round(array_sum($allDurations) / count($allDurations), 1),
            'totalReleased' => $totalReleased,
            'metRate' => $totalReleased === 0 ? null : (int) round(($totalMet / $totalReleased) * 100),
        ];
    }

    /**
     * Get the middle value of an already-sorted collection of durations.
     *
     * @param  Collection<int, float>  $durations
     */
    private function median(Collection $durations): ?float
    {
        $count = $durations->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return round((float) $durations[$middle], 1);
        }

        return round(((float) $durations[$middle - 1] + (float) $durations[$middle]) / 2, 1);
    }
}
