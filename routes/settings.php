<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');

    Route::livewire('settings/student-profile', 'pages::settings.student-profile')
        ->middleware(['role:student'])
        ->name('student-profile.edit');

    Route::livewire('settings/mail', 'pages::settings.mail')
        ->middleware(['role:administrator'])
        ->name('mail-settings.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});
