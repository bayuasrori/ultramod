<?php

namespace App\Console\Commands;

use App\Platform\Exceptions\AppException;
use App\Platform\Services\AppManager;
use Illuminate\Console\Command;

class AppInstallCommand extends Command
{
    protected $signature = 'platform:app:install {app : App ID}';

    protected $description = 'Install an app (runs its migrations and stores its permissions)';

    public function handle(AppManager $manager): int
    {
        try {
            $app = $manager->install($this->argument('app'));
        } catch (AppException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("App [{$app->app_id}] installed.");

        return self::SUCCESS;
    }
}
