<?php

namespace App\Console\Commands;

use App\Platform\Exceptions\AppException;
use App\Platform\Services\AppUpgrader;
use App\Platform\Upgrades\AppUpgradeItem;
use App\Platform\Upgrades\UpgradePlan;
use Illuminate\Console\Command;

class AppUpgradeCommand extends Command
{
    protected $signature = 'platform:app:upgrade
        {app? : App ID}
        {--all : Upgrade every app that has a newer version available}
        {--dry-run : Show what would happen without changing anything}
        {--force : Re-apply migrations and permissions even when the version has not moved}';

    protected $description = 'Upgrade an app: run its pending migrations, versioned upgrade steps and permission sync';

    public function handle(AppUpgrader $upgrader): int
    {
        $appId = $this->argument('app');

        if ($appId === null && ! $this->option('all')) {
            $this->error('Pass an app ID or --all.');

            return self::FAILURE;
        }

        try {
            $plan = $this->option('all') && $appId === null
                ? $upgrader->planOutdated()
                : $upgrader->plan((string) $appId, (bool) $this->option('force'));
        } catch (AppException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->renderPlan($plan);

        if ($plan->isBlocked()) {
            return self::FAILURE;
        }

        if ($plan->isEmpty()) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run — nothing was changed.');

            return self::SUCCESS;
        }

        try {
            $upgrader->execute($plan);
        } catch (AppException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($plan->items as $item) {
            $this->info("App [{$item->appId()}] upgraded to {$item->toVersion}.");
        }

        return self::SUCCESS;
    }

    protected function renderPlan(UpgradePlan $plan): void
    {
        foreach ($plan->warnings as $warning) {
            $this->comment($warning);
        }

        foreach ($plan->blockers as $blocker) {
            $this->error($blocker);
        }

        if ($plan->isEmpty()) {
            return;
        }

        $this->table(
            ['App', 'From', 'To', 'Reason', 'Migrations', 'Steps', 'Permissions'],
            array_map(fn (AppUpgradeItem $item) => [
                $item->appId(),
                $item->fromVersion,
                $item->toVersion,
                $item->reason,
                count($item->pendingMigrations),
                count($item->steps),
                '+'.count($item->permissionsAdded).' / -'.count($item->permissionsRemoved),
            ], $plan->items),
        );
    }
}
