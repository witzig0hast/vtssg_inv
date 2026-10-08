<x-layout :title="$asset->asset_tag">
    <x-page-head :title="$asset->asset_tag.' · '.$asset->model->full_name" :sub="$asset->name">
        @can('assets.checkout')<a href="{{ route('assets.checkout', $asset) }}" class="btn btn-primary">Ausgeben</a>@endcan
        @can('assets.label')<a href="{{ route('assets.label', $asset) }}" class="btn">Etikett</a>@endcan
        @can('inspections.record')<a href="{{ route('inspections.create', ['asset' => $asset->id]) }}" class="btn">Prüfung erfassen</a>@endcan
        @can('maintenance.report')<a href="{{ route('maintenance.create', ['asset' => $asset->id]) }}" class="btn">Defekt melden</a>@endcan
        @can('assets.update')<a href="{{ route('assets.edit', $asset) }}" class="btn">Bearbeiten</a>@endcan
        @can('assets.delete')<x-delete :action="route('assets.destroy', $asset)" confirm="Asset wirklich löschen?" />@endcan
    </x-page-head>

    @if ($asset->is_blocked)<div class="alert mb-4 border-red-200 bg-red-50 text-red-800">🔒 Gesperrt: {{ $asset->blocked_reason ?: 'ohne Angabe' }}</div>@endif
    @if ($asset->isInspectionOverdue())<div class="alert mb-4 border-red-200 bg-red-50 text-red-800">⚠ Prüfung überfällig seit {{ $asset->next_inspection_due->format('d.m.Y') }}@if ($asset->blockingOverdueInspections()) – Ausgabe gesperrt.@endif</div>@endif
    @if (($rem = $asset->serviceHoursRemaining()) !== null && $rem <= $asset->serviceHoursLimit() * 0.1)<div class="alert mb-4 border-amber-200 bg-amber-50 text-amber-800">💡 Lebensdauer fast erreicht: noch {{ number_format($rem, 0, ',', '.') }} h von {{ number_format($asset->serviceHoursLimit(), 0, ',', '.') }} h.</div>@endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card"><div class="card-h"><h2>Details</h2>@include('partials.status-badge', ['status' => $asset->status])</div>
                <dl class="dl card-b">
                    <dt>Modell</dt><dd><a href="{{ route('models.show', $asset->model) }}">{{ $asset->model->full_name }}</a> <span class="text-zinc-500">({{ $asset->model->category->name }})</span></dd>
                    <dt>Seriennummer</dt><dd>{{ $asset->serial ?: '—' }}</dd>
                    <dt>Standort</dt><dd>{{ $asset->location?->path ?: '—' }}</dd>
                    @if ($asset->parent)<dt>Gehört zu</dt><dd><a href="{{ route('assets.show', $asset->parent) }}">{{ $asset->parent->asset_tag }} {{ $asset->parent->displayName() }}</a></dd>@endif
                    @if ($asset->model->isQuantity())<dt>Menge</dt><dd>{{ $asset->availableQuantity() }} verfügbar von {{ $asset->quantity }} {{ $asset->model->unit }}</dd>@endif
                    <dt>Gekauft</dt><dd>{{ $asset->purchase_date?->format('d.m.Y') ?: '—' }} · {{ \App\Support\Money::format($asset->purchase_cost_cents) }}@if ($asset->supplier) · {{ $asset->supplier->name }}@endif</dd>
                    <dt>Garantie bis</dt><dd>{{ $asset->warranty_until?->format('d.m.Y') ?: '—' }}</dd>
                    <dt>Betriebsstunden</dt><dd>{{ number_format($asset->operating_hours, 1, ',', '.') }} h</dd>
                    <dt>Notizen</dt><dd class="whitespace-pre-line">{{ $asset->notes ?: '—' }}</dd>
                </dl>
            </div>

            @if ($current->isNotEmpty())
            <div class="card border-brand-200"><div class="card-h"><h2>Aktuell ausgegeben</h2></div>
                <table class="tbl"><tbody>@foreach ($current as $c)
                    <tr><td>{{ $c->assigneeLabel() }}@if ($c->quantity > 1) <span class="text-zinc-500">× {{ $c->quantity }}</span>@endif<div class="text-xs text-zinc-500">seit {{ $c->checked_out_at->format('d.m.Y H:i') }}@if ($c->expected_return_at) · Rückgabe bis {{ $c->expected_return_at->format('d.m.Y') }}@endif</div></td>
                    <td class="text-right">@can('assets.checkout')
                        <form method="post" action="{{ route('checkouts.checkin', $c) }}" class="flex flex-wrap items-center justify-end gap-2">@csrf
                            @if ($c->quantity > 1)<input type="number" name="quantity" min="1" max="{{ $c->quantity }}" value="{{ $c->quantity }}" class="input w-20" aria-label="Menge">@endif
                            @if ($asset->serviceHoursLimit())<input type="number" step="0.1" min="0" name="hours" placeholder="Std." class="input w-20" aria-label="Betriebsstunden">@endif
                            <select name="condition" class="input w-auto">@foreach (\App\Models\Checkout::CONDITIONS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                            <input name="note" placeholder="Notiz" class="input w-40"><button class="btn btn-sm btn-primary">Zurücknehmen</button></form>@endcan</td></tr>@endforeach</tbody></table>
            </div>
            @endif

            @if ($asset->is_container || $asset->children->isNotEmpty())
            <div class="card"><div class="card-h"><h2>Inhalt (Case/Kit)</h2>@can('assets.create')<a href="{{ route('assets.create', ['parent' => $asset->id]) }}" class="btn btn-sm">+ Inhalt</a>@endcan</div>
                <table class="tbl"><tbody>@forelse ($asset->children as $c)<tr><td><a href="{{ route('assets.show', $c) }}">{{ $c->asset_tag }}</a></td><td>{{ $c->displayName() }}</td><td>@include('partials.status-badge', ['status' => $c->status])</td></tr>@empty<tr><td class="text-zinc-500">Leer.</td></tr>@endforelse</tbody></table>
            </div>
            @endif

            <div class="card"><div class="card-h"><h2>Verlauf Ausgaben</h2></div>
                <table class="tbl"><thead><tr><th>Wann</th><th>An</th><th>Rückgabe</th><th>Zustand</th></tr></thead><tbody>
                    @forelse ($checkouts as $c)<tr><td class="whitespace-nowrap">{{ $c->checked_out_at->format('d.m.Y H:i') }}<div class="text-xs text-zinc-500">{{ $c->performer?->name }}</div></td><td>{{ $c->assigneeLabel() }}</td><td class="whitespace-nowrap">{{ $c->returned_at?->format('d.m.Y H:i') ?: '—' }}</td><td>{{ \App\Models\Checkout::CONDITIONS[$c->condition_in ?? $c->condition_out] ?? '' }}</td></tr>
                    @empty<tr><td colspan="4" class="text-zinc-500">Noch keine Ausgaben.</td></tr>@endforelse</tbody></table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card card-b text-center">
                <div class="mx-auto h-40 w-40">{!! \App\Support\Qr::svg($asset->qrPayload()) !!}</div>
                <div class="mt-2 font-mono text-sm">{{ $asset->asset_tag }}</div>
                <div class="mt-2 flex flex-wrap justify-center gap-2"><button type="button" class="btn btn-sm hidden" data-nfc-write="{{ $asset->asset_tag }}">NFC-Tag beschreiben</button><a class="btn btn-sm" href="{{ route('pass', $asset->uuid) }}" target="_blank" rel="noopener">Gerätepass</a></div>
            </div>

            <div class="card"><div class="card-h"><h2>Prüfungen</h2></div>
                <ul class="divide-y divide-zinc-100 text-sm">
                    @forelse ($dues as $d)<li class="flex items-center justify-between gap-2 px-5 py-2.5"><div>{{ $d['type']->name }}<div class="text-xs text-zinc-500">zuletzt: {{ $d['last']?->performed_at->format('d.m.Y') ?? 'nie' }}</div></div>
                        <x-badge :color="$d['due']->isPast() ? '#dc2626' : ($d['due']->diffInDays(today()) <= 30 ? '#d97706' : '#16a34a')">{{ $d['due']->format('d.m.Y') }}</x-badge></li>
                    @empty<li class="px-5 py-3 text-zinc-500">Für dieses Modell sind keine Prüfarten definiert.</li>@endforelse
                </ul>
                @if ($inspections->isNotEmpty())<div class="border-t border-zinc-200 px-5 py-3 text-xs text-zinc-500">
                    @foreach ($inspections->take(5) as $i)<div class="py-0.5">{{ $i->performed_at->format('d.m.Y') }} · {{ $i->type->code }} · {{ \App\Models\Inspection::RESULTS[$i->result] }}@if($i->protocol_number) · {{ $i->protocol_number }}@endif</div>@endforeach</div>@endif
            </div>

            @if ($asset->model->service_hours_limit)
            @can('assets.checkout')<div class="card"><div class="card-h"><h2>Betriebsstunden</h2><span class="text-sm text-zinc-500">{{ number_format($asset->operating_hours, 1, ',', '.') }} / {{ $asset->model->service_hours_limit }} h</span></div>
                <form method="post" action="{{ route('assets.hours', $asset) }}" class="card-b flex gap-2">@csrf<input type="number" step="0.1" min="0.1" name="hours" placeholder="Stunden" class="input" required><button class="btn">Buchen</button></form></div>@endcan
            @endif
            <div class="card"><div class="card-h"><h2>Wartung</h2></div>
                <ul class="divide-y divide-zinc-100 text-sm">@forelse ($tickets as $t)<li class="px-5 py-2.5"><a href="{{ route('maintenance.show', $t) }}">{{ $t->title }}</a><div class="text-xs text-zinc-500">{{ \App\Models\MaintenanceTicket::STATUSES[$t->status] }} · {{ $t->created_at->format('d.m.Y') }}</div></li>@empty<li class="px-5 py-3 text-zinc-500">Keine Tickets.</li>@endforelse</ul>
            </div>

            @include('partials.attachments', ['target' => $asset, 'type' => 'asset', 'canUpload' => auth()->user()->can('assets.update')])

            @can('assets.update')
            <div class="card"><div class="card-h"><h2>Sperre</h2></div>
                <form method="post" action="{{ route('assets.block', $asset) }}" class="card-b space-y-3">@csrf
                    <input type="hidden" name="blocked" value="{{ $asset->is_blocked ? 0 : 1 }}">
                    @unless ($asset->is_blocked)<input name="reason" placeholder="Grund" class="input">@endunless
                    <button class="btn {{ $asset->is_blocked ? '' : 'btn-danger' }}">{{ $asset->is_blocked ? 'Sperre aufheben' : 'Asset sperren' }}</button>
                </form>
            </div>
            @endcan
        </div>
    </div>
</x-layout>
