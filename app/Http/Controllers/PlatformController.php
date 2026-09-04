<?php

namespace App\Http\Controllers;

use App\Platform\Exceptions\AppException;
use App\Platform\Models\PlatformApp;
use App\Platform\Services\AppManager;
use App\Platform\Services\AppScaffolder;
use App\Platform\Services\AppUpgrader;
use App\Platform\Upgrades\UpgradePlan;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class PlatformController extends Controller
{
    public function index(AppManager $manager)
    {
        $this->discoverCached($manager);

        // The dashboard is a launcher: only apps that are actually present
        // in the installation belong here. Everything else (discovered apps
        // waiting to be installed, uninstall, etc.) lives on the Apps page.
        $cards = PlatformApp::whereNot('status', PlatformApp::STATUS_DISCOVERED)
            ->orderBy('menu_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PlatformApp $app) => $this->cardData($app, $manager));

        return view('platform.home', [
            'apps' => $cards,
            'greeting' => $this->greeting(),
            // The launcher is the app list, so the sidebar stands down here.
            'hideSidebar' => true,
            'upgradableCount' => $cards->filter(fn (array $card) => $card['app']->hasUpgrade())->count(),
        ]);
    }

    protected function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * Full app registry: every discovered app with the install / enable /
     * disable / uninstall controls.
     */
    public function apps(AppManager $manager)
    {
        $this->discoverCached($manager);

        $apps = PlatformApp::orderBy('app_id')->get();

        return view('platform.apps.index', [
            'apps' => $apps,
            'upgradableCount' => $apps->filter(fn (PlatformApp $app) => $app->hasUpgrade())->count(),
        ]);
    }

    /**
     * Reading a handful of manifests is cheap, but not free on every page
     * load. Behind a short cache it removes the need to remember
     * `platform:app:discover` before a new version shows up.
     */
    protected function discoverCached(AppManager $manager): void
    {
        Cache::remember('platform.discover', now()->addSeconds(60), function () use ($manager) {
            $manager->discover();

            return true;
        });
    }

    /**
     * @return array{app: PlatformApp, description: ?string, entry: ?string}
     */
    protected function cardData(PlatformApp $app, AppManager $manager): array
    {
        // The description is mirrored into the registry by discovery, so the
        // launcher never opens a manifest file per tile.
        $description = $app->description;

        $entry = null;

        if ($app->status === PlatformApp::STATUS_ENABLED) {
            $entry = $manager->menuFor($app->app_id)[0]['route'] ?? null;
        }

        return [
            'app' => $app,
            'description' => $description ?: null,
            'entry' => $entry,
        ];
    }

    /**
     * Read-only preview of an upgrade, rendered as the confirmation dialog.
     */
    public function upgradePlan(string $app, AppUpgrader $upgrader)
    {
        $force = request()->boolean('force');

        return response()->json($upgrader->plan($app, $force)->toArray());
    }

    public function upgradeAllPlan(AppUpgrader $upgrader)
    {
        return response()->json($upgrader->planOutdated()->toArray());
    }

    public function upgrade(string $app, AppUpgrader $upgrader)
    {
        $force = request()->boolean('force');

        try {
            $plan = $upgrader->upgrade($app, $force);
        } catch (AppException $e) {
            return redirect()->route('platform.apps.index')->with('error', $e->getMessage());
        }

        return redirect()->route('platform.apps.index')->with('status', $this->upgradeSummary($plan));
    }

    public function upgradeAll(AppUpgrader $upgrader)
    {
        try {
            $plan = $upgrader->execute($upgrader->planOutdated());
        } catch (AppException $e) {
            return redirect()->route('platform.apps.index')->with('error', $e->getMessage());
        }

        return redirect()->route('platform.apps.index')->with('status', $this->upgradeSummary($plan));
    }

    protected function upgradeSummary(UpgradePlan $plan): string
    {
        $parts = array_map(
            fn ($item) => "{$item->appId()} → {$item->toVersion}",
            $plan->items,
        );

        return 'Upgraded '.implode(', ', $parts).'.';
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
            // Blank rows are how the editor represents "not filled in yet";
            // they are dropped below rather than rejected.
            'columns.name.*' => ['nullable', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'columns.type.*' => ['nullable', 'string', 'in:string,text,integer,float,decimal,boolean,date,datetime'],
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
                return $this->crudError('CRUD mode needs at least one column.');
            }

            $reserved = array_intersect($names, AppScaffolder::RESERVED_COLUMNS);

            if ($reserved !== []) {
                return $this->crudError(
                    'The columns '.implode(', ', AppScaffolder::RESERVED_COLUMNS).' are generated automatically; '.
                    'remove '.implode(', ', $reserved).'.'
                );
            }

            if (count($names) !== count(array_unique($names))) {
                return $this->crudError('Column names must be unique.');
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
            .($activate ? ' and installed & enabled.' : '. It is now discovered — install and enable it from the Apps page.');

        return redirect()->route('platform.apps.index')->with('status', $message);
    }

    /**
     * Send the wizard back with an inline error on the columns editor, keeping
     * everything the user already typed.
     */
    protected function crudError(string $message)
    {
        return redirect()->route('platform.apps.create')
            ->withErrors(['columns' => $message])
            ->withInput();
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
        } catch (\Exception $e) {
            return redirect()->route('platform.apps.index')->with('error', $e->getMessage());
        }

        return redirect()->route('platform.apps.index')->with('status', "App [{$app}] {$action}ed successfully.");
    }

    public function test(string $app)
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('platform:app:test', ['app_id' => $app]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            if (str_contains($output, 'ERRORS') || str_contains($output, 'FAILURES')) {
                return redirect()->route('platform.apps.index')->with('error', "Tests failed for app [{$app}]. See console for details.");
            }
            
            return redirect()->route('platform.apps.index')->with('status', "Tests passed for app [{$app}].");
        } catch (\Exception $e) {
            return redirect()->route('platform.apps.index')->with('error', $e->getMessage());
        }
    }

    public function seed(string $app)
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('platform:app:seed', ['app_id' => $app]);
            return redirect()->route('platform.apps.index')->with('status', "Seeder executed successfully for app [{$app}].");
        } catch (\Exception $e) {
            return redirect()->route('platform.apps.index')->with('error', $e->getMessage());
        }
    }
}
