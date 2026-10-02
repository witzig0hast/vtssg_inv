<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request, TwoFactor $tf)
    {
        $user = $request->user();
        $setup = null;
        if (! $user->hasTwoFactor() && $request->session()->has('2fa_setup_secret')) {
            $secret = $request->session()->get('2fa_setup_secret');
            $setup = ['secret' => $secret, 'qr' => $tf->qrDataUri($tf->uri($user, $secret))];
        }

        return view('profile.edit', [
            'user' => $user,
            'setup' => $setup,
            'tokens' => $user->tokens()->latest()->get(),
            'recovery' => $request->session()->pull('recovery_codes'),
            'newToken' => $request->session()->pull('new_token'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:40']]);
        $request->user()->update($data);

        return back()->with('status', 'Profil gespeichert.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(config('inventory.password_min'))->letters()->mixedCase()->numbers()->uncompromised(0)],
        ]);
        if (Hash::check($data['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Das neue Passwort muss sich vom alten unterscheiden.']);
        }
        $request->user()->forceFill(['password' => $data['password'], 'must_change_password' => false, 'password_changed_at' => now()])->save();
        // Andere Sitzungen ungültig machen
        $request->session()->regenerate();
        AuditLog::record('password_changed', $request->user(), summary: $request->user()->email);

        return back()->with('status', 'Passwort geändert.');
    }

    public function twoFactorStart(Request $request, TwoFactor $tf)
    {
        abort_if($request->user()->hasTwoFactor(), 409);
        $request->session()->put('2fa_setup_secret', $tf->newSecret());

        return redirect()->route('profile.edit');
    }

    public function twoFactorConfirm(Request $request, TwoFactor $tf)
    {
        $request->validate(['code' => ['required', 'string']]);
        $secret = $request->session()->get('2fa_setup_secret');
        abort_unless($secret, 409);
        if (! $tf->verify($secret, $request->string('code'))) {
            return back()->withErrors(['code' => 'Der Code ist ungültig.']);
        }
        $codes = $tf->newRecoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => $secret, 'two_factor_recovery_codes' => $codes, 'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('2fa_setup_secret');
        $request->session()->flash('recovery_codes', $codes);
        AuditLog::record('2fa_enabled', $request->user(), summary: $request->user()->email);

        return redirect()->route('profile.edit');
    }

    public function twoFactorDisable(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        abort_if(config('inventory.require_2fa'), 403, '2FA ist vorgeschrieben.');
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLog::record('2fa_disabled', $request->user(), summary: $request->user()->email);

        return back()->with('status', '2FA deaktiviert.');
    }

    public function tokenCreate(Request $request)
    {
        $data = $request->validate(['token_name' => ['required', 'string', 'max:60']]);
        $token = $request->user()->createToken($data['token_name'], ['inventory'], now()->addDays(365));
        AuditLog::record('api_token_created', $request->user(), summary: $data['token_name']);

        return back()->with('new_token', $token->plainTextToken);
    }

    public function tokenDelete(Request $request, int $id)
    {
        $request->user()->tokens()->whereKey($id)->delete();

        return back()->with('status', 'Token gelöscht.');
    }
}
