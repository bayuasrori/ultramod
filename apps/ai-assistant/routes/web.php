<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\AiAssistant\Http\Controllers\ChatController;
use PlatformApps\AiAssistant\Http\Controllers\ConversationController;

Route::prefix('ai')->middleware(['can:ai.view'])->group(function () {
    Route::get('/', [ConversationController::class, 'index'])->name('ai-assistant.index');

    Route::middleware('can:ai.chat')->group(function () {
        Route::get('/conversations/create', [ConversationController::class, 'create'])->name('ai-assistant.create');
        Route::post('/conversations', [ConversationController::class, 'store'])->name('ai-assistant.store');
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('ai-assistant.show');
        Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send'])->name('ai-assistant.send');
        Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('ai-assistant.destroy');
    });

    Route::get('/settings', [ChatController::class, 'settings'])->name('ai-assistant.settings');
    Route::put('/settings', [ChatController::class, 'updateSettings'])->name('ai-assistant.settings.update');
});
