<x-layout title="Mein Profil">
    <x-page-head title="Mein Profil" :sub="$user->email" />
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card"><div class="card-h"><h2>Stammdaten</h2></div>
            <form method="post" action="{{ route('profile.update') }}" class="card-b space-y-4">@csrf @method('PUT')
                <x-input name="name" label="Name" :value="$user->name" required />
                <x-input name="phone" label="Telefon" :value="$user->phone" />
                <button class="btn btn-primary">Speichern</button>
            </form>
        </div>

        <div class="card"><div class="card-h"><h2>Passwort ändern</h2></div>
            <form method="post" action="{{ route('profile.password') }}" class="card-b space-y-4">@csrf @method('PUT')
                <x-input name="current_password" type="password" label="Aktuelles Passwort" required autocomplete="current-password" />
                <x-input name="password" type="password" label="Neues Passwort" required autocomplete="new-password" :hint="'Mindestens '.config('inventory.password_min').' Zeichen, Groß-/Kleinbuchstaben, Zahl; wird gegen bekannte Datenlecks geprüft.'" />
                <x-input name="password_confirmation" type="password" label="Wiederholen" required autocomplete="new-password" />
                <button class="btn btn-primary">Passwort ändern</button>
            </form>
        </div>

        <div class="card"><div class="card-h"><h2>Zwei-Faktor-Authentifizierung</h2>
            <x-badge :color="$user->hasTwoFactor() ? '#16a34a' : '#d97706'">{{ $user->hasTwoFactor() ? 'Aktiv' : 'Inaktiv' }}</x-badge></div>
            <div class="card-b space-y-4">
                @if ($recovery)
                    <div class="alert border-amber-200 bg-amber-50 text-amber-900">
                        <p class="font-semibold">Recovery-Codes – jetzt sicher speichern, sie werden nur einmal angezeigt:</p>
                        <ul class="mt-2 grid grid-cols-2 gap-1 font-mono text-sm">@foreach ($recovery as $c)<li>{{ $c }}</li>@endforeach</ul>
                    </div>
                @endif
                @if ($user->hasTwoFactor())
                    <form method="post" action="{{ route('profile.2fa.disable') }}" data-confirm="2FA wirklich deaktivieren?" class="space-y-3">@csrf @method('DELETE')
                        <x-input name="current_password" type="password" label="Passwort zur Bestätigung" required />
                        <button class="btn btn-danger" @disabled(config('inventory.require_2fa'))>2FA deaktivieren</button>
                    </form>
                @elseif ($setup)
                    <p class="text-sm text-slate-600">Scanne den QR-Code mit einer Authenticator-App (z. B. Aegis, 2FAS, Google Authenticator) und gib den Code ein.</p>
                    <img src="{{ $setup['qr'] }}" alt="QR-Code für 2FA" class="h-48 w-48 rounded border border-slate-200">
                    <p class="text-xs text-slate-500">Manueller Schlüssel: <code class="font-mono">{{ $setup['secret'] }}</code></p>
                    <form method="post" action="{{ route('profile.2fa.confirm') }}" class="flex items-end gap-2">@csrf
                        <x-input name="code" label="6-stelliger Code" inputmode="numeric" autocomplete="one-time-code" class="flex-1" />
                        <button class="btn btn-primary">Aktivieren</button>
                    </form>
                @else
                    <p class="text-sm text-slate-600">Schütze dein Konto zusätzlich mit einem Einmalcode.</p>
                    <form method="post" action="{{ route('profile.2fa.start') }}">@csrf<button class="btn btn-primary">2FA einrichten</button></form>
                @endif
            </div>
        </div>

        <div class="card"><div class="card-h"><h2>API-Tokens</h2></div>
            <div class="card-b space-y-4">
                @if ($newToken)<div class="alert border-amber-200 bg-amber-50 text-amber-900">Neuer Token (wird nur einmal angezeigt):<br><code class="break-all font-mono text-xs">{{ $newToken }}</code></div>@endif
                <form method="post" action="{{ route('profile.tokens.create') }}" class="flex items-end gap-2">@csrf
                    <x-input name="token_name" label="Bezeichnung" class="flex-1" placeholder="z. B. Handscanner Lager" /><button class="btn">Token erstellen</button>
                </form>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($tokens as $t)<li class="flex items-center justify-between py-2"><span>{{ $t->name }} <span class="text-xs text-slate-500">· läuft ab {{ $t->expires_at?->format('d.m.Y') }}</span></span>
                        <x-delete :action="route('profile.tokens.delete', $t->id)" confirm="Token löschen?" /></li>@empty<li class="py-2 text-slate-500">Keine Tokens.</li>@endforelse
                </ul>
                <p class="hint">Nutzung: <code>Authorization: Bearer &lt;token&gt;</code> auf <code>/api/v1/…</code></p>
            </div>
        </div>
    </div>
</x-layout>
