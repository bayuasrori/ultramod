<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('platform.index');
});

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

// Authenticated
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/profile/security', [ProfileController::class, 'security'])->name('profile.security');

    Route::get('/platform', [PlatformController::class, 'index'])->name('platform.index');

    // Admin-only platform management
    Route::middleware('can:platform.manage')->group(function () {
        Route::get('/platform/apps/create', [PlatformController::class, 'create'])->name('platform.apps.create');
        Route::post('/platform/apps/create', [PlatformController::class, 'store'])->name('platform.apps.store');

        Route::prefix('platform/apps/{app}')->name('platform.apps.')->group(function () {
            Route::post('/install', [PlatformController::class, 'install'])->name('install');
            Route::post('/enable', [PlatformController::class, 'enable'])->name('enable');
            Route::post('/disable', [PlatformController::class, 'disable'])->name('disable');
            Route::post('/uninstall', [PlatformController::class, 'uninstall'])->name('uninstall');
        });

        Route::resource('/platform/roles', RoleController::class)->except(['show'])->names('platform.roles');
        Route::resource('/platform/users', UserController::class)->except(['show', 'destroy'])->names('platform.users');
        Route::put('/platform/users/{user}/activate', [UserController::class, 'activate'])->name('platform.users.activate');
        Route::put('/platform/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('platform.users.deactivate');
    });
});
