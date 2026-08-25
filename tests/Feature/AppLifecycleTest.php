<?php

namespace Tests\Feature;

use App\Platform\Models\PlatformApp;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase as BaseTestCase;

class AppLifecycleTest extends BaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        $db = __DIR__.'/../../database/testing.sqlite';
        if (file_exists($db)) {
            unlink($db);
        }
        touch($db);

        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Fresh platform tables per test; app state is managed explicitly
        // by each scenario to mirror the real lifecycle.
        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('platform:app:discover');
        $this->actingAsAdmin();
    }

    protected function tearDown(): void
    {
        $this->app = null;
        parent::tearDown();
    }

    /**
     * Rebuild the application so that the set of enabled apps is loaded
     * again from the registry — the same effect as the next request.
     */
    protected function nextRequest(): void
    {
        $this->refreshApplication();
        $this->actingAsAdmin();
    }

    public function test_hello_route_is_unavailable_when_disabled(): void
    {
        $this->artisan('platform:app:install', ['app' => 'hello'])->assertSuccessful();
        $this->artisan('platform:app:enable', ['app' => 'hello'])->assertSuccessful();

        $this->nextRequest();
        $this->get('/hello')->assertOk()->assertSee('Hello from Platform App!');

        $this->artisan('platform:app:disable', ['app' => 'hello'])->assertSuccessful();

        $this->nextRequest();
        $this->get('/hello')->assertNotFound();
    }

    public function test_hello_can_be_enabled_again(): void
    {
        $this->artisan('platform:app:install', ['app' => 'hello'])->assertSuccessful();
        $this->artisan('platform:app:enable', ['app' => 'hello'])->assertSuccessful();
        $this->artisan('platform:app:disable', ['app' => 'hello'])->assertSuccessful();

        $this->artisan('platform:app:enable', ['app' => 'hello'])->assertSuccessful();

        $this->nextRequest();
        $this->get('/hello')->assertOk();
    }

    public function test_notes_install_runs_migration_and_crud_works(): void
    {
        $this->artisan('platform:app:install', ['app' => 'notes'])->assertSuccessful();
        $this->artisan('platform:app:enable', ['app' => 'notes'])->assertSuccessful();

        $this->assertTrue(Schema::hasTable('notes'));
        $this->assertDatabaseHas('migrations', ['migration' => '2026_08_18_000001_create_notes_table']);
        $this->assertTrue(PlatformApp::where('app_id', 'notes')->firstOrFail()->permissions()->count() === 4);

        $this->nextRequest();

        $this->get('/notes')->assertOk();
        $this->post('/notes', ['title' => 'First note', 'content' => 'Hello'])->assertRedirect('/notes/1');
        $this->get('/notes')->assertSee('First note');

        $note = \DB::table('notes')->first();
        $this->put("/notes/{$note->id}", ['title' => 'Updated', 'content' => 'Hello'])->assertRedirect("/notes/{$note->id}");
        $this->get('/notes')->assertSee('Updated');

        $this->delete("/notes/{$note->id}")->assertRedirect('/notes');
        $this->get('/notes')->assertDontSee('Updated');
    }

    public function test_notes_validation_rejects_empty_title(): void
    {
        $this->artisan('platform:app:install', ['app' => 'notes'])->assertSuccessful();
        $this->artisan('platform:app:enable', ['app' => 'notes'])->assertSuccessful();
        $this->nextRequest();

        $this->post('/notes', ['title' => '', 'content' => 'x'])->assertSessionHasErrors('title');
    }

    public function test_disabling_notes_hides_route_but_keeps_data(): void
    {
        $this->artisan('platform:app:install', ['app' => 'notes'])->assertSuccessful();
        $this->artisan('platform:app:enable', ['app' => 'notes'])->assertSuccessful();
        $this->nextRequest();

        $this->post('/notes', ['title' => 'Keep me', 'content' => 'x'])->assertRedirect();
        $this->artisan('platform:app:disable', ['app' => 'notes'])->assertSuccessful();

        $this->nextRequest();
        $this->get('/notes')->assertNotFound();

        $this->artisan('platform:app:enable', ['app' => 'notes'])->assertSuccessful();
        $this->nextRequest();

        $this->get('/notes')->assertOk()->assertSee('Keep me');
        $this->assertSame(1, \DB::table('notes')->count());
    }

    public function test_uninstall_removes_registry_entry_and_runs_cleanup(): void
    {
        $this->artisan('platform:app:install', ['app' => 'notes'])->assertSuccessful();
        $this->artisan('platform:app:enable', ['app' => 'notes'])->assertSuccessful();
        $this->artisan('platform:app:disable', ['app' => 'notes'])->assertSuccessful();

        $this->artisan('platform:app:uninstall', ['app' => 'notes'])->assertSuccessful();

        $this->assertFalse(PlatformApp::where('app_id', 'notes')->exists());
        $this->assertFalse(Schema::hasTable('notes'));
    }

    public function test_uninstall_is_rejected_for_enabled_app(): void
    {
        $this->artisan('platform:app:install', ['app' => 'hello'])->assertSuccessful();
        $this->artisan('platform:app:enable', ['app' => 'hello'])->assertSuccessful();

        $this->artisan('platform:app:uninstall', ['app' => 'hello'])->assertFailed();
    }

    /**
     * The app shortcuts live in the sidebar, which every page carries except
     * the launcher itself — so the catalogue page is where it is asserted.
     */
    public function test_platform_menu_shows_only_enabled_apps(): void
    {
        $this->nextRequest();
        $this->get('/platform/apps')->assertOk()
            ->assertDontSee('>Notes<', false)
            ->assertDontSee('>Hello<', false);

        $this->artisan('platform:app:install', ['app' => 'hello'])->assertSuccessful();
        $this->artisan('platform:app:install', ['app' => 'notes'])->assertSuccessful();
        $this->artisan('platform:app:enable', ['app' => 'hello'])->assertSuccessful();
        $this->artisan('platform:app:enable', ['app' => 'notes'])->assertSuccessful();

        $this->nextRequest();
        $this->get('/platform/apps')->assertOk()
            ->assertSee('>Hello<', false)
            ->assertSee('>Notes<', false);

        $this->artisan('platform:app:disable', ['app' => 'notes'])->assertSuccessful();
        $this->nextRequest();
        $this->get('/platform/apps')->assertDontSee('>Notes<', false);
    }

    public function test_the_launcher_carries_no_sidebar_of_its_own(): void
    {
        $this->artisan('platform:app:install', ['app' => 'hello'])->assertSuccessful();
        $this->artisan('platform:app:enable', ['app' => 'hello'])->assertSuccessful();

        $this->nextRequest();

        $this->get('/platform')->assertOk()->assertDontSee('id="appSidebar"', false);
        $this->get('/platform/apps')->assertOk()->assertSee('id="appSidebar"', false);
    }
}
