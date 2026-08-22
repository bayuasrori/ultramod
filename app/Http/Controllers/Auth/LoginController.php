<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Models\PlatformLoginHistory;
use App\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request, AuditLogger $audit)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = strtolower($validated['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = ceil($seconds / 60);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$minutes} minute(s).",
            ]);
        }

        $user = User::where('email', $validated['email'])->first();

        if ($user && ! $user->is_active) {
            $this->recordHistory($request, $user, false);
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated.',
            ]);
        }

        if (! Auth::attempt($validated, $request->boolean('remember'))) {
            $this->recordHistory($request, $user, false);
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $this->recordHistory($request, $user, true);
        $audit->log('login', actorId: $user->id);

        return redirect()->intended(route('platform.index'));
    }

    public function logout(Request $request, AuditLogger $audit)
    {
        $audit->log('logout');

        PlatformLoginHistory::where('user_id', $request->user()?->id)
            ->whereNull('logout_at')
            ->latest('login_at')
            ->limit(1)
            ->update(['logout_at' => now()]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function recordHistory(Request $request, ?User $user, bool $successful): void
    {
        PlatformLoginHistory::create([
            'user_id' => $user?->id,
            'email' => $request->input('email', ''),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'successful' => $successful,
            'login_at' => now(),
        ]);
    }
}
