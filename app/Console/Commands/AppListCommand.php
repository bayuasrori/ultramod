<?php

namespace App\Console\Commands;

use App\Platform\Models\PlatformApp;
use Illuminate\Console\Command;

class AppListCommand extends Command
{
    protected $signature = 'platform:app:list';

    protected $description = 'List all registered platform apps and their status';

    public function handle(): int
    {
        $apps = PlatformApp::query()->orderBy('app_id')->get();

        if ($apps->isEmpty()) {
            $this->info('No apps discovered yet. Run `php artisan platform:app:discover`.');

            return self::SUCCESS;
        }

        $this->table(
            ['App ID', 'Name', 'Version', 'Status', 'Installed At'],
            $apps->map(fn (PlatformApp $app) => [
                $app->app_id,
                $app->name,
                $app->version,
                $app->status,
                $app->installed_at?->format('Y-m-d H:i') ?? '-',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
