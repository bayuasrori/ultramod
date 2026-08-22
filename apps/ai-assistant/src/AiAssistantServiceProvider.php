<?php

namespace PlatformApps\AiAssistant;

use App\Platform\Contracts\MenuProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AiAssistantServiceProvider extends ServiceProvider implements MenuProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->group($this->appPath('routes/web.php'));
        $this->loadViewsFrom($this->appPath('resources/views'), 'ai-assistant');
        $this->loadMigrationsFrom($this->appPath('database/migrations'));
    }

    public function menu(): array
    {
        return [
            ['label' => 'AI Assistant', 'route' => 'ai-assistant.index'],
        ];
    }

    protected function appPath(string $path = ''): string
    {
        return dirname(__DIR__) . ($path !== '' ? '/' . $path : '');
    }
}
