@props(['color' => '#64748b'])
<span {{ $attributes->merge(['class' => 'badge']) }} style="background-color: {{ $color }}1a; color: {{ $color }}; border: 1px solid {{ $color }}40">{{ $slot }}</span>
