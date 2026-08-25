<?php

namespace PlatformApps\FormBuilder;

use App\Platform\Contracts\MenuProvider;
use App\Platform\Contracts\Uninstallable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class FormBuilderServiceProvider extends ServiceProvider implements MenuProvider, Uninstallable
{
    public function boot(): void
    {
        // Public fill routes run without auth; everything else requires login.
        Route::middleware(['web'])->group($this->appPath('routes/web.php'));
        $this->loadViewsFrom($this->appPath('resources/views'), 'form-builder');
        $this->loadMigrationsFrom($this->appPath('database/migrations'));
    }

    public function menu(): array
    {
        return [
            ['label' => 'Forms', 'route' => 'form-builder.index'],
        ];
    }

    public function uninstall(): void
    {
        Schema::dropIfExists('form_builder_submissions');
        Schema::dropIfExists('form_builder_fields');
        Schema::dropIfExists('form_builder_forms');
    }

    protected function appPath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? '/'.$path : '');
    }
}
