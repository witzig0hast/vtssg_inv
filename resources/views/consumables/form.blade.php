<x-layout title="Artikel">
    <x-page-head :title="$c->exists ? $c->name.' bearbeiten' : 'Neuer Artikel'" />
    <form method="post" action="{{ $c->exists ? route('consumables.update', $c) : route('consumables.store') }}" class="card card-b max-w-3xl">
        @csrf @if ($c->exists) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-2">
            <x-input name="name" label="Bezeichnung" :value="$c->name" required class="md:col-span-2" />
            <x-input name="sku" label="Artikelnummer" :value="$c->sku" />
            <x-select name="category_id" label="Kategorie" :value="$c->category_id" :options="$categories->pluck('name', 'id')" empty="— keine —" />
            <x-select name="manufacturer_id" label="Hersteller" :value="$c->manufacturer_id" :options="$manufacturers->pluck('name', 'id')" empty="— keiner —" />
            <x-select name="supplier_id" label="Lieferant" :value="$c->supplier_id" :options="$suppliers->pluck('name', 'id')" empty="— keiner —" />
            <x-select name="location_id" label="Lagerort" :value="$c->location_id" :options="$locations->mapWithKeys(fn ($l) => [$l->id => $l->path])" empty="— keiner —" />
            <x-input name="unit" label="Einheit" :value="$c->unit" required />
            @unless ($c->exists)<x-input name="quantity" type="number" min="0" label="Anfangsbestand" value="0" />@endunless
            <x-input name="min_quantity" type="number" min="0" label="Mindestbestand" :value="$c->min_quantity" />
            <x-input name="unit_cost" label="Einkaufspreis je Einheit (€)" :value="\App\Support\Money::input($c->unit_cost_cents)" />
            <x-textarea name="notes" label="Notizen" :value="$c->notes" class="md:col-span-2" />
        </div>
        <div class="mt-5 flex gap-2"><button class="btn btn-primary">Speichern</button><a href="{{ route('consumables.index') }}" class="btn">Abbrechen</a></div>
    </form>
</x-layout>
