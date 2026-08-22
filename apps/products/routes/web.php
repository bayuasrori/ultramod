<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\Products\Http\Controllers\ProductsController;

Route::resource('/products', ProductsController::class)->except(['show'])->names('products');
