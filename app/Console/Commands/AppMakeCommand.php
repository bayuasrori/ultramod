<?php

namespace App\Console\Commands;

use App\Platform\Exceptions\AppException;
use App\Platform\Services\AppScaffolder;
use Illuminate\Console\Command;

class AppMakeCommand extends Command
{
    protected $signature = 'platform:make-app {name : App ID (e.g. weather)}';

    protected $description = 'Scaffold a new platform application skeleton';

    public function handle(AppScaffolder $scaffolder): int
    {
        try {
            $app = $scaffolder->scaffold($this->argument('name'));
        } catch (AppException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("App [{$app['id']}] created at {$app['path']}.");
        $this->line('Next steps:');
        $this->line('  php artisan app:discover');
        $this->line("  php artisan app:install {$app['id']}");
        $this->line("  php artisan app:enable {$app['id']}");

        return self::SUCCESS;
    }
}
