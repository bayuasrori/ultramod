<?php

namespace Tests\Feature;

use App\Platform\Models\PlatformApp;
use Tests\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class PlatformUiTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('platform:app:discover');
        $this->refreshApplication();
        $this->actingAsAdmin();
    }

    public function test_full_lifecycle_from_the_platform_page(): void
    {
        // discovered: Install + Enable buttons offered, Disable/Uninstall not
        $this->get('/platform')
            ->assertOk()
            ->assertSee('>Install<', false)
            ->assertDontSee('>Disable<', false)
            ->assertDontSee('>Uninstall<', false);

        // install via UI runs the app's migrations
        $this->post(route('platform.apps.install', 'notes'))->assertRedirect(route('platform.index'));
        $this->assertTrue(Schema::hasTable('notes'));
        $this->assertSame(PlatformApp::STATUS_INSTALLED, PlatformApp::where('app_id', 'notes')->value('status'));

        // enable via UI makes the app live
        $this->post(route('platform.apps.enable', 'notes'))->assertRedirect(route('platform.index'));
        $this->refreshApplication();
        $this->actingAsAdmin();
        $this->get('/notes')->assertOk();

        // disable via UI takes the app out of the runtime
        $this->post(route('platform.apps.disable', 'notes'))->assertRedirect(route('platform.index'));
        $this->refreshApplication();
        $this->actingAsAdmin();
        $this->get('/notes')->assertNotFound();

        // uninstall via UI removes registry entry and runs cleanup
        $this->post(route('platform.apps.uninstall', 'notes'))->assertRedirect(route('platform.index'));
        $this->assertFalse(PlatformApp::where('app_id', 'notes')->exists());
        $this->assertFalse(Schema::hasTable('notes'));
    }

    public function test_invalid_transition_shows_flash_error(): void
    {
        // uninstalling an enabled app is rejected with a friendly message
        $this->artisan('platform:app:install', ['app' => 'hello']);
        $this->artisan('platform:app:enable', ['app' => 'hello']);
        $this->refreshApplication();
        $this->actingAsAdmin();

        $this->post(route('platform.apps.uninstall', 'hello'))
            ->assertRedirect(route('platform.index'))
            ->assertSessionHas('error');

        $this->get('/platform')->assertOk()->assertSee('Cannot uninstall app [hello]');
    }
}
