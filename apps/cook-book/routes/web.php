<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\CookBook\Http\Controllers\CookBookController;

Route::get('/cook-book', [CookBookController::class, 'index'])->name('cook-book.index');
