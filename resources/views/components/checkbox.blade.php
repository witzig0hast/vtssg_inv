@props(['name', 'label', 'checked' => false, 'hint' => null])
<div {{ $attributes->only('class') }}>
    <label class="flex items-center gap-2 text-sm text-zinc-700">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) class="h-4 w-4 rounded border-zinc-300 text-brand-600 focus:ring-brand-500" {{ $attributes->except('class') }}>
        {{ $label }}
    </label>
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
</div>
