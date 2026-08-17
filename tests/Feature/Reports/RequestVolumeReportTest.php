<?php

use App\Actions\Reports\BuildRequestVolumeReport;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

test('requests are counted per document type and status', function () {
    $tor = DocumentType::factory()->create(['name' => 'Transcript of Records']);
    $form = DocumentType::factory()->create(['name' => 'Form 137']);

    DocumentRequest::factory()->count(3)->for($tor)->pending()->create();
    DocumentRequest::factory()->count(2)->for($tor)->released()->create();
    DocumentRequest::factory()->for($form)->pending()->create();

    $report = app(BuildRequestVolumeReport::class)();

    $rows = collect($report['rows'])->keyBy('type');

    expect($rows['Transcript of Records']['statuses'][RequestStatus::Pending->value])->toBe(3)
        ->and($rows['Transcript of Records']['statuses'][RequestStatus::Released->value])->toBe(2)
        ->and($rows['Transcript of Records']['total'])->toBe(5)
        ->and($rows['Form 137']['total'])->toBe(1)
        ->and($report['grandTotal'])->toBe(6);
});

test('the status totals add up across document types', function () {
    DocumentRequest::factory()->count(4)->pending()->create();
    DocumentRequest::factory()->count(2)->released()->create();

    $report = app(BuildRequestVolumeReport::class)();

    expect($report['totals'][RequestStatus::Pending->value])->toBe(4)
        ->and($report['totals'][RequestStatus::Released->value])->toBe(2)
        ->and(array_sum($report['totals']))->toBe($report['grandTotal']);
});

test('the date range excludes requests outside it', function () {
    $this->travelTo('2026-03-15 09:00:00');
    DocumentRequest::factory()->count(2)->create();

    $this->travelTo('2026-06-15 09:00:00');
    DocumentRequest::factory()->count(5)->create();

    $this->travelBack();

    $report = app(BuildRequestVolumeReport::class)(
        CarbonImmutable::parse('2026-06-01'),
        CarbonImmutable::parse('2026-06-30'),
    );

    expect($report['grandTotal'])->toBe(5);
});

test('a document type with no requests still appears with zeroes', function () {
    DocumentType::factory()->create(['name' => 'Never Requested']);

    $report = app(BuildRequestVolumeReport::class)();

    $row = collect($report['rows'])->firstWhere('type', 'Never Requested');

    expect($row['total'])->toBe(0)
        ->and($row['statuses'][RequestStatus::Pending->value])->toBe(0);
});

test('an empty period reports zero rather than failing', function () {
    $report = app(BuildRequestVolumeReport::class)(
        CarbonImmutable::parse('2020-01-01'),
        CarbonImmutable::parse('2020-01-31'),
    );

    expect($report['grandTotal'])->toBe(0);
});

test('staff can open the reports screen', function (string $helper) {
    $this->actingAs($helper());

    $this->get(route('registrar.reports.index'))->assertOk();
})->with(['registrarStaff', 'administrator']);

test('students cannot open the reports screen', function () {
    $this->actingAs(student());

    $this->get(route('registrar.reports.index'))->assertForbidden();
});

test('the volume tab renders the figures', function () {
    $type = DocumentType::factory()->create(['name' => 'Good Moral Certificate']);
    DocumentRequest::factory()->count(2)->for($type)->create();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.reports')
        ->set('tab', 'volume')
        ->assertSee('Good Moral Certificate')
        ->assertSee('Requests by document type and status');
});
