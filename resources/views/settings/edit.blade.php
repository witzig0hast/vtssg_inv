<x-layout title="Einstellungen">
    <x-page-head title="Einstellungen" />
    <form method="post" action="{{ route('settings.update') }}" class="card card-b max-w-2xl space-y-4">@csrf @method('PUT')
        <x-input name="company_name" label="Firmenname" :value="$s['company_name']" required />
        <x-textarea name="company_address" label="Firmenadresse (für Lieferscheine)" :value="$s['company_address']" />
        <div class="grid gap-4 md:grid-cols-2"><x-input name="asset_tag_prefix" label="Asset-Tag-Präfix" :value="$s['asset_tag_prefix']" required /><x-input name="asset_tag_digits" type="number" label="Stellen" :value="$s['asset_tag_digits']" required /></div>
        <x-input name="inspection_warn_days" type="number" label="Prüfungen vorwarnen (Tage)" :value="$s['inspection_warn_days']" required />
        <x-input name="notify_email" type="email" label="Zusätzliche Benachrichtigungs-E-Mail" :value="$s['notify_email']" hint="Erhält die tägliche Übersicht fälliger Prüfungen & Mindestbestände." />
        <x-input name="revenue_account" label="Erlöskonto (Buchhaltungs-Export)" :value="$s['revenue_account']" required />
        <fieldset class="space-y-3 rounded-lg border border-slate-200 p-4"><legend class="px-1 text-sm font-semibold">Webhook (z. B. Rentman, Slack-Brücke, n8n)</legend>
            <x-input name="webhook_url" type="url" label="Ziel-URL (https)" :value="$s['webhook_url']" hint="Ereignisse: project.status_changed, asset.checked_out/in, ticket.created, inspection.failed. Signatur: X-Inventory-Signature (HMAC-SHA256). Private Netze sind blockiert." />
            <x-input name="webhook_secret" type="password" label="Signatur-Geheimnis" hint="{{ $hasSecret ? 'Gesetzt – leer lassen, um beizubehalten.' : 'Mindestens 16 Zeichen.' }}" autocomplete="new-password" />
        </fieldset>
        <button class="btn btn-primary">Speichern</button>
    </form>
    @if ($s['webhook_url'])
    <div class="card mt-6 max-w-2xl"><div class="card-h"><h2>Webhook-Zustellungen</h2><form method="post" action="{{ route('settings.webhook-test') }}">@csrf<button class="btn btn-sm">Test senden</button></form></div>
        <table class="tbl"><tbody>@forelse ($deliveries as $d)<tr><td>{{ \Carbon\Carbon::parse($d->created_at)->format('d.m. H:i') }}</td><td>{{ $d->event }}</td><td class="{{ $d->error ? 'text-red-600' : 'text-green-700' }}">{{ $d->error ?: 'HTTP '.$d->status }}</td></tr>@empty<tr><td class="text-slate-500">Noch keine Zustellungen.</td></tr>@endforelse</tbody></table></div>
    @endif
</x-layout>
