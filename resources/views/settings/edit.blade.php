<x-layout title="Einstellungen">
    <x-page-head title="Einstellungen" />
    <form method="post" action="{{ route('settings.update') }}" class="card card-b max-w-2xl space-y-4">@csrf @method('PUT')
        <x-input name="company_name" label="Firmenname" :value="$s['company_name']" required />
        <x-textarea name="company_address" label="Firmenadresse (für Lieferscheine)" :value="$s['company_address']" />
        <div class="grid gap-4 md:grid-cols-2"><x-input name="asset_tag_prefix" label="Asset-Tag-Präfix" :value="$s['asset_tag_prefix']" required /><x-input name="asset_tag_digits" type="number" label="Stellen" :value="$s['asset_tag_digits']" required /></div>
        <x-input name="inspection_warn_days" type="number" label="Prüfungen vorwarnen (Tage)" :value="$s['inspection_warn_days']" required />
        <x-input name="notify_email" type="email" label="Zusätzliche Benachrichtigungs-E-Mail" :value="$s['notify_email']" hint="Erhält die tägliche Übersicht fälliger Prüfungen & Mindestbestände." />
        <button class="btn btn-primary">Speichern</button>
    </form>
</x-layout>
