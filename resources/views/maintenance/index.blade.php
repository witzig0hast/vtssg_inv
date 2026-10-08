<x-layout title="Wartung">
    <x-page-head title="Wartung & Defekte">@can('maintenance.report')<a href="{{ route('maintenance.create') }}" class="btn btn-primary">+ Defekt melden</a>@endcan</x-page-head>
    <div class="mb-4 flex flex-wrap gap-2">@foreach (['active' => 'Aktiv', 'open' => 'Offen', 'in_progress' => 'In Arbeit', 'waiting_parts' => 'Wartet auf Teile', 'done' => 'Erledigt', 'all' => 'Alle'] as $k => $v)<a href="{{ route('maintenance.index', ['status' => $k]) }}" class="btn btn-sm {{ request('status', 'active') === $k ? 'btn-primary' : '' }}">{{ $v }}</a>@endforeach</div>
    <div class="card overflow-x-auto"><table class="tbl"><thead><tr><th>Ticket</th><th>Asset</th><th>Priorität</th><th>Status</th><th>Zuständig</th><th>Erstellt</th></tr></thead><tbody>
        @forelse ($tickets as $t)<tr><td><a href="{{ route('maintenance.show', $t) }}" class="font-medium">{{ $t->title }}</a></td><td><a href="{{ route('assets.show', $t->asset) }}">{{ $t->asset->asset_tag }}</a> <span class="text-zinc-500">{{ $t->asset->model->full_name }}</span></td>
            <td><x-badge :color="['critical' => '#dc2626', 'high' => '#ea580c', 'normal' => '#3f3f46', 'low' => '#71717a'][$t->priority]">{{ \App\Models\MaintenanceTicket::PRIORITIES[$t->priority] }}</x-badge></td><td>{{ \App\Models\MaintenanceTicket::STATUSES[$t->status] }}</td><td>{{ $t->assignee?->name }}</td><td>{{ $t->created_at->format('d.m.Y') }}</td></tr>
        @empty<tr><td colspan="6" class="py-8 text-center text-zinc-500">Keine Tickets.</td></tr>@endforelse</tbody></table></div>
    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layout>
