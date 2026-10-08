<x-layout title="Benutzer">
    <x-page-head title="Benutzer"><form method="get" class="flex gap-2"><input name="q" value="{{ request('q') }}" placeholder="Suchen…" class="input w-48"><button class="btn">Suchen</button></form><a href="{{ route('users.create') }}" class="btn btn-primary">+ Benutzer</a></x-page-head>
    <div class="card overflow-x-auto"><table class="tbl"><thead><tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>2FA</th><th>Letzter Login</th><th>Status</th><th></th></tr></thead><tbody>
        @foreach ($users as $u)<tr><td class="font-medium">{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->roles->pluck('name')->implode(', ') }}</td><td>{{ $u->hasTwoFactor() ? '✓' : '—' }}</td><td class="text-zinc-500">{{ $u->last_login_at?->format('d.m.Y H:i') ?: '—' }}</td>
            <td><x-badge :color="$u->is_active ? '#16a34a' : '#dc2626'">{{ $u->is_active ? 'Aktiv' : 'Deaktiviert' }}</x-badge></td>
            <td class="whitespace-nowrap text-right"><a href="{{ route('users.edit', $u) }}" class="btn btn-sm">Bearbeiten</a>@if ($u->id !== auth()->id())<x-delete :action="route('users.destroy', $u)" confirm="Benutzer löschen?" />@endif</td></tr>@endforeach</tbody></table></div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layout>
