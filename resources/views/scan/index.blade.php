<x-layout title="Scannen">
    <x-page-head title="Scannen" sub="Handscanner (Tastatur-Modus) oder Kamera. Asset-Tag, UUID oder Seriennummer." />
    <div class="grid gap-6 lg:grid-cols-2">
        <div>
            <form id="scan-form" data-lookup="{{ route('scan.lookup') }}" data-action="{{ route('scan.action') }}" class="card card-b flex gap-2">
                <input id="scan-code" class="input text-lg" placeholder="Code scannen oder eingeben…" autocomplete="off" autocapitalize="off" spellcheck="false">
                <button type="button" class="btn" data-camera-for="scan-code" data-submit>📷</button>
                <button class="btn btn-primary">OK</button>
            </form>
            <div id="scan-card" class="card card-b mt-4 hidden"></div>
        </div>
        <div>
            <h3 class="mb-2">Protokoll</h3>
            <ul id="scan-log" class="space-y-2"></ul>
            <div class="card card-b mt-6">
                <h3 class="mb-2">Packen für ein Projekt</h3>
                <ul class="divide-y divide-zinc-100 text-sm">
                    @forelse ($projects as $p)<li class="flex items-center justify-between py-2"><span>{{ $p->number }} · {{ $p->name }}</span>@can('projects.pack')<a class="btn btn-sm" href="{{ route('projects.pack', $p) }}">Packen / Rückbau</a>@endcan</li>@empty<li class="py-2 text-zinc-500">Keine aktiven Projekte.</li>@endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layout>
