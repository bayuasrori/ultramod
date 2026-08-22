<?php

namespace PlatformApps\CookBook\Http\Controllers;

use App\Platform\Models\PlatformApp;
use Illuminate\Routing\Controller;

class CookBookController extends Controller
{
    public function index()
    {
        return view('cook-book::cook-book', [
            'app' => PlatformApp::where('app_id', 'cook-book')->firstOrFail(),
        ]);
    }
}
