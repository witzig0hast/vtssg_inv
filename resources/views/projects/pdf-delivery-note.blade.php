<!doctype html><html lang="de"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#111}h1{font-size:18px;margin:0 0 4px}table{width:100%;border-collapse:collapse;margin-top:8px}th{background:#eee;text-align:left;padding:4px 6px;font-size:9px;text-transform:uppercase}td{padding:4px 6px;border-bottom:1px solid #ddd}.r{text-align:right}.g td{background:#f3f3f3;font-weight:bold}.meta td{border:0;padding:1px 6px 1px 0}.sig{margin-top:50px}.sig div{display:inline-block;width:45%;border-top:1px solid #333;padding-top:3px;margin-right:4%}
</style></head><body>
<table class="meta"><tr><td><b>{{ $company }}</b><br>{!! nl2br(e($companyAddress)) !!}</td><td class="r"><h1>Lieferschein</h1>Nr. {{ $project->number }}<br>{{ now()->format('d.m.Y') }}</td></tr></table>
<table class="meta" style="margin-top:14px"><tr><td style="width:50%"><b>Kunde</b><br>{{ $project->customer?->name }}<br>{{ $project->customer?->contact }}<br>{{ $project->customer?->address }}</td>
<td><b>Projekt</b><br>{{ $project->name }}<br>Ort: {{ $project->venue ?: $project->venueLocation?->name }}<br>Zeitraum: {{ $project->starts_at?->format('d.m.Y H:i') }} – {{ $project->ends_at?->format('d.m.Y H:i') }}<br>Ansprechpartner: {{ $project->manager?->name }}</td></tr></table>
<table><thead><tr><th>Pos.</th><th>Bezeichnung</th><th class="r">Menge</th><th class="r">Einzelpreis/Tag</th><th class="r">Tage</th><th class="r">Summe</th></tr></thead><tbody>
@php $n = 0; $sum = 0; @endphp
@foreach ($groups as $g => $items)<tr class="g"><td colspan="6">{{ $g }}</td></tr>
@foreach ($items as $i)@php $n++; $line = $i->unitPriceCents() * $i->quantity * $project->rentalDays(); $sum += $line; @endphp
<tr><td>{{ $n }}</td><td>{{ $i->label() }}@if($i->is_external) (Zumiete)@endif</td><td class="r">{{ $i->quantity }}</td><td class="r">{{ \App\Support\Money::format($i->unitPriceCents()) }}</td><td class="r">{{ $project->rentalDays() }}</td><td class="r">{{ \App\Support\Money::format($line) }}</td></tr>@endforeach @endforeach
</tbody><tfoot><tr><td colspan="5" class="r"><b>Summe netto</b></td><td class="r"><b>{{ \App\Support\Money::format($sum) }}</b></td></tr></tfoot></table>
<div class="sig"><div>Ausgegeben (Datum, Unterschrift)</div><div>@if($signature)<img src="{{ $signature }}" style="height:40px"><br>@endif Empfangen @if($project->signed_by)— {{ $project->signed_by }}, {{ $project->signed_at?->format('d.m.Y H:i') }}@else(Datum, Unterschrift)@endif</div></div>
</body></html>
