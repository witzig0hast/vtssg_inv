<x-guest title="Anmelden">
    <form method="post" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-input name="email" type="email" label="E-Mail" required autofocus autocomplete="username" />
        <x-input name="password" type="password" label="Passwort" required autocomplete="current-password" />
        <button class="btn btn-primary w-full">Anmelden</button>
    </form>
</x-guest>
