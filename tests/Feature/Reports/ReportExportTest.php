<?php

use App\Actions\Reports\ExportReportToCsv;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Capture the CSV body from a streamed download response.
 */
function csvBody($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

test('the volume report exports with a heading row', function () {
    $type = DocumentType::factory()->create(['name' => 'Transcript of Records']);
    DocumentRequest::factory()->count(2)->for($type)->pending()->create();

    $this->actingAs(registrarStaff());

    $streamed = Livewire::test('pages::registrar.reports')
        ->set('tab', 'volume')
        ->instance()
        ->export(app(ExportReportToCsv::class));

    $body = csvBody($streamed);

    expect($body)->toContain('Document type')
        ->and($body)->toContain('Transcript of Records')
        ->and($body)->toContain('Total');
});

test('the exported volume report has one row per document type plus a heading', function () {
    DocumentType::factory()->count(3)->create();

    $this->actingAs(registrarStaff());

    $streamed = Livewire::test('pages::registrar.reports')
        ->set('tab', 'volume')
        ->instance()
        ->export(app(ExportReportToCsv::class));

    $lines = array_filter(explode("\n", trim(csvBody($streamed))));

    expect($lines)->toHaveCount(4);
});

test('the turnaround report exports its own columns', function () {
    $type = DocumentType::factory()->create(['name' => 'Form 137', 'processing_days' => 5]);
    $created = now()->subDays(20);

    DocumentRequest::factory()->for($type)->create([
        'status' => RequestStatus::Released,
        'created_at' => $created,
        'released_at' => $created->copy()->addDays(4),
    ]);

    $this->actingAs(registrarStaff());

    $streamed = Livewire::test('pages::registrar.reports')
        ->set('tab', 'turnaround')
        ->instance()
        ->export(app(ExportReportToCsv::class));

    $body = csvBody($streamed);

    expect($body)->toContain('Average days')
        ->and($body)->toContain('Target days')
        ->and($body)->toContain('Form 137');
});

test('the appointment report exports its own columns', function () {
    TimeSlot::factory()->onDate(CarbonImmutable::parse('2026-10-05'))->create(['capacity' => 5, 'booked_count' => 2]);

    $this->actingAs(registrarStaff());

    $streamed = Livewire::test('pages::registrar.reports')
        ->set('tab', 'appointments')
        ->set('from', '2026-10-01')
        ->set('until', '2026-10-31')
        ->instance()
        ->export(app(ExportReportToCsv::class));

    $body = csvBody($streamed);

    expect($body)->toContain('Utilisation %')
        ->and($body)->toContain('2026-10-05');
});

test('the download is named after the report and period', function () {
    $this->actingAs(registrarStaff());

    $streamed = Livewire::test('pages::registrar.reports')
        ->set('tab', 'volume')
        ->set('from', '2026-01-01')
        ->set('until', '2026-01-31')
        ->instance()
        ->export(app(ExportReportToCsv::class));

    expect($streamed->headers->get('content-disposition'))
        ->toContain('request-volume_2026-01-01_to_2026-01-31.csv');
});

test('the export is served as CSV', function () {
    $this->actingAs(registrarStaff());

    $streamed = Livewire::test('pages::registrar.reports')
        ->instance()
        ->export(app(ExportReportToCsv::class));

    expect($streamed->headers->get('content-type'))->toContain('text/csv');
});

test('an empty report still exports its headings', function () {
    $this->actingAs(registrarStaff());

    $streamed = Livewire::test('pages::registrar.reports')
        ->set('tab', 'appointments')
        ->set('from', '2020-01-01')
        ->set('until', '2020-01-31')
        ->instance()
        ->export(app(ExportReportToCsv::class));

    $body = trim(csvBody($streamed));

    expect($body)->toContain('Date')
        ->and(explode("\n", $body))->toHaveCount(1);
});
