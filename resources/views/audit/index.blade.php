<x-layout title="Audit-Log" wide>
    <x-page-head title="Audit-Log" sub="Lückenloses, manipulationsgesichertes Änderungsprotokoll (Hash-Kette)"><a href="{{ request()->fullUrlWithQuery(['verify' => 1]) }}" class="btn">Integrität prüfen</a></x-page-head>
    @if ($verify)<div class="alert mb-4 {{ $verify['ok'] ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' }}">{{ $verify['ok'] ? "✓ Kette intakt ({$verify['checked']} Einträge geprüft)." : "✕ MANIPULATION ab Eintrag #{$verify['broken_at']} erkannt!" }}</div>@endif
    <form method="get" class="card card-b mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <input name="q" value="{{ request('q') }}" placeholder="Zusammenfassung…" class="input">
        <select name="event" class="input"><option value="">Alle Ereignisse</option>@foreach ($events as $e)<option @selected(request('event') === $e)>{{ $e }}</option>@endforeach</select>
        <select name="user" class="input"><option value="">Alle Benutzer</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach</select>
        <input type="date" name="from" value="{{ request('from') }}" class="input"><input type="date" name="to" value="{{ request('to') }}" class="input"><button class="btn btn-primary">Filtern</button></form>
    <div class="card overflow-x-auto"><table class="tbl"><thead><tr><th>#</th><th>Zeit</th><th>Benutzer</th><th>Ereignis</th><th>Objekt</th><th>Änderung</th><th>IP</th></tr></thead><tbody>
        @foreach ($logs as $l)<tr class="align-top"><td class="text-slate-400">{{ $l->id }}</td><td class="whitespace-nowrap">{{ $l->created_at->format('d.m.Y H:i:s') }}</td><td>{{ $l->user?->name ?? '—' }}</td><td><x-badge :color="str_contains($l->event, 'fail') || str_contains($l->event, 'delete') ? '#dc2626' : '#4f46e5'">{{ $l->event }}</x-badge></td>
            <td>{{ $l->summary }}<div class="text-xs text-slate-400">{{ $l->auditable_type ? class_basename($l->auditable_type).' #'.$l->auditable_id : '' }}</div></td>
            <td class="max-w-md text-xs">@foreach (($l->new_values ?? []) as $k => $v)<div class="truncate"><span class="text-slate-500">{{ $k }}:</span> @if (isset($l->old_values[$k]))<s class="text-red-500">{{ \Illuminate\Support\Str::limit(is_array($l->old_values[$k]) ? json_encode($l->old_values[$k]) : (string) $l->old_values[$k], 40) }}</s> → @endif{{ \Illuminate\Support\Str::limit(is_array($v) ? json_encode($v) : (string) $v, 60) }}</div>@endforeach</td><td class="text-xs text-slate-500">{{ $l->ip }}</td></tr>@endforeach</tbody></table></div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layout>
