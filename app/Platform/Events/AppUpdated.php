<?php

namespace App\Platform\Events;

use App\Platform\Models\PlatformApp;
use Illuminate\Foundation\Events\Dispatchable;

class AppUpdated
{
    use Dispatchable;

    public function __construct(public PlatformApp $app, public string $oldVersion, public string $newVersion)
    {
    }
}
