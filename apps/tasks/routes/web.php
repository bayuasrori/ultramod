<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\Tasks\Http\Controllers\TaskController;

Route::prefix('tasks')->name('tasks.')->group(function () {
    Route::get('/', [TaskController::class, 'index'])->name('index');
    Route::post('/', [TaskController::class, 'store'])->name('store');
    Route::patch('/{task}/toggle', [TaskController::class, 'toggle'])->name('toggle');
    Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');
});
