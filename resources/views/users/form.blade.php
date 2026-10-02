<x-layout title="Benutzer">
    <x-page-head :title="$u->exists ? $u->name.' bearbeiten' : 'Neuer Benutzer'" />
    <form method="post" action="{{ $u->exists ? route('users.update', $u) : route('users.store') }}" class="card card-b max-w-2xl space-y-4">@csrf @if ($u->exists) @method('PUT') @endif
        <x-input name="name" label="Name" :value="$u->name" required />
        <x-input name="email" type="email" label="E-Mail" :value="$u->email" required />
        <x-input name="phone" label="Telefon" :value="$u->phone" />
        <x-select name="role" label="Rolle" required :options="$roles->pluck('name', 'name')" :value="$u->roles->first()?->name" hint="Administrator: alles · Lager: Bestand, Prüfung, Wartung · Projektleitung: Projekte & Kunden · Techniker: Scannen/Packen · Nur Lesen" />
        <x-input name="password" type="password" :label="$u->exists ? 'Neues Passwort (leer = unverändert)' : 'Initiales Passwort'" :required="! $u->exists" autocomplete="new-password" hint="Der Benutzer muss es beim ersten Login ändern." />
        <x-checkbox name="is_active" label="Konto aktiv" :checked="$u->is_active" />
        @if ($u->exists && $u->hasTwoFactor())<x-checkbox name="reset_2fa" label="2FA zurücksetzen (z. B. Gerät verloren)" :checked="false" />@endif
        <div class="flex gap-2"><button class="btn btn-primary">Speichern</button><a href="{{ route('users.index') }}" class="btn">Abbrechen</a></div>
    </form>
</x-layout>
