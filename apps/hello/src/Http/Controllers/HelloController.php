<?php

namespace PlatformApps\Hello\Http\Controllers;

use App\Platform\Models\PlatformApp;
use Illuminate\Routing\Controller;

class HelloController extends Controller
{
    public function index()
    {
        $app = PlatformApp::where('app_id', 'hello')->firstOrFail();

        return view('hello::hello', [
            'app' => $app,
        ]);
    }
}
