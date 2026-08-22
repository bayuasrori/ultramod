<?php

namespace App\Console\Commands;

use App\Platform\Exceptions\AppException;
use App\Platform\Services\AppManager;
use Illuminate\Console\Command;

class AppUninstallCommand extends Command
{
    protected $signature = 'platform:app:uninstall {app : App ID}';

    protected $description = 'Uninstall a disabled app (removes registry entry, runs app cleanup)';

    public function handle(AppManager $manager): int
    {
        try {
            $app = $manager->uninstall($this->argument('app'));
        } catch (AppException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("App [{$app->app_id}] uninstalled.");

        return self::SUCCESS;
    }
}
