<?php

namespace Tests\Feature;

use App\Platform\Models\PlatformApp;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase as BaseTestCase;

class AppWizardTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('platform:app:discover');
        $this->refreshApplication();
        $this->actingAsAdmin();
    }

    protected function tearDown(): void
    {
        $composerFile = base_path('composer.json');
        $composer = json_decode((string) file_get_contents($composerFile), true);

        foreach (['inventory' => 'Inventory', 'stockroom' => 'Stockroom'] as $id => $studly) {
            if (is_dir(base_path('apps/'.$id))) {
                File::deleteDirectory(base_path('apps/'.$id));
            }

            PlatformApp::where('app_id', $id)->delete();
            unset($composer['autoload']['psr-4']["PlatformApps\\{$studly}\\"]);
        }

        Schema::dropIfExists('items');
        Schema::dropIfExists('boxes');

        file_put_contents(
            $composerFile,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        parent::tearDown();
    }

    public function test_wizard_scaffolds_and_activates_a_new_app(): void
    {
        $this->get(route('platform.apps.create'))
            ->assertOk()
            ->assertSee('Create a new app');

        $this->post(route('platform.apps.store'), ['name' => 'inventory', 'activate' => '1'])
            ->assertRedirect(route('platform.apps.index'))
            ->assertSessionHas('status');

        // skeleton created
        $this->assertFileExists(base_path('apps/inventory/platform.json'));
        $this->assertFileExists(base_path('apps/inventory/src/InventoryServiceProvider.php'));
        $this->assertFileExists(base_path('apps/inventory/routes/web.php'));

        // registered, installed and enabled
        $app = PlatformApp::where('app_id', 'inventory')->firstOrFail();
        $this->assertSame(PlatformApp::STATUS_ENABLED, $app->status);

        // manually require the newly scaffolded files since composer autoloader is already cached in this process
        require_once base_path('apps/inventory/src/InventoryServiceProvider.php');
        require_once base_path('apps/inventory/src/Http/Controllers/InventoryController.php');

        // the new app is live: its route responds and its menu is listed
        $this->refreshApplication();
        $this->actingAsAdmin();
        $this->get('/inventory')->assertOk()->assertSee('Hello from Inventory App!');
        $this->get('/platform')->assertOk()->assertSee('Inventory');

        // composer.json got the new namespace
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $this->assertArrayHasKey('PlatformApps\\Inventory\\', $composer['autoload']['psr-4']);
    }

    public function test_wizard_generates_a_full_crud_stack(): void
    {
        $this->post(route('platform.apps.store'), [
            'name' => 'inventory',
            'crud' => '1',
            'table' => 'items',
            'columns' => [
                'name' => ['title', 'price', 'weight', 'notes', 'active'],
                'type' => ['string', 'decimal', 'float', 'text', 'boolean'],
            ],
        ])
            ->assertRedirect(route('platform.apps.index'))
            ->assertSessionHas('status');

        $base = base_path('apps/inventory');

        $this->assertFileExists($base.'/src/Models/Item.php');
        $this->assertFileExists($base.'/src/Http/Requests/StoreItemRequest.php');
        $this->assertFileExists($base.'/resources/views/inventory/index.blade.php');
        $this->assertFileExists($base.'/resources/views/inventory/form.blade.php');
        $this->assertFileDoesNotExist(
            $base.'/resources/views/inventory.blade.php',
            'the plain placeholder view is replaced by the CRUD views',
        );

        $migration = file_get_contents(glob($base.'/database/migrations/*_create_items_table.php')[0]);

        $this->assertStringContainsString('$table->id();', $migration);
        $this->assertStringContainsString("\$table->string('title');", $migration);
        $this->assertStringContainsString("\$table->decimal('price', 10, 2);", $migration);
        $this->assertStringContainsString("\$table->float('weight');", $migration);
        $this->assertStringContainsString("\$table->text('notes');", $migration);
        $this->assertStringContainsString("\$table->boolean('active');", $migration);
        $this->assertStringContainsString('$table->timestamps();', $migration);

        // Only the edit form may spoof PUT; the create form posts to store.
        $form = file_get_contents($base.'/resources/views/inventory/form.blade.php');
        $this->assertStringContainsString('@if ($record->id)', $form);
        $this->assertStringContainsString("@method('PUT')", $form);

        $request = file_get_contents($base.'/src/Http/Requests/StoreItemRequest.php');
        $this->assertStringContainsString("'weight' => ['required', 'numeric'],", $request);

        $model = file_get_contents($base.'/src/Models/Item.php');
        $this->assertStringContainsString("'weight' => 'float',", $model);
        $this->assertStringNotContainsString("'id'", $model);
    }

    /**
     * The structural assertions above cannot catch a resource route whose
     * parameter does not match the controller argument, so the generated app
     * is booted and driven through its own CRUD cycle here.
     */
    public function test_generated_crud_app_serves_a_working_edit_and_update(): void
    {
        $this->post(route('platform.apps.store'), [
            'name' => 'stockroom',
            'crud' => '1',
            'table' => 'boxes',
            'activate' => '1',
            'columns' => [
                'name' => ['title', 'active'],
                'type' => ['string', 'boolean'],
            ],
        ])->assertRedirect(route('platform.apps.index'));

        // The Composer autoloader in this process predates the new files.
        foreach ([
            'src/StockroomServiceProvider.php',
            'src/Models/Box.php',
            'src/Http/Requests/StoreBoxRequest.php',
            'src/Http/Controllers/StockroomController.php',
        ] as $file) {
            require_once base_path('apps/stockroom/'.$file);
        }

        $this->refreshApplication();
        $this->actingAsAdmin();

        $this->assertSame(
            'stockroom/{record}/edit',
            app('router')->getRoutes()->getByName('stockroom.edit')->uri(),
            'the resource parameter must match the controller argument',
        );

        $this->get('/stockroom/create')
            ->assertOk()
            ->assertSee('<input type="hidden" name="active" value="0">', false);

        $this->post('/stockroom', ['title' => 'Widget', 'active' => '1'])->assertRedirect('/stockroom');

        $id = DB::table('boxes')->value('id');
        $this->assertNotNull($id);

        $this->get("/stockroom/{$id}/edit")
            ->assertOk()
            ->assertSee('value="Widget"', false)
            ->assertSee('name="_method" value="PUT"', false);

        $this->put("/stockroom/{$id}", ['title' => 'Renamed', 'active' => '0'])->assertRedirect('/stockroom');
        $this->assertDatabaseHas('boxes', ['id' => $id, 'title' => 'Renamed', 'active' => 0]);

        $this->delete("/stockroom/{$id}")->assertRedirect('/stockroom');
        $this->assertDatabaseMissing('boxes', ['id' => $id]);
    }

    public function test_wizard_rejects_generated_and_duplicate_columns(): void
    {
        $payload = [
            'name' => 'inventory',
            'crud' => '1',
            'table' => 'items',
        ];

        $this->post(route('platform.apps.store'), $payload + [
            'columns' => ['name' => ['id', 'title'], 'type' => ['integer', 'string']],
        ])->assertSessionHasErrors('columns');

        $this->post(route('platform.apps.store'), $payload + [
            'columns' => ['name' => ['title', 'title'], 'type' => ['string', 'string']],
        ])->assertSessionHasErrors('columns');

        $this->post(route('platform.apps.store'), $payload + [
            'columns' => ['name' => [''], 'type' => ['string']],
        ])->assertSessionHasErrors('columns');

        $this->post(route('platform.apps.store'), $payload + [
            'columns' => ['name' => ['title'], 'type' => ['jsonb']],
        ])->assertSessionHasErrors('columns.type.0');

        $this->assertDirectoryDoesNotExist(base_path('apps/inventory'));
    }

    public function test_wizard_rejects_invalid_names(): void
    {
        $this->post(route('platform.apps.store'), ['name' => 'Not Valid!'])
            ->assertSessionHasErrors('name');

        $this->post(route('platform.apps.store'), ['name' => 'notes'])
            ->assertRedirect(route('platform.apps.create'))
            ->assertSessionHas('error');
    }
}
