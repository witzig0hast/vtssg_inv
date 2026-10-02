<x-guest title="Zwei-Faktor">
    <form method="post" action="{{ route('two-factor.verify') }}" class="space-y-4">
        @csrf
        <p class="text-sm text-slate-600">Gib den 6-stelligen Code deiner Authenticator-App ein.</p>
        <x-input name="code" label="Code" inputmode="numeric" autocomplete="one-time-code" autofocus />
        <button class="btn btn-primary w-full">Bestätigen</button>
    </form>
    <details class="mt-4 text-sm"><summary class="cursor-pointer text-slate-500">Recovery-Code verwenden</summary>
        <form method="post" action="{{ route('two-factor.verify') }}" class="mt-3 space-y-3">@csrf
            <x-input name="recovery_code" label="Recovery-Code" />
            <button class="btn w-full">Mit Recovery-Code anmelden</button>
        </form>
    </details>
    <form method="post" action="{{ route('logout') }}" class="mt-4 text-center">@csrf<button class="text-xs text-slate-500 underline">Abbrechen</button></form>
</x-guest>
