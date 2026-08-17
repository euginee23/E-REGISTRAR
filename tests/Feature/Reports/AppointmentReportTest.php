<?php

use App\Actions\Reports\BuildAppointmentReport;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

test('slot capacity and bookings are totalled per day', function () {
    $date = CarbonImmutable::parse('2026-10-05');

    TimeSlot::factory()->onDate($date)->startingAt(8)->create(['capacity' => 5, 'booked_count' => 2]);
    TimeSlot::factory()->onDate($date)->startingAt(9)->create(['capacity' => 5, 'booked_count' => 3]);

    $report = app(BuildAppointmentReport::class)();
    $row = collect($report['rows'])->firstWhere('date', '2026-10-05');

    expect($row['slots'])->toBe(2)
        ->and($row['capacity'])->toBe(10)
        ->and($row['booked'])->toBe(5)
        ->and($row['utilisation'])->toBe(50);
});

test('attendance is counted from the appointments', function () {
    $date = CarbonImmutable::parse('2026-10-05');
    $slot = TimeSlot::factory()->onDate($date)->create(['capacity' => 10]);

    Appointment::factory()->count(2)->for($slot)->create(['status' => AppointmentStatus::Completed]);
    Appointment::factory()->for($slot)->create(['status' => AppointmentStatus::NoShow]);
    Appointment::factory()->for($slot)->create(['status' => AppointmentStatus::Cancelled]);

    $row = collect(app(BuildAppointmentReport::class)()['rows'])->firstWhere('date', '2026-10-05');

    expect($row['completed'])->toBe(2)
        ->and($row['noShows'])->toBe(1)
        ->and($row['cancelled'])->toBe(1);
});

test('the date range excludes days outside it', function () {
    TimeSlot::factory()->onDate(CarbonImmutable::parse('2026-10-05'))->create();
    TimeSlot::factory()->onDate(CarbonImmutable::parse('2026-11-05'))->create();

    $report = app(BuildAppointmentReport::class)(
        CarbonImmutable::parse('2026-10-01'),
        CarbonImmutable::parse('2026-10-31'),
    );

    expect($report['rows'])->toHaveCount(1)
        ->and($report['rows'][0]['date'])->toBe('2026-10-05');
});

test('overall utilisation is computed across the whole period', function () {
    TimeSlot::factory()->onDate(CarbonImmutable::parse('2026-10-05'))->startingAt(8)->create(['capacity' => 4, 'booked_count' => 4]);
    TimeSlot::factory()->onDate(CarbonImmutable::parse('2026-10-06'))->startingAt(8)->create(['capacity' => 4, 'booked_count' => 0]);

    $report = app(BuildAppointmentReport::class)();

    expect($report['totals']['capacity'])->toBe(8)
        ->and($report['totals']['booked'])->toBe(4)
        ->and($report['utilisation'])->toBe(50);
});

test('a period with no slots reports zero rather than dividing by zero', function () {
    $report = app(BuildAppointmentReport::class)(
        CarbonImmutable::parse('2020-01-01'),
        CarbonImmutable::parse('2020-01-31'),
    );

    expect($report['rows'])->toBe([])
        ->and($report['utilisation'])->toBe(0);
});

test('days are reported in chronological order', function () {
    TimeSlot::factory()->onDate(CarbonImmutable::parse('2026-10-08'))->startingAt(8)->create();
    TimeSlot::factory()->onDate(CarbonImmutable::parse('2026-10-05'))->startingAt(8)->create();
    TimeSlot::factory()->onDate(CarbonImmutable::parse('2026-10-07'))->startingAt(8)->create();

    $dates = array_column(app(BuildAppointmentReport::class)()['rows'], 'date');

    expect($dates)->toBe(['2026-10-05', '2026-10-07', '2026-10-08']);
});

test('the appointments tab renders the figures', function () {
    TimeSlot::factory()->onDate(CarbonImmutable::parse('2026-10-05'))->create(['capacity' => 5, 'booked_count' => 1]);

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.reports')
        ->set('tab', 'appointments')
        ->set('from', '2026-10-01')
        ->set('until', '2026-10-31')
        ->assertSee('Slot usage and attendance')
        ->assertSee('Oct 5, 2026');
});

test('the summary tab renders the headline figures', function () {
    DocumentRequest::factory()->count(3)->pending()->create();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.reports')
        ->set('tab', 'summary')
        ->assertSee('Total requests')
        ->assertSee('Most requested documents');
});
