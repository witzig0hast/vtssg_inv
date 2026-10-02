<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Erzwingt: aktives Konto, abgeschlossene 2FA-Abfrage, Passwortwechsel bei Erstanmeldung. */
class EnsureAccountReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active || $user->trashed()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Dieses Konto ist deaktiviert.']);
        }

        if ($request->session()->get('2fa_pending') && ! $request->routeIs('two-factor.*', 'logout')) {
            return redirect()->route('two-factor.challenge');
        }

        if ($user->must_change_password && ! $request->routeIs('profile.*', 'logout', 'two-factor.*')) {
            return redirect()->route('profile.edit')->with('warning', 'Bitte ändere zuerst dein Passwort.');
        }

        if (config('inventory.require_2fa') && ! $user->hasTwoFactor() && ! $request->routeIs('profile.*', 'logout', 'two-factor.*')) {
            return redirect()->route('profile.edit')->with('warning', 'Für dein Konto ist eine Zwei-Faktor-Authentifizierung vorgeschrieben.');
        }

        return $next($request);
    }
}
