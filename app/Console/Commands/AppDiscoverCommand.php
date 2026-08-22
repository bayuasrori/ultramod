<?php

namespace App\Console\Commands;

use App\Platform\Services\AppManager;
use Illuminate\Console\Command;

class AppDiscoverCommand extends Command
{
    protected $signature = 'platform:app:discover';

    protected $description = 'Discover apps in the apps directory and register them';

    public function handle(AppManager $manager): int
    {
        $manifests = $manager->discover();

        $this->info('Discovered '.count($manifests).' app(s):');

        foreach ($manifests as $manifest) {
            $this->line("  - {$manifest->id} ({$manifest->name} v{$manifest->version})");
        }

        return self::SUCCESS;
    }
}
