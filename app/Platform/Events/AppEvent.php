<?php

namespace App\Platform\Events;

use App\Platform\Models\PlatformApp;

abstract class AppEvent
{
    public function __construct(public readonly PlatformApp $app)
    {
    }
}
