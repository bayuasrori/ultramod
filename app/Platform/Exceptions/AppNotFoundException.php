<?php

namespace App\Platform\Exceptions;

class AppNotFoundException extends AppException
{
    public static function forId(string $appId): self
    {
        return new self("App [{$appId}] was not found. Run `php artisan app:discover` first.");
    }
}
