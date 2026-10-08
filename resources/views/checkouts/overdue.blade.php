<x-layout title="Offene Ausgaben" wide>
    <x-page-head title="Offene Ausgaben" sub="Alles, was aktuell draußen ist" />
    <div class="card overflow-x-auto"><table class="tbl"><thead><tr><th>Asset</th><th>An</th><th>Seit</th><th>Rückgabe bis</th><th>Menge</th><th></th></tr></thead><tbody>
        @forelse ($checkouts as $c)<tr><td><a href="{{ route('assets.show', $c->asset) }}" class="font-medium">{{ $c->asset->asset_tag }}</a> {{ $c->asset->model->full_name }}</td><td>{{ $c->assigneeLabel() }}</td><td>{{ $c->checked_out_at->format('d.m.Y H:i') }}</td>
            <td>@if ($c->expected_return_at)<x-badge :color="$c->isOverdue() ? '#dc2626' : '#16a34a'">{{ $c->expected_return_at->format('d.m.Y') }}</x-badge>@endif</td><td>{{ $c->quantity }}</td>
            <td class="text-right"><form method="post" action="{{ route('checkouts.checkin', $c) }}" class="inline">@csrf<input type="hidden" name="condition" value="ok"><button class="btn btn-sm">Zurücknehmen</button></form></td></tr>
        @empty<tr><td colspan="6" class="py-8 text-center text-zinc-500">Alles im Lager. ✓</td></tr>@endforelse</tbody></table></div>
    <div class="mt-4">{{ $checkouts->links() }}</div>
</x-layout>
