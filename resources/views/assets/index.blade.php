<x-layout title="Assets" wide>
    <x-page-head title="Assets" :sub="$assets->total().' Treffer'">
        @can('assets.export')<a href="{{ route('assets.export', request()->query()) }}" class="btn">CSV-Export</a>@endcan
        @can('assets.import')<a href="{{ route('assets.import') }}" class="btn">Import</a>@endcan
        @can('assets.create')<a href="{{ route('assets.create') }}" class="btn btn-primary">+ Asset</a>@endcan
    </x-page-head>

    <form method="get" class="card card-b mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <input name="q" value="{{ request('q') }}" placeholder="Tag, Seriennr., Modell…" class="input lg:col-span-2">
        <select name="category" class="input"><option value="">Alle Kategorien</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select>
        <select name="status" class="input"><option value="">Alle Status</option>@foreach ($statuses as $s)<option value="{{ $s->id }}" @selected(request('status') == $s->id)>{{ $s->name }}</option>@endforeach</select>
        <select name="location" class="input"><option value="">Alle Standorte</option>@foreach ($locations as $l)<option value="{{ $l->id }}" @selected(request('location') == $l->id)>{{ $l->name }}</option>@endforeach</select>
        <select name="state" class="input"><option value="">Jeder Zustand</option>
            @foreach (['out' => 'Ausgegeben', 'in' => 'Im Lager', 'overdue' => 'Prüfung überfällig', 'due_soon' => 'Prüfung bald fällig', 'blocked' => 'Gesperrt'] as $k => $v)<option value="{{ $k }}" @selected(request('state') === $k)>{{ $v }}</option>@endforeach</select>
        <div class="flex gap-2 lg:col-span-6"><button class="btn btn-primary">Filtern</button><a href="{{ route('assets.index') }}" class="btn">Zurücksetzen</a></div>
    </form>

    <form method="post" action="{{ route('assets.bulk') }}">
        @csrf
        <div class="card overflow-x-auto">
            <table class="tbl">
                <thead><tr>
                    <th class="w-8"><input type="checkbox" data-check-all aria-label="Alle"></th>
                    <th><a href="{{ request()->fullUrlWithQuery(['sort' => 'asset_tag', 'dir' => request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="text-slate-500">Tag</a></th>
                    <th>Modell</th><th>Seriennr.</th><th>Status</th><th>Standort</th><th>Menge</th>
                    <th><a href="{{ request()->fullUrlWithQuery(['sort' => 'next_inspection_due', 'dir' => request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="text-slate-500">Nächste Prüfung</a></th>
                </tr></thead>
                <tbody>
                @forelse ($assets as $a)
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="{{ $a->id }}" aria-label="{{ $a->asset_tag }}"></td>
                        <td class="whitespace-nowrap font-medium"><a href="{{ route('assets.show', $a) }}">{{ $a->asset_tag }}</a>@if ($a->is_blocked) <span title="{{ $a->blocked_reason }}">🔒</span>@endif @if ($a->is_container)<span title="Case/Kit">▣</span>@endif</td>
                        <td>{{ $a->model->full_name }}<div class="text-xs text-slate-500">{{ $a->model->category->name }}@if($a->name) · {{ $a->name }}@endif</div></td>
                        <td class="text-slate-600">{{ $a->serial }}</td>
                        <td>@include('partials.status-badge', ['status' => $a->status])</td>
                        <td class="text-slate-600">{{ $a->location?->name }}</td>
                        <td>{{ $a->model->isQuantity() ? $a->availableQuantity().' / '.$a->quantity : '' }}</td>
                        <td>@if ($a->next_inspection_due)<x-badge :color="$a->next_inspection_due->isPast() ? '#dc2626' : ($a->next_inspection_due->diffInDays(today()) <= 30 ? '#d97706' : '#16a34a')">{{ $a->next_inspection_due->format('d.m.Y') }}</x-badge>@endif</td>
                    </tr>
                @empty<tr><td colspan="8" class="py-8 text-center text-slate-500">Keine Assets gefunden.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3 flex flex-wrap items-end gap-3">
            <select name="action" class="input w-auto"><option value="labels">Etiketten drucken</option>@can('assets.update')<option value="status">Status setzen</option><option value="location">Standort setzen</option>@endcan</select>
            <select name="status_label_id" class="input w-auto"><option value="">Status…</option>@foreach ($statuses as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
            <select name="location_id" class="input w-auto"><option value="">Standort…</option>@foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select>
            <button class="btn">Auf Auswahl anwenden</button>
        </div>
    </form>
    <div class="mt-4">{{ $assets->links() }}</div>
</x-layout>
