<?php

use App\Models\DocumentType;
use App\Models\StudentRegistryEntry;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\StudentRegistrySeeder;
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

test('the student registry seeder stocks the roster registration checks against', function () {
    $this->seed(StudentRegistrySeeder::class);

    expect(StudentRegistryEntry::query()->count())->toBeGreaterThan(0)
        ->and(StudentRegistryEntry::query()->where('student_number', '2022-10231')->exists())->toBeTrue();
});

test('the student registry seeder is idempotent', function () {
    $this->seed(StudentRegistrySeeder::class);
    $count = StudentRegistryEntry::query()->count();

    $this->seed(StudentRegistrySeeder::class);

    expect(StudentRegistryEntry::query()->count())->toBe($count);
});

test('the demo students claim their roster entries', function () {
    $this->seed(StudentRegistrySeeder::class);
    $this->seed(DemoUserSeeder::class);

    $entry = StudentRegistryEntry::query()->where('student_number', '2022-10231')->firstOrFail();

    expect($entry->isClaimed())->toBeTrue()
        ->and($entry->claimedBy->email)->toBe('student@e-registrar.test');
});

test('re-running the demo seeder never detaches an account from its roster entry', function () {
    $this->seed(StudentRegistrySeeder::class);
    $this->seed(DemoUserSeeder::class);
    $this->seed(StudentRegistrySeeder::class);

    expect(StudentRegistryEntry::query()->where('student_number', '2022-10231')->firstOrFail()->isClaimed())
        ->toBeTrue();
});

test('the seeded transcript and form 137 must be paid for', function () {
    $this->seed(DocumentTypeSeeder::class);

    $chargeable = DocumentType::query()->where('requires_payment', true)->pluck('slug')->all();

    expect($chargeable)->toEqualCanonicalizing(['form-137', 'transcript-of-records', 'good-moral-certificate']);
});

test('every chargeable seeded document carries a fee above zero', function () {
    $this->seed(DocumentTypeSeeder::class);

    expect(DocumentType::query()->where('requires_payment', true)->where('fee', '<=', 0)->count())->toBe(0);
});
