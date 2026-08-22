<?php

namespace PlatformApps\Kanban;

use App\Platform\Contracts\MenuProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class KanbanServiceProvider extends ServiceProvider implements MenuProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->group($this->appPath('routes/web.php'));
        $this->loadViewsFrom($this->appPath('resources/views'), 'kanban');
        $this->loadMigrationsFrom($this->appPath('database/migrations'));
    }

    public function menu(): array
    {
        return [
            ['label' => 'Kanban', 'route' => 'kanban.boards.index'],
        ];
    }

    protected function appPath(string $path = ''): string
    {
        return dirname(__DIR__) . ($path !== '' ? '/' . $path : '');
    }
}
