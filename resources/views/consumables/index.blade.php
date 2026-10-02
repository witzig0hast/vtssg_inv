<x-layout title="Verbrauchsmaterial">
    <x-page-head title="Verbrauchsmaterial" sub="Gaffa, Batterien, Leuchtmittel, Fluid …">@can('consumables.manage')<a href="{{ route('consumables.create') }}" class="btn btn-primary">+ Artikel</a>@endcan</x-page-head>
    <form method="get" class="mb-4 flex gap-2"><input name="q" value="{{ request('q') }}" placeholder="Suchen…" class="input max-w-xs"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="low" value="1" @checked(request('low'))> nur Mindestbestand</label><button class="btn">Filtern</button></form>
    <div class="card overflow-x-auto"><table class="tbl"><thead><tr><th>Artikel</th><th>Kategorie</th><th>Standort</th><th class="text-right">Bestand</th><th class="text-right">Minimum</th></tr></thead><tbody>
        @forelse ($rows as $c)<tr><td><a href="{{ route('consumables.show', $c) }}" class="font-medium">{{ $c->name }}</a><div class="text-xs text-slate-500">{{ $c->sku }}</div></td><td>{{ $c->category?->name }}</td><td>{{ $c->location?->name }}</td>
            <td class="text-right {{ $c->isLow() ? 'font-semibold text-red-600' : '' }}">{{ $c->quantity }} {{ $c->unit }}</td><td class="text-right text-slate-500">{{ $c->min_quantity }}</td></tr>
        @empty<tr><td colspan="5" class="py-8 text-center text-slate-500">Keine Artikel.</td></tr>@endforelse</tbody></table></div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layout>
