<x-layout title="Inventur">
    <x-page-head title="Inventur" sub="Soll/Ist-Abgleich per Scan" />
    @can('stocktakes.manage')<form method="post" action="{{ route('stocktakes.store') }}" class="card card-b mb-6 flex flex-wrap items-end gap-3">@csrf
        <x-input name="name" label="Bezeichnung" :value="'Inventur '.now()->format('m/Y')" class="flex-1 min-w-48" required />
        <x-select name="location_id" label="Standort (inkl. Unterstandorte)" :options="$locations->mapWithKeys(fn ($l) => [$l->id => $l->path])" required />
        <button class="btn btn-primary">Inventur starten</button></form>@endcan
    <div class="card"><table class="tbl"><thead><tr><th>Name</th><th>Standort</th><th>Gestartet</th><th>Positionen</th><th>Status</th></tr></thead><tbody>
        @forelse ($rows as $s)<tr><td><a href="{{ route('stocktakes.show', $s) }}" class="font-medium">{{ $s->name }}</a></td><td>{{ $s->location?->name }}</td><td>{{ $s->created_at->format('d.m.Y') }} · {{ $s->starter?->name }}</td><td>{{ $s->lines_count }}</td><td><x-badge :color="$s->status === 'open' ? '#2563eb' : '#64748b'">{{ $s->status === 'open' ? 'Offen' : 'Abgeschlossen' }}</x-badge></td></tr>
        @empty<tr><td colspan="5" class="py-8 text-center text-slate-500">Noch keine Inventuren.</td></tr>@endforelse</tbody></table></div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layout>
