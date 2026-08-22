<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Models\PlatformLoginHistory;
use App\Platform\Models\PlatformRole;
use App\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        // First registered user becomes the administrator; everyone after
        // that is a regular member.
        $role = User::count() === 0
            ? PlatformRole::where('slug', 'admin')->first()
            : PlatformRole::where('slug', 'member')->first();

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'platform_role_id' => $role?->id,
            'is_active' => true,
        ]);

        $audit->log('user.registered', target: $user);

        Auth::login($user);

        return redirect()->route('platform.index');
    }
}
