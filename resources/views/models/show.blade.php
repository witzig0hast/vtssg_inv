<x-layout :title="$m->full_name">
    <x-page-head :title="$m->full_name" :sub="$m->category->name">
        @can('assets.create')<a href="{{ route('assets.create', ['model' => $m->id]) }}" class="btn btn-primary">+ Asset</a>@endcan
        @can('models.manage')<a href="{{ route('models.edit', $m) }}" class="btn">Bearbeiten</a><x-delete :action="route('models.destroy', $m)" confirm="Modell löschen?" />@endcan
    </x-page-head>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2"><div class="card-h"><h2>Assets ({{ $assets->count() }})</h2></div>
            <table class="tbl"><tbody>@forelse ($assets as $a)<tr><td><a href="{{ route('assets.show', $a) }}">{{ $a->asset_tag }}</a></td><td>{{ $a->serial }}</td><td>@include('partials.status-badge', ['status' => $a->status])</td><td>{{ $a->location?->name }}</td></tr>@empty<tr><td class="text-zinc-500">Noch keine Assets.</td></tr>@endforelse</tbody></table></div>
        <div class="space-y-6">
            @if ($m->image_path)<img src="{{ route('models.image', $m) }}" alt="" class="card w-full object-contain">@endif
            <div class="card"><dl class="dl card-b">
                <dt>Hersteller</dt><dd>{{ $m->manufacturer?->name ?: '—' }}</dd><dt>Modellnr.</dt><dd>{{ $m->model_number ?: '—' }}</dd>
                <dt>Art</dt><dd>{{ $m->isQuantity() ? 'Mengenartikel' : 'Einzelgerät' }}</dd><dt>Mietpreis/Tag</dt><dd>{{ \App\Support\Money::format($m->rental_price_cents) }}</dd>
                <dt>Gewicht</dt><dd>{{ $m->weight_kg ? $m->weight_kg.' kg' : '—' }}</dd><dt>Leistung</dt><dd>{{ $m->power_w ? $m->power_w.' W' : '—' }}</dd>
                <dt>Lebensdauer</dt><dd>{{ $m->service_hours_limit ? $m->service_hours_limit.' h' : '—' }}</dd></dl></div>
            <div class="card"><div class="card-h"><h2>Prüfpflichten</h2></div><ul class="divide-y divide-zinc-100 text-sm">@forelse ($m->inspectionTypes as $t)<li class="px-5 py-2.5">{{ $t->name }} <span class="text-zinc-500">alle {{ $t->pivot->interval_months ?: $t->default_interval_months }} Monate</span></li>@empty<li class="px-5 py-3 text-zinc-500">Keine.</li>@endforelse</ul></div>
        </div>
    </div>
</x-layout>
