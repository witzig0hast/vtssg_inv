<x-layout :title="$project->exists ? $project->number.' bearbeiten' : 'Neues Projekt'">
    <x-page-head :title="$project->exists ? $project->number.' bearbeiten' : 'Neues Projekt'" />
    <form method="post" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" class="card card-b max-w-4xl">
        @csrf @if ($project->exists) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-2">
            <x-input name="name" label="Projektname" :value="$project->name" required class="md:col-span-2" />
            <x-select name="customer_id" label="Kunde" :value="$project->customer_id" :options="$customers->pluck('name', 'id')" empty="— keiner —" />
            <x-select name="manager_id" label="Projektleitung" :value="$project->manager_id" :options="$managers->pluck('name', 'id')" empty="— keine —" />
            <x-input name="venue" label="Veranstaltungsort" :value="$project->venue" />
            <x-select name="venue_location_id" label="Ort aus Standorten" :value="$project->venue_location_id" :options="$locations->pluck('name', 'id')" empty="— keiner —" />
            <x-input name="setup_at" type="datetime-local" label="Aufbau / Beladung ab" :value="$project->setup_at?->format('Y-m-d\TH:i')" hint="Beginn der Reservierung." />
            <x-input name="teardown_at" type="datetime-local" label="Rückgabe im Lager bis" :value="$project->teardown_at?->format('Y-m-d\TH:i')" hint="Ende der Reservierung." />
            <x-input name="starts_at" type="datetime-local" label="Veranstaltung Beginn" :value="$project->starts_at?->format('Y-m-d\TH:i')" />
            <x-input name="ends_at" type="datetime-local" label="Veranstaltung Ende" :value="$project->ends_at?->format('Y-m-d\TH:i')" />
            <x-textarea name="notes" label="Notizen" :value="$project->notes" class="md:col-span-2" />
        </div>
        <div class="mt-5 flex gap-2"><button class="btn btn-primary">Speichern</button><a href="{{ $project->exists ? route('projects.show', $project) : route('projects.index') }}" class="btn">Abbrechen</a></div>
    </form>
</x-layout>
