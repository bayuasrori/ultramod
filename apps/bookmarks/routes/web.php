<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\Bookmarks\Http\Controllers\BookmarksController;

Route::prefix('bookmarks')->middleware('can:bookmarks.view')->group(function () {
    Route::get('/', [BookmarksController::class, 'index'])->name('bookmarks.index');

    Route::middleware('can:bookmarks.create')->group(function () {
        Route::get('/create', [BookmarksController::class, 'create'])->name('bookmarks.create');
        Route::post('/', [BookmarksController::class, 'store'])->name('bookmarks.store');
    });

    Route::middleware('can:bookmarks.update')->group(function () {
        Route::get('/{bookmark}/edit', [BookmarksController::class, 'edit'])->name('bookmarks.edit');
        Route::put('/{bookmark}', [BookmarksController::class, 'update'])->name('bookmarks.update');
        Route::put('/{bookmark}/favorite', [BookmarksController::class, 'toggleFavorite'])->name('bookmarks.favorite');
    });

    Route::delete('/{bookmark}', [BookmarksController::class, 'destroy'])
        ->name('bookmarks.destroy')->middleware('can:bookmarks.delete');
});
