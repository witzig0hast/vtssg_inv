<x-layout :title="$def['title']">
    <x-page-head :title="$def['title']">
        <form method="get" class="flex gap-2"><input name="q" value="{{ request('q') }}" placeholder="Suchen…" class="input w-48"><button class="btn">Suchen</button></form>
        @if ($def['can_manage'])<a href="{{ route('master.create', $def['key']) }}" class="btn btn-primary">+ Neu</a>@endif
    </x-page-head>
    <div class="card overflow-x-auto">
        <table class="tbl">
            <thead><tr>@foreach ($def['columns'] as $c)<th>{{ $c[1] }}</th>@endforeach<th></th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    @foreach ($def['columns'] as $c)
                        <td>
                            @if ($def['key'] === 'status-labels' && $loop->first)<x-badge :color="$r->color">{{ $r->name }}</x-badge>
                            @else{{ isset($c[2]) ? $c[2]($r) : data_get($r, $c[0]) }}@endif
                        </td>
                    @endforeach
                    <td class="whitespace-nowrap text-right">
                        @if ($def['can_manage'])
                            <a href="{{ route('master.edit', [$def['key'], $r->id]) }}" class="btn btn-sm">Bearbeiten</a>
                            <x-delete :action="route('master.destroy', [$def['key'], $r->id])" />
                        @endif
                    </td>
                </tr>
            @empty<tr><td colspan="{{ count($def['columns']) + 1 }}" class="py-8 text-center text-zinc-500">Noch keine Einträge.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layout>
