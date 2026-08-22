<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Platform\Services\AppManager;
use App\Platform\Models\PlatformApp;

class ArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_dependencies_prevent_disable()
    {
        $manager = $this->app->make(AppManager::class);
        $manager->discover();

        $manager->install('notes');
        $manager->enable('notes');

        $manager->install('notes-status');
        $manager->enable('notes-status');

        $this->expectException(\Exception::class);
        $manager->disable('notes');
    }
}
