@props(['name', 'label' => null, 'value' => null, 'rows' => 3])
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="f-{{ $name }}" class="label">{{ $label }}</label>@endif
    <textarea id="f-{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->except('class')->merge(['class' => 'input']) }}>{{ old($name, $value) }}</textarea>
    @error($name)<p class="err">{{ $message }}</p>@enderror
</div>
