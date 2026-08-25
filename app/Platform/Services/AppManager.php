<?php

namespace App\Platform\Services;

use App\Platform\Contracts\MenuProvider;
use App\Platform\Contracts\Uninstallable;
use App\Platform\Events\AppDisabled;
use App\Platform\Events\AppDiscovered;
use App\Platform\Events\AppEnabled;
use App\Platform\Events\AppInstalled;
use App\Platform\Events\AppUninstalled;
use App\Platform\Exceptions\AppNotFoundException;
use App\Platform\Exceptions\InvalidAppStateException;
use App\Platform\Exceptions\InvalidManifestException;
use App\Platform\Manifests\AppManifest;
use App\Platform\Models\PlatformApp;
use App\Platform\Models\PlatformAppPermission;
use App\Platform\Models\PlatformRole;
use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

class AppManager
{
    /**
     * Provider classes of apps currently loaded in this process.
     *
     * @var array<string, class-string>
     */
    protected array $loadedProviders = [];

    public function __construct(protected Container $container) {}

    public function appsPath(): string
    {
        return base_path(config('platform.apps_path', 'apps'));
    }

    /**
     * Scan the apps directory and register any new app in the registry.
     *
     * @return array<int, AppManifest> all discovered manifests
     */
    public function discover(): array
    {
        $manifests = [];

        if (is_dir($this->appsPath())) {
            foreach (Finder::create()->in($this->appsPath())->directories()->depth(0) as $dir) {
                $manifestFile = $dir->getPathname().DIRECTORY_SEPARATOR.'platform.json';
                if (! is_file($manifestFile)) {
                    continue;
                }

                $manifests[] = $manifest = AppManifest::fromFile($manifestFile);

                $record = PlatformApp::where('app_id', $manifest->id)->first();

                if ($record === null) {
                    $record = PlatformApp::create([
                        'app_id' => $manifest->id,
                        'name' => $manifest->name,
                        'version' => $manifest->version,
                        'available_version' => $manifest->version,
                        'manifest_hash' => $this->manifestHash($manifest->id),
                        'provider' => $manifest->provider,
                        'menu_order' => $manifest->menuOrder,
                        'status' => PlatformApp::STATUS_DISCOVERED,
                    ]);
                    Event::dispatch(new AppDiscovered($record));
                } else {
                    // `version` is the version whose migrations have actually
                    // run. Discovery only ever reports what is *available*;
                    // overwriting the installed version here would make an
                    // outstanding upgrade impossible to detect.
                    $attributes = [
                        'name' => $manifest->name,
                        'provider' => $manifest->provider,
                        'available_version' => $manifest->version,
                        // Purely presentational, so it follows the manifest
                        // immediately without waiting for an upgrade.
                        'menu_order' => $manifest->menuOrder,
                    ];

                    if (! $record->isLive()) {
                        $attributes['version'] = $manifest->version;
                        $attributes['manifest_hash'] = $this->manifestHash($manifest->id);
                    }

                    $record->update($attributes);
                }
            }
        }

        return $manifests;
    }

    public function install(string $appId): PlatformApp
    {
        $app = $this->find($appId);

        if (! in_array($app->status, [PlatformApp::STATUS_DISCOVERED, PlatformApp::STATUS_DISABLED])) {
            throw InvalidAppStateException::transition($app, 'install');
        }

        $manifest = $this->manifestFor($appId);

        if (! $manifest->supportsPlatform(config('platform.version'))) {
            throw new InvalidManifestException(
                "App [{$appId}] requires platform {$manifest->platformConstraint}, ".
                'but this platform is '.config('platform.version').'.'
            );
        }

        foreach ($manifest->requires as $reqId => $reqConstraint) {
            $reqApp = PlatformApp::where('app_id', $reqId)->first();
            if (! $reqApp || ! in_array($reqApp->status, [PlatformApp::STATUS_INSTALLED, PlatformApp::STATUS_ENABLED])) {
                throw new Exception("Cannot install {$appId} because required app {$reqId} is not installed.");
            }

            if (! AppManifest::satisfies($reqApp->version, (string) $reqConstraint)) {
                throw new Exception(
                    "Cannot install {$appId}: it requires {$reqId} {$reqConstraint}, but {$reqApp->version} is installed."
                );
            }
        }

        $this->runAppMigrations($appId);

        $this->syncPermissions($appId, grantAllViews: true);

        $attributes = [
            'status' => PlatformApp::STATUS_INSTALLED,
            'installed_at' => $app->installed_at ?? now(),
            'available_version' => $manifest->version,
        ];

        // A first install lands directly on the manifest version. Re-installing
        // an app that was only disabled must not fake a version jump: its
        // upgrade steps have not run, so it stays outdated until upgraded.
        if ($app->installed_at === null) {
            $attributes['version'] = $manifest->version;
            $attributes['manifest_hash'] = $this->manifestHash($appId);
        }

        $app->update($attributes);

        Event::dispatch(new AppInstalled($app));

        return $app;
    }

