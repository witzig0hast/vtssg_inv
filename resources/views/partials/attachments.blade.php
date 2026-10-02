<div class="card"><div class="card-h"><h2>Dateien</h2></div>
    <ul class="divide-y divide-slate-100 text-sm">
        @forelse ($target->attachments as $a)
            <li class="flex items-center justify-between gap-2 px-5 py-2.5">
                <div class="min-w-0"><a href="{{ route('attachments.download', $a) }}" class="block truncate">{{ $a->label ?: $a->original_name }}</a><div class="text-xs text-slate-500">{{ $a->original_name }} · {{ number_format($a->size / 1024, 0, ',', '.') }} KB · {{ $a->created_at->format('d.m.Y') }}</div></div>
                @if ($canUpload)<x-delete :action="route('attachments.destroy', $a)" confirm="Datei löschen?" label="×" />@endif
            </li>
        @empty<li class="px-5 py-3 text-slate-500">Keine Dateien.</li>@endforelse
    </ul>
    @if ($canUpload)
    <form method="post" action="{{ route('attachments.store', [$type, $target->id]) }}" enctype="multipart/form-data" class="space-y-2 border-t border-slate-200 p-4">@csrf
        <input type="file" name="file" required class="block w-full text-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.csv,.xlsx">
        <input name="label" placeholder="Bezeichnung (z. B. Handbuch)" class="input">
        <button class="btn btn-sm">Hochladen</button>
    </form>
    @endif
</div>
