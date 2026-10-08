<x-layout :title="'Packen '.$project->number">
    @php $items = $project->items->where('is_external', false); @endphp
    <x-page-head :title="'Packen: '.$project->number.' · '.$project->name" sub="Beladung per Scan oder Rückbau/Retour buchen">
        <a href="{{ route('projects.show', $project) }}" class="btn">← Projekt</a>
    </x-page-head>
    <div id="pack-root" data-url="{{ route('projects.pack.scan', $project) }}" class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-4">
            <div class="card card-b space-y-3">
                <div class="flex gap-2">
                    <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border border-zinc-300 p-3 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50"><input type="radio" name="mode" value="load" checked> <b>Beladen</b></label>
                    <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border border-zinc-300 p-3 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50"><input type="radio" name="mode" value="unload"> <b>Rückbau</b></label>
                </div>
                <div class="flex gap-2"><input id="pack-code" class="input text-lg" placeholder="Code scannen…" autocomplete="off" autocapitalize="off" spellcheck="false"><button type="button" class="btn" data-camera-for="pack-code">📷</button><button type="button" id="pack-submit" class="btn btn-primary">OK</button></div>
                <select id="pack-condition" class="input"><option value="ok">Rückbau: Zustand in Ordnung</option><option value="worn">Rückbau: Gebrauchsspuren</option><option value="damaged">Rückbau: defekt → Ticket</option></select>
                <div><div class="mb-1 flex justify-between text-sm"><span>Fortschritt</span><span id="pack-progress">{{ $items->sum('packed_quantity') }} / {{ $items->sum('quantity') }}</span></div>
                    <div class="h-3 overflow-hidden rounded-full bg-zinc-200"><div id="pack-bar" class="h-full bg-brand-600 transition-all" style="width: {{ $items->sum('quantity') ? round($items->sum('packed_quantity') / $items->sum('quantity') * 100) : 0 }}%"></div></div></div>
            </div>
            <form method="post" action="{{ route('projects.photo', $project) }}" enctype="multipart/form-data" class="card card-b flex flex-wrap items-center gap-2">@csrf
                <span class="text-sm font-medium">Beladefoto (Case-Check)</span>
                <input type="file" name="photo" accept="image/*" capture="environment" required class="text-sm">
                <input name="label" placeholder="Case / Hinweis" class="input w-40"><button class="btn btn-sm">Speichern</button>
            </form>
            <ul id="pack-log" class="space-y-2"></ul>
        </div>
        <div class="card"><div class="card-h"><h2>Soll-Liste</h2></div>
            <table class="tbl"><thead><tr><th>Position</th><th class="text-right">Soll</th><th class="text-right">Verladen</th><th class="text-right">Zurück</th></tr></thead><tbody>
                @foreach ($items as $i)<tr data-item="{{ $i->id }}"><td>{{ $i->label() }}</td><td class="text-right">{{ $i->quantity }}</td><td class="text-right" data-packed>{{ $i->packed_quantity }}</td><td class="text-right" data-returned>{{ $i->returned_quantity }}</td></tr>@endforeach
            </tbody></table></div>
    </div>
</x-layout>
