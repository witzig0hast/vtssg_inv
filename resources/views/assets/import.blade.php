<x-layout title="Import">
    <x-page-head title="Assets importieren" sub="CSV (Semikolon oder Komma, UTF-8)">
        <a href="{{ route('assets.import.template') }}" class="btn">Vorlage herunterladen</a>
    </x-page-head>
    @if (session('import_errors'))<div class="alert mb-4 border-red-200 bg-red-50 text-red-800"><ul class="list-disc pl-5">@foreach (session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ route('assets.import.store') }}" enctype="multipart/form-data" class="card card-b max-w-2xl space-y-4">@csrf
        <p class="text-sm text-zinc-600">Erwartete Spalten: <code>{{ implode(', ', $headers) }}</code>. Modell, Status und Standort müssen bereits existieren. Der Import ist atomar – bei einem Fehler wird nichts übernommen.</p>
        <div><label class="label" for="f-file">CSV-Datei</label><input id="f-file" type="file" name="file" accept=".csv,.txt" required class="block w-full text-sm">@error('file')<p class="err">{{ $message }}</p>@enderror</div>
        <x-checkbox name="dry_run" label="Nur prüfen (Testlauf), nichts speichern" :checked="true" />
        <button class="btn btn-primary">Importieren</button>
    </form>
</x-layout>
