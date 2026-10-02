<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = Str::lower($data['email']);
        $throttleKey = $email.'|'.$request->ip();
        $max = config('inventory.max_login_attempts');

        if (RateLimiter::tooManyAttempts($throttleKey, $max)) {
            $sec = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages(['email' => "Zu viele Versuche. Bitte in $sec Sekunden erneut probieren."]);
        }

        $user = User::query()->where('email', $email)->first();

        // Immer einen Hash prüfen, um Timing-Unterschiede (User-Enumeration) zu vermeiden.
        $ok = Hash::check($data['password'], $user?->password ?? '$2y$12$usesomesillystringforsaltuUlYEyaJQFNMFEXeN0hYOzMZoZ9nGq');

        if (! $user || ! $ok || ! $user->is_active || $user->isLocked()) {
            RateLimiter::hit($throttleKey, 60);
            if ($user && ! $ok) {
                $user->increment('failed_logins');
                if ($user->failed_logins >= $max) {
                    $user->forceFill(['locked_until' => now()->addMinutes(config('inventory.lockout_minutes')), 'failed_logins' => 0])->saveQuietly();
                }
            }
            AuditLog::record('login_failed', $user, summary: $email);
            throw ValidationException::withMessages(['email' => 'Anmeldung nicht möglich. Zugangsdaten prüfen oder später erneut versuchen.']);
        }

        RateLimiter::clear($throttleKey);
        $user->forceFill(['failed_logins' => 0, 'locked_until' => null, 'last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();

        Auth::login($user, false);
        $request->session()->regenerate();
        if ($user->hasTwoFactor()) {
            $request->session()->put('2fa_pending', true);
        }
        AuditLog::record('login', $user, summary: $user->email);

        return $user->hasTwoFactor() ? redirect()->route('two-factor.challenge') : redirect()->intended(route('dashboard'));
    }

    public function challenge()
    {
        return view('auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request, TwoFactor $tf)
    {
        $request->validate(['code' => ['nullable', 'string'], 'recovery_code' => ['nullable', 'string']]);
        $user = $request->user();
        $key = '2fa|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Zu viele Versuche. Bitte warten.']);
        }

        $valid = $request->filled('code')
            ? $tf->verify($user->two_factor_secret, $request->string('code'))
            : ($request->filled('recovery_code') && $tf->consumeRecoveryCode($user, $request->string('recovery_code')));

        if (! $valid) {
            RateLimiter::hit($key, 60);
            AuditLog::record('2fa_failed', $user, summary: $user->email);
            throw ValidationException::withMessages(['code' => 'Der Code ist ungültig.']);
        }

        RateLimiter::clear($key);
        $request->session()->forget('2fa_pending');
        $request->session()->regenerate();
        AuditLog::record('2fa_passed', $user, summary: $user->email);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout', $request->user(), summary: $request->user()?->email);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
