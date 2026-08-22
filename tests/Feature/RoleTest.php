<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Models\PlatformAppPermission;
use App\Platform\Models\PlatformRole;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformRoleSeeder::class);

        // A permission that mimics one registered by an installed app.
        PlatformAppPermission::firstOrCreate(
            ['app_id' => 'notes', 'name' => 'notes.create'],
        );
    }

    protected function memberWith(array $permissionNames): User
    {
        $role = PlatformRole::create(['name' => 'Editor', 'slug' => 'editor']);

        $role->syncPermissionIds(
            PlatformAppPermission::whereIn('name', $permissionNames)->pluck('id')->all()
        );

        return User::create([
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => 'secret123',
            'platform_role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    protected function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'root@example.com',
            'password' => 'secret123',
            'platform_role_id' => PlatformRole::where('slug', 'admin')->first()->id,
            'is_active' => true,
        ]);
    }

    public function test_user_without_permission_is_denied(): void
    {
        $user = $this->memberWith([]);

        $this->assertFalse($user->can('notes.create'));
        $this->assertFalse($user->can('platform.manage'));
    }

    public function test_user_with_role_permission_is_allowed(): void
    {
        $user = $this->memberWith(['notes.create']);

        $this->assertTrue($user->can('notes.create'));
        $this->assertFalse($user->can('notes.delete'));
    }

    public function test_super_admin_bypasses_all_gates(): void
    {
        $admin = $this->admin();

        $this->assertTrue($admin->can('notes.create'));
        $this->assertTrue($admin->can('notes.delete'));
        $this->assertTrue($admin->can('platform.manage'));
        $this->assertTrue($admin->can('anything.else'));
    }

    public function test_permission_revocation_takes_effect_immediately(): void
    {
        $user = $this->memberWith(['notes.create']);
        $this->assertTrue($user->can('notes.create'));

        $user->role->syncPermissionIds([]);
        $user->refresh();

        $this->assertFalse($user->can('notes.create'));
    }

    public function test_management_pages_require_platform_manage(): void
    {
        $member = $this->memberWith(['notes.create']);

        $this->actingAs($member)->get('/platform/roles')->assertForbidden();
        $this->actingAs($member)->get('/platform/users')->assertForbidden();
    }

    public function test_admin_can_access_management_pages(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/platform/roles')->assertOk();
        $this->actingAs($admin)->get('/platform/users')->assertOk();
        $this->actingAs($admin)->get('/platform/apps/create')->assertOk();
    }

    public function test_admin_can_create_role_with_permissions(): void
    {
        $admin = $this->admin();
        $permissionId = PlatformAppPermission::where('name', 'notes.create')->first()->id;

        $this->actingAs($admin)->post('/platform/roles', [
            'name' => 'Note Writer',
            'permissions' => [$permissionId],
        ])->assertRedirect('/platform/roles');

        $role = PlatformRole::where('slug', 'note-writer')->first();

        $this->assertNotNull($role);
        $this->assertFalse($role->is_super_admin);
        $this->assertTrue($role->hasPermission('notes.create'));
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $admin = $this->admin();
        $permissionId = PlatformAppPermission::where('name', 'notes.create')->first()->id;

        $role = PlatformRole::create(['name' => 'Writer', 'slug' => 'writer']);

        $this->actingAs($admin)->put("/platform/roles/{$role->id}", [
            'name' => 'Writer',
            'permissions' => [$permissionId],
        ])->assertRedirect('/platform/roles');

        $role->refresh();
        $this->assertTrue($role->hasPermission('notes.create'));

        // revoke
        $this->actingAs($admin)->put("/platform/roles/{$role->id}", [
            'name' => 'Writer',
            'permissions' => [],
        ])->assertRedirect('/platform/roles');

        $role->refresh();
        $this->assertFalse($role->hasPermission('notes.create'));
    }

    public function test_admin_role_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $adminRole = PlatformRole::where('slug', 'admin')->first();

        $this->actingAs($admin)
            ->delete("/platform/roles/{$adminRole->id}")
            ->assertRedirect('/platform/roles');

        $this->assertDatabaseHas('platform_roles', ['id' => $adminRole->id]);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $role = PlatformRole::create(['name' => 'Busy', 'slug' => 'busy']);

        User::create([
            'name' => 'Holder',
            'email' => 'holder@example.com',
            'password' => 'secret123',
            'platform_role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete("/platform/roles/{$role->id}")
            ->assertRedirect('/platform/roles');

        $this->assertDatabaseHas('platform_roles', ['id' => $role->id]);
    }

    public function test_admin_can_create_and_edit_user(): void
    {
        $admin = $this->admin();
        $roleId = PlatformRole::where('slug', 'member')->first()->id;

        $this->actingAs($admin)->post('/platform/users', [
            'name' => 'New Person',
            'email' => 'person@example.com',
            'password' => 'secret123',
            'platform_role_id' => $roleId,
        ])->assertRedirect('/platform/users');

        $user = User::where('email', 'person@example.com')->first();
        $this->assertNotNull($user);

        $this->actingAs($admin)->put("/platform/users/{$user->id}", [
            'name' => 'Renamed Person',
            'email' => 'person@example.com',
            'platform_role_id' => $roleId,
        ])->assertRedirect('/platform/users');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Renamed Person']);
    }

    public function test_admin_can_deactivate_and_activate_user(): void
    {
        $admin = $this->admin();
        $user = User::create([
            'name' => 'Target',
            'email' => 'target@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put("/platform/users/{$user->id}/deactivate")
            ->assertRedirect('/platform/users');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);

        $this->actingAs($admin)
            ->put("/platform/users/{$user->id}/activate")
            ->assertRedirect('/platform/users');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/platform/users/{$admin->id}/deactivate")
            ->assertStatus(400);

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
    }

    public function test_installing_an_app_grants_view_permissions_to_member_role(): void
    {
        PlatformAppPermission::firstOrCreate(['app_id' => 'kanban', 'name' => 'kanban.view']);
        PlatformAppPermission::firstOrCreate(['app_id' => 'kanban', 'name' => 'kanban.create']);

        // a user holding the seeded member role has no kanban access yet
        $member = User::create([
            'name' => 'Plain Member',
            'email' => 'plain@example.com',
            'password' => 'secret123',
            'platform_role_id' => PlatformRole::where('slug', 'member')->first()->id,
            'is_active' => true,
        ]);
        $this->assertFalse($member->can('kanban.view'));

        // simulate app install permission sync through AppManager::syncPermissions
        \App\Platform\Models\PlatformApp::firstOrCreate(
            ['app_id' => 'kanban'],
            [
                'name' => 'Kanban',
                'version' => '1.0.0',
                'provider' => 'PlatformApps\\Kanban\\KanbanServiceProvider',
                'status' => 'discovered',
            ],
        );

        $manager = $this->app->make(\App\Platform\Services\AppManager::class);
        $manager->install('kanban');

        $member->refresh()->load('role.permissions');
        $this->assertTrue($member->can('kanban.view'), 'member should gain view permission');
        $this->assertFalse($member->can('kanban.create'), 'member must not gain write permission');
    }
}
