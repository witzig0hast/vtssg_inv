<x-layout title="Rider importieren">
    <x-page-head title="Technical Rider importieren" :sub="$project->number.' · '.$project->name"><a href="{{ route('projects.show', $project) }}" class="btn">← Projekt</a></x-page-head>
    @if ($rows === null)
        <form method="post" action="{{ route('projects.rider.parse', $project) }}" enctype="multipart/form-data" class="card card-b max-w-3xl space-y-4">@csrf
            <p class="text-sm text-slate-600">Rider als Text einfügen oder PDF/TXT/CSV hochladen. Zeilen wie <code>8x Robe MegaPointe</code> oder <code>2 Shure SM58</code> werden erkannt und mit deinem Bestand abgeglichen. Du prüfst alles, bevor etwas übernommen wird.</p>
            <x-textarea name="text" label="Rider-Text" rows="10" />
            <div><label class="label" for="f-file">oder Datei</label><input id="f-file" type="file" name="file" accept=".pdf,.txt,.csv" class="block w-full text-sm">@error('file')<p class="err">{{ $message }}</p>@enderror</div>
            <button class="btn btn-primary">Analysieren</button>
        </form>
    @else
        <form method="post" action="{{ route('projects.rider.apply', $project) }}" class="space-y-4">@csrf
            <div class="card overflow-x-auto"><table class="tbl"><thead><tr><th></th><th>Zeile im Rider</th><th class="w-24">Menge</th><th>Zuordnung</th></tr></thead><tbody>
                @forelse ($rows as $i => $r)
                    <tr><td><input type="checkbox" name="items[{{ $i }}][use]" value="1" @checked(! empty($r['candidates']) && $r['candidates'][0]['score'] >= 0.6)></td>
                        <td>{{ $r['raw'] }}<input type="hidden" name="items[{{ $i }}][raw]" value="{{ $r['name'] }}"></td>
                        <td><input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $r['qty'] }}" class="input"></td>
                        <td><select name="items[{{ $i }}][asset_model_id]" class="input"><option value="">— nicht im Bestand → Zumiete —</option>
                            @foreach ($r['candidates'] as $c)<option value="{{ $c['id'] }}" @selected($loop->first && $c['score'] >= 0.6)>{{ $c['label'] }} ({{ (int) ($c['score'] * 100) }} %{{ isset($c['free']) ? ', frei: '.$c['free'] : '' }})</option>@endforeach</select></td></tr>
                @empty<tr><td colspan="4" class="py-6 text-center text-slate-500">Keine Positionen erkannt.</td></tr>@endforelse
            </tbody></table></div>
            <div class="flex items-end gap-3"><x-input name="group" label="Gruppe" value="Rider" class="w-48" /><button class="btn btn-primary">Ausgewählte übernehmen</button><a href="{{ route('projects.rider', $project) }}" class="btn">Neu</a></div>
        </form>
    @endif
</x-layout>
