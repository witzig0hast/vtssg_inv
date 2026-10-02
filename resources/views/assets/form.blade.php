<x-layout :title="$asset->exists ? $asset->asset_tag.' bearbeiten' : 'Neues Asset'">
    <x-page-head :title="$asset->exists ? $asset->asset_tag.' bearbeiten' : 'Neues Asset'" />
    <form method="post" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}" class="card card-b max-w-4xl">
        @csrf @if ($asset->exists) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-2">
            <x-select name="asset_model_id" label="Modell" required :value="$asset->asset_model_id" :options="$models->mapWithKeys(fn ($m) => [$m->id => $m->full_name.' ('.($m->tracking === 'quantity' ? 'Menge' : 'Einzel').')'])" empty="— wählen —" class="md:col-span-2" />
            <x-input name="asset_tag" label="Asset-Tag" :value="$asset->asset_tag" hint="Leer lassen für automatische Vergabe ({{ \App\Models\Setting::get('asset_tag_prefix') }}…)." />
            <x-input name="serial" label="Seriennummer" :value="$asset->serial" />
            <x-input name="name" label="Bezeichnung / Spitzname" :value="$asset->name" hint="z. B. „Lichtpult FOH“" />
            <x-select name="status_label_id" label="Status" required :value="$asset->status_label_id" :options="$statuses->pluck('name', 'id')" />
            <x-select name="location_id" label="Stammlagerort" :value="$asset->location_id" :options="$locations->mapWithKeys(fn ($l) => [$l->id => $l->path])" empty="— keiner —" />
            <x-select name="parent_id" label="Gehört zu Case/Kit" :value="$asset->parent_id" :options="$containers->mapWithKeys(fn ($c) => [$c->id => $c->asset_tag.' '.$c->displayName()])" empty="— keinem —" />
            <x-input name="quantity" type="number" min="1" label="Menge (nur Mengenartikel)" :value="$asset->quantity" />
            @unless ($asset->exists)<x-input name="copies" type="number" min="1" max="100" label="Anzahl Kopien" value="1" hint="Legt mehrere gleiche Einzelgeräte auf einmal an (jeweils eigener Tag)." />@endunless
            <x-input name="purchase_date" type="date" label="Kaufdatum" :value="$asset->purchase_date?->toDateString()" />
            <x-input name="purchase_cost" label="Kaufpreis (€)" :value="\App\Support\Money::input($asset->purchase_cost_cents)" />
            <x-input name="invoice_number" label="Rechnungsnummer" :value="$asset->invoice_number" />
            <x-select name="supplier_id" label="Lieferant" :value="$asset->supplier_id" :options="$suppliers->pluck('name', 'id')" empty="— keiner —" />
            <x-input name="warranty_until" type="date" label="Garantie bis" :value="$asset->warranty_until?->toDateString()" />
            <x-input name="operating_hours" type="number" step="0.1" min="0" label="Betriebsstunden" :value="$asset->operating_hours" />
            <x-checkbox name="is_container" label="Ist ein Case / Kit (enthält andere Assets)" :checked="$asset->is_container" class="md:col-span-2" />
            <x-textarea name="notes" label="Notizen" :value="$asset->notes" class="md:col-span-2" />
        </div>
        <div class="mt-5 flex gap-2"><button class="btn btn-primary">Speichern</button><a href="{{ $asset->exists ? route('assets.show', $asset) : route('assets.index') }}" class="btn">Abbrechen</a></div>
    </form>
</x-layout>
