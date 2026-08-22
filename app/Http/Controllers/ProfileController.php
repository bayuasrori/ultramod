<?php

namespace App\Http\Controllers;

use App\Platform\Models\PlatformLoginHistory;
use App\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('platform.profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request, AuditLogger $audit)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update($validated);
        $audit->log('profile.updated', target: $request->user());

        return redirect()->route('profile.edit')->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request, AuditLogger $audit)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $audit->log('profile.password_changed', target: $request->user());

        return redirect()->route('profile.edit')->with('status', 'Password changed.');
    }

    public function security(Request $request)
    {
        $history = PlatformLoginHistory::where('user_id', $request->user()->id)
            ->latest('login_at')
            ->limit(20)
            ->get();

        return view('platform.profile.security', ['history' => $history]);
    }
}
