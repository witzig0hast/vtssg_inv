<x-layout title="Projekte" wide>
    <x-page-head title="Projekte">
        <a href="{{ route('projects.calendar') }}" class="btn">Kalender</a>
        @can('projects.create')<a href="{{ route('projects.create') }}" class="btn btn-primary">+ Projekt</a>@endcan
    </x-page-head>
    <form method="get" class="mb-4 flex flex-wrap gap-2"><input name="q" value="{{ request('q') }}" placeholder="Nummer, Name, Kunde, Ort…" class="input max-w-xs">
        <select name="status" class="input w-auto"><option value="">Alle aktiven</option>@foreach (\App\Models\Project::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select><button class="btn">Filtern</button></form>
    <div class="card overflow-x-auto"><table class="tbl"><thead><tr><th>Nr.</th><th>Projekt</th><th>Kunde</th><th>Aufbau</th><th>Einsatz</th><th>Leitung</th><th>Pos.</th><th>Status</th></tr></thead><tbody>
        @forelse ($projects as $p)<tr>
            <td class="whitespace-nowrap font-medium"><a href="{{ route('projects.show', $p) }}">{{ $p->number }}</a></td>
            <td>{{ $p->name }}<div class="text-xs text-slate-500">{{ $p->venue }}</div></td><td>{{ $p->customer?->name }}</td>
            <td class="whitespace-nowrap">{{ $p->setup_at?->format('d.m.Y H:i') }}</td><td class="whitespace-nowrap">{{ $p->starts_at?->format('d.m.Y') }}@if ($p->ends_at && ! $p->ends_at->isSameDay($p->starts_at)) – {{ $p->ends_at->format('d.m.Y') }}@endif</td>
            <td>{{ $p->manager?->name }}</td><td>{{ $p->items_count }}</td><td><x-badge :color="['draft' => '#64748b', 'planned' => '#2563eb', 'confirmed' => '#0891b2', 'loaded' => '#7c3aed', 'running' => '#16a34a', 'returned' => '#d97706', 'closed' => '#475569', 'cancelled' => '#dc2626'][$p->status]">{{ \App\Models\Project::STATUSES[$p->status] }}</x-badge></td></tr>
        @empty<tr><td colspan="8" class="py-8 text-center text-slate-500">Keine Projekte.</td></tr>@endforelse
    </tbody></table></div>
    <div class="mt-4">{{ $projects->links() }}</div>
</x-layout>
