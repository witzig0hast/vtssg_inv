<x-layout title="Defekt melden">
    <x-page-head title="Defekt / Wartung melden" />
    <form method="post" action="{{ route('maintenance.store') }}" class="card card-b max-w-2xl space-y-4">@csrf
        <div><label class="label" for="mt-tag">Asset-Tag <span class="text-red-500">*</span></label><div class="flex gap-2"><input id="mt-tag" name="asset_tag" value="{{ old('asset_tag', $asset?->asset_tag) }}" class="input" required><button type="button" class="btn" data-camera-for="mt-tag">📷</button></div>@error('asset_tag')<p class="err">{{ $message }}</p>@enderror</div>
        <x-input name="title" label="Kurzbeschreibung" required />
        <x-textarea name="description" label="Details" rows="4" />
        <x-select name="priority" label="Priorität" :options="\App\Models\MaintenanceTicket::PRIORITIES" value="normal" />
        <x-select name="assigned_to" label="Zuständig" :options="$users->pluck('name', 'id')" empty="— niemand —" />
        <x-checkbox name="blocks_asset" label="Gerät bis zur Behebung sperren (Status „defekt“)" :checked="true" />
        <div class="flex gap-2"><button class="btn btn-primary">Ticket erstellen</button><a href="{{ route('maintenance.index') }}" class="btn">Abbrechen</a></div>
    </form>
</x-layout>
