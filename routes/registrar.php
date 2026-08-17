<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:administrator,registrar_staff'])
    ->prefix('registrar')
    ->group(function () {
        Route::livewire('requests', 'pages::registrar.requests')
            ->name('registrar.requests.index');

        Route::livewire('requests/{documentRequest}', 'pages::registrar.show-request')
            ->name('registrar.requests.show');

        Route::livewire('appointments', 'pages::registrar.appointments')
            ->name('registrar.appointments.index');

        Route::livewire('time-slots', 'pages::registrar.time-slots')
            ->name('registrar.time-slots.index');

        Route::livewire('reports', 'pages::registrar.reports')
            ->name('registrar.reports.index');

        Route::middleware(['role:administrator'])->prefix('admin')->group(function () {
            Route::livewire('users', 'pages::admin.users')
                ->name('admin.users.index');

            Route::livewire('document-types', 'pages::admin.document-types')
                ->name('admin.document-types.index');
        });
    });
