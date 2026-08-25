<?php

namespace App\Platform\Upgrades;

use App\Platform\Models\PlatformApp;

/**
 * Everything an upgrade step is allowed to know about the run it is part of.
 */
class UpgradeContext
{
    /** @var array<int, string> */
    protected array $messages = [];

    public function __construct(
        public readonly PlatformApp $app,
        public readonly string $fromVersion,
        public readonly string $toVersion,
        public readonly string $stepVersion,
    ) {}

    /**
     * Record a line for the upgrade log shown in the UI and the console.
     */
    public function info(string $message): void
    {
        $this->messages[] = $message;
    }

    /** @return array<int, string> */
    public function messages(): array
    {
        return $this->messages;
    }
}
