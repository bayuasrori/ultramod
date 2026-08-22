<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\Notes\Http\Controllers\NoteController;

Route::prefix('notes')->name('notes.')->middleware('can:notes.view')->group(function () {
    Route::get('/', [NoteController::class, 'index'])->name('index');
    Route::get('/create', [NoteController::class, 'create'])->name('create')->middleware('can:notes.create');
    Route::post('/', [NoteController::class, 'store'])->name('store')->middleware('can:notes.create');

    Route::get('/{note}', [NoteController::class, 'show'])->name('show');
    Route::get('/{note}/edit', [NoteController::class, 'edit'])->name('edit')->middleware('can:notes.update');
    Route::put('/{note}', [NoteController::class, 'update'])->name('update')->middleware('can:notes.update');
    Route::delete('/{note}', [NoteController::class, 'destroy'])->name('destroy')->middleware('can:notes.delete');

    Route::put('/{note}/revisions/{revision}/restore', [NoteController::class, 'restoreRevision'])
        ->name('revisions.restore')->middleware('can:notes.update');

    Route::post('/{note}/attachments', [NoteController::class, 'uploadAttachment'])
        ->name('attachments.upload')->middleware('can:notes.update');
    Route::get('/{note}/attachments/{fileId}/download', [NoteController::class, 'downloadAttachment'])
        ->name('attachments.download');
    Route::delete('/{note}/attachments/{fileId}', [NoteController::class, 'deleteAttachment'])
        ->name('attachments.delete')->middleware('can:notes.update');
});
