<x-layout title="Kalender" wide>
    <x-page-head :title="$month->translatedFormat('F Y')">
        <a class="btn" href="{{ route('projects.calendar', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}">←</a>
        <a class="btn" href="{{ route('projects.calendar') }}">Heute</a>
        <a class="btn" href="{{ route('projects.calendar', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">→</a>
        <a class="btn" href="{{ route('projects.index') }}">Liste</a>
    </x-page-head>
    <div class="card overflow-hidden">
        <div class="grid grid-cols-7 border-b border-zinc-200 bg-zinc-50 text-center text-xs font-semibold uppercase text-zinc-500">@foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $d)<div class="py-2">{{ $d }}</div>@endforeach</div>
        <div class="grid grid-cols-7">
            @foreach ($days as $date => $projects)
                @php $d = \Carbon\Carbon::parse($date); @endphp
                <div class="min-h-28 border-b border-r border-zinc-100 p-1.5 {{ $d->month !== $month->month ? 'bg-zinc-50/70 text-zinc-400' : '' }}">
                    <div class="mb-1 text-xs {{ $d->isToday() ? 'inline-flex h-5 w-5 items-center justify-center rounded-full bg-brand-600 font-semibold text-white' : '' }}">{{ $d->day }}</div>
                    @foreach ($projects as $p)
                        <a href="{{ route('projects.show', $p) }}" class="mb-0.5 block truncate rounded px-1.5 py-0.5 text-[11px] font-medium text-white" style="background: {{ ['draft' => '#a1a1aa', 'planned' => '#3f3f46', 'confirmed' => '#52525b', 'loaded' => '#27272a', 'running' => '#16a34a', 'returned' => '#d97706', 'closed' => '#71717a'][$p->status] ?? '#71717a' }}" title="{{ $p->number }} {{ $p->name }}">{{ $p->name }}</a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</x-layout>
