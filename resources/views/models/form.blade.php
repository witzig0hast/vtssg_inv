<x-layout :title="$m->exists ? $m->name.' bearbeiten' : 'Neues Modell'">
    <x-page-head :title="$m->exists ? $m->full_name.' bearbeiten' : 'Neues Modell'" />
    <form method="post" action="{{ $m->exists ? route('models.update', $m) : route('models.store') }}" enctype="multipart/form-data" class="card card-b max-w-4xl">
        @csrf @if ($m->exists) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-2">
            <x-input name="name" label="Bezeichnung" :value="$m->name" required />
            <x-input name="model_number" label="Modellnummer / Artikelnummer" :value="$m->model_number" />
            <x-select name="manufacturer_id" label="Hersteller" :value="$m->manufacturer_id" :options="$manufacturers->pluck('name', 'id')" empty="— keiner —" />
            <x-select name="category_id" label="Kategorie" :value="$m->category_id" :options="$categories->pluck('name', 'id')" required empty="— wählen —" />
            <x-select name="tracking" label="Verwaltungsart" :value="$m->tracking" :options="\App\Models\AssetModel::TRACKING" required hint="Mengenartikel (z. B. XLR-Kabel 10 m): ein Datensatz mit Stückzahl." />
            <x-input name="unit" label="Einheit" :value="$m->unit" required />
            <x-input name="rental_price" label="Mietpreis pro Einsatztag (€)" :value="\App\Support\Money::input($m->rental_price_cents)" />
            <x-input name="weight_kg" type="number" step="0.01" label="Gewicht (kg)" :value="$m->weight_kg" />
            <x-input name="power_w" type="number" label="Leistungsaufnahme (W)" :value="$m->power_w" />
            <x-input name="depreciation_months" type="number" label="Abschreibung (Monate)" :value="$m->depreciation_months" />
            <x-input name="service_hours_limit" type="number" label="Lebensdauer (Betriebsstunden)" :value="$m->service_hours_limit" hint="z. B. Lampenlebensdauer – Warnung bei 90 %." />
            <div><label class="label" for="f-image">Bild</label><input id="f-image" type="file" name="image" accept="image/*" class="block w-full text-sm">@error('image')<p class="err">{{ $message }}</p>@enderror</div>
            <x-textarea name="notes" label="Notizen" :value="$m->notes" class="md:col-span-2" />
        </div>

        <h3 class="mt-6 mb-2">Prüfpflichten</h3>
        <p class="hint mb-3">Gilt für alle Assets dieses Modells. Intervall leer = Standard der Prüfart.</p>
        <div class="grid gap-2 md:grid-cols-2">
            @foreach ($inspectionTypes as $t)
                @php $sel = $m->exists ? $m->inspectionTypes->firstWhere('id', $t->id) : null; @endphp
                <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm">
                    <input type="checkbox" name="inspection_enabled[{{ $t->id }}]" value="1" @checked(old('inspection_enabled.'.$t->id, (bool) $sel)) class="h-4 w-4 rounded border-slate-300">
                    <span class="flex-1">{{ $t->name }}<span class="block text-xs text-slate-500">Standard {{ $t->default_interval_months }} Monate{{ $t->blocks_checkout ? ' · sperrt Ausgabe' : '' }}</span></span>
                    <input type="number" min="1" max="120" name="inspection_types[{{ $t->id }}]" value="{{ old('inspection_types.'.$t->id, $sel?->pivot->interval_months) }}" placeholder="Mon." class="input w-20" aria-label="Intervall">
                </label>
            @endforeach
        </div>
        <div class="mt-5 flex gap-2"><button class="btn btn-primary">Speichern</button><a href="{{ $m->exists ? route('models.show', $m) : route('models.index') }}" class="btn">Abbrechen</a></div>
    </form>
</x-layout>
