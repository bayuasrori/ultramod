<?php

namespace PlatformApps\Notes;

use App\Platform\Contracts\MenuProvider;
use App\Platform\Contracts\Uninstallable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class NotesServiceProvider extends ServiceProvider implements MenuProvider, Uninstallable
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->group($this->appPath('routes/web.php'));
        $this->loadViewsFrom($this->appPath('resources/views'), 'notes');
        $this->loadMigrationsFrom($this->appPath('database/migrations'));
    }

    public function menu(): array
    {
        return [
            ['label' => 'Notes', 'route' => 'notes.index'],
        ];
    }

    public function uninstall(): void
    {
        Schema::dropIfExists('note_tags');
        Schema::dropIfExists('note_revisions');
        Schema::dropIfExists('notes');
    }

    protected function appPath(string $path = ''): string
    {
        return dirname(__DIR__).($path !== '' ? '/'.$path : '');
    }
}
