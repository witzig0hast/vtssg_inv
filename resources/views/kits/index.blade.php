<x-layout title="Packlisten-Vorlagen">
    <x-page-head title="Packlisten-Vorlagen" sub="Wiederkehrende Pakete (z. B. „PA klein“, „Bühne 6×4“)"><a href="{{ route('kits.create') }}" class="btn btn-primary">+ Vorlage</a></x-page-head>
    <div class="card"><table class="tbl"><tbody>
        @forelse ($kits as $k)<tr><td><a href="{{ route('kits.edit', $k) }}" class="font-medium">{{ $k->name }}</a><div class="text-xs text-slate-500">{{ $k->description }}</div></td><td>{{ $k->items_count }} Positionen</td><td class="text-right"><x-delete :action="route('kits.destroy', $k)" /></td></tr>
        @empty<tr><td class="py-8 text-center text-slate-500">Noch keine Vorlagen. Du kannst jede Projekt-Packliste als Vorlage speichern.</td></tr>@endforelse
    </tbody></table></div>
</x-layout>
