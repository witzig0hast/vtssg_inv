<x-layout :title="$c->name">
    <x-page-head :title="$c->name" :sub="$c->category?->name">
        @can('consumables.manage')<a href="{{ route('consumables.edit', $c) }}" class="btn">Bearbeiten</a><x-delete :action="route('consumables.destroy', $c)" />@endcan
    </x-page-head>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <div class="card card-b text-center"><div class="text-5xl font-semibold {{ $c->isLow() ? 'text-red-600' : '' }}">{{ $c->quantity }}</div><div class="text-slate-500">{{ $c->unit }} · Minimum {{ $c->min_quantity }}</div></div>
            @can('consumables.manage')
            <div class="card"><div class="card-h"><h2>Buchung</h2></div>
                <form method="post" action="{{ route('consumables.movement', $c) }}" class="card-b space-y-3">@csrf
                    <x-select name="reason" label="Art" :options="\App\Models\Consumable::REASONS" value="usage" />
                    <x-input name="delta" type="number" label="Menge" value="1" hint="Bei Korrektur: negativ möglich." />
                    <x-select name="project_id" label="Projekt (optional)" :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->number.' · '.$p->name])" empty="— keins —" />
                    <x-input name="note" label="Notiz" />
                    <button class="btn btn-primary">Buchen</button>
                </form></div>
            @endcan
        </div>
        <div class="card lg:col-span-2"><div class="card-h"><h2>Bewegungen</h2></div>
            <table class="tbl"><thead><tr><th>Wann</th><th>Art</th><th class="text-right">Menge</th><th>Von</th><th>Notiz</th></tr></thead><tbody>
                @forelse ($movements as $m)<tr><td class="whitespace-nowrap">{{ $m->created_at->format('d.m.Y H:i') }}</td><td>{{ \App\Models\Consumable::REASONS[$m->reason] }}</td><td class="text-right {{ $m->delta < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $m->delta > 0 ? '+' : '' }}{{ $m->delta }}</td><td>{{ $m->user?->name }}</td><td>{{ $m->note }}</td></tr>@empty<tr><td colspan="5" class="text-slate-500">Keine Bewegungen.</td></tr>@endforelse</tbody></table></div>
    </div>
</x-layout>
