<?php

use Illuminate\Support\Facades\Route;
use PlatformApps\Customers\Http\Controllers\CustomersController;

Route::resource('/customers', CustomersController::class)->except(['show'])->names('customers');
