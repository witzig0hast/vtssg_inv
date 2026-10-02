<x-layout :title="$def['singular']">
    <x-page-head :title="($row->exists ? $def['singular'].' bearbeiten' : $def['singular'].' anlegen')" />
    <form method="post" action="{{ $row->exists ? route('master.update', [$def['key'], $row->id]) : route('master.store', $def['key']) }}" class="card card-b max-w-2xl space-y-4">
        @csrf @if ($row->exists) @method('PUT') @endif
        @foreach ($def['fields'] as $f)
            @php
                [$name, $label, $type] = $f;
                $opts = $f[4] ?? [];
                $opts = is_callable($opts) ? $opts() : $opts;
                $required = str_contains($f[3] ?? '', 'required');
            @endphp
            @if ($type === 'select')
                <x-select :name="$name" :label="$label" :options="$opts" :value="$row->{$name}" :empty="($f[5] ?? false) ? '— keine —' : null" :required="$required" />
            @elseif ($type === 'textarea')
                <x-textarea :name="$name" :label="$label" :value="$row->{$name}" />
            @elseif ($type === 'checkbox')
                <x-checkbox :name="$name" :label="$label" :checked="(bool) $row->{$name}" />
            @else
                <x-input :name="$name" :label="$label" :type="$type" :value="$row->{$name} ?? ($type === 'color' ? '#64748b' : null)" :required="$required" />
            @endif
        @endforeach
        <div class="flex gap-2"><button class="btn btn-primary">Speichern</button><a href="{{ route('master.index', $def['key']) }}" class="btn">Abbrechen</a></div>
    </form>
</x-layout>
