<?php

namespace App\Platform\Upgrades;

/**
 * The result of a read-only dry run. The UI renders it as a confirmation
 * dialog and the CLI prints it for `--dry-run`; nothing here has touched
 * the database.
 */
class UpgradePlan
{
    /** @var array<int, AppUpgradeItem> topologically ordered: dependencies first */
    public array $items = [];

    /** @var array<int, string> */
    public array $blockers = [];

    /** @var array<int, string> */
    public array $warnings = [];

    public function addItem(AppUpgradeItem $item): void
    {
        $this->items[] = $item;
    }

    public function block(string $message): void
    {
        if (! in_array($message, $this->blockers, true)) {
            $this->blockers[] = $message;
        }
    }

    public function warn(string $message): void
    {
        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }

    public function isBlocked(): bool
    {
        return $this->blockers !== [];
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function requiresMaintenance(): bool
    {
        foreach ($this->items as $item) {
            if ($item->maintenance) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public function appIds(): array
    {
        return array_map(fn (AppUpgradeItem $item) => $item->appId(), $this->items);
    }

    public function has(string $appId): bool
    {
        return in_array($appId, $this->appIds(), true);
    }

    public function itemFor(string $appId): ?AppUpgradeItem
    {
        foreach ($this->items as $item) {
            if ($item->appId() === $appId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Re-order so that an app never runs before an app it depends on.
     *
     * @param  array<string, array<int, string>>  $dependencies  app id => required app ids
     */
    public function sortTopologically(array $dependencies): void
    {
        $sorted = [];
        $visited = [];

        $visit = function (AppUpgradeItem $item) use (&$visit, &$sorted, &$visited, $dependencies): void {
            $id = $item->appId();

            if (isset($visited[$id])) {
                return;
            }

            // Marking before recursing keeps a dependency cycle from looping
            // forever; the cycle simply falls back to discovery order.
            $visited[$id] = true;

            foreach ($dependencies[$id] ?? [] as $requiredId) {
                $required = $this->itemFor($requiredId);

                if ($required !== null) {
                    $visit($required);
                }
            }

            $sorted[] = $item;
        };

        foreach ($this->items as $item) {
            $visit($item);
        }

        $this->items = $sorted;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'blocked' => $this->isBlocked(),
            'blockers' => $this->blockers,
            'warnings' => $this->warnings,
            'maintenance' => $this->requiresMaintenance(),
            'apps' => array_map(fn (AppUpgradeItem $item) => $item->toArray(), $this->items),
        ];
    }
}
