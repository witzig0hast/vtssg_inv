<x-layout :title="$st->name">
    <x-page-head :title="$st->name" :sub="$st->location?->name.' · '.($st->status === 'open' ? 'offen' : 'abgeschlossen')">
        @if ($st->status === 'open')@can('stocktakes.manage')<form method="post" action="{{ route('stocktakes.close', $st) }}" data-confirm="Inventur abschließen? Fehlende Assets bleiben unverändert, werden aber protokolliert.">@csrf<button class="btn btn-primary">Abschließen</button></form>@endcan @endif
    </x-page-head>
    <div id="stocktake-root" data-url="{{ route('stocktakes.scan', $st) }}" class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-4">
            @if ($st->status === 'open')
            <div class="card card-b"><div class="flex gap-2"><input id="pack-code" class="input text-lg" placeholder="Code scannen…" autocomplete="off"><button type="button" class="btn" data-camera-for="pack-code">📷</button><button type="button" id="pack-submit" class="btn btn-primary">OK</button></div>
                <p class="hint mt-2">Nach dem Scannen Seite neu laden, um die Listen zu aktualisieren.</p></div>
            @endif
            <ul id="pack-log" class="space-y-2"></ul>
            <div class="card"><div class="card-h"><h2>Gefunden ({{ $found->count() }})</h2></div><ul class="divide-y divide-zinc-100 text-sm">@foreach ($found as $l)<li class="px-5 py-2">{{ $l->asset->asset_tag }} · {{ $l->asset->model->full_name }}@if ($l->expected_quantity === 0) <x-badge color="#d97706">unerwartet</x-badge>@endif</li>@endforeach</ul></div>
        </div>
        <div class="card"><div class="card-h"><h2>Fehlend ({{ $missing->where('expected_quantity', '>', 0)->count() }})</h2></div><ul class="divide-y divide-zinc-100 text-sm">@foreach ($missing->where('expected_quantity', '>', 0) as $l)<li class="px-5 py-2"><a href="{{ route('assets.show', $l->asset) }}">{{ $l->asset->asset_tag }}</a> · {{ $l->asset->model->full_name }}</li>@endforeach</ul></div>
    </div>
</x-layout>
