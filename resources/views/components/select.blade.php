@props(['name', 'label' => null, 'options' => [], 'value' => null, 'empty' => null, 'required' => false, 'hint' => null])
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="f-{{ $name }}" class="label">{{ $label }}@if($required) <span class="text-red-500">*</span>@endif</label>@endif
    <select id="f-{{ $name }}" name="{{ $name }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'input']) }}>
        @if ($empty !== null)<option value="">{{ $empty }}</option>@endif
        @foreach ($options as $k => $v)
            <option value="{{ $k }}" @selected((string) old($name, $value) === (string) $k)>{{ $v }}</option>
        @endforeach
    </select>
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p class="err">{{ $message }}</p>@enderror
</div>
