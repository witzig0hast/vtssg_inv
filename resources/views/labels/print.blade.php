<x-layout title="Etiketten" wide>
    <x-page-head title="Etiketten drucken" :sub="$labels->count().' Etiketten'">
        <form method="get" class="flex items-center gap-2">
            @foreach ($labels as $l)<input type="hidden" name="ids[]" value="{{ $l['asset']->id }}">@endforeach
            <select name="format" class="input w-auto">
                @foreach (['small' => 'Klein 50×25 mm', 'medium' => 'Mittel 70×35 mm', 'sheet' => 'A4-Bogen (3×8)'] as $k => $v)<option value="{{ $k }}" @selected($format === $k)>{{ $v }}</option>@endforeach
            </select><button class="btn">Anwenden</button>
        </form>
        <button type="button" data-print class="btn btn-primary">Drucken</button>
    </x-page-head>
    @php $size = ['small' => 'w-[50mm] h-[25mm]', 'medium' => 'w-[70mm] h-[35mm]', 'sheet' => 'w-[70mm] h-[36mm]'][$format]; @endphp
    <div class="flex flex-wrap gap-2 print:gap-0">
        @foreach ($labels as $l)
            <div class="{{ $size }} flex items-center gap-2 overflow-hidden rounded border border-dashed border-slate-300 bg-white p-1.5 print:border-0 print:break-inside-avoid" style="break-inside: avoid">
                <div class="h-full aspect-square shrink-0 [&>svg]:h-full [&>svg]:w-full">{!! $l['qr'] !!}</div>
                <div class="min-w-0 leading-tight">
                    <div class="font-mono text-sm font-bold">{{ $l['asset']->asset_tag }}</div>
                    <div class="truncate text-[10px]">{{ $l['asset']->model->full_name }}</div>
                    @if ($l['asset']->serial)<div class="truncate text-[9px] text-slate-600">SN {{ $l['asset']->serial }}</div>@endif
                    <div class="truncate text-[8px] text-slate-500">{{ $company }}</div>
                </div>
            </div>
        @endforeach
    </div>
</x-layout>
