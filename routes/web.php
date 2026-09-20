<?php

use App\Http\Controllers\DownloadAttachmentController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::get('attachments/{attachment}', DownloadAttachmentController::class)
        ->name('attachments.download');

    Route::livewire('notifications', 'pages::notifications')->name('notifications.index');

    // One slip serves students and staff alike; the page authorizes the
    // viewer against the request itself.
    Route::livewire('requests/{documentRequest}/slip', 'pages::requests.slip')
        ->name('requests.slip');
});

require __DIR__.'/settings.php';
require __DIR__.'/student.php';
require __DIR__.'/registrar.php';
