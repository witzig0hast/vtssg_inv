@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false])
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="f-{{ $name }}" class="label">{{ $label }}@if($required) <span class="text-red-500">*</span>@endif</label>@endif
    <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'input']) }}>
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p class="err">{{ $message }}</p>@enderror
</div>
