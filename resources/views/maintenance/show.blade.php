<x-layout :title="$t->title">
    <x-page-head :title="$t->title" :sub="'Ticket #'.$t->id.' · '.$t->asset->asset_tag.' '.$t->asset->model->full_name"><a href="{{ route('assets.show', $t->asset) }}" class="btn">Zum Asset</a></x-page-head>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card card-b whitespace-pre-line">{{ $t->description ?: 'Keine Beschreibung.' }}<div class="mt-3 text-xs text-slate-500 not-italic">Gemeldet von {{ $t->reporter?->name }} am {{ $t->created_at->format('d.m.Y H:i') }}</div></div>
            <div class="card"><div class="card-h"><h2>Kommentare</h2></div>
                <ul class="divide-y divide-slate-100 text-sm">@forelse ($t->comments as $c)<li class="px-5 py-3"><div class="text-xs text-slate-500">{{ $c->user?->name }} · {{ $c->created_at->format('d.m.Y H:i') }}</div><div class="whitespace-pre-line">{{ $c->body }}</div></li>@empty<li class="px-5 py-3 text-slate-500">Noch keine Kommentare.</li>@endforelse</ul>
                @can('maintenance.report')<form method="post" action="{{ route('maintenance.comment', $t) }}" class="card-b space-y-2 border-t border-slate-200">@csrf<x-textarea name="body" rows="2" placeholder="Kommentar…" /><button class="btn btn-sm">Kommentieren</button></form>@endcan</div>
        </div>
        <div class="space-y-6">
            @can('maintenance.manage')
            <div class="card"><div class="card-h"><h2>Bearbeiten</h2></div>
                <form method="post" action="{{ route('maintenance.update', $t) }}" class="card-b space-y-3">@csrf @method('PUT')
                    <x-select name="status" label="Status" :options="\App\Models\MaintenanceTicket::STATUSES" :value="$t->status" />
                    <x-select name="priority" label="Priorität" :options="\App\Models\MaintenanceTicket::PRIORITIES" :value="$t->priority" />
                    <x-select name="assigned_to" label="Zuständig" :options="$users->pluck('name', 'id')" :value="$t->assigned_to" empty="— niemand —" />
                    <x-input name="cost" label="Reparaturkosten (€)" :value="\App\Support\Money::input($t->cost_cents)" />
                    <x-checkbox name="release_asset" label="Bei Abschluss Gerät wieder freigeben" :checked="true" />
                    <button class="btn btn-primary">Speichern</button>
                </form></div>
            @else
            <div class="card card-b text-sm">Status: <b>{{ \App\Models\MaintenanceTicket::STATUSES[$t->status] }}</b><br>Zuständig: {{ $t->assignee?->name ?? '—' }}</div>
            @endcan
            @include('partials.attachments', ['target' => $t, 'type' => 'ticket', 'canUpload' => auth()->user()->can('maintenance.report')])
        </div>
    </div>
</x-layout>
