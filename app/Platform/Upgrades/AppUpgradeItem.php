<?php

namespace App\Platform\Upgrades;

use App\Platform\Models\PlatformApp;

/**
 * One app's slice of an upgrade plan: where it is, where it is going, and
 * exactly what will be executed to get there.
 */
class AppUpgradeItem
{
    public const REASON_REQUESTED = 'requested';

    public const REASON_DEPENDENCY = 'dependency';

    /**
     * @param  array<int, string>  $pendingMigrations
     * @param  array<int, array{version: string, phase: string, class: class-string}>  $steps
     * @param  array<int, string>  $permissionsAdded
     * @param  array<int, string>  $permissionsRemoved
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly PlatformApp $app,
        public readonly string $fromVersion,
        public readonly string $toVersion,
        public readonly string $reason,
        public readonly array $pendingMigrations = [],
        public readonly array $steps = [],
        public readonly array $permissionsAdded = [],
        public readonly array $permissionsRemoved = [],
        public readonly array $warnings = [],
        public readonly bool $maintenance = false,
    ) {}

    public function appId(): string
    {
        return $this->app->app_id;
    }

    public function isReapply(): bool
    {
        return version_compare($this->toVersion, $this->fromVersion, '<=');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'app_id' => $this->appId(),
            'name' => $this->app->name,
            'from_version' => $this->fromVersion,
            'to_version' => $this->toVersion,
            'reason' => $this->reason,
            'reapply' => $this->isReapply(),
            'pending_migrations' => $this->pendingMigrations,
            // The absolute path of each step file stays server-side.
            'steps' => array_map(
                fn (array $step) => ['version' => $step['version'], 'phase' => $step['phase'], 'step' => $step['step']],
                $this->steps,
            ),
            'permissions_added' => $this->permissionsAdded,
            'permissions_removed' => $this->permissionsRemoved,
            'warnings' => $this->warnings,
        ];
    }
}
