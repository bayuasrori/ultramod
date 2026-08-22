<?php

namespace PlatformApps\Antrian\Http\Controllers;

use App\Platform\Models\PlatformApp;
use Illuminate\Routing\Controller;

class AntrianController extends Controller
{
    public function index()
    {
        return view('antrian::antrian', [
            'app' => PlatformApp::where('app_id', 'antrian')->firstOrFail(),
        ]);
    }
}
