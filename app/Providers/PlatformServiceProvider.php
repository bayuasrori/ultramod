<?php

namespace App\Providers;

use App\Platform\Contracts\ExtensionSlot;
use App\Platform\Services\AppManager;
use App\Platform\Services\AppUpgrader;
use App\Platform\Services\AuditLogger;
use App\Platform\Services\FileManager;
use App\Platform\Services\SettingsManager;
use App\Platform\Services\SlotManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppManager::class);
        $this->app->singleton(AppUpgrader::class);
        $this->app->singleton(ExtensionSlot::class, SlotManager::class);
        $this->app->singleton(SettingsManager::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(FileManager::class);

        // Register the service providers of all enabled apps. Apps that are
        // not enabled never enter the Laravel runtime: no routes, no views,
        // no listeners, no commands.
        $this->app->booted(function ($app) {
            $app->make(AppManager::class)->registerEnabledApps();
        });
    }

    public function boot(): void
    {
        $this->app->booted(function () {
            $this->registerPermissionGates();
        });

        View::composer('platform.layout', function ($view) {
            $view->with('platformMenu', $this->app->make(AppManager::class)->menuItems());
        });

        Blade::directive('extensionslot', function ($expression) {
            return "<?php echo app(\App\Platform\Contracts\ExtensionSlot::class)->render($expression); ?>";
        });
    }

    /**
     * Authorization resolves dynamically: super admins bypass everything,
     * other users pass when their role grants the permission. Returning
     * null falls through to normal policies for abilities that are not
     * platform permissions (e.g. model policies defined by apps).
     */
    protected function registerPermissionGates(): void
    {
        try {
            Gate::before(function ($user, string $ability) {
                if ($user === null) {
                    return false;
                }

                if ($user->isSuperAdmin()) {
                    return true;
                }

                return $user->hasPermission($ability) ? true : null;
            });
        } catch (\Throwable) {
            // Platform tables may not exist yet (e.g. during first migrate).
        }
    }
}
