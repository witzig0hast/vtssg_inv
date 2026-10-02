<x-guest title="Gerätepass">
    <div class="text-center">
        <div class="font-mono text-sm text-slate-500">{{ $asset->asset_tag }}</div>
        <h1 class="mt-1 text-xl">{{ $asset->model->full_name }}</h1>
        <div class="text-sm text-slate-500">{{ $asset->model->category->name }}</div>
        <div class="mt-5 rounded-lg border p-4 {{ $valid ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' }}">
            <div class="font-semibold">{{ $valid ? '✓ Prüfung gültig' : '✕ Prüfung überfällig' }}</div>
            @if ($last)<div class="text-sm">Zuletzt geprüft: {{ $last->performed_at->format('d.m.Y') }} ({{ $last->type->name }})</div>@endif
            @if ($asset->next_inspection_due)<div class="text-sm">Nächste Prüfung: {{ $asset->next_inspection_due->format('d.m.Y') }}</div>@endif
        </div>
        <div class="mt-5 text-xs text-slate-500">Eigentum von<br><b>{{ $company }}</b><br>{!! nl2br(e($address)) !!}</div>
    </div>
</x-guest>
