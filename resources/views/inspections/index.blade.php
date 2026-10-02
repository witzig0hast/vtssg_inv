<x-layout title="Prüfungen" wide>
    <x-page-head title="Prüfungen" sub="DGUV V3, Rigging, Hebezeuge & Co. – Fälligkeiten im Blick">@can('inspections.record')<a href="{{ route('inspections.create') }}" class="btn btn-primary">+ Prüfung erfassen</a>@endcan</x-page-head>
    <div class="mb-4 flex flex-wrap gap-2">@foreach (['attention' => 'Handlungsbedarf', 'overdue' => 'Überfällig', 'soon' => 'Bald fällig (≤ '.$warn.' T)', 'all' => 'Alle'] as $k => $v)<a href="{{ route('inspections.index', ['scope' => $k]) }}" class="btn btn-sm {{ request('scope', 'attention') === $k ? 'btn-primary' : '' }}">{{ $v }}</a>@endforeach</div>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card overflow-x-auto xl:col-span-2"><table class="tbl"><thead><tr><th>Fällig</th><th>Asset</th><th>Prüfarten</th><th>Standort</th><th></th></tr></thead><tbody>
            @forelse ($assets as $a)<tr><td class="whitespace-nowrap"><x-badge :color="$a->next_inspection_due->isPast() ? '#dc2626' : ($a->next_inspection_due->diffInDays(today()) <= $warn ? '#d97706' : '#16a34a')">{{ $a->next_inspection_due->format('d.m.Y') }}</x-badge></td>
                <td><a href="{{ route('assets.show', $a) }}" class="font-medium">{{ $a->asset_tag }}</a> {{ $a->model->full_name }}</td><td class="text-xs text-slate-500">{{ $a->model->inspectionTypes->pluck('code')->implode(', ') }}</td><td>{{ $a->location?->name }}</td>
                <td class="text-right">@can('inspections.record')<a class="btn btn-sm" href="{{ route('inspections.create', ['asset' => $a->id]) }}">Prüfen</a>@endcan</td></tr>
            @empty<tr><td colspan="5" class="py-8 text-center text-slate-500">Nichts fällig. ✓</td></tr>@endforelse</tbody></table></div>
        <div class="card"><div class="card-h"><h2>Zuletzt geprüft</h2></div><ul class="divide-y divide-slate-100 text-sm">@foreach ($recent as $i)<li class="px-5 py-2.5"><a href="{{ route('assets.show', $i->asset) }}">{{ $i->asset->asset_tag }}</a> · {{ $i->type->code }}<div class="text-xs text-slate-500">{{ $i->performed_at->format('d.m.Y') }} · {{ \App\Models\Inspection::RESULTS[$i->result] }} · {{ $i->performer?->name ?? $i->inspector_name }}</div></li>@endforeach</ul></div>
    </div>
    <div class="mt-4">{{ $assets->links() }}</div>
</x-layout>
