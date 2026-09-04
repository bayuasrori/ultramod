<?php

namespace App\Console\Commands;

use App\Platform\Services\AppManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class AppTestCommand extends Command
{
    protected $signature = 'platform:app:test {app_id : The ID of the app}';
    protected $description = 'Run tests for a specific platform app';

    public function handle(AppManager $manager): int
    {
        $appId = $this->argument('app_id');
        $path = $manager->appsPath() . DIRECTORY_SEPARATOR . $appId . DIRECTORY_SEPARATOR . 'tests';

        if (!is_dir($path)) {
            $this->error("No tests directory found for app [{$appId}] at {$path}");
            return self::FAILURE;
        }

        $this->info("Running tests for [{$appId}]...");

        $result = Process::run("php artisan test {$path}");

        if ($result->successful()) {
            $this->info($result->output());
            return self::SUCCESS;
        }

        $this->error($result->output());
        return self::FAILURE;
    }
}
