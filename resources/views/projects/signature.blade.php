<x-layout title="Unterschrift">
    <x-page-head title="Empfangsbestätigung" :sub="$project->number.' · '.$project->name"><a href="{{ route('projects.show', $project) }}" class="btn">← Projekt</a></x-page-head>
    <form id="sig-form" method="post" action="{{ route('projects.signature.store', $project) }}" class="card card-b max-w-2xl space-y-4">@csrf
        <p class="text-sm text-zinc-600">Der Empfänger bestätigt den Erhalt des Materials. Die Unterschrift erscheint auf dem Lieferschein.</p>
        <x-input name="name" label="Name des Empfängers" :value="$project->customer?->contact" required />
        <div><label class="label">Unterschrift</label><canvas id="sig-canvas" width="640" height="220" class="w-full touch-none rounded-lg border border-zinc-300 bg-white"></canvas>@error('image')<p class="err">{{ $message }}</p>@enderror</div>
        <input type="hidden" name="image" id="sig-image">
        <div class="flex gap-2"><button type="submit" class="btn btn-primary">Speichern</button><button type="button" id="sig-clear" class="btn">Löschen</button></div>
    </form>
</x-layout>
