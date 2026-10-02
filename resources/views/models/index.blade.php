<x-layout title="Modelle">
    <x-page-head title="Modelle" sub="Gerätetypen – Grundlage für Assets, Preise und Prüffristen">
        @can('models.manage')<a href="{{ route('models.create') }}" class="btn btn-primary">+ Modell</a>@endcan
    </x-page-head>
    <form method="get" class="mb-4 flex gap-2"><input name="q" value="{{ request('q') }}" placeholder="Suchen…" class="input max-w-xs">
        <select name="category" class="input w-auto"><option value="">Alle Kategorien</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select><button class="btn">Filtern</button></form>
    <div class="card overflow-x-auto"><table class="tbl"><thead><tr><th>Modell</th><th>Kategorie</th><th>Art</th><th>Assets</th><th>Mietpreis/Tag</th></tr></thead><tbody>
        @forelse ($models as $m)<tr><td><a href="{{ route('models.show', $m) }}" class="font-medium">{{ $m->full_name }}</a><div class="text-xs text-slate-500">{{ $m->model_number }}</div></td><td>{{ $m->category->name }}</td><td>{{ $m->isQuantity() ? 'Menge' : 'Einzelgerät' }}</td><td>{{ $m->assets_count }}</td><td>{{ \App\Support\Money::format($m->rental_price_cents) }}</td></tr>
        @empty<tr><td colspan="5" class="py-8 text-center text-slate-500">Keine Modelle.</td></tr>@endforelse
    </tbody></table></div>
    <div class="mt-4">{{ $models->links() }}</div>
</x-layout>
