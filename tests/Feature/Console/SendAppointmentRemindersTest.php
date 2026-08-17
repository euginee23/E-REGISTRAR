<?php

use App\Enums\AppointmentStatus;
use App\Enums\NotificationType;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\Notification;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Create an appointment on the given date for a fresh student.
 */
function appointmentOn(CarbonImmutable $date, AppointmentStatus $status = AppointmentStatus::Scheduled, int $hour = 9): Appointment
{
    $slot = TimeSlot::factory()->onDate($date)->startingAt($hour)->create();

    return Appointment::factory()
        ->for($slot)
        ->for(DocumentRequest::factory()->for(student()->student)->processing())
        ->create(['status' => $status]);
}

test('students with an appointment tomorrow are reminded', function () {
    $this->travelTo('2026-09-08 16:00:00');

    $appointment = appointmentOn(CarbonImmutable::parse('2026-09-09'));

    $this->artisan('appointments:send-reminders')
        ->expectsOutputToContain('1 reminder(s) sent')
        ->assertSuccessful();

    $student = $appointment->documentRequest->student->user;

    expect($student->unreadNotificationsCount())->toBe(1)
        ->and($student->registrarNotifications()->first()->type)->toBe(NotificationType::AppointmentReminder);
});

test('appointments on other days are left alone', function () {
    $this->travelTo('2026-09-08 16:00:00');

    appointmentOn(CarbonImmutable::parse('2026-09-10'));
    appointmentOn(CarbonImmutable::parse('2026-09-11'));

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    expect(Notification::query()->count())->toBe(0);
});

test('running twice never reminds the same student again', function () {
    $this->travelTo('2026-09-08 16:00:00');

    appointmentOn(CarbonImmutable::parse('2026-09-09'));

    $this->artisan('appointments:send-reminders')->assertSuccessful();
    $this->artisan('appointments:send-reminders')
        ->expectsOutputToContain('0 reminder(s) sent')
        ->assertSuccessful();

    expect(Notification::query()->count())->toBe(1);
});

test('the reminder stamp records that it was sent', function () {
    $this->travelTo('2026-09-08 16:00:00');

    $appointment = appointmentOn(CarbonImmutable::parse('2026-09-09'));

    expect($appointment->reminder_sent_at)->toBeNull();

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    expect($appointment->fresh()->reminder_sent_at)->not->toBeNull();
});

test('confirmed appointments are reminded too', function () {
    $this->travelTo('2026-09-08 16:00:00');

    appointmentOn(CarbonImmutable::parse('2026-09-09'), AppointmentStatus::Confirmed);

    $this->artisan('appointments:send-reminders')
        ->expectsOutputToContain('1 reminder(s) sent')
        ->assertSuccessful();
});

test('cancelled and finished appointments are not reminded', function (AppointmentStatus $status) {
    $this->travelTo('2026-09-08 16:00:00');

    appointmentOn(CarbonImmutable::parse('2026-09-09'), $status);

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    expect(Notification::query()->count())->toBe(0);
})->with([
    AppointmentStatus::Cancelled,
    AppointmentStatus::Completed,
    AppointmentStatus::NoShow,
]);

test('several students due tomorrow are all reminded', function () {
    $this->travelTo('2026-09-08 16:00:00');

    $tomorrow = CarbonImmutable::parse('2026-09-09');

    appointmentOn($tomorrow, hour: 9);
    appointmentOn($tomorrow, hour: 10);
    appointmentOn($tomorrow, hour: 11);

    $this->artisan('appointments:send-reminders')
        ->expectsOutputToContain('3 reminder(s) sent')
        ->assertSuccessful();

    expect(Notification::query()->count())->toBe(3);
});

test('the reminder names the document and the time', function () {
    $this->travelTo('2026-09-08 16:00:00');

    $appointment = appointmentOn(CarbonImmutable::parse('2026-09-09'));

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    $message = Notification::query()->firstOrFail()->message;

    expect($message)->toContain($appointment->documentRequest->display_name)
        ->and($message)->toContain('September 9')
        ->and($message)->toContain('9:00 AM');
});

test('only the requesting student is reminded', function () {
    $this->travelTo('2026-09-08 16:00:00');

    registrarStaff();
    $appointment = appointmentOn(CarbonImmutable::parse('2026-09-09'));

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    expect(Notification::query()->count())->toBe(1)
        ->and(Notification::query()->firstOrFail()->user_id)
        ->toBe($appointment->documentRequest->student->user_id);
});

test('the command is scheduled on weekday afternoons', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'appointments:send-reminders'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 16 * * 1-5');
});
