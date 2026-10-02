<x-layout title="Ausgeben">
    <x-page-head :title="'Ausgeben: '.$asset->asset_tag" :sub="$asset->model->full_name" />
    <form method="post" action="{{ route('assets.checkout.store', $asset) }}" class="card card-b max-w-2xl space-y-4">@csrf
        @if ($asset->is_container)<div class="alert border-brand-200 bg-brand-50 text-brand-900">Case/Kit: der Inhalt ({{ $asset->children()->count() }} Teile) wird mit ausgegeben.</div>@endif
        <x-select name="assignee_type" label="Ausgeben an" data-switch="assignee_type" required :options="\App\Models\Checkout::ASSIGNEES" value="project" />
        <div data-show-when="assignee_type=user"><x-select name="assignee_id" label="Mitarbeiter" :options="$users->pluck('name', 'id')" empty="— wählen —" /></div>
        <div data-show-when="assignee_type=project"><x-select name="assignee_id" label="Projekt" :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->number.' · '.$p->name])" empty="— wählen —" /></div>
        <div data-show-when="assignee_type=location"><x-select name="assignee_id" label="Standort" :options="$locations->mapWithKeys(fn ($l) => [$l->id => $l->path])" empty="— wählen —" /></div>
        <div data-show-when="assignee_type=customer"><x-select name="assignee_id" label="Kunde" :options="$customers->pluck('name', 'id')" empty="— wählen —" /></div>
        @if ($asset->model->isQuantity())<x-input name="quantity" type="number" min="1" :max="$asset->availableQuantity()" label="Menge (verfügbar: {{ $asset->availableQuantity() }})" value="1" />@endif
        <x-input name="expected_return_at" type="datetime-local" label="Rückgabe bis" />
        <x-select name="condition" label="Zustand bei Ausgabe" :options="\App\Models\Checkout::CONDITIONS" value="ok" />
        <x-input name="note" label="Notiz" />
        <div class="flex gap-2"><button class="btn btn-primary">Ausgeben</button><a href="{{ route('assets.show', $asset) }}" class="btn">Abbrechen</a></div>
    </form>
</x-layout>
