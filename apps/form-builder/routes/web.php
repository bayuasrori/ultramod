<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\FormBuilder\Http\Controllers\FieldController;
use PlatformApps\FormBuilder\Http\Controllers\FillController;
use PlatformApps\FormBuilder\Http\Controllers\FormController;
use PlatformApps\FormBuilder\Http\Controllers\SubmissionController;

Route::prefix('form-builder')->group(function () {
    // Public forms are accessible without login. Private forms are gated
    // in the controller — this keeps the routes simple and the slug
    // available for the resolution logic.
    Route::get('/f/{slug}', [FillController::class, 'show'])->name('form-builder.fill');
    Route::post('/f/{slug}', [FillController::class, 'store'])->name('form-builder.fill.store');

    // Everything below requires authentication.
    Route::middleware('auth')->group(function () {
        Route::middleware('can:form-builder.view')->group(function () {
            Route::get('/', [FormController::class, 'index'])->name('form-builder.index');
            Route::get('/{form}/submissions', [SubmissionController::class, 'index'])->name('form-builder.submissions');
            Route::get('/{form}/submissions/export', [SubmissionController::class, 'export'])->name('form-builder.submissions.export');
        });

        Route::middleware('can:form-builder.create')->group(function () {
            Route::get('/create', [FormController::class, 'create'])->name('form-builder.create');
            Route::post('/', [FormController::class, 'store'])->name('form-builder.store');
        });

        Route::middleware('can:form-builder.update')->group(function () {
            Route::get('/{form}/edit', [FormController::class, 'edit'])->name('form-builder.edit');
            Route::put('/{form}', [FormController::class, 'update'])->name('form-builder.update');

            Route::get('/{form}/build', [FormController::class, 'build'])->name('form-builder.build');
            Route::post('/{form}/fields', [FieldController::class, 'store'])->name('form-builder.fields.store');
            Route::put('/{form}/fields/{field}', [FieldController::class, 'update'])->name('form-builder.fields.update');
            Route::delete('/{form}/fields/{field}', [FieldController::class, 'destroy'])->name('form-builder.fields.destroy');
            Route::post('/{form}/fields/{field}/move', [FieldController::class, 'move'])->name('form-builder.fields.move');
        });

        Route::middleware('can:form-builder.delete')->group(function () {
            Route::delete('/{form}', [FormController::class, 'destroy'])->name('form-builder.destroy');
            Route::delete('/{form}/submissions/{submission}', [SubmissionController::class, 'destroy'])->name('form-builder.submissions.destroy');
        });
    });
});
