<?php

namespace Tests\Feature;

use App\Platform\Models\PlatformApp;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase as BaseTestCase;

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
        // the dashboard is a launcher: discovered apps are not listed there
        $this->get('/platform')
            ->assertOk()
            ->assertDontSee('>Install<', false)
            ->assertDontSee('>Disable<', false)
            ->assertDontSee('>Uninstall<', false);

        // the Apps page lists every discovered app with the Install button
        $this->get(route('platform.apps.index'))
            ->assertOk()
            ->assertSee('>Install<', false)
            ->assertDontSee('>Disable<', false)
            ->assertDontSee('>Uninstall<', false);

        // install via UI runs the app's migrations
        $this->post(route('platform.apps.install', 'notes'))->assertRedirect(route('platform.apps.index'));
        $this->assertTrue(Schema::hasTable('notes'));
        $this->assertSame(PlatformApp::STATUS_INSTALLED, PlatformApp::where('app_id', 'notes')->value('status'));

        // enable via UI makes the app live
        $this->post(route('platform.apps.enable', 'notes'))->assertRedirect(route('platform.apps.index'));
        $this->refreshApplication();
        $this->actingAsAdmin();
        $this->get('/notes')->assertOk();

        // an installed app shows up as a card on the dashboard
        $this->get('/platform')
            ->assertOk()
            ->assertSee('notes', false);

        // disable via UI takes the app out of the runtime
        $this->post(route('platform.apps.disable', 'notes'))->assertRedirect(route('platform.apps.index'));
        $this->refreshApplication();
        $this->actingAsAdmin();
        $this->get('/notes')->assertNotFound();

        // uninstall via UI removes registry entry and runs cleanup
        $this->post(route('platform.apps.uninstall', 'notes'))->assertRedirect(route('platform.apps.index'));
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
            ->assertRedirect(route('platform.apps.index'))
            ->assertSessionHas('error');

        $this->get('/platform')->assertOk()->assertSee('Cannot uninstall app [hello]');
    }
}
