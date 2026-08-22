<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\Kanban\Http\Controllers\BoardController;
use PlatformApps\Kanban\Http\Controllers\ColumnController;
use PlatformApps\Kanban\Http\Controllers\TaskController;

Route::prefix('kanban')->middleware('can:kanban.view')->group(function () {
    Route::get('/boards', [BoardController::class, 'index'])->name('kanban.boards.index');
    Route::get('/boards/{board}', [BoardController::class, 'show'])->name('kanban.boards.show');

    Route::middleware('can:kanban.create')->group(function () {
        Route::post('/boards', [BoardController::class, 'store'])->name('kanban.boards.store');
    });

    Route::middleware('can:kanban.update')->group(function () {
        Route::put('/boards/{board}', [BoardController::class, 'update'])->name('kanban.boards.update');
        Route::post('/boards/{board}/columns', [ColumnController::class, 'store'])->name('kanban.columns.store');
        Route::put('/boards/{board}/columns/{column}', [ColumnController::class, 'update'])->name('kanban.columns.update');
        Route::delete('/boards/{board}/columns/{column}', [ColumnController::class, 'destroy'])->name('kanban.columns.destroy');
        Route::put('/tasks/{task}/move', [TaskController::class, 'move'])->name('kanban.tasks.move');
    });

    Route::middleware('can:kanban.delete')->group(function () {
        Route::delete('/boards/{board}', [BoardController::class, 'destroy'])->name('kanban.boards.destroy');
    });

    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('kanban.tasks.edit')->middleware('can:kanban.update');
    Route::post('/columns/{column}/tasks', [TaskController::class, 'store'])->name('kanban.tasks.store')->middleware('can:kanban.create');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('kanban.tasks.update')->middleware('can:kanban.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('kanban.tasks.destroy')->middleware('can:kanban.delete');
});
