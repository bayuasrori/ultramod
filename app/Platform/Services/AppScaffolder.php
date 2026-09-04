<?php

namespace App\Platform\Services;

use App\Platform\Exceptions\AppException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class AppScaffolder
{
    /**
     * Generated for every CRUD table, so they may not be declared by hand.
     */
    public const RESERVED_COLUMNS = ['id', 'created_at', 'updated_at'];

    public function __construct(protected AppManager $manager) {}

    /**
     * Scaffold a new app skeleton and make it discoverable.
     *
     * @return array{path: string, id: string, namespace: string}
     */
    public function scaffold(string $name): array
    {
        $id = Str::slug($name);

        if ($id === '' || ! preg_match('/^[a-z][a-z0-9-]*$/', $id)) {
            throw new AppException("Invalid app name [{$name}]: use lowercase letters, numbers and dashes, starting with a letter.");
        }

        $studly = Str::studly($id);
        $appPath = $this->manager->defaultAppsPath().DIRECTORY_SEPARATOR.$id;

        if (is_dir($appPath)) {
            throw new AppException("App [{$id}] already exists at {$appPath}.");
        }

        $this->createSkeleton($appPath, $id, $studly);
        $this->registerAutoload($id, $studly);

        return ['path' => $appPath, 'id' => $id, 'namespace' => "PlatformApps\\{$studly}"];
    }

    protected function createSkeleton(string $path, string $id, string $studly): void
    {
        foreach (['src/Http/Controllers', 'src/Models', 'routes', 'resources/views', 'database/migrations', 'tests'] as $dir) {
            mkdir($path.'/'.$dir, 0755, true);
        }

        file_put_contents($path.'/platform.json', $this->manifestStub($id, $studly));
        file_put_contents($path.'/src/'.$studly.'ServiceProvider.php', $this->providerStub($id, $studly));
        file_put_contents($path.'/src/Http/Controllers/'.$studly.'Controller.php', $this->controllerStub($id, $studly));
        file_put_contents($path.'/routes/web.php', $this->routesStub($id, $studly));
        file_put_contents($path.'/resources/views/'.$id.'.blade.php', $this->viewStub($id, $studly));
    }

    protected function registerAutoload(string $id, string $studly): void
    {
        $composerFile = base_path('composer.json');
        $composer = json_decode((string) file_get_contents($composerFile), true);

        $composer['autoload']['psr-4']["PlatformApps\\{$studly}\\"] = "apps/{$id}/src/";
        $composer['autoload']['psr-4']["PlatformApps\\{$studly}\\Database\\Seeders\\"] = "apps/{$id}/database/seeders/";

        file_put_contents(
            $composerFile,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $result = Process::path(base_path())->env($this->composerEnv())->run('composer dump-autoload');

        if ($result->failed()) {
            throw new AppException(
                'Could not regenerate the Composer autoloader: '.trim($result->errorOutput())
            );
        }
    }

    /**
     * Composer refuses to run without HOME or COMPOSER_HOME, which are often
     * absent in web-server processes. Fall back to a writable directory.
     *
     * @return array<string, string>
     */
    protected function composerEnv(): array
    {
        if (getenv('HOME') || getenv('COMPOSER_HOME')) {
            return [];
        }

        $home = sys_get_temp_dir().'/ultramod-composer-home';
        if (! is_dir($home)) {
            @mkdir($home, 0755, true);
        }

        return ['HOME' => $home, 'COMPOSER_HOME' => $home];
    }

    protected function manifestStub(string $id, string $studly): string
    {
        return <<<JSON
        {
            "id": "{$id}",
            "name": "{$studly} App",
            "version": "0.1.0",
            "description": "{$studly} platform application",
            "provider": "PlatformApps\\\\{$studly}\\\\{$studly}ServiceProvider",
            "requires": {
                "platform": "^1.0"
            },
            "permissions": [
                "{$id}.view"
            ]
        }
        JSON."\n";
    }

    protected function providerStub(string $id, string $studly): string
    {
        return <<<PHP
        <?php

        namespace PlatformApps\\{$studly};

        use App\\Platform\\Contracts\\MenuProvider;
        use Illuminate\\Support\\Facades\\Route;
        use Illuminate\\Support\\ServiceProvider;

        class {$studly}ServiceProvider extends ServiceProvider implements MenuProvider
        {
            public function register(): void
            {
                //
            }

            public function boot(): void
            {
                Route::middleware(['web', 'auth'])->group(\$this->appPath('routes/web.php'));
                \$this->loadViewsFrom(\$this->appPath('resources/views'), '{$id}');
                \$this->loadMigrationsFrom(\$this->appPath('database/migrations'));
            }

            public function menu(): array
            {
                return [
                    ['label' => '{$studly}', 'route' => '{$id}.index'],
                ];
            }

            protected function appPath(string \$path = ''): string
            {
                return dirname(__DIR__).(\$path !== '' ? '/'.\$path : '');
            }
        }
        PHP."\n";
    }

    protected function controllerStub(string $id, string $studly): string
    {
        return <<<PHP
        <?php

        namespace PlatformApps\\{$studly}\\Http\\Controllers;

        use App\\Platform\\Models\\PlatformApp;
        use Illuminate\\Routing\\Controller;

        class {$studly}Controller extends Controller
        {
            public function index()
            {
                return view('{$id}::{$id}', [
                    'app' => PlatformApp::where('app_id', '{$id}')->firstOrFail(),
                ]);
            }
        }
        PHP."\n";
    }

    protected function routesStub(string $id, string $studly): string
    {
        return <<<PHP
        <?php

        use Illuminate\\Support\\Facades\\Route;
        use PlatformApps\\{$studly}\\Http\\Controllers\\{$studly}Controller;

        Route::get('/{$id}', [{$studly}Controller::class, 'index'])->name('{$id}.index');
        PHP."\n";
    }

    protected function viewStub(string $id, string $studly): string
    {
        return <<<BLADE
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$studly} App</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="bg-body-tertiary">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
            <div class="container">
                <a class="navbar-brand" href="{{ route('platform.index') }}">Ultramod</a>
                <div class="navbar-nav">
                    <a class="nav-link active" href="{{ route('{$id}.index') }}">{$studly}</a>
                </div>
            </div>
        </nav>
        <main class="container">
            <div class="card shadow-sm" style="max-width: 32rem;">
                <div class="card-body">
                    <h1 class="h3 card-title">Hello from {$studly} App!</h1>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between"><span>App</span><code>{{ \$app->app_id }}</code></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Version</span><code>{{ \$app->version }}</code></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Status</span><span class="badge text-bg-success">{{ \$app->status }}</span></li>
                    </ul>
                </div>
            </div>
        </main>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        BLADE."\n";
    }

    /**
     * Scaffold an app with a full CRUD stack for one table.
     *
     * @param  array<int, array{name: string, type: string}>  $columns
     * @return array{path: string, id: string, namespace: string}
     */
    public function scaffoldCrud(string $name, string $table, array $columns): array
    {
        $id = Str::slug($name);

        if ($id === '' || ! preg_match('/^[a-z][a-z0-9-]*$/', $id)) {
            throw new AppException("Invalid app name [{$name}]: use lowercase letters, numbers and dashes, starting with a letter.");
        }

        if ($columns === []) {
            throw new AppException('At least one column is required for CRUD scaffolding.');
        }

        $seen = [];

        foreach ($columns as $column) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $column['name'])) {
                throw new AppException("Invalid column name [{$column['name']}]: use lowercase letters, numbers and underscores, starting with a letter.");
            }

            if (in_array($column['name'], self::RESERVED_COLUMNS, true)) {
                throw new AppException("Column [{$column['name']}] is generated automatically and cannot be declared.");
            }

            if (isset($seen[$column['name']])) {
                throw new AppException("Column [{$column['name']}] is declared twice.");
            }
            $seen[$column['name']] = true;

            if (! isset($this->columnTypes[$column['type']])) {
                throw new AppException("Unsupported column type [{$column['type']}].");
            }
        }

        $result = $this->scaffold($name);
        $studly = Str::studly($id);
        $model = Str::studly(Str::singular($table));
        $appPath = $result['path'];

        foreach (['src/Models', 'src/Http/Requests', 'resources/views/'.$id] as $dir) {
            @mkdir($appPath.'/'.$dir, 0755, true);
        }

        file_put_contents($appPath.'/database/migrations/'.now()->format('Y_m_d_His')."_create_{$table}_table.php", $this->crudMigrationStub($table, $columns));
        file_put_contents($appPath."/src/Models/{$model}.php", $this->crudModelStub($studly, $model, $columns, $table));
        file_put_contents($appPath.'/src/Http/Controllers/'.$studly.'Controller.php', $this->crudControllerStub($id, $studly, $model));
        file_put_contents($appPath."/src/Http/Requests/Store{$model}Request.php", $this->crudRequestStub($studly, $model, $columns));
        file_put_contents($appPath.'/routes/web.php', $this->crudRoutesStub($id, $studly));
        file_put_contents($appPath."/resources/views/{$id}/index.blade.php", $this->crudIndexStub($id, $columns));
        file_put_contents($appPath."/resources/views/{$id}/form.blade.php", $this->crudFormStub($id, $model, $columns));

        // The plain skeleton's placeholder view is replaced by the CRUD views.
        @unlink($appPath."/resources/views/{$id}.blade.php");

        return $result;
    }

    /**
     * The column types the generator offers — the common ones, deliberately
     * not every type the schema builder supports.
     *
     * @var array<string, string>
     */
    protected array $columnTypes = [
        'string' => 'string',
        'text' => 'text',
        'integer' => 'integer',
        'float' => 'float',
        'decimal' => 'decimal',
        'boolean' => 'boolean',
        'date' => 'date',
        'datetime' => 'datetime',
    ];

    protected function migrationField(string $type, string $name): string
    {
        return match ($type) {
            'text' => "\$table->text('{$name}')",
            'decimal' => "\$table->decimal('{$name}', 10, 2)",
            'datetime' => "\$table->dateTime('{$name}')",
            default => "\$table->{$this->columnTypes[$type]}('{$name}')",
        };
    }

    protected function crudMigrationStub(string $table, array $columns): string
    {
        $fields = '';
        foreach ($columns as $column) {
            $method = match ($column['type']) {
                'decimal' => "decimal('{$column['name']}', 10, 2)",
                'datetime' => "dateTime('{$column['name']}')",
                default => "{$this->columnTypes[$column['type']]}('{$column['name']}')",
            };
            $fields .= "            \$table->{$method};
";
        }

        return <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table) {
                    \$table->id();
        {$fields}            \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };
        PHP."\n";
    }

    protected function crudModelStub(string $studly, string $model, array $columns, string $table): string
    {
        $fillable = implode(', ', array_map(fn ($c) => "'{$c['name']}'", $columns));

        return <<<PHP
        <?php

        namespace PlatformApps\\{$studly}\Models;

        use Illuminate\Database\Eloquent\Model;

        class {$model} extends Model
        {
            protected \$table = '{$table}';

            protected \$fillable = [
                {$fillable},
            ];

            protected function casts(): array
            {
                return [
        {$this->modelCasts($columns)}        ];
            }
        }
        PHP."\n";
    }

    protected function modelCasts(array $columns): string
    {
        $casts = '';
        foreach ($columns as $column) {
            $cast = match ($column['type']) {
                'integer' => 'integer',
                'float' => 'float',
                'decimal' => 'decimal:2',
                'boolean' => 'boolean',
                'date' => 'date',
                'datetime' => 'datetime',
                default => null,
            };

            if ($cast !== null) {
                $casts .= "            '{$column['name']}' => '{$cast}',
";
            }
        }

        return $casts;
    }

    protected function crudControllerStub(string $id, string $studly, string $model): string
    {
        return <<<PHP
        <?php

        namespace PlatformApps\\{$studly}\Http\Controllers;

        use Illuminate\Routing\Controller;
        use PlatformApps\\{$studly}\Http\Requests\Store{$model}Request;
        use PlatformApps\\{$studly}\Models\\{$model};

        class {$studly}Controller extends Controller
        {
            public function index()
            {
                return view('{$id}::{$id}.index', [
                    'records' => {$model}::latest('id')->paginate(20),
                ]);
            }

            public function create()
            {
                return view('{$id}::{$id}.form', ['record' => new {$model}()]);
            }

            public function store(Store{$model}Request \$request)
            {
                {$model}::create(\$request->validated());

                return redirect()->route('{$id}.index');
            }

            public function edit({$model} \$record)
            {
                return view('{$id}::{$id}.form', ['record' => \$record]);
            }

            public function update(Store{$model}Request \$request, {$model} \$record)
            {
                \$record->update(\$request->validated());

                return redirect()->route('{$id}.index');
            }

            public function destroy({$model} \$record)
            {
                \$record->delete();

                return redirect()->route('{$id}.index');
            }
        }
        PHP."\n";
    }

    protected function crudRequestStub(string $studly, string $model, array $columns): string
    {
        $rules = '';
        foreach ($columns as $column) {
            $rule = match ($column['type']) {
                'integer' => "['required', 'integer']",
                'float', 'decimal' => "['required', 'numeric']",
                'boolean' => "['nullable', 'boolean']",
                'date' => "['required', 'date']",
                'datetime' => "['required', 'date']",
                default => "['required', 'string', 'max:255']",
            };
            $rules .= "            '{$column['name']}' => {$rule},
";
        }

        return <<<PHP
        <?php

        namespace PlatformApps\\{$studly}\Http\Requests;

        use Illuminate\Foundation\Http\FormRequest;

        class Store{$model}Request extends FormRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            public function rules(): array
            {
                return [
        {$rules}        ];
            }
        }
        PHP."\n";
    }

    protected function crudRoutesStub(string $id, string $studly): string
    {
        return <<<PHP
        <?php

        use Illuminate\Support\Facades\Route;
        use PlatformApps\\{$studly}\Http\Controllers\\{$studly}Controller;

        // Without parameters() the route parameter is named after the URI
        // segment, which would not match the controller's \$record argument:
        // every action would silently receive an empty model.
        Route::resource('/{$id}', {$studly}Controller::class)
            ->parameters(['{$id}' => 'record'])
            ->except(['show'])
            ->names('{$id}');
        PHP."\n";
    }

    protected function crudIndexStub(string $id, array $columns): string
    {
        $headers = '';
        foreach ($columns as $column) {
            $headers .= '                        <th>'.Str::headline($column['name']).'</th>
';
        }

        $cells = '';
        foreach ($columns as $column) {
            $name = $column['name'];
            $cells .= "                        <td>{{ \$record->{$name} }}</td>
";
        }

        return <<<BLADE
        @extends('platform.layout')

        @section('title', '{$id}')

        @section('content')
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h1 class="h3 mb-0">{$id}</h1>
                <a href="{{ route('{$id}.create') }}" class="btn btn-primary">+ New</a>
            </div>

            @if (\$records->isEmpty())
                <div class="alert alert-light text-center">No records yet. <a href="{{ route('{$id}.create') }}">Create the first one</a>.</div>
            @else
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
        {$headers}                        <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach (\$records as \$record)
                                <tr>
        {$cells}                        <td class="text-end">
                                        <a href="{{ route('{$id}.edit', \$record) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="POST" action="{{ route('{$id}.destroy', \$record) }}" class="d-inline"
                                              onsubmit="return confirm('Delete this record?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-3">{{ \$records->links() }}</div>
            @endif
        @endsection
        BLADE."\n";
    }

    protected function crudFormStub(string $id, string $model, array $columns): string
    {
        $fields = '';
        foreach ($columns as $column) {
            $name = $column['name'];
            $label = Str::headline($name);
            $errorClass = "@error('{$name}') is-invalid @enderror";
            $old = "{{ old('{$name}', \$record->{$name} ?? '') }}";

            $input = match ($column['type']) {
                'text' => '<textarea name="'.$name.'" id="'.$name.'" class="form-control '.$errorClass.'" rows="4">'.$old.'</textarea>',
                'integer' => '<input type="number" name="'.$name.'" id="'.$name.'" class="form-control '.$errorClass.'" value="'.$old.'">',
                'float' => '<input type="number" step="any" name="'.$name.'" id="'.$name.'" class="form-control '.$errorClass.'" value="'.$old.'">',
                'decimal' => '<input type="number" step="0.01" name="'.$name.'" id="'.$name.'" class="form-control '.$errorClass.'" value="'.$old.'">',
                'boolean' => '<input type="checkbox" name="'.$name.'" id="'.$name.'" value="1" class="form-check-input" @checked(old(\''.$name.'\', $record->'.$name.'))>',
                'date' => '<input type="date" name="'.$name.'" id="'.$name.'" class="form-control '.$errorClass.'" value="'.$old.'">',
                'datetime' => '<input type="datetime-local" name="'.$name.'" id="'.$name.'" class="form-control '.$errorClass.'" value="'.$old.'">',
                default => '<input type="text" name="'.$name.'" id="'.$name.'" class="form-control '.$errorClass.'" value="'.$old.'">',
            };

            if ($column['type'] === 'boolean') {
                // An unchecked box submits nothing, so without the hidden 0 the
                // field would simply keep its previous value on every update.
                $fields .= '                <div class="form-check mb-3">'."\n"
                    .'                    <input type="hidden" name="'.$name.'" value="0">'."\n"
                    .'                    '.$input."\n"
                    .'                    <label class="form-check-label" for="'.$name.'">'.$label.'</label>'."\n"
                    .'                </div>'."\n";
            } else {
                $fields .= '                <div class="mb-3">'."\n"
                    .'                    <label for="'.$name.'" class="form-label">'.$label.'</label>'."\n"
                    .'                    '.$input."\n"
                    .'                </div>'."\n";
            }
        }

        $blade = <<<'BLADE'
@extends('platform.layout')

@section('title', '{id} form')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 card-title mb-3">@empty($record->id) Create @else Edit @endempty</h1>
                    <form method="POST" @empty($record->id) action="{{ route('{id}.store') }}" @else action="{{ route('{id}.update', $record) }}" @endempty>
                        @csrf
                        @if ($record->id)
                            @method('PUT')
                        @endif
{fields}
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('{id}.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
BLADE;

        return str_replace(
            ['{id}', '{fields}'],
            [$id, $fields],
            $blade
        )."\n";
    }
}
