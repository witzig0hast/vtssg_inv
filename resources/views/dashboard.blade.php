<x-layout title="Übersicht">
    <x-page-head title="Übersicht" :sub="now()->translatedFormat('l, d. F Y')">
        @can('projects.create')<a href="{{ route('projects.create') }}" class="btn btn-primary">+ Projekt</a>@endcan
        @can('assets.create')<a href="{{ route('assets.create') }}" class="btn">+ Asset</a>@endcan
    </x-page-head>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        <x-stat label="Assets" :value="$stats['assets']" :href="route('assets.index')" />
        <x-stat label="Ausgegeben" :value="$stats['out']" :href="auth()->user()->can('assets.checkout') ? route('checkouts.open') : null" tone="brand" />
        <x-stat label="Prüfung überfällig" :value="$stats['overdue_inspections']" :href="route('inspections.index', ['scope' => 'overdue'])" :tone="$stats['overdue_inspections'] ? 'red' : 'green'" />
        <x-stat label="Prüfung bald" :value="$stats['soon_inspections']" :href="route('inspections.index', ['scope' => 'soon'])" :tone="$stats['soon_inspections'] ? 'amber' : 'green'" />
        <x-stat label="Offene Defekte" :value="$stats['open_tickets']" :href="route('maintenance.index')" :tone="$stats['open_tickets'] ? 'amber' : 'green'" />
        <x-stat label="Rückgabe überfällig" :value="$stats['overdue_returns']" :href="auth()->user()->can('assets.checkout') ? route('checkouts.open') : null" :tone="$stats['overdue_returns'] ? 'red' : 'green'" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-h"><h2>Anstehende Projekte</h2><a href="{{ route('projects.calendar') }}" class="text-sm">Kalender →</a></div>
            <table class="tbl"><tbody>
                @forelse ($upcoming as $p)
                    <tr><td><a href="{{ route('projects.show', $p) }}" class="font-medium">{{ $p->number }} · {{ $p->name }}</a><div class="text-xs text-zinc-500">{{ $p->customer?->name }}</div></td>
                        <td class="text-right text-xs text-zinc-500">{{ ($p->setup_at ?? $p->starts_at)?->format('d.m. H:i') }}<br><x-badge>{{ \App\Models\Project::STATUSES[$p->status] }}</x-badge></td></tr>
                @empty<tr><td class="text-zinc-500">Keine anstehenden Projekte.</td></tr>@endforelse
            </tbody></table>
        </div>

        <div class="card">
            <div class="card-h"><h2>In den nächsten 3 Tagen zu packen</h2></div>
            <table class="tbl"><tbody>
                @forelse ($toPack as $p)
                    <tr><td><a href="{{ route('projects.show', $p) }}" class="font-medium">{{ $p->number }} · {{ $p->name }}</a></td><td class="text-right text-xs text-zinc-500">Aufbau {{ $p->setup_at->format('d.m. H:i') }}</td>
                        <td class="text-right">@can('projects.pack')<a class="btn btn-sm" href="{{ route('projects.pack', $p) }}">Packen</a>@endcan</td></tr>
                @empty<tr><td class="text-zinc-500">Nichts zu packen.</td></tr>@endforelse
            </tbody></table>
        </div>

        <div class="card">
            <div class="card-h"><h2>Prüfungen fällig (≤ {{ $warn }} Tage)</h2><a href="{{ route('inspections.index') }}" class="text-sm">Alle →</a></div>
            <table class="tbl"><tbody>
                @forelse ($dueAssets as $a)
                    <tr><td><a href="{{ route('assets.show', $a) }}" class="font-medium">{{ $a->asset_tag }}</a> <span class="text-zinc-500">{{ $a->model->full_name }}</span></td>
                        <td class="text-right"><x-badge :color="$a->next_inspection_due->isPast() ? '#dc2626' : '#d97706'">{{ $a->next_inspection_due->format('d.m.Y') }}</x-badge></td></tr>
                @empty<tr><td class="text-zinc-500">Alles geprüft. ✓</td></tr>@endforelse
            </tbody></table>
        </div>

        <div class="card">
            <div class="card-h"><h2>Offene Defekte / Wartung</h2><a href="{{ route('maintenance.index') }}" class="text-sm">Alle →</a></div>
            <table class="tbl"><tbody>
                @forelse ($tickets as $t)
                    <tr><td><a href="{{ route('maintenance.show', $t) }}" class="font-medium">{{ $t->title }}</a><div class="text-xs text-zinc-500">{{ $t->asset->asset_tag }} · {{ $t->asset->model->full_name }}</div></td>
                        <td class="text-right"><x-badge :color="['critical' => '#dc2626', 'high' => '#ea580c', 'normal' => '#3f3f46', 'low' => '#71717a'][$t->priority]">{{ \App\Models\MaintenanceTicket::PRIORITIES[$t->priority] }}</x-badge></td></tr>
                @empty<tr><td class="text-zinc-500">Keine offenen Tickets.</td></tr>@endforelse
            </tbody></table>
        </div>

        @if ($overdueReturns->isNotEmpty())
        <div class="card">
            <div class="card-h"><h2>Überfällige Rückgaben</h2></div>
            <table class="tbl"><tbody>
                @foreach ($overdueReturns as $c)
                    <tr><td><a href="{{ route('assets.show', $c->asset) }}" class="font-medium">{{ $c->asset->asset_tag }}</a> <span class="text-zinc-500">{{ $c->asset->model->full_name }}</span><div class="text-xs text-zinc-500">{{ $c->assigneeLabel() }}</div></td>
                        <td class="text-right text-xs text-red-600">seit {{ $c->expected_return_at->format('d.m.Y') }}</td></tr>
                @endforeach
            </tbody></table>
        </div>
        @endif

        @if ($lowStock->isNotEmpty())
        <div class="card">
            <div class="card-h"><h2>Mindestbestand unterschritten</h2></div>
            <table class="tbl"><tbody>
                @foreach ($lowStock as $c)
                    <tr><td><a href="{{ route('consumables.show', $c) }}" class="font-medium">{{ $c->name }}</a></td><td class="text-right text-red-600">{{ $c->quantity }} {{ $c->unit }} <span class="text-xs text-zinc-500">(min. {{ $c->min_quantity }})</span></td></tr>
                @endforeach
            </tbody></table>
        </div>
        @endif
    </div>
</x-layout>
