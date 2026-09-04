<?php

namespace App\Console\Commands;

use App\Platform\Services\AppManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AppSeedCommand extends Command
{
    protected $signature = 'platform:app:seed {app_id : The ID of the app} {--class=DatabaseSeeder : The class name of the root seeder}';
    protected $description = 'Seed the database with records for a specific app';

    public function handle(AppManager $manager): int
    {
        $appId = $this->argument('app_id');
        $class = $this->option('class');
        
        $studly = Str::studly($appId);
        $seederClass = "PlatformApps\\{$studly}\\Database\\Seeders\\{$class}";
        
        $path = $manager->appPath($appId) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders' . DIRECTORY_SEPARATOR . $class . '.php';

        if (!is_file($path)) {
            $this->warn("No seeder found for app [{$appId}] at {$path}");
            return self::SUCCESS;
        }

        require_once $path;

        if (!class_exists($seederClass)) {
            $this->error("Seeder class [{$seederClass}] not found in {$path}");
            return self::FAILURE;
        }

        $this->info("Seeding [{$appId}] using {$seederClass}...");
        
        $seeder = new $seederClass();
        $seeder->setContainer($this->laravel)->setCommand($this);
        $seeder->run();

        $this->info("Seeded successfully.");

        return self::SUCCESS;
    }
}
