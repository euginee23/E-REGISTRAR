<?php

use App\Http\Controllers\DownloadAttachmentController;
use App\Http\Controllers\OpenNotificationController;
use App\Http\Controllers\SwitchAccountController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Outside the verified group: the 403 page offers it to anyone signed in to
// the wrong account, whatever state that account is in.
Route::post('switch-account', SwitchAccountController::class)
    ->middleware('auth')
    ->name('switch-account');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::get('attachments/{attachment}', DownloadAttachmentController::class)
        ->name('attachments.download');

    Route::livewire('notifications', 'pages::notifications')->name('notifications.index');

    Route::get('notifications/{notification}/open', OpenNotificationController::class)
        ->name('notifications.open');

    // One slip serves students and staff alike; the page authorizes the
    // viewer against the request itself.
    Route::livewire('requests/{documentRequest}/slip', 'pages::requests.slip')
        ->name('requests.slip');
});

require __DIR__.'/settings.php';
require __DIR__.'/student.php';
require __DIR__.'/registrar.php';
