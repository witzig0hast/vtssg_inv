<x-layout title="Vorlage">
    <x-page-head :title="$kit->exists ? 'Vorlage bearbeiten' : 'Neue Vorlage'" />
    <form method="post" action="{{ $kit->exists ? route('kits.update', $kit) : route('kits.store') }}" class="card card-b max-w-4xl space-y-4">
        @csrf @if ($kit->exists) @method('PUT') @endif
        <x-input name="name" label="Name" :value="$kit->name" required />
        <x-textarea name="description" label="Beschreibung" :value="$kit->description" rows="2" />
        <table class="tbl"><thead><tr><th>Modell</th><th class="w-28">Menge</th><th>Gruppe</th><th></th></tr></thead>
            <tbody id="kit-rows">
            @foreach ($kit->items as $i => $it)
                <tr><td><select name="items[{{ $i }}][asset_model_id]" class="input">@foreach ($models as $m)<option value="{{ $m->id }}" @selected($m->id === $it->asset_model_id)>{{ $m->full_name }}</option>@endforeach</select></td>
                    <td><input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $it->quantity }}" class="input"></td><td><input name="items[{{ $i }}][group]" value="{{ $it->group }}" class="input"></td>
                    <td><button type="button" data-remove-row class="btn btn-sm">×</button></td></tr>
            @endforeach
            </tbody></table>
        <template id="kit-row-template"><tr><td><select name="items[__i__][asset_model_id]" class="input">@foreach ($models as $m)<option value="{{ $m->id }}">{{ $m->full_name }}</option>@endforeach</select></td>
            <td><input type="number" min="1" name="items[__i__][quantity]" value="1" class="input"></td><td><input name="items[__i__][group]" class="input"></td><td><button type="button" data-remove-row class="btn btn-sm">×</button></td></tr></template>
        <button type="button" id="add-kit-row" class="btn">+ Zeile</button>
        <div class="flex gap-2"><button class="btn btn-primary">Speichern</button><a href="{{ route('kits.index') }}" class="btn">Abbrechen</a></div>
    </form>
</x-layout>
