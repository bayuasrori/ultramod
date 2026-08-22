<?php

namespace PlatformApps\Products;

use App\Platform\Contracts\MenuProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ProductsServiceProvider extends ServiceProvider implements MenuProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->group($this->appPath('routes/web.php'));
        $this->loadViewsFrom($this->appPath('resources/views'), 'products');
        $this->loadMigrationsFrom($this->appPath('database/migrations'));
    }

    public function menu(): array
    {
        return [
            ['label' => 'Products', 'route' => 'products.index'],
        ];
    }

    protected function appPath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? '/'.$path : '');
    }
}
