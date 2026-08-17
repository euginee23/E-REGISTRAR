<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:student'])
    ->prefix('student')
    ->group(function () {
        Route::livewire('requests', 'pages::student.requests')
            ->name('student.requests.index');

        Route::livewire('requests/create', 'pages::student.create-request')
            ->name('student.requests.create');

        Route::livewire('requests/{documentRequest}', 'pages::student.show-request')
            ->name('student.requests.show');
    });