    /**
     * Kept for backwards compatibility: re-runs the app's migrations and
     * permission sync even when the version has not moved. The versioned
     * pipeline lives in AppUpgrader.
     */
    public function update(string $appId): PlatformApp
    {
        $app = $this->find($appId);

        if (! $app->isLive()) {
            throw InvalidAppStateException::transition($app, 'update');
        }

        $this->container->make(AppUpgrader::class)->upgrade($appId, force: true);

        return $this->find($appId);
    }

    public function enable(string $appId): PlatformApp
    {
        $app = $this->find($appId);

        if (! in_array($app->status, [PlatformApp::STATUS_INSTALLED, PlatformApp::STATUS_DISABLED])) {
            throw InvalidAppStateException::transition($app, 'enable');
        }

        $manifest = $this->manifestFor($appId);
        foreach ($manifest->requires as $reqId => $reqConstraint) {
            $reqApp = PlatformApp::where('app_id', $reqId)->first();
            if (! $reqApp || $reqApp->status !== PlatformApp::STATUS_ENABLED) {
                throw new Exception("Cannot enable {$appId} because required app {$reqId} is not enabled.");
            }

            if (! AppManifest::satisfies($reqApp->version, (string) $reqConstraint)) {
                throw new Exception(
                    "Cannot enable {$appId}: it requires {$reqId} {$reqConstraint}, but {$reqApp->version} is installed."
                );
            }
        }

        $app->update(['status' => PlatformApp::STATUS_ENABLED]);

        $this->registerApp($appId);

        Event::dispatch(new AppEnabled($app));

        return $app;
    }

    public function disable(string $appId): PlatformApp
    {
        $app = $this->find($appId);

        if ($app->status !== PlatformApp::STATUS_ENABLED) {
            throw InvalidAppStateException::transition($app, 'disable');
        }

        // Check if any enabled app requires this app
        $enabledApps = PlatformApp::where('status', PlatformApp::STATUS_ENABLED)->get();
        foreach ($enabledApps as $enabledApp) {
            try {
                $manifest = $this->manifestFor($enabledApp->app_id);
                if (isset($manifest->requires[$appId])) {
                    throw new Exception("Cannot disable {$app->name} because {$enabledApp->name} depends on it.");
                }
            } catch (AppNotFoundException $e) {
                // Ignore if manifest missing
            }
        }

        $app->update(['status' => PlatformApp::STATUS_DISABLED]);

        // The provider stays loaded in the current process; it will not be
        // registered on the next request. Source code is never touched.
        unset($this->loadedProviders[$appId]);

        Event::dispatch(new AppDisabled($app));

        return $app;
    }

