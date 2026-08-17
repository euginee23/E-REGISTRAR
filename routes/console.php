<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Late afternoon Manila time, the day before the appointment. This only lands
// at the right local hour because the application timezone is Asia/Manila.
Schedule::command('appointments:send-reminders')
    ->weekdays()
    ->dailyAt('16:00')
    ->withoutOverlapping();
