<?php

namespace App\Platform\Exceptions;

class UpgradeFailedException extends AppException
{
    /** @param array<int, string> $blockers */
    public static function blocked(array $blockers): self
    {
        return new self('Upgrade cannot run: '.implode(' ', $blockers));
    }

    public static function step(string $appId, string $step, string $reason): self
    {
        return new self("Upgrade of [{$appId}] failed at step [{$step}]: {$reason}");
    }
}
