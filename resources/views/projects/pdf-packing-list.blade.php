<!doctype html><html lang="de"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#111}h1{font-size:18px;margin:0 0 4px}table{width:100%;border-collapse:collapse;margin-top:8px}th{background:#eee;text-align:left;padding:4px 6px;font-size:9px;text-transform:uppercase}td{padding:5px 6px;border-bottom:1px solid #ddd}.r{text-align:right}.g td{background:#f3f3f3;font-weight:bold}.box{display:inline-block;width:11px;height:11px;border:1px solid #333}
</style></head><body>
<h1>Packliste {{ $project->number }}</h1><div>{{ $project->name }} · {{ $project->customer?->name }} · Aufbau {{ $project->setup_at?->format('d.m.Y H:i') }} · Ort {{ $project->venue ?: $project->venueLocation?->name }}</div>
<table><thead><tr><th style="width:14px"></th><th>Bezeichnung</th><th>Tag / Seriennr.</th><th class="r">Menge</th><th>Notiz</th><th style="width:14px">Zurück</th></tr></thead><tbody>
@foreach ($groups as $g => $items)<tr class="g"><td colspan="6">{{ $g }}</td></tr>
@foreach ($items as $i)<tr><td><span class="box"></span></td><td>{{ $i->label() }}@if($i->is_external) <b>(Zumiete {{ $i->supplier?->name }})</b>@endif</td><td>{{ $i->asset?->asset_tag }} {{ $i->asset?->serial }}</td><td class="r">{{ $i->quantity }}</td><td>{{ $i->note }}</td><td><span class="box"></span></td></tr>@endforeach @endforeach
</tbody></table></body></html>
