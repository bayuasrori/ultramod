<?php

namespace Tests\Feature;

use App\Platform\Models\PlatformApp;
use Tests\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class TasksAppTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('platform:app:discover');
        $this->actingAsAdmin();
    }

    protected function nextRequest(): void
    {
        $this->refreshApplication();
        $this->actingAsAdmin();
    }

    public function test_tasks_full_lifecycle(): void
    {
        $this->assertFalse(Schema::hasTable('tasks'));

        $this->artisan('platform:app:install', ['app' => 'tasks'])->assertSuccessful();
        $this->artisan('platform:app:enable', ['app' => 'tasks'])->assertSuccessful();
        $this->nextRequest();

        $this->assertTrue(Schema::hasTable('tasks'));
        $this->assertSame(4, PlatformApp::where('app_id', 'tasks')->firstOrFail()->permissions()->count());

        // create
        $this->post('/tasks', ['title' => 'Belajar platform'])->assertRedirect('/tasks');
        $this->post('/tasks', ['title' => 'Buat app baru'])->assertRedirect('/tasks');
        $this->get('/tasks')->assertOk()->assertSee('Belajar platform')->assertSee('Buat app baru');

        // validation
        $this->post('/tasks', ['title' => ''])->assertSessionHasErrors('title');

        // toggle to done
        $id = \DB::table('tasks')->orderBy('id')->value('id');
        $this->patch("/tasks/{$id}/toggle")->assertRedirect('/tasks');
        $this->assertSame(1, \DB::table('tasks')->where('done', true)->count());

        // delete
        $this->delete("/tasks/{$id}")->assertRedirect('/tasks');
        $this->assertSame(1, \DB::table('tasks')->count());

        // disable hides route, data survives
        $this->artisan('platform:app:disable', ['app' => 'tasks'])->assertSuccessful();
        $this->nextRequest();
        $this->get('/tasks')->assertNotFound();

        $this->artisan('platform:app:enable', ['app' => 'tasks'])->assertSuccessful();
        $this->nextRequest();
        $this->get('/tasks')->assertOk()->assertSee('Buat app baru');

        // uninstall (must be disabled first) drops the table
        $this->artisan('platform:app:uninstall', ['app' => 'tasks'])->assertFailed();
        $this->artisan('platform:app:disable', ['app' => 'tasks'])->assertSuccessful();
        $this->artisan('platform:app:uninstall', ['app' => 'tasks'])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('tasks'));
        $this->assertFalse(PlatformApp::where('app_id', 'tasks')->exists());
    }
}
