<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\Hello\Http\Controllers\HelloController;

Route::get('/hello', [HelloController::class, 'index'])->name('hello.index');
