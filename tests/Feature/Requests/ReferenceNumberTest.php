<?php

use App\Models\DocumentRequest;

test('reference numbers follow the registrar\'s filing format', function () {
    $request = DocumentRequest::factory()->create();

    expect($request->reference_no)->toMatch('/^REG-\d{4}-\d{6}$/');
});

test('reference numbers run in sequence within a year', function () {
    $this->travelTo('2026-03-01 09:00:00');

    $first = DocumentRequest::factory()->create();
    $second = DocumentRequest::factory()->create();
    $third = DocumentRequest::factory()->create();

    expect($first->reference_no)->toBe('REG-2026-000001')
        ->and($second->reference_no)->toBe('REG-2026-000002')
        ->and($third->reference_no)->toBe('REG-2026-000003');
});

test('the sequence restarts each calendar year', function () {
    $this->travelTo('2026-12-31 23:00:00');
    DocumentRequest::factory()->count(3)->create();

    $this->travelTo('2027-01-01 08:00:00');
    $newYear = DocumentRequest::factory()->create();

    expect($newYear->reference_no)->toBe('REG-2027-000001');
});

test('the year in the reference matches the year of submission', function () {
    $this->travelTo('2027-06-15 10:00:00');

    expect(DocumentRequest::factory()->create()->reference_no)->toStartWith('REG-2027-');
});

test('every request in a batch receives a distinct reference', function () {
    DocumentRequest::factory()->count(50)->create();

    expect(DocumentRequest::query()->distinct()->count('reference_no'))->toBe(50);
});

test('an explicitly supplied reference number is respected', function () {
    $request = DocumentRequest::factory()->create(['reference_no' => 'REG-2026-999999']);

    expect($request->reference_no)->toBe('REG-2026-999999');
});

test('a request can never be persisted without a reference', function () {
    expect(DocumentRequest::factory()->create()->reference_no)->not->toBeEmpty();
});

test('the reference number is the route key', function () {
    $request = DocumentRequest::factory()->create();

    expect(route('student.requests.show', $request))->toContain($request->reference_no);
});
