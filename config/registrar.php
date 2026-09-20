<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registrar Office Hours
    |--------------------------------------------------------------------------
    |
    | The window during which appointment time slots may be generated, and the
    | days of the week the office is open (1 = Monday ... 7 = Sunday, matching
    | Carbon's ISO day numbering). Slot generation never produces a slot that
    | falls outside these hours or on a closed day.
    |
    */

    'office' => [
        'name' => env('REGISTRAR_OFFICE_NAME', 'Office of the University Registrar'),
        'address' => env('REGISTRAR_OFFICE_ADDRESS', 'Ground Floor, Administration Building'),
        'contact' => env('REGISTRAR_OFFICE_CONTACT', 'registrar@example.edu'),
        'opens_at' => env('REGISTRAR_OPENS_AT', '08:00'),
        'closes_at' => env('REGISTRAR_CLOSES_AT', '17:00'),
        'open_days' => [1, 2, 3, 4, 5],
        'slot_minutes' => 60,
        'default_capacity' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Appointment Booking
    |--------------------------------------------------------------------------
    |
    | How far ahead a student may book a claiming appointment, and how many
    | hours before the appointment the reminder notification is issued.
    |
    */

    'booking' => [
        'max_days_ahead' => 60,
        'reminder_hours_before' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Reference Numbers
    |--------------------------------------------------------------------------
    |
    | Reference numbers are formatted as {prefix}-{year}-{sequence}, e.g.
    | "REG-2026-000137". The sequence restarts each calendar year.
    |
    */

    'reference' => [
        'prefix' => 'REG',
        'pad' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar Badges
    |--------------------------------------------------------------------------
    |
    | How long the sidebar's counts are cached, and how often the sidebar asks
    | for fresh ones. The sidebar renders on every page, so the counts are
    | worth caching; a badge that lags a little costs nothing.
    |
    */

    'nav' => [
        'badge_ttl' => 30,
        'poll' => '60s',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Every in-app notification is mirrored to the recipient by email. The
    | emails are queued, so a worker must be running for them to leave; turn
    | the mirror off where no worker is available and the bell still works.
    |
    */

    'notifications' => [
        'mail' => env('REGISTRAR_MAIL_NOTIFICATIONS', true),
        'queue' => env('REGISTRAR_MAIL_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Student Registry
    |--------------------------------------------------------------------------
    |
    | The roster of students the school enrolled, which registration is checked
    | against so an account cannot be opened against someone else's student
    | number. Imports are capped so a mistaken upload cannot exhaust memory.
    |
    */

    'registry' => [
        'max_import_rows' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Supporting Requirements
    |--------------------------------------------------------------------------
    |
    | Uploaded requirements are identification documents and authorization
    | letters, so they are stored on a private disk and served only through an
    | authorized download route.
    |
    */

    'attachments' => [
        'disk' => 'requirements',
        'max_files' => 5,
        'max_kb' => 5120,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],

];
