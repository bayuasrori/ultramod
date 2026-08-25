<?php

namespace Tests\Feature;

use App\Platform\Exceptions\UpgradeFailedException;
use App\Platform\Models\PlatformApp;
use App\Platform\Models\PlatformAppPermission;
use App\Platform\Models\PlatformAppUpgrade;
use App\Platform\Models\PlatformRole;
use App\Platform\Services\AppManager;
use App\Platform\Services\AppUpgrader;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The upgrade pipeline is exercised against throwaway apps written to a
 * temporary apps directory, because the interesting scenarios all require
 * the manifest on disk to change between two points in time.
 */
class AppUpgradeTest extends TestCase
{
    protected string $appsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Each test gets its own fixture tree. PHPUnit runs the whole suite in
        // one process and opcache only revalidates a path every couple of
        // seconds, so reusing file names across tests would execute stale code.
        $this->appsPath = 'storage/framework/testing/apps/'.$this->name();

        File::deleteDirectory($this->fullPath());
        File::makeDirectory($this->fullPath(), 0755, true);

        config(['platform.apps_path' => $this->appsPath]);

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->actingAsAdmin();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fullPath());

        parent::tearDown();
    }

    // ---------------------------------------------------------------- setup

    protected function fullPath(string $suffix = ''): string
    {
        return base_path($this->appsPath.($suffix === '' ? '' : '/'.$suffix));
    }

    protected function logPath(): string
    {
        return base_path($this->appsPath.'/run.log');
    }

    /** @return array<int, string> */
    protected function log(): array
    {
        return is_file($this->logPath())
            ? array_values(array_filter(explode("\n", file_get_contents($this->logPath()))))
            : [];
    }

    /** @param array<string, mixed> $overrides */
    protected function writeManifest(string $id, array $overrides = []): void
    {
        File::ensureDirectoryExists($this->fullPath($id));

        File::put($this->fullPath($id).'/platform.json', json_encode(array_merge([
            'id' => $id,
            'name' => ucfirst($id),
            'version' => '1.0.0',
            'description' => 'fixture',
            'provider' => 'PlatformApps\\Fixture\\'.ucfirst($id).'ServiceProvider',
            'requires' => ['platform' => '^1.0'],
            'permissions' => [$id.'.view', $id.'.create'],
        ], $overrides), JSON_PRETTY_PRINT));
    }

    protected function writeMigration(string $id, string $name, string $table, string $marker): void
    {
        File::ensureDirectoryExists($this->fullPath($id).'/database/migrations');

        File::put($this->fullPath($id)."/database/migrations/{$name}.php", <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                file_put_contents('{$this->logPath()}', "{$marker}\\n", FILE_APPEND);

                Schema::create('{$table}', function (Blueprint \$table) {
                    \$table->id();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };
        PHP);
    }

    protected function writeStep(string $id, string $version, string $phase, string $marker, bool $fails = false): void
    {
        $file = $phase === 'pre' ? 'PreUpgrade' : 'PostUpgrade';
        File::ensureDirectoryExists($this->fullPath($id)."/upgrades/{$version}");

        $body = $fails
            ? "throw new \\RuntimeException('{$marker} exploded');"
            : "file_put_contents('{$this->logPath()}', \"{$marker}\\n\", FILE_APPEND);";

        File::put($this->fullPath($id)."/upgrades/{$version}/{$file}.php", <<<PHP
        <?php

        return new class implements \\App\\Platform\\Contracts\\UpgradeStep
        {
            public function run(\\App\\Platform\\Upgrades\\UpgradeContext \$context): void
            {
                {$body}
                \$context->info('{$marker}');
            }
        };
        PHP);
    }

    protected function manager(): AppManager
    {
        return $this->app->make(AppManager::class);
    }

    protected function upgrader(): AppUpgrader
    {
        return $this->app->make(AppUpgrader::class);
    }

    protected function installDemo(): PlatformApp
    {
        $this->writeManifest('demo');
        $this->writeMigration('demo', '2026_01_01_000000_create_demo_items', 'demo_items', 'migration:1.0');

        $this->manager()->discover();
        $this->manager()->install('demo');

        // The install migration writes to the same log the upgrade steps use;
        // only what happens during the upgrade is interesting.
        @unlink($this->logPath());

        return PlatformApp::where('app_id', 'demo')->first();
    }

    // ----------------------------------------------------------- discovery

    public function test_discovery_reports_the_available_version_without_touching_the_installed_one(): void
    {
        $app = $this->installDemo();
        $this->assertSame('1.0.0', $app->version);
        $this->assertFalse($app->hasUpgrade());

        $this->writeManifest('demo', ['version' => '1.1.0']);
        $this->manager()->discover();

        $app->refresh();
        $this->assertSame('1.0.0', $app->version, 'installed version must survive discovery');
        $this->assertSame('1.1.0', $app->available_version);
        $this->assertTrue($app->hasUpgrade());
    }

    public function test_a_discovered_app_tracks_the_manifest_version(): void
    {
        $this->writeManifest('demo', ['version' => '2.0.0']);
        $this->manager()->discover();

        $app = PlatformApp::where('app_id', 'demo')->first();

        $this->assertSame('2.0.0', $app->version);
        $this->assertFalse($app->hasUpgrade(), 'an uninstalled app is not upgradable');
    }

    // ------------------------------------------------------------- pipeline

    public function test_upgrade_runs_pending_migrations_and_bumps_the_version(): void
    {
        $app = $this->installDemo();

        $this->writeManifest('demo', ['version' => '1.1.0']);
        $this->writeMigration('demo', '2026_02_01_000000_create_demo_tags', 'demo_tags', 'migration:1.1');
        $this->manager()->discover();

        $this->upgrader()->upgrade('demo');

        $this->assertTrue(Schema::hasTable('demo_tags'));
        $this->assertSame('1.1.0', $app->refresh()->version);
        $this->assertNotNull($app->upgraded_at);
        $this->assertNull($app->last_upgrade_error);
    }

    public function test_steps_run_oldest_version_first_with_migrations_between_pre_and_post(): void
    {
        $this->installDemo();

        $this->writeManifest('demo', ['version' => '1.2.0']);
        $this->writeMigration('demo', '2026_02_01_000000_create_demo_tags', 'demo_tags', 'migration');
        $this->writeStep('demo', '1.1.0', 'pre', 'pre:1.1');
        $this->writeStep('demo', '1.1.0', 'post', 'post:1.1');
        $this->writeStep('demo', '1.2.0', 'pre', 'pre:1.2');
        $this->writeStep('demo', '1.2.0', 'post', 'post:1.2');
        $this->manager()->discover();

        $this->upgrader()->upgrade('demo');

        $this->assertSame(
            ['pre:1.1', 'pre:1.2', 'migration', 'post:1.1', 'post:1.2'],
            $this->log(),
        );
    }

    public function test_steps_of_the_installed_version_are_not_replayed(): void
    {
        $this->installDemo();

        $this->writeManifest('demo', ['version' => '1.1.0']);
        $this->writeStep('demo', '1.0.0', 'post', 'post:1.0');
        $this->writeStep('demo', '1.1.0', 'post', 'post:1.1');
        $this->manager()->discover();

        $this->upgrader()->upgrade('demo');

        $this->assertSame(['post:1.1'], $this->log());
    }

    public function test_a_step_that_already_succeeded_is_skipped_on_a_reapply(): void
    {
        $this->installDemo();

        $this->writeManifest('demo', ['version' => '1.1.0']);
        $this->writeStep('demo', '1.1.0', 'post', 'post:1.1');
        $this->manager()->discover();

        $this->upgrader()->upgrade('demo');
        $this->upgrader()->upgrade('demo', force: true);

        $this->assertSame(['post:1.1'], $this->log(), 'the step must not run twice');
        $this->assertDatabaseHas('platform_app_upgrades', [
            'app_id' => 'demo',
            'to_version' => '1.1.0',
            'step' => 'upgrades/1.1.0/PostUpgrade.php',
            'status' => PlatformAppUpgrade::STATUS_SUCCESS,
        ]);
    }

    public function test_an_up_to_date_app_is_refused_unless_forced(): void
    {
        $this->installDemo();

        $plan = $this->upgrader()->plan('demo');

        $this->assertTrue($plan->isEmpty());
        $this->assertContains('App [Demo] is already at version 1.0.0.', $plan->warnings);

        $this->expectException(UpgradeFailedException::class);
        $this->upgrader()->execute($plan);
    }

    // ------------------------------------------------------------- failures

    public function test_a_failing_step_leaves_the_version_alone_and_rolls_the_migration_back(): void
    {
        $app = $this->installDemo();

        $this->writeManifest('demo', ['version' => '1.1.0']);
        $this->writeMigration('demo', '2026_02_01_000000_create_demo_tags', 'demo_tags', 'migration:1.1');
        $this->writeStep('demo', '1.1.0', 'post', 'post:1.1', fails: true);
        $this->manager()->discover();

        try {
            $this->upgrader()->upgrade('demo');
            $this->fail('the upgrade should have failed');
        } catch (UpgradeFailedException $e) {
            $this->assertStringContainsString('exploded', $e->getMessage());
        }

        $app->refresh();
        $this->assertSame('1.0.0', $app->version, 'a failed upgrade must not bump the version');
        $this->assertStringContainsString('exploded', (string) $app->last_upgrade_error);
        $this->assertFalse(Schema::hasTable('demo_tags'), 'the migration batch must be rolled back');
        $this->assertDatabaseHas('platform_app_upgrades', [
            'app_id' => 'demo',
            'step' => 'upgrades/1.1.0/PostUpgrade.php',
            'status' => PlatformAppUpgrade::STATUS_FAILED,
        ]);
    }

    // ---------------------------------------------------------- permissions

    public function test_role_permission_assignments_survive_an_upgrade(): void
    {
        $this->installDemo();

        $member = PlatformRole::where('slug', 'member')->first();
        $create = PlatformAppPermission::where('app_id', 'demo')->where('name', 'demo.create')->first();
        $member->permissions()->syncWithoutDetaching([$create->id]);

        $this->writeManifest('demo', [
            'version' => '1.1.0',
            'permissions' => ['demo.view', 'demo.create', 'demo.delete'],
        ]);
        $this->manager()->discover();

        $this->upgrader()->upgrade('demo');

        $this->assertDatabaseHas('platform_role_permissions', [
            'platform_role_id' => $member->id,
            'platform_app_permission_id' => $create->id,
        ]);
        $this->assertSame(
            $create->id,
            PlatformAppPermission::where('app_id', 'demo')->where('name', 'demo.create')->value('id'),
            'existing permissions must keep their id',
        );
        $this->assertDatabaseHas('platform_app_permissions', ['app_id' => 'demo', 'name' => 'demo.delete']);
    }

    public function test_a_permission_dropped_from_the_manifest_is_removed_with_its_pivot_rows(): void
    {
        $this->installDemo();

        $member = PlatformRole::where('slug', 'member')->first();
        $create = PlatformAppPermission::where('app_id', 'demo')->where('name', 'demo.create')->first();
        $member->permissions()->syncWithoutDetaching([$create->id]);

        $this->writeManifest('demo', ['version' => '1.1.0', 'permissions' => ['demo.view']]);
        $this->manager()->discover();

        $this->upgrader()->upgrade('demo');

        $this->assertDatabaseMissing('platform_app_permissions', ['app_id' => 'demo', 'name' => 'demo.create']);
        $this->assertSame(
            0,
            DB::table('platform_role_permissions')->where('platform_app_permission_id', $create->id)->count(),
        );
    }

    // ---------------------------------------------------------- dependencies

    public function test_an_incompatible_dependent_blocks_the_upgrade(): void
    {
        $this->installExtension();

        $this->writeManifest('demo', ['version' => '2.0.0']);
        $this->manager()->discover();

        $plan = $this->upgrader()->plan('demo');

        $this->assertTrue($plan->isBlocked());
        $this->assertStringContainsString('requires Demo ^1.0', implode(' ', $plan->blockers));
    }

    public function test_a_compatible_dependent_is_upgraded_along_with_its_dependency(): void
    {
        $this->installExtension();

        $this->writeManifest('demo', ['version' => '2.0.0']);
        $this->writeManifest('demo-ext', [
            'version' => '2.0.0',
            'requires' => ['platform' => '^1.0', 'demo' => '^2.0'],
            'permissions' => ['demo-ext.view'],
        ]);
        $this->manager()->discover();

        $plan = $this->upgrader()->plan('demo');

        $this->assertFalse($plan->isBlocked(), implode(' ', $plan->blockers));
        $this->assertSame(['demo', 'demo-ext'], $plan->appIds(), 'the dependency must be upgraded first');

        $this->upgrader()->execute($plan);

        $this->assertSame('2.0.0', PlatformApp::where('app_id', 'demo')->value('version'));
        $this->assertSame('2.0.0', PlatformApp::where('app_id', 'demo-ext')->value('version'));
    }

    protected function installExtension(): void
    {
        $this->installDemo();

        $this->writeManifest('demo-ext', [
            'requires' => ['platform' => '^1.0', 'demo' => '^1.0'],
            'permissions' => ['demo-ext.view'],
        ]);

        $this->manager()->discover();
        $this->manager()->install('demo-ext');
    }

    // ------------------------------------------------------------------- UI

    public function test_the_dashboard_offers_an_upgrade_button_and_previews_the_plan(): void
    {
        $this->installDemo();

        $this->writeManifest('demo', ['version' => '1.1.0']);
        $this->writeMigration('demo', '2026_02_01_000000_create_demo_tags', 'demo_tags', 'migration:1.1');
        $this->manager()->discover();

        // The launcher flags the pending update and offers it as an action.
        $this->get('/platform')
            ->assertOk()
            ->assertSee('1 update available')
            ->assertSee('update to 1.1.0')
            ->assertSee(route('platform.apps.upgrade.plan', 'demo'), false);

        $this->getJson('/platform/apps/demo/upgrade-plan')
            ->assertOk()
            ->assertJsonPath('blocked', false)
            ->assertJsonPath('apps.0.app_id', 'demo')
            ->assertJsonPath('apps.0.from_version', '1.0.0')
            ->assertJsonPath('apps.0.to_version', '1.1.0')
            ->assertJsonPath('apps.0.pending_migrations.0', '2026_02_01_000000_create_demo_tags');

        $this->post('/platform/apps/demo/upgrade')
            ->assertRedirect('/platform/apps')
            ->assertSessionHas('status');

        $this->assertSame('1.1.0', PlatformApp::where('app_id', 'demo')->value('version'));
    }

    public function test_a_blocked_upgrade_reports_the_reason_instead_of_running(): void
    {
        $this->installExtension();

        $this->writeManifest('demo', ['version' => '2.0.0']);
        $this->manager()->discover();

        $this->getJson('/platform/apps/demo/upgrade-plan')
            ->assertOk()
            ->assertJsonPath('blocked', true);

        $this->post('/platform/apps/demo/upgrade')
            ->assertRedirect('/platform/apps')
            ->assertSessionHas('error');

        $this->assertSame('1.0.0', PlatformApp::where('app_id', 'demo')->value('version'));
    }
}
