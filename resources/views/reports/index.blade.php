<x-layout title="Berichte">
    <x-page-head title="Berichte" sub="Auswertungen und Buchhaltungs-Exporte">
        <a href="{{ route('accounting.afa') }}" class="btn">Anlagenspiegel CSV</a>
    </x-page-head>
    <form method="get" action="{{ route('accounting.revenue') }}" class="card card-b mb-6 flex flex-wrap items-end gap-3">
        <div class="text-sm font-semibold">Erlösbuchungen (DATEV-ähnlich)</div>
        <x-input name="from" type="date" label="Von" :value="now()->startOfMonth()->toDateString()" required /><x-input name="to" type="date" label="Bis" :value="now()->toDateString()" required />
        <button class="btn">CSV exportieren</button><span class="hint">Abgeschlossene/zurückgebuchte Projekte im Zeitraum. Format bitte mit dem Steuerberater abstimmen.</span>
    </form>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Anschaffungswert" :value="\App\Support\Money::format($totalValue)" />
        <x-stat label="Buchwert (linear)" :value="\App\Support\Money::format($bookValue)" tone="brand" />
        <x-stat label="Projekt-Einsätze (12 Mon.)" :value="$utilization" />
        <x-stat label="Lampen/Std. kritisch" :value="$lampWarn->count()" :tone="$lampWarn->count() ? 'amber' : 'green'" />
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card"><div class="card-h"><h2>Bestand nach Kategorie</h2></div><table class="tbl"><thead><tr><th>Kategorie</th><th class="text-right">Stück</th><th class="text-right">Wert</th></tr></thead><tbody>@foreach ($byCategory as $k => $v)<tr><td>{{ $k }}</td><td class="text-right">{{ $v['count'] }}</td><td class="text-right">{{ \App\Support\Money::format($v['value']) }}</td></tr>@endforeach</tbody></table></div>
        <div class="card"><div class="card-h"><h2>Projekte pro Monat</h2></div><div class="card-b space-y-2">@php $max = max(1, $projectsByMonth->max() ?? 1); @endphp
            @forelse ($projectsByMonth as $m => $n)<div class="flex items-center gap-3 text-sm"><span class="w-16 text-zinc-500">{{ $m }}</span><div class="h-4 flex-1 overflow-hidden rounded bg-zinc-100"><div class="h-full bg-brand-500" style="width: {{ $n / $max * 100 }}%"></div></div><span class="w-6 text-right">{{ $n }}</span></div>@empty<p class="text-zinc-500">Keine Daten.</p>@endforelse</div></div>
        <div class="card lg:col-span-2"><div class="card-h"><h2>Ersatzbedarf (Prognose, 24 Monate)</h2><span class="text-xs text-zinc-500">nach Betriebsstunden-Tempo bzw. Abschreibungsende</span></div>
            <table class="tbl"><tbody>@forelse ($forecast as $f)<tr><td><a href="{{ route('assets.show', $f['asset']) }}">{{ $f['asset']->asset_tag }}</a> {{ $f['asset']->model->full_name }}</td><td class="text-zinc-500">{{ $f['reason'] }}</td><td class="text-right">{{ $f['date']->format('m/Y') }}</td></tr>@empty<tr><td class="text-zinc-500">Kein Ersatzbedarf absehbar.</td></tr>@endforelse</tbody></table></div>
        <div class="card lg:col-span-2"><div class="card-h"><h2>Lebenszykluskosten (Top 15)</h2><span class="text-xs text-zinc-500">Kaufpreis + Reparaturen, je Projekteinsatz</span></div>
            <table class="tbl"><thead><tr><th>Asset</th><th class="text-right">Reparaturen</th><th class="text-right">Gesamtkosten</th><th class="text-right">Einsätze</th><th class="text-right">€ / Einsatz</th></tr></thead><tbody>
                @foreach ($lifecycle as $r)<tr><td><a href="{{ route('assets.show', $r['asset']) }}">{{ $r['asset']->asset_tag }}</a> {{ $r['asset']->model->full_name }}</td><td class="text-right">{{ \App\Support\Money::format($r['repairs']) }}</td><td class="text-right">{{ \App\Support\Money::format($r['total']) }}</td><td class="text-right">{{ $r['uses'] }}</td><td class="text-right">{{ \App\Support\Money::format($r['per_use']) }}</td></tr>@endforeach</tbody></table></div>
    </div>
</x-layout>
