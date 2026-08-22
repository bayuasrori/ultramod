<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Platform\Models\PlatformRole;
use App\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('platform.users.index', [
            'users' => User::with('role')->orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('platform.users.form', [
            'user' => new User(),
            'roles' => PlatformRole::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'platform_role_id' => ['nullable', 'exists:platform_roles,id'],
        ]);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $audit->log('user.created', target: $user);

        return redirect()->route('platform.users.index')->with('status', "User [{$user->name}] created.");
    }

    public function edit(User $user)
    {
        return view('platform.users.form', [
            'user' => $user,
            'roles' => PlatformRole::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user, AuditLogger $audit)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', Password::defaults()],
            'platform_role_id' => ['nullable', 'exists:platform_roles,id'],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        $audit->log('user.updated', target: $user);

        return redirect()->route('platform.users.index')->with('status', "User [{$user->name}] updated.");
    }

    public function activate(User $user, AuditLogger $audit)
    {
        $this->guardSelfAction($user, 'activate');

        $user->update(['is_active' => true]);
        $audit->log('user.activated', target: $user);

        return redirect()->route('platform.users.index')->with('status', "User [{$user->name}] activated.");
    }

    public function deactivate(User $user, AuditLogger $audit)
    {
        $this->guardSelfAction($user, 'deactivate');

        $user->update(['is_active' => false]);
        $audit->log('user.deactivated', target: $user);

        return redirect()->route('platform.users.index')->with('status', "User [{$user->name}] deactivated.");
    }

    protected function guardSelfAction(User $user, string $action): void
    {
        if ($user->id === auth()->id()) {
            abort(400, "You cannot {$action} your own account.");
        }
    }
}
