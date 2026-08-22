<?php

namespace App\Console\Commands;

use App\Platform\Services\AppManager;
use Illuminate\Console\Command;

class AppUpdateCommand extends Command
{
    protected $signature = 'platform:app:update {app : App ID}';
    protected $description = 'Update an installed app, running its new migrations and syncing permissions';

    public function handle(AppManager $manager): int
    {
        $appId = $this->argument('app');

        try {
            $app = $manager->update($appId);
            $this->info("App [{$appId}] successfully updated to version {$app->version}.");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
