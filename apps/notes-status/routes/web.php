<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\NotesStatus\Http\Controllers\NoteStatusController;

Route::prefix('notes-status')->name('notes-status.')->middleware('can:notes-status.view')->group(function () {
    Route::get('/', [NoteStatusController::class, 'index'])->name('index');
    Route::get('/statuses/{status}/notes', [NoteStatusController::class, 'notes'])->name('notes');
    Route::post('/notes/{noteId}/status', [NoteStatusController::class, 'assign'])
        ->name('assign')->middleware('can:notes-status.update');
});
