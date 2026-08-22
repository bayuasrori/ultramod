<?php

namespace App\Platform\Exceptions;

use App\Platform\Models\PlatformApp;

class InvalidAppStateException extends AppException
{
    public static function transition(PlatformApp $app, string $action): self
    {
        return new self(
            "Cannot {$action} app [{$app->app_id}]: current status is [{$app->status}]."
        );
    }
}
