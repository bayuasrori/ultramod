<?php

namespace App\Http\Controllers;

use App\Platform\Exceptions\AppException;
use App\Platform\Models\PlatformApp;
use App\Platform\Services\AppManager;
use App\Platform\Services\AppScaffolder;
use Illuminate\Routing\Controller;

class PlatformController extends Controller
{
    public function index()
    {
        return view('platform.home', [
            'apps' => PlatformApp::orderBy('app_id')->get(),
        ]);
    }

    public function create()
    {
        return view('platform.create');
    }

    public function store(AppScaffolder $scaffolder, AppManager $manager)
    {
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9-]*$/'],
            'table' => ['nullable', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'columns.name' => ['nullable', 'array'],
            'columns.name.*' => ['string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'columns.type.*' => ['string', 'in:string,text,integer,decimal,boolean,date,datetime'],
        ], [
            'name.regex' => 'App name may only contain lowercase letters, numbers and dashes, and must start with a letter.',
            'table.regex' => 'Table name may only contain lowercase letters, numbers and underscores.',
            'columns.name.*.regex' => 'Column names may only contain lowercase letters, numbers and underscores.',
        ]);

        $activate = request()->boolean('activate');
        $crud = request()->boolean('crud');

        $columns = [];
        if ($crud) {
            $names = array_values(array_filter($validated['columns']['name'] ?? [], fn ($n) => $n !== '' && $n !== null));
            $types = $validated['columns']['type'] ?? [];

            if ($names === []) {
                return redirect()->route('platform.apps.create')
                    ->with('error', 'CRUD mode needs at least one column.')
                    ->withInput();
            }

            foreach ($names as $i => $name) {
                $columns[] = ['name' => $name, 'type' => $types[$i] ?? 'string'];
            }

            if (empty($validated['table'])) {
                $validated['table'] = $validated['name'];
            }
        }

        try {
            $app = $crud
                ? $scaffolder->scaffoldCrud($validated['name'], $validated['table'], $columns)
                : $scaffolder->scaffold($validated['name']);

            $manager->discover();

            if ($activate) {
                $manager->install($app['id']);
                $manager->enable($app['id']);
            }
        } catch (AppException $e) {
            return redirect()->route('platform.apps.create')->with('error', $e->getMessage())->withInput();
        }

        $message = "App [{$app['id']}] scaffolded at apps/{$app['id']}"
            .($crud ? ' with full CRUD for table '.$validated['table'] : '')
            .($activate ? ' and installed & enabled.' : '. It is now discovered — install and enable it from the dashboard.');

        return redirect()->route('platform.index')->with('status', $message);
    }

    public function install(string $app, AppManager $manager)
    {
        return $this->runAction('install', $app, $manager);
    }

    public function enable(string $app, AppManager $manager)
    {
        return $this->runAction('enable', $app, $manager);
    }

    public function disable(string $app, AppManager $manager)
    {
        return $this->runAction('disable', $app, $manager);
    }

    public function uninstall(string $app, AppManager $manager)
    {
        return $this->runAction('uninstall', $app, $manager);
    }

    protected function runAction(string $action, string $app, AppManager $manager)
    {
        try {
            $manager->{$action}($app);
        } catch (AppException $e) {
            return redirect()->route('platform.index')->with('error', $e->getMessage());
        }

        return redirect()->route('platform.index')->with('status', "App [{$app}] {$action}ed successfully.");
    }
}
