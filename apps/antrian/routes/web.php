<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\Antrian\Http\Controllers\AntrianController;

Route::get('/antrian', [AntrianController::class, 'index'])->name('antrian.index');
