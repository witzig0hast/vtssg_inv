<x-layout title="Suche">
    <x-page-head title="Suche" :sub="'„'.$term.'“'" />
    @php $none = $assets->isEmpty() && $models->isEmpty() && $projects->isEmpty() && $consumables->isEmpty(); @endphp
    @if ($none)<div class="card card-b text-zinc-500">Keine Treffer.</div>@endif
    <div class="grid gap-6 lg:grid-cols-2">
        @if ($assets->isNotEmpty())<div class="card"><div class="card-h"><h2>Assets</h2></div><ul class="divide-y divide-zinc-100 text-sm">@foreach ($assets as $a)<li class="px-5 py-2.5"><a href="{{ route('assets.show', $a) }}" class="font-medium">{{ $a->asset_tag }}</a> {{ $a->model->full_name }} <span class="text-zinc-500">{{ $a->serial }}</span></li>@endforeach</ul></div>@endif
        @if ($models->isNotEmpty())<div class="card"><div class="card-h"><h2>Modelle</h2></div><ul class="divide-y divide-zinc-100 text-sm">@foreach ($models as $m)<li class="px-5 py-2.5"><a href="{{ route('models.show', $m) }}">{{ $m->full_name }}</a></li>@endforeach</ul></div>@endif
        @if ($projects->isNotEmpty())<div class="card"><div class="card-h"><h2>Projekte</h2></div><ul class="divide-y divide-zinc-100 text-sm">@foreach ($projects as $p)<li class="px-5 py-2.5"><a href="{{ route('projects.show', $p) }}">{{ $p->number }} · {{ $p->name }}</a></li>@endforeach</ul></div>@endif
        @if ($consumables->isNotEmpty())<div class="card"><div class="card-h"><h2>Verbrauchsmaterial</h2></div><ul class="divide-y divide-zinc-100 text-sm">@foreach ($consumables as $c)<li class="px-5 py-2.5"><a href="{{ route('consumables.show', $c) }}">{{ $c->name }}</a></li>@endforeach</ul></div>@endif
    </div>
</x-layout>
