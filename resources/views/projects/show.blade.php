@php $colors = ['draft' => '#71717a', 'planned' => '#3f3f46', 'confirmed' => '#52525b', 'loaded' => '#27272a', 'running' => '#16a34a', 'returned' => '#d97706', 'closed' => '#3f3f46', 'cancelled' => '#dc2626']; @endphp
<x-layout :title="$project->number" wide>
    <x-page-head :title="$project->number.' · '.$project->name" :sub="$project->customer?->name">
        <x-badge :color="$colors[$project->status]">{{ \App\Models\Project::STATUSES[$project->status] }}</x-badge>
        @can('projects.pack')<a href="{{ route('projects.pack', $project) }}" class="btn btn-primary">Packen / Rückbau</a>@endcan
        <a href="{{ route('projects.document', [$project, 'packing-list']) }}" class="btn" target="_blank">Packliste PDF</a>
        <a href="{{ route('projects.document', [$project, 'delivery-note']) }}" class="btn" target="_blank">Lieferschein PDF</a>
        @can('reports.view')<a href="{{ route('accounting.invoice', $project) }}" class="btn">Rechnungsposten CSV</a>@endcan
        @can('projects.pack')<a href="{{ route('projects.signature', $project) }}" class="btn">Unterschrift</a>@endcan
        @can('projects.update')<a href="{{ route('projects.rider', $project) }}" class="btn">Rider importieren</a>@endcan
        @can('projects.update')<a href="{{ route('projects.edit', $project) }}" class="btn">Bearbeiten</a>@endcan
        @can('projects.create')<form method="post" action="{{ route('projects.duplicate', $project) }}" class="inline">@csrf<button class="btn">Duplizieren</button></form>@endcan
        @can('projects.delete')<x-delete :action="route('projects.destroy', $project)" confirm="Projekt löschen?" />@endcan
    </x-page-head>

    @if ($conflicts)<div class="alert mb-4 border-red-200 bg-red-50 text-red-800"><b>Konflikte / Warnungen:</b><ul class="mt-1 list-disc pl-5">@foreach ($conflicts as $c)<li>{{ $c['message'] }}</li>@endforeach</ul></div>@endif

    <div class="grid gap-6 xl:grid-cols-4">
        <div class="space-y-6 xl:col-span-3">
            <div class="card"><div class="card-h"><h2>Packliste</h2>
                <div class="flex items-center gap-3 text-sm"><div class="h-2 w-40 overflow-hidden rounded-full bg-zinc-200"><div class="h-full bg-brand-600" style="width: {{ $progress }}%"></div></div><span class="text-zinc-500">{{ $progress }} % verladen</span></div></div>
                <div class="overflow-x-auto"><table class="tbl">
                    <thead><tr><th>Position</th><th class="text-right">Menge</th><th class="text-right">Verladen</th><th class="text-right">Zurück</th><th>Verfügbarkeit</th><th class="text-right">Preis/Tag</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($groups as $group => $items)
                        <tr><td colspan="7" class="!bg-zinc-100 !py-1.5 text-xs font-semibold uppercase tracking-wide text-zinc-600">{{ $group }}</td></tr>
                        @foreach ($items as $i)
                            @php $av = $avail[$i->asset_model_id] ?? null; @endphp
                            <tr>
                                <td>{{ $i->label() }}@if ($i->is_external) <x-badge color="#d97706">Zumiete{{ $i->supplier ? ' · '.$i->supplier->name : '' }}</x-badge>@endif
                                    @if ($i->note)<div class="text-xs text-zinc-500">{{ $i->note }}</div>@endif</td>
                                <td class="text-right">{{ $i->quantity }}</td>
                                <td class="text-right"><x-badge :color="$i->isFullyPacked() ? '#16a34a' : '#71717a'">{{ $i->packed_quantity }}</x-badge></td>
                                <td class="text-right">{{ $i->returned_quantity }}</td>
                                <td class="text-xs">@if ($av && ! $i->is_external)<span class="{{ $av['free'] < 0 ? 'text-red-600 font-semibold' : 'text-zinc-600' }}">frei {{ max(0, $av['free']) }} / Bestand {{ $av['total'] }}</span>@endif</td>
                                <td class="whitespace-nowrap text-right">{{ \App\Support\Money::format($i->unitPriceCents()) }}</td>
                                <td class="whitespace-nowrap text-right">@can('projects.update')<x-delete :action="route('projects.items.remove', [$project, $i])" confirm="Position entfernen?" label="×" />@endcan</td>
                            </tr>
                        @endforeach
                    @empty<tr><td colspan="7" class="py-8 text-center text-zinc-500">Noch keine Positionen.</td></tr>@endforelse
                    </tbody>
                    @if ($groups->isNotEmpty())<tfoot><tr><td colspan="5" class="px-4 py-2 text-right text-zinc-500">Mietwert ({{ $project->rentalDays() }} Tag(e))</td><td class="px-4 py-2 text-right font-semibold">{{ \App\Support\Money::format($rentalSum) }}</td><td></td></tr></tfoot>@endif
                </table></div>
            </div>

            @can('projects.update')
            <div class="card"><div class="card-h"><h2>Position hinzufügen</h2></div>
                <form method="post" action="{{ route('projects.items.add', $project) }}" class="card-b grid gap-3 md:grid-cols-6">@csrf
                    <select name="kind" data-switch="kind" class="input md:col-span-2"><option value="model">Modell (Menge reservieren)</option><option value="asset">Konkretes Asset (Tag)</option><option value="external">Zumiete (extern)</option></select>
                    <div data-show-when="kind=model" class="md:col-span-3"><select name="asset_model_id" class="input" aria-label="Modell"><option value="">— Modell wählen —</option>@foreach ($models as $m)<option value="{{ $m->id }}">{{ $m->full_name }}</option>@endforeach</select></div>
                    <div data-show-when="kind=asset" class="hidden md:col-span-3"><div class="flex gap-2"><input id="add-tag" name="asset_tag" class="input" placeholder="Asset-Tag"><button type="button" class="btn" data-camera-for="add-tag">📷</button></div></div>
                    <div data-show-when="kind=external" class="hidden md:col-span-3 grid grid-cols-2 gap-2"><input name="external_name" class="input" placeholder="Bezeichnung"><select name="supplier_id" class="input"><option value="">Lieferant…</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                    <input type="number" name="quantity" value="1" min="1" class="input" aria-label="Menge">
                    <input name="group" list="groups" class="input md:col-span-2" placeholder="Gruppe (z. B. Licht, Ton)"><datalist id="groups">@foreach ($groups->keys() as $g)<option value="{{ $g }}">@endforeach</datalist>
                    <input name="price" class="input" placeholder="Preis/Tag €">
                    <input name="external_cost" class="input" placeholder="Einkauf € (Zumiete)">
                    <input name="note" class="input md:col-span-2" placeholder="Notiz">
                    <button class="btn btn-primary">Hinzufügen</button>
                </form>
                @if ($templates->isNotEmpty())
                <form method="post" action="{{ route('projects.template.apply', $project) }}" class="card-b flex flex-wrap items-center gap-2 border-t border-zinc-200">@csrf
                    <span class="text-sm text-zinc-600">Vorlage übernehmen:</span>
                    <select name="kit_template_id" class="input w-auto">@foreach ($templates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
                    <input type="number" name="multiplier" value="1" min="1" class="input w-20" aria-label="Faktor"><button class="btn">Übernehmen</button>
                </form>@endif
                <form method="post" action="{{ route('projects.template.save', $project) }}" class="card-b flex items-center gap-2 border-t border-zinc-200">@csrf
                    <input name="name" class="input max-w-xs" placeholder="Als Vorlage speichern unter…" required><button class="btn">Speichern</button></form>
            </div>
            @endcan
        </div>

        <div class="space-y-6">
            @can('projects.update')
            @if ($transitions)<div class="card"><div class="card-h"><h2>Status</h2></div><div class="card-b flex flex-wrap gap-2">
                @foreach ($transitions as $t)<form method="post" action="{{ route('projects.status', $project) }}">@csrf<input type="hidden" name="status" value="{{ $t }}"><button class="btn btn-sm {{ in_array($t, ['confirmed', 'loaded']) ? 'btn-primary' : '' }}">→ {{ \App\Models\Project::STATUSES[$t] }}</button></form>@endforeach</div></div>@endif
            @endcan
            <div class="card"><div class="card-h"><h2>Transport</h2></div><div class="card-b text-sm">
                <div>Gesamtgewicht: <b>{{ number_format($weightKg, 0, ',', '.') }} kg</b> · Leistung: <b>{{ number_format($powerW / 1000, 1, ',', '.') }} kW</b></div>
                @foreach ($vehicles as $v)<div class="mt-1 {{ $weightKg > $v->max_load_kg ? 'text-red-600' : 'text-green-700' }}">{{ $weightKg > $v->max_load_kg ? '✕' : '✓' }} {{ $v->name }} ({{ number_format($v->max_load_kg, 0, ',', '.') }} kg){{ $weightKg > $v->max_load_kg ? ' – zu schwer' : '' }}</div>@endforeach
                @if ($project->signed_at)<div class="mt-2 text-xs text-zinc-500">Unterschrieben von {{ $project->signed_by }} am {{ $project->signed_at->format('d.m.Y H:i') }}</div>@endif
            </div></div>
            <div class="card"><dl class="dl card-b">
                <dt>Leitung</dt><dd>{{ $project->manager?->name ?: '—' }}</dd>
                <dt>Ort</dt><dd>{{ $project->venue ?: ($project->venueLocation?->name ?: '—') }}</dd>
                <dt>Aufbau</dt><dd>{{ $project->setup_at?->format('d.m.Y H:i') ?: '—' }}</dd>
                <dt>Beginn</dt><dd>{{ $project->starts_at?->format('d.m.Y H:i') ?: '—' }}</dd>
                <dt>Ende</dt><dd>{{ $project->ends_at?->format('d.m.Y H:i') ?: '—' }}</dd>
                <dt>Rückgabe</dt><dd>{{ $project->teardown_at?->format('d.m.Y H:i') ?: '—' }}</dd>
                <dt>Notizen</dt><dd class="whitespace-pre-line">{{ $project->notes ?: '—' }}</dd></dl></div>
            @include('partials.attachments', ['target' => $project, 'type' => 'project', 'canUpload' => auth()->user()->can('projects.update')])
        </div>
    </div>
</x-layout>
