@props(['label', 'value', 'href' => null, 'tone' => 'slate'])
@php $tones = ['slate' => 'text-zinc-950', 'red' => 'text-red-600', 'amber' => 'text-amber-600', 'green' => 'text-zinc-950', 'brand' => 'text-zinc-950']; @endphp
<a @if($href) href="{{ $href }}" @endif class="card block p-5 no-underline transition hover:border-zinc-400">
    <div class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">{{ $label }}</div>
    <div class="mt-2 text-3xl font-semibold tracking-tight {{ $tones[$tone] ?? $tones['slate'] }}">{{ $value }}</div>
</a>
