<?php

use App\Actions\Reports\BuildTurnaroundReport;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Create a released request that took an exact number of calendar days.
 *
 * Anchored to a known Monday so working-day arithmetic is deterministic
 * regardless of which day the suite happens to run on.
 */
function releasedIn(DocumentType $type, float $days): DocumentRequest
{
    $created = CarbonImmutable::parse('2026-07-06 09:00:00');

    return DocumentRequest::factory()->for($type)->create([
        'status' => RequestStatus::Released,
        'created_at' => $created,
        'released_at' => $created->addDays($days),
    ]);
}

test('the average turnaround is measured per document type', function () {
    $type = DocumentType::factory()->create(['name' => 'Transcript of Records', 'processing_days' => 7]);

    releasedIn($type, 4);
    releasedIn($type, 6);
    releasedIn($type, 8);

    $report = app(BuildTurnaroundReport::class)();
    $row = collect($report['rows'])->firstWhere('type', 'Transcript of Records');

    expect($row['released'])->toBe(3)
        ->and($row['average'])->toBe(6.0)
        ->and($row['median'])->toBe(6.0)
        ->and($row['fastest'])->toBe(4.0)
        ->and($row['slowest'])->toBe(8.0);
});

test('the median handles an even number of releases', function () {
    $type = DocumentType::factory()->create(['processing_days' => 7]);

    releasedIn($type, 2);
    releasedIn($type, 4);
    releasedIn($type, 6);
    releasedIn($type, 8);

    $row = collect(app(BuildTurnaroundReport::class)()['rows'])->first();

    expect($row['median'])->toBe(5.0)
        ->and($row['average'])->toBe(5.0);
});

test('releases are split by whether they met the target', function () {
    $type = DocumentType::factory()->create(['name' => 'Form 137', 'processing_days' => 5]);

    // From Monday 2026-07-06: +3 days is Thursday (3 working days, met),
    // +5 days is Saturday (5 working days, met), +12 days is the Saturday
    // after next (10 working days, missed).
    releasedIn($type, 3);
    releasedIn($type, 5);
    releasedIn($type, 12);

    $row = collect(app(BuildTurnaroundReport::class)()['rows'])->firstWhere('type', 'Form 137');

    expect($row['sla'])->toBe(5)
        ->and($row['met'])->toBe(2)
        ->and($row['missed'])->toBe(1);
});

test('a weekend in the middle does not count against the target', function () {
    $type = DocumentType::factory()->create(['processing_days' => 3]);

    // Friday 2026-07-10 submitted, released Wednesday 2026-07-15: five
    // calendar days, but only three working days, so the target was met.
    $created = CarbonImmutable::parse('2026-07-10 09:00:00');

    DocumentRequest::factory()->for($type)->create([
        'status' => RequestStatus::Released,
        'created_at' => $created,
        'released_at' => $created->addDays(5),
    ]);

    $row = collect(app(BuildTurnaroundReport::class)()['rows'])->first();

    expect($row['average'])->toBe(5.0)
        ->and($row['met'])->toBe(1)
        ->and($row['missed'])->toBe(0);
});

test('the overall met rate is a percentage of all releases', function () {
    $type = DocumentType::factory()->create(['processing_days' => 5]);

    releasedIn($type, 1);
    releasedIn($type, 2);
    releasedIn($type, 3);
    releasedIn($type, 20);

    $report = app(BuildTurnaroundReport::class)();

    expect($report['totalReleased'])->toBe(4)
        ->and($report['metRate'])->toBe(75);
});

test('only released requests are measured', function () {
    $type = DocumentType::factory()->create(['processing_days' => 5]);

    releasedIn($type, 3);
    DocumentRequest::factory()->count(5)->for($type)->pending()->create();
    DocumentRequest::factory()->count(2)->for($type)->readyForRelease()->create();

    expect(app(BuildTurnaroundReport::class)()['totalReleased'])->toBe(1);
});

test('a period with no releases reports null rather than dividing by zero', function () {
    DocumentRequest::factory()->count(3)->pending()->create();

    $report = app(BuildTurnaroundReport::class)();

    expect($report['rows'])->toBe([])
        ->and($report['overallAverage'])->toBeNull()
        ->and($report['metRate'])->toBeNull();
});

test('document types are ordered by how many were released', function () {
    $busy = DocumentType::factory()->create(['name' => 'Busy Document', 'processing_days' => 5]);
    $quiet = DocumentType::factory()->create(['name' => 'Quiet Document', 'processing_days' => 5]);

    releasedIn($busy, 2);
    releasedIn($busy, 3);
    releasedIn($busy, 4);
    releasedIn($quiet, 2);

    $rows = app(BuildTurnaroundReport::class)()['rows'];

    expect($rows[0]['type'])->toBe('Busy Document')
        ->and($rows[1]['type'])->toBe('Quiet Document');
});

test('the turnaround tab renders the figures', function () {
    $type = DocumentType::factory()->create(['name' => 'Transcript of Records', 'processing_days' => 7]);
    releasedIn($type, 5);

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.reports')
        ->set('tab', 'turnaround')
        ->assertSee('Days from submission to release')
        ->assertSee('Transcript of Records');
});
