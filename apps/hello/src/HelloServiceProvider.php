<?php

namespace PlatformApps\Hello;

use App\Platform\Contracts\MenuProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HelloServiceProvider extends ServiceProvider implements MenuProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->group($this->appPath('routes/web.php'));
        $this->loadViewsFrom($this->appPath('resources/views'), 'hello');
    }

    public function menu(): array
    {
        return [
            ['label' => 'Hello', 'route' => 'hello.index'],
        ];
    }

    protected function appPath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? '/'.$path : '');
    }
}
