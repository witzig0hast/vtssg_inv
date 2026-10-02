<x-layout title="Prüfung erfassen">
    <x-page-head title="Prüfung erfassen" />
    <form method="post" action="{{ route('inspections.store') }}" enctype="multipart/form-data" class="card card-b max-w-3xl">@csrf
        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2"><label class="label" for="insp-tag">Asset-Tag <span class="text-red-500">*</span></label>
                <div class="flex gap-2"><input id="insp-tag" name="asset_tag" value="{{ old('asset_tag', $asset?->asset_tag) }}" class="input" required><button type="button" class="btn" data-camera-for="insp-tag">📷</button></div>@error('asset_tag')<p class="err">{{ $message }}</p>@enderror</div>
            <x-select name="inspection_type_id" label="Prüfart" required :options="$types->pluck('name', 'id')" :value="$asset?->model->inspectionTypes->first()?->id" empty="— wählen —" />
            <x-input name="performed_at" type="date" label="Geprüft am" :value="today()->toDateString()" required />
            <x-select name="result" label="Ergebnis" :options="$results" value="passed" required hint="„Nicht bestanden“ sperrt das Gerät und erzeugt ein Ticket." />
            <x-input name="next_due" type="date" label="Nächste Prüfung" hint="Leer = automatisch aus Intervall." />
            <x-input name="inspector_name" label="Prüfer (extern / Sachkundiger)" />
            <x-input name="protocol_number" label="Protokoll-Nr." />
            <x-textarea name="notes" label="Bemerkungen / Messwerte" class="md:col-span-2" />
            <div class="md:col-span-2"><label class="label" for="f-file">Prüfprotokoll (PDF/Foto)</label><input id="f-file" type="file" name="file" class="block w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">@error('file')<p class="err">{{ $message }}</p>@enderror</div>
        </div>
        <div class="mt-5 flex gap-2"><button class="btn btn-primary">Speichern</button><a href="{{ route('inspections.index') }}" class="btn">Abbrechen</a></div>
    </form>
</x-layout>
