<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Platform\Services\AppManager;
use Illuminate\Support\Facades\File;

class AppMakeComponentCommand extends Command
{
    protected $signature = 'platform:make {type : The component type (model, controller, migration, seeder)} {app_id : The ID of the app} {name : The name of the component}';
    protected $description = 'Make a component for a specific app';

    public function handle(AppManager $manager): int
    {
        $type = strtolower($this->argument('type'));
        $appId = $this->argument('app_id');
        $name = $this->argument('name');
        
        $studlyApp = Str::studly($appId);
        $basePath = $manager->appPath($appId) ?? $manager->defaultAppsPath() . DIRECTORY_SEPARATOR . $appId;
        
        if (!is_dir($basePath)) {
            $this->error("App [{$appId}] does not exist at {$basePath}");
            return self::FAILURE;
        }

        switch ($type) {
            case 'model':
                return $this->makeModel($basePath, $studlyApp, $name);
            case 'controller':
                return $this->makeController($basePath, $studlyApp, $name);
            case 'migration':
                return $this->makeMigration($basePath, $name);
            case 'seeder':
                return $this->makeSeeder($basePath, $studlyApp, $name);
            default:
                $this->error("Unsupported component type: {$type}");
                return self::FAILURE;
        }
    }

    protected function makeModel($basePath, $studlyApp, $name)
    {
        $name = Str::studly($name);
        $path = $basePath . '/src/Models/' . $name . '.php';
        
        File::ensureDirectoryExists(dirname($path));
        
        $stub = <<<PHP
<?php

namespace PlatformApps\\{$studlyApp}\\Models;

use Illuminate\Database\Eloquent\Model;

class {$name} extends Model
{
    protected \$guarded = [];
}
PHP;
        File::put($path, $stub);
        $this->info("Model [{$name}] created successfully in app [{$studlyApp}].");
        return self::SUCCESS;
    }

    protected function makeController($basePath, $studlyApp, $name)
    {
        $name = Str::studly($name);
        if (!Str::endsWith($name, 'Controller')) {
            $name .= 'Controller';
        }
        $path = $basePath . '/src/Http/Controllers/' . $name . '.php';
        
        File::ensureDirectoryExists(dirname($path));
        
        $stub = <<<PHP
<?php

namespace PlatformApps\\{$studlyApp}\\Http\\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class {$name} extends Controller
{
    public function index()
    {
        //
    }
}
PHP;
        File::put($path, $stub);
        $this->info("Controller [{$name}] created successfully in app [{$studlyApp}].");
        return self::SUCCESS;
    }

    protected function makeSeeder($basePath, $studlyApp, $name)
    {
        $name = Str::studly($name);
        if (!Str::endsWith($name, 'Seeder')) {
            $name .= 'Seeder';
        }
        $path = $basePath . '/database/seeders/' . $name . '.php';
        
        File::ensureDirectoryExists(dirname($path));
        
        $stub = <<<PHP
<?php

namespace PlatformApps\\{$studlyApp}\\Database\\Seeders;

use Illuminate\Database\Seeder;

class {$name} extends Seeder
{
    public function run(): void
    {
        //
    }
}
PHP;
        File::put($path, $stub);
        $this->info("Seeder [{$name}] created successfully in app [{$studlyApp}].");
        return self::SUCCESS;
    }

    protected function makeMigration($basePath, $name)
    {
        $name = Str::snake($name);
        $prefix = date('Y_m_d_His');
        $filename = $prefix . '_' . $name . '.php';
        $path = $basePath . '/database/migrations/' . $filename;
        
        File::ensureDirectoryExists(dirname($path));
        
        $stub = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::create('table_name', function (Blueprint \$table) {
        //     \$table->id();
        //     \$table->timestamps();
        // });
    }

    public function down(): void
    {
        // Schema::dropIfExists('table_name');
    }
};
PHP;
        File::put($path, $stub);
        $this->info("Migration [{$filename}] created successfully in app.");
        return self::SUCCESS;
    }
}
