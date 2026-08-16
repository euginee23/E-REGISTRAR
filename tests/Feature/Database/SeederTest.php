<?php

use App\Models\DocumentType;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\TimeSlotSeeder;

test('the document type seeder creates the registrar\'s issuable documents', function () {
    $this->seed(DocumentTypeSeeder::class);

    expect(DocumentType::query()->pluck('slug')->all())->toEqualCanonicalizing([
        'form-137',
        'transcript-of-records',
        'certificate-of-enrollment',
        'good-moral-certificate',
        'other-academic-document',
    ]);
});

test('the document type seeder is idempotent', function () {
    $this->seed(DocumentTypeSeeder::class);
    $this->seed(DocumentTypeSeeder::class);

    expect(DocumentType::query()->count())->toBe(5);
});

test('only the other-academic-document type takes a free-text name', function () {
    $this->seed(DocumentTypeSeeder::class);

    expect(DocumentType::query()->where('requires_custom_name', true)->pluck('slug')->all())
        ->toBe(['other-academic-document']);
});

test('seeded document types carry a processing window', function () {
    $this->seed(DocumentTypeSeeder::class);

    expect(DocumentType::query()->where('processing_days', '<', 1)->count())->toBe(0);
});

test('the time slot seeder never opens the office on a weekend', function () {
    $this->seed(TimeSlotSeeder::class);

    $weekendSlots = TimeSlot::query()->get()->filter(
        fn (TimeSlot $slot) => $slot->slot_date->isWeekend(),
    );

    expect($weekendSlots)->toBeEmpty()
        ->and(TimeSlot::query()->count())->toBeGreaterThan(0);
});

test('the time slot seeder keeps slots inside office hours', function () {
    $this->seed(TimeSlotSeeder::class);

    $slot = TimeSlot::query()->orderBy('slot_date')->orderBy('start_time')->firstOrFail();

    expect($slot->start_time)->toBe('08:00:00')
        ->and(TimeSlot::query()->where('end_time', '>', '17:00:00')->count())->toBe(0);
});

test('the time slot seeder does not duplicate slots when run twice', function () {
    $this->seed(TimeSlotSeeder::class);
    $first = TimeSlot::query()->count();

    $this->seed(TimeSlotSeeder::class);

    expect(TimeSlot::query()->count())->toBe($first);
});

test('regenerating slots preserves existing bookings', function () {
    $this->seed(TimeSlotSeeder::class);

    $slot = TimeSlot::query()->orderBy('slot_date')->orderBy('start_time')->firstOrFail();
    $slot->update(['booked_count' => 3]);

    $this->seed(TimeSlotSeeder::class);

    expect($slot->fresh()->booked_count)->toBe(3);
});

test('the time slot seeder covers the coming weeks', function () {
    $this->seed(TimeSlotSeeder::class);

    $latest = TimeSlot::query()->max('slot_date');

    expect(CarbonImmutable::parse($latest)->greaterThan(CarbonImmutable::today()->addWeeks(3)))->toBeTrue();
});
