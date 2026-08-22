<?php

namespace App\Console\Commands;

use App\Platform\Exceptions\AppException;
use App\Platform\Services\AppManager;
use Illuminate\Console\Command;

class AppEnableCommand extends Command
{
    protected $signature = 'platform:app:enable {app : App ID}';

    protected $description = 'Enable an installed or disabled app';

    public function handle(AppManager $manager): int
    {
        try {
            $app = $manager->enable($this->argument('app'));
        } catch (AppException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("App [{$app->app_id}] enabled.");

        return self::SUCCESS;
    }
}
