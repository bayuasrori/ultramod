<?php

namespace Tests\Feature;

use App\Platform\Models\PlatformApp;
use Tests\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class AppWizardTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('platform:app:discover');
        $this->refreshApplication();
        $this->actingAsAdmin();
    }

    protected function tearDown(): void
    {
        if (is_dir(base_path('apps/inventory'))) {
            File::deleteDirectory(base_path('apps/inventory'));
        }

        PlatformApp::where('app_id', 'inventory')->delete();

        $composerFile = base_path('composer.json');
        $composer = json_decode((string) file_get_contents($composerFile), true);
        unset($composer['autoload']['psr-4']['PlatformApps\\Inventory\\']);
        file_put_contents(
            $composerFile,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        parent::tearDown();
    }

    public function test_wizard_scaffolds_and_activates_a_new_app(): void
    {
        $this->get(route('platform.apps.create'))
            ->assertOk()
            ->assertSee('Create a new app');

        $this->post(route('platform.apps.store'), ['name' => 'inventory', 'activate' => '1'])
            ->assertRedirect(route('platform.index'))
            ->assertSessionHas('status');

        // skeleton created
        $this->assertFileExists(base_path('apps/inventory/platform.json'));
        $this->assertFileExists(base_path('apps/inventory/src/InventoryServiceProvider.php'));
        $this->assertFileExists(base_path('apps/inventory/routes/web.php'));

        // registered, installed and enabled
        $app = PlatformApp::where('app_id', 'inventory')->firstOrFail();
        $this->assertSame(PlatformApp::STATUS_ENABLED, $app->status);

        // manually require the newly scaffolded files since composer autoloader is already cached in this process
        require_once base_path('apps/inventory/src/InventoryServiceProvider.php');
        require_once base_path('apps/inventory/src/Http/Controllers/InventoryController.php');

        // the new app is live: its route responds and its menu is listed
        $this->refreshApplication();
        $this->actingAsAdmin();
        $this->get('/inventory')->assertOk()->assertSee('Hello from Inventory App!');
        $this->get('/platform')->assertOk()->assertSee('Inventory');

        // composer.json got the new namespace
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $this->assertArrayHasKey('PlatformApps\\Inventory\\', $composer['autoload']['psr-4']);
    }

    public function test_wizard_rejects_invalid_names(): void
    {
        $this->post(route('platform.apps.store'), ['name' => 'Not Valid!'])
            ->assertSessionHasErrors('name');

        $this->post(route('platform.apps.store'), ['name' => 'notes'])
            ->assertRedirect(route('platform.apps.create'))
            ->assertSessionHas('error');
    }
}