    public function uninstall(string $appId): PlatformApp
    {
        $app = $this->find($appId);

        if ($app->status !== PlatformApp::STATUS_DISABLED) {
            throw InvalidAppStateException::transition($app, 'uninstall');
        }

        $installedApps = PlatformApp::whereIn('status', [PlatformApp::STATUS_INSTALLED, PlatformApp::STATUS_ENABLED])->get();
        foreach ($installedApps as $installedApp) {
            try {
                $manifest = $this->manifestFor($installedApp->app_id);
                if (isset($manifest->requires[$appId])) {
                    throw new Exception("Cannot uninstall {$app->name} because {$installedApp->name} depends on it.");
                }
            } catch (AppNotFoundException $e) {
                // Ignore if manifest missing
            }
        }

        $provider = $app->provider;
        if (class_exists($provider)) {
            $instance = new $provider($this->container);
            if ($instance instanceof Uninstallable) {
                $instance->uninstall();
            }
        }

        $app->permissions()->delete();
        $app->delete();

        Event::dispatch(new AppUninstalled($app));

        return $app;
    }

    public function find(string $appId): PlatformApp
    {
        $app = PlatformApp::where('app_id', $appId)->first();

        if ($app === null) {
            throw AppNotFoundException::forId($appId);
        }

        return $app;
    }

    public function manifestFor(string $appId): AppManifest
    {
        $file = $this->appsPath().DIRECTORY_SEPARATOR.$appId.DIRECTORY_SEPARATOR.'platform.json';

        if (! is_file($file)) {
            throw AppNotFoundException::forId($appId);
        }

        return AppManifest::fromFile($file);
    }

