<?php

namespace App\Platform\Services;

use App\Platform\Contracts\MenuProvider;
use App\Platform\Contracts\Uninstallable;
use App\Platform\Events\AppDisabled;
use App\Platform\Events\AppDiscovered;
use App\Platform\Events\AppEnabled;
use App\Platform\Events\AppInstalled;
use App\Platform\Events\AppUninstalled;
use App\Platform\Events\AppUpdated;
use App\Platform\Exceptions\AppNotFoundException;
use App\Platform\Exceptions\InvalidAppStateException;
use App\Platform\Exceptions\InvalidManifestException;
use App\Platform\Manifests\AppManifest;
use App\Platform\Models\PlatformApp;
use App\Platform\Models\PlatformAppPermission;
use App\Platform\Models\PlatformRole;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;
use Exception;

class AppManager
{
    /**
     * Provider classes of apps currently loaded in this process.
     *
     * @var array<string, class-string>
     */
    protected array $loadedProviders = [];

    public function __construct(protected Container $container)
    {
    }

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
                        'provider' => $manifest->provider,
                        'status' => PlatformApp::STATUS_DISCOVERED,
                    ]);
                    Event::dispatch(new AppDiscovered($record));
                } else {
                    $record->update([
                        'name' => $manifest->name,
                        'version' => $manifest->version,
                        'provider' => $manifest->provider,
                    ]);
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
            if (!$reqApp || !in_array($reqApp->status, [PlatformApp::STATUS_INSTALLED, PlatformApp::STATUS_ENABLED])) {
                throw new Exception("Cannot install {$appId} because required app {$reqId} is not installed.");
            }
        }

        $this->runAppMigrations($appId);

        $this->syncPermissions($appId);

        $app->update([
            'status' => PlatformApp::STATUS_INSTALLED,
            'installed_at' => $app->installed_at ?? now(),
        ]);

        Event::dispatch(new AppInstalled($app));

        return $app;
    }

    public function update(string $appId): PlatformApp
    {
        $app = $this->find($appId);

        if (! in_array($app->status, [PlatformApp::STATUS_INSTALLED, PlatformApp::STATUS_ENABLED])) {
            throw InvalidAppStateException::transition($app, 'update');
        }

        $manifest = $this->manifestFor($appId);
        $oldVersion = $app->version;

        if (! $manifest->supportsPlatform(config('platform.version'))) {
            throw new InvalidManifestException(
                "App [{$appId}] requires platform {$manifest->platformConstraint}, ".
                'but this platform is '.config('platform.version').'.'
            );
        }

        foreach ($manifest->requires as $reqId => $reqConstraint) {
            $reqApp = PlatformApp::where('app_id', $reqId)->first();
            if (!$reqApp || !in_array($reqApp->status, [PlatformApp::STATUS_INSTALLED, PlatformApp::STATUS_ENABLED])) {
                throw new Exception("Cannot update {$appId} because required app {$reqId} is not installed.");
            }
        }

        $this->runAppMigrations($appId);

        $this->syncPermissions($appId);

        $app->update([
            'version' => $manifest->version,
            'name' => $manifest->name,
            'provider' => $manifest->provider,
        ]);

        Event::dispatch(new AppUpdated($app, $oldVersion, $manifest->version));

        return $app;
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
            if (!$reqApp || $reqApp->status !== PlatformApp::STATUS_ENABLED) {
                throw new Exception("Cannot enable {$appId} because required app {$reqId} is not enabled.");
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
     * Combined menu of all enabled apps implementing MenuProvider.
     *
     * @return array<int, array{label: string, route: string}>
     */
    public function menuItems(): array
    {
        $items = [];

        foreach ($this->loadedProviders as $providerClass) {
            $provider = new $providerClass($this->container);

            if ($provider instanceof MenuProvider) {
                $items = array_merge($items, $provider->menu());
            }
        }

        return $items;
    }

    /**
     * Re-create the app's permission catalogue from its manifest, then keep
     * the default member role in sync: members always get the app's view
     * permissions so a freshly installed app is usable by regular users
     * without manual role editing.
     */
    protected function syncPermissions(string $appId): void
    {
        $manifest = $this->manifestFor($appId);

        $app = PlatformApp::where('app_id', $appId)->firstOrFail();
        $app->permissions()->delete();

        foreach ($manifest->permissions as $permission) {
            PlatformAppPermission::create(['app_id' => $appId, 'name' => $permission]);
        }

        $member = PlatformRole::where('slug', 'member')->first();

        if ($member && ! $member->is_super_admin) {
            $viewPermissionIds = PlatformAppPermission::where('app_id', $appId)
                ->where('name', 'like', '%.view')
                ->pluck('id');

            $member->permissions()->syncWithoutDetaching($viewPermissionIds);
        }
    }

    protected function runAppMigrations(string $appId): void
    {
        $path = config('platform.apps_path').'/'.$appId.'/database/migrations';

        if (is_dir(base_path($path))) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }
    }
}
