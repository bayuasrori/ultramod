<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Platform\Models\PlatformAppPermission;
use App\Platform\Models\PlatformRole;
use App\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return view('platform.roles.index', [
            'roles' => PlatformRole::withCount('permissions')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('platform.roles.form', [
            'role' => new PlatformRole(),
            'groupedPermissions' => $this->groupedPermissions(),
            'selectedIds' => [],
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_super_admin' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:platform_app_permissions,id'],
        ]);

        $role = PlatformRole::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'is_super_admin' => $request->boolean('is_super_admin'),
        ]);

        $role->syncPermissionIds($validated['permissions'] ?? []);

        $audit->log('role.created', target: $role, metadata: ['name' => $role->name]);

        return redirect()->route('platform.roles.index')->with('status', "Role [{$role->name}] created.");
    }

    public function edit(PlatformRole $role)
    {
        return view('platform.roles.form', [
            'role' => $role,
            'groupedPermissions' => $this->groupedPermissions(),
            'selectedIds' => $role->permissions()->pluck('platform_app_permissions.id')->all(),
        ]);
    }

    public function update(Request $request, PlatformRole $role, AuditLogger $audit)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_super_admin' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:platform_app_permissions,id'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'is_super_admin' => $request->boolean('is_super_admin'),
        ]);

        $role->syncPermissionIds($validated['permissions'] ?? []);

        $audit->log('role.updated', target: $role, metadata: ['name' => $role->name]);

        return redirect()->route('platform.roles.index')->with('status', "Role [{$role->name}] updated.");
    }

    public function destroy(PlatformRole $role, AuditLogger $audit)
    {
        if ($role->slug === 'admin') {
            return redirect()->route('platform.roles.index')
                ->with('error', 'The Administrator role cannot be deleted.');
        }

        if (User::where('platform_role_id', $role->id)->exists()) {
            return redirect()->route('platform.roles.index')
                ->with('error', "Role [{$role->name}] is still assigned to users.");
        }

        $audit->log('role.deleted', metadata: ['name' => $role->name]);

        $role->delete();

        return redirect()->route('platform.roles.index')->with('status', "Role [{$role->name}] deleted.");
    }

    /**
     * @return array<string, \Illuminate\Support\Collection<int, PlatformAppPermission>>
     */
    protected function groupedPermissions()
    {
        return PlatformAppPermission::orderBy('app_id')->orderBy('name')
            ->get()
            ->groupBy('app_id');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $i = 1;

        while (PlatformRole::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }
}
