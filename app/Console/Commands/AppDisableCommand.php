<?php

namespace App\Console\Commands;

use App\Platform\Exceptions\AppException;
use App\Platform\Services\AppManager;
use Illuminate\Console\Command;

class AppDisableCommand extends Command
{
    protected $signature = 'platform:app:disable {app : App ID}';

    protected $description = 'Disable an enabled app (source code is kept)';

    public function handle(AppManager $manager): int
    {
        try {
            $app = $manager->disable($this->argument('app'));
        } catch (AppException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("App [{$app->app_id}] disabled. Its routes and services are inactive from the next request.");

        return self::SUCCESS;
    }
}
