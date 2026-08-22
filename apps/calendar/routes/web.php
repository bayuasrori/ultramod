<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\Calendar\Http\Controllers\CalendarController;

Route::prefix('calendar')->middleware('can:calendar.view')->group(function () {
    Route::get('/', [CalendarController::class, 'index'])->name('calendar.index');

    Route::middleware('can:calendar.create')->group(function () {
        Route::get('/events/create', [CalendarController::class, 'create'])->name('calendar.events.create');
        Route::post('/events', [CalendarController::class, 'store'])->name('calendar.events.store');
    });

    Route::middleware('can:calendar.update')->group(function () {
        Route::get('/events/{event}/edit', [CalendarController::class, 'edit'])->name('calendar.events.edit');
        Route::put('/events/{event}', [CalendarController::class, 'update'])->name('calendar.events.update');
    });

    Route::middleware('can:calendar.delete')->group(function () {
        Route::delete('/events/{event}', [CalendarController::class, 'destroy'])->name('calendar.events.destroy');
    });
});