    /**
     * Register the service providers of all enabled apps. Called once per
     * request from the PlatformServiceProvider; this is what keeps disabled
     * apps completely out of the Laravel runtime.
     */
    public function registerEnabledApps(): void
    {
        try {
            if (! Schema::hasTable('platform_apps')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        PlatformApp::where('status', PlatformApp::STATUS_ENABLED)
            ->pluck('app_id')
            ->each(fn (string $appId) => $this->registerApp($appId));
    }

    public function registerApp(string $appId): void
    {
        $app = PlatformApp::where('app_id', $appId)->firstOrFail();

        // The source of an app can be removed while it is still registered;
        // a stale registry entry must never take the whole platform down.
        if (! class_exists($app->provider)) {
            return;
        }

        $this->container->register($app->provider);
        $this->loadedProviders[$appId] = $app->provider;
    }

    /**
     * Combined menu of all enabled apps implementing MenuProvider, ordered by
     * the `menu_order` each manifest declares. An app may override the
     * position of a single entry by giving that entry its own `order`.
     *
     * @return array<int, array{label: string, route: string}>
     */
    public function menuItems(): array
    {
        $order = PlatformApp::whereIn('app_id', array_keys($this->loadedProviders))
            ->pluck('menu_order', 'app_id');

        $items = [];

        foreach ($this->loadedProviders as $appId => $providerClass) {
            $provider = new $providerClass($this->container);

            if (! $provider instanceof MenuProvider) {
                continue;
            }

            $appOrder = (int) ($order[$appId] ?? AppManifest::DEFAULT_MENU_ORDER);

            foreach ($provider->menu() as $item) {
                $items[] = [
                    'item' => $item,
                    'order' => (int) ($item['order'] ?? $appOrder),
                    'label' => (string) ($item['label'] ?? ''),
                ];
            }
        }

        // Equal weights fall back to the label so the navbar never reshuffles
        // between requests.
        usort($items, fn (array $a, array $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);

        return array_column($items, 'item');
    }

    /**
     * Menu entries contributed by a single app, independent of the combined
     * navbar menu. Only meaningful for enabled apps, whose providers (and
     * therefore routes) are registered in the runtime.
     *
     * @return array<int, array{label: string, route: string}>
     */
    public function menuFor(string $appId): array
    {
        $app = PlatformApp::where('app_id', $appId)->first();

        if ($app === null || ! class_exists($app->provider)) {
            return [];
        }

        $provider = new $app->provider($this->container);

        if (! $provider instanceof MenuProvider) {
            return [];
        }

        return $provider->menu();
    }

    /**
     * Reconcile the app's permission catalogue with its manifest.
     *
     * Permissions are matched by name and their rows are reused, because
     * `platform_role_permissions` points at permission ids: deleting and
     * re-creating the catalogue would silently drop every role assignment
     * the administrator made. Only permissions the manifest dropped are
     * removed, together with their pivot rows.
     *
     * On install ($grantAllViews) every view permission is granted to the
     * default member role, so a freshly installed app is usable without
     * manual role editing. On upgrade only the permissions the new version
     * introduces are granted, so an administrator's revocations survive.
     *
     * @return array{added: array<int, string>, removed: array<int, string>}
     */
    public function syncPermissions(string $appId, bool $grantAllViews = false): array
    {
        $manifest = $this->manifestFor($appId);

        $existing = PlatformAppPermission::where('app_id', $appId)->pluck('id', 'name');
        $wanted = collect($manifest->permissions)->unique()->values();

        $added = $wanted->reject(fn (string $name) => $existing->has($name))->values();
        $removed = $existing->keys()->reject(fn (string $name) => $wanted->contains($name))->values();

        foreach ($added as $name) {
            PlatformAppPermission::create(['app_id' => $appId, 'name' => $name]);
        }

        if ($removed->isNotEmpty()) {
            $removedIds = $existing->only($removed->all())->values()->all();

            DB::table('platform_role_permissions')
                ->whereIn('platform_app_permission_id', $removedIds)
                ->delete();

            PlatformAppPermission::whereIn('id', $removedIds)->delete();
        }

        $this->grantViewPermissionsToMembers(
            $appId,
            $grantAllViews ? $wanted->all() : $added->all(),
        );

        return ['added' => $added->all(), 'removed' => $removed->all()];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function grantViewPermissionsToMembers(string $appId, array $permissions): void
    {
        $member = PlatformRole::where('slug', 'member')->first();

        if ($member === null || $member->is_super_admin || $permissions === []) {
            return;
        }

        $viewPermissionIds = PlatformAppPermission::where('app_id', $appId)
            ->whereIn('name', $permissions)
            ->where('name', 'like', '%.view')
            ->pluck('id');

        $member->permissions()->syncWithoutDetaching($viewPermissionIds);
    }

    /**
     * What the app's permission catalogue would gain and lose, without
     * touching anything. Used to build the upgrade preview.
     *
     * @return array{added: array<int, string>, removed: array<int, string>}
     */
    public function permissionDiff(string $appId): array
    {
        $manifest = $this->manifestFor($appId);

        $existing = PlatformAppPermission::where('app_id', $appId)->pluck('name');
        $wanted = collect($manifest->permissions)->unique()->values();

        return [
            'added' => $wanted->diff($existing)->values()->all(),
            'removed' => $existing->diff($wanted)->values()->all(),
        ];
    }

    public function migrationsPath(string $appId): string
    {
        return config('platform.apps_path').'/'.$appId.'/database/migrations';
    }

    public function hasMigrations(string $appId): bool
    {
        return is_dir(base_path($this->migrationsPath($appId)));
    }

    public function runAppMigrations(string $appId): string
    {
        if (! $this->hasMigrations($appId)) {
            return '';
        }

        Artisan::call('migrate', ['--path' => $this->migrationsPath($appId), '--force' => true]);

        return trim(Artisan::output());
    }

    /**
     * Fingerprint of the manifest as it is on disk. Recorded at install and
     * upgrade time so a manifest edited without a version bump is visible.
     */
    public function manifestHash(string $appId): string
    {
        $file = $this->appsPath().DIRECTORY_SEPARATOR.$appId.DIRECTORY_SEPARATOR.'platform.json';

        return is_file($file) ? hash_file('sha256', $file) : '';
    }

    /**
     * Installed apps whose manifest declares a dependency on the given app.
     *
     * @return array<int, PlatformApp>
     */
    public function dependentsOf(string $appId): array
    {
        $dependents = [];

        $candidates = PlatformApp::whereIn('status', [PlatformApp::STATUS_INSTALLED, PlatformApp::STATUS_ENABLED])
            ->where('app_id', '!=', $appId)
            ->get();

        foreach ($candidates as $candidate) {
            try {
                $manifest = $this->manifestFor($candidate->app_id);
            } catch (AppNotFoundException|InvalidManifestException) {
                continue;
            }

            if (isset($manifest->requires[$appId])) {
                $dependents[] = $candidate;
            }
        }

        return $dependents;
    }
}
