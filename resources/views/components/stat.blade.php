@props(['label', 'value', 'href' => null, 'tone' => 'slate'])
@php $tones = ['slate' => 'text-slate-900', 'red' => 'text-red-600', 'amber' => 'text-amber-600', 'green' => 'text-green-600', 'brand' => 'text-brand-600']; @endphp
<a @if($href) href="{{ $href }}" @endif class="card block p-4 transition hover:shadow-md">
    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</div>
    <div class="mt-1 text-3xl font-semibold {{ $tones[$tone] ?? $tones['slate'] }}">{{ $value }}</div>
</a>
