<?php

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\UpdateAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Models\DocumentRequest;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Book one appointment on a given day, for any student.
 */
function calendarBooking(CarbonImmutable $date, int $hour = 9)
{
    $slot = TimeSlot::factory()->onDate($date)->startingAt($hour)->create(['capacity' => 5]);

    return app(BookAppointment::class)(DocumentRequest::factory()->processing()->create(), $slot);
}

test('the calendar opens on the month view', function () {
    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.appointments')
        ->assertSet('view', 'month')
        ->assertSeeHtml('data-test="month-calendar"');
});

test('the month shows every student\'s bookings, not just one', function () {
    $date = CarbonImmutable::today()->addWeekday();

    calendarBooking($date, 9);
    calendarBooking($date, 10);
    calendarBooking($date, 11);

    $this->actingAs(registrarStaff());

    $grid = Livewire::test('pages::registrar.appointments')
        ->set('month', $date->format('Y-m'))
        ->instance()
        ->monthGrid;

    $day = collect($grid)->flatten(1)->firstWhere('date', $date->toDateString());

    expect($day['total'])->toBe(3);
});

test('a day with no bookings counts zero', function () {
    $date = CarbonImmutable::today()->addWeekday();

    $this->actingAs(registrarStaff());

    $grid = Livewire::test('pages::registrar.appointments')
        ->set('month', $date->format('Y-m'))
        ->instance()
        ->monthGrid;

    $day = collect($grid)->flatten(1)->firstWhere('date', $date->toDateString());

    expect($day['total'])->toBe(0)
        ->and($day['byStatus'])->toBe([]);
});

test('bookings in other months are not counted', function () {
    // Anchored a month ahead so every slot is bookable; a slot in the past
    // would be refused before the grid ever saw it.
    $target = CarbonImmutable::today()->addMonth()->startOfMonth()->addWeekday();
    $farAway = $target->addMonths(3);

    calendarBooking($target);
    calendarBooking($farAway);

    $this->actingAs(registrarStaff());

    $grid = Livewire::test('pages::registrar.appointments')
        ->set('month', $target->format('Y-m'))
        ->instance()
        ->monthGrid;

    $days = collect($grid)->flatten(1);

    expect($days->firstWhere('date', $farAway->toDateString()))->toBeNull()
        ->and($days->sum('total'))->toBe(1);
});

test('the grid always covers whole weeks starting on Sunday', function () {
    $this->actingAs(registrarStaff());

    $grid = Livewire::test('pages::registrar.appointments')
        ->set('month', '2026-09')
        ->instance()
        ->monthGrid;

    foreach ($grid as $week) {
        expect($week)->toHaveCount(7);
    }

    expect(CarbonImmutable::parse($grid[0][0]['date'])->dayOfWeek)->toBe(CarbonImmutable::SUNDAY);
});

test('days outside the chosen month are marked as such', function () {
    $this->actingAs(registrarStaff());

    $grid = Livewire::test('pages::registrar.appointments')
        ->set('month', '2026-09')
        ->instance()
        ->monthGrid;

    $days = collect($grid)->flatten(1);

    expect($days->firstWhere('date', '2026-09-15')['inMonth'])->toBeTrue()
        ->and($days->where('inMonth', false)->count())->toBeGreaterThan(0);
});

test('clicking a day opens it in the day view', function () {
    $date = CarbonImmutable::today()->addWeekday();
    $appointment = calendarBooking($date);

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.appointments')
        ->call('showDay', $date->toDateString())
        ->assertSet('view', 'day')
        ->assertSet('date', $date->toDateString())
        ->assertSee($appointment->documentRequest->reference_no);
});

test('the day view offers a way back to the month', function () {
    $date = CarbonImmutable::today()->addWeekday();
    calendarBooking($date);

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.appointments')
        ->call('showDay', $date->toDateString())
        ->assertSeeHtml('data-test="back-to-month"')
        ->call('showMonth')
        ->assertSet('view', 'month');
});

test('choosing a date drops straight into that day', function () {
    $date = CarbonImmutable::today()->addWeekday();

    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.appointments')
        ->set('date', $date->toDateString())
        ->assertSet('view', 'day')
        ->assertSet('month', $date->format('Y-m'));
});

test('the month can be stepped forwards and back', function () {
    $this->actingAs(registrarStaff());

    Livewire::test('pages::registrar.appointments')
        ->set('month', '2026-09')
        ->call('shiftMonth', 1)
        ->assertSet('month', '2026-10')
        ->call('shiftMonth', -2)
        ->assertSet('month', '2026-08')
        ->assertSet('view', 'month');
});

test('a cancelled appointment stops counting as booked', function () {
    $date = CarbonImmutable::today()->addWeekday();
    $appointment = calendarBooking($date);

    app(UpdateAppointmentStatus::class)($appointment, AppointmentStatus::Cancelled, registrarStaff());

    $this->actingAs(registrarStaff());

    $summary = Livewire::test('pages::registrar.appointments')
        ->set('month', $date->format('Y-m'))
        ->instance()
        ->monthSummary;

    expect($summary['booked'])->toBe(0);
});

test('the month summary counts completions and no-shows', function () {
    $date = CarbonImmutable::today()->addWeekday();
    $staff = registrarStaff();

    app(UpdateAppointmentStatus::class)(calendarBooking($date, 9), AppointmentStatus::Completed, $staff);
    app(UpdateAppointmentStatus::class)(calendarBooking($date, 10), AppointmentStatus::NoShow, $staff);
    calendarBooking($date, 11);

    $this->actingAs($staff);

    $summary = Livewire::test('pages::registrar.appointments')
        ->set('month', $date->format('Y-m'))
        ->instance()
        ->monthSummary;

    expect($summary['completed'])->toBe(1)
        ->and($summary['noShows'])->toBe(1)
        ->and($summary['booked'])->toBe(2);
});

test('a busy month is summarised without a query per day', function () {
    $start = CarbonImmutable::today()->addMonth()->startOfMonth();

    for ($i = 0; $i < 12; $i++) {
        calendarBooking($start->addDays($i % 20), 8 + ($i % 8));
    }

    $this->actingAs(registrarStaff());

    DB::enableQueryLog();

    Livewire::test('pages::registrar.appointments')
        ->set('month', $start->format('Y-m'))
        ->instance()
        ->monthGrid;

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // One aggregate for the whole grid, plus whatever the page itself needs;
    // a per-day query would push this well past forty.
    expect($queries)->toBeLessThan(15);
});

test('students still cannot reach the calendar in either view', function () {
    $this->actingAs(student());

    $this->get(route('registrar.appointments.index', ['view' => 'month']))->assertForbidden();
    $this->get(route('registrar.appointments.index', ['view' => 'day']))->assertForbidden();
});

test('a forged view falls back to the month grid', function () {
    $this->actingAs(registrarStaff());

    Livewire::withQueryParams(['view' => 'nonsense'])
        ->test('pages::registrar.appointments')
        ->assertSet('view', 'month');
});
