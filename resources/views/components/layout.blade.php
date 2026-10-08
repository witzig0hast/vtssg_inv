@props(['title' => null, 'wide' => false])
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#ffffff">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <title>{{ $title ? $title.' · ' : '' }}{{ \App\Models\Setting::get('company_name') }} Inventar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50/60">
@php
    $u = auth()->user();
    $link = fn ($route, $label, $icon, $perm = null, $match = null) => ($perm === null || $u->can($perm))
        ? '<a href="'.e(route($route)).'" class="nav-link '.(request()->routeIs(...explode('|', $match ?? $route.'*')) ? 'active' : '').'"><span class="w-5 text-center">'.$icon.'</span>'.e($label).'</a>' : '';
@endphp
<div class="flex min-h-screen">
    <aside id="sidebar" class="fixed inset-y-0 z-30 hidden w-60 shrink-0 flex-col overflow-y-auto border-r border-zinc-200 bg-white px-3 py-5 lg:sticky lg:top-0 lg:flex lg:h-screen print:hidden">
        <a href="{{ route('dashboard') }}" class="mb-5 flex items-center gap-2.5 px-3 text-zinc-950 no-underline hover:text-zinc-950">
            <img src="/icon.svg" alt="" class="h-8 w-8 shrink-0"><span class="min-w-0 break-words text-sm font-semibold leading-tight tracking-tight">{{ \App\Models\Setting::get('company_name') }}<span class="block text-xs font-normal text-zinc-500">Inventar</span></span>
        </a>
        {!! $link('dashboard', 'Übersicht', '⌂', null, 'dashboard') !!}
        {!! $link('scan', 'Scannen', '▣', 'assets.view') !!}

        <div class="nav-h">Equipment</div>
        {!! $link('assets.index', 'Assets', '▤', 'assets.view', 'assets.*') !!}
        {!! $link('models.index', 'Modelle', '◫', 'models.view', 'models.*') !!}
        {!! $link('consumables.index', 'Verbrauchsmaterial', '◔', 'consumables.view', 'consumables.*') !!}
        {!! $link('checkouts.open', 'Offene Ausgaben', '⇄', 'assets.checkout') !!}

        <div class="nav-h">Einsatz</div>
        {!! $link('projects.index', 'Projekte', '◧', 'projects.view', 'projects.index|projects.show|projects.create|projects.edit|projects.pack') !!}
        {!! $link('projects.calendar', 'Kalender', '▦', 'projects.view') !!}
        {!! $link('kits.index', 'Packlisten-Vorlagen', '☰', 'projects.update', 'kits.*') !!}
        @can('customers.view')<a href="{{ route('master.index', 'customers') }}" class="nav-link {{ request()->route('resource') === 'customers' ? 'active' : '' }}"><span class="w-5 text-center">☺</span>Kunden</a>@endcan

        <div class="nav-h">Sicherheit &amp; Wartung</div>
        {!! $link('inspections.index', 'Prüfungen', '✓', 'inspections.view', 'inspections.*') !!}
        {!! $link('maintenance.index', 'Wartung & Defekte', '⚙', 'maintenance.view', 'maintenance.*') !!}
        {!! $link('stocktakes.index', 'Inventur', '☑', 'stocktakes.view', 'stocktakes.*') !!}

        <div class="nav-h">Verwaltung</div>
        @can('masterdata.view')
            @foreach (['categories' => 'Kategorien', 'manufacturers' => 'Hersteller', 'suppliers' => 'Lieferanten', 'locations' => 'Standorte', 'status-labels' => 'Status'] as $k => $l)
                <a href="{{ route('master.index', $k) }}" class="nav-link {{ request()->route('resource') === $k ? 'active' : '' }}"><span class="w-5 text-center">·</span>{{ $l }}</a>
            @endforeach
        @endcan
        @can('inspections.manage')<a href="{{ route('master.index', 'inspection-types') }}" class="nav-link {{ request()->route('resource') === 'inspection-types' ? 'active' : '' }}"><span class="w-5 text-center">·</span>Prüfarten</a>@endcan
        {!! $link('reports', 'Berichte', '◩', 'reports.view') !!}
        {!! $link('users.index', 'Benutzer', '☻', 'users.manage', 'users.*') !!}
        {!! $link('audit.index', 'Audit-Log', '⌕', 'audit.view', 'audit.*') !!}
        {!! $link('settings.edit', 'Einstellungen', '✱', 'settings.manage', 'settings.*') !!}
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-zinc-200 bg-white/90 px-4 py-2.5 backdrop-blur print:hidden">
            <button type="button" data-toggle-nav class="btn btn-sm lg:hidden" aria-label="Menü">☰</button>
            <form action="{{ route('search') }}" method="get" class="flex-1 max-w-xl">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Suchen: Asset-Tag, Seriennummer, Modell, Projekt …" class="input" autocomplete="off" aria-label="Suche">
            </form>
            @can('assets.view')<a href="{{ route('scan') }}" class="btn btn-primary btn-sm">▣ Scan</a>@endcan
            <div class="ml-auto flex items-center gap-3 text-sm">
                <a href="{{ route('profile.edit') }}" class="hidden text-zinc-600 hover:text-zinc-950 sm:inline">{{ $u->name }}</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-sm">Abmelden</button></form>
            </div>
        </header>

        <main class="mx-auto w-full {{ $wide ? 'max-w-none' : 'max-w-7xl' }} flex-1 px-4 py-8 sm:px-8">
            @if (session('status'))<div class="alert mb-4 border-zinc-300 bg-zinc-50 text-zinc-900" role="status">{{ session('status') }}</div>@endif
            @if (session('warning'))<div class="alert mb-4 border-amber-300 bg-amber-50 text-amber-900" role="status">{{ session('warning') }}</div>@endif
            @php $globalErrors = collect($errors->getMessages())->only(['delete', 'checkout', 'checkin', 'status', 'item', 'file', 'role', 'quantity', 'asset_tag', 'import', 'general'])->flatten(); @endphp
            @if ($globalErrors->isNotEmpty())
                <div class="alert mb-4 border-red-200 bg-red-50 text-red-800" role="alert">
                    <ul class="list-disc pl-5">@foreach ($globalErrors as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            @if ($errors->any() && $globalErrors->isEmpty())
                <div class="alert mb-4 border-red-200 bg-red-50 text-red-800" role="alert">Bitte die markierten Felder prüfen.</div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>

<dialog id="scanner-dialog" class="m-auto w-full max-w-md rounded-xl p-0 backdrop:bg-black/60">
    <div class="p-4">
        <div class="mb-2 flex items-center justify-between"><h3>Code scannen</h3><button type="button" id="scanner-close" class="btn btn-sm">Schließen</button></div>
        <div id="scanner-view" class="overflow-hidden rounded-lg bg-black"></div>
        <p id="scanner-error" class="err"></p>
    </div>
</dialog>
</body>
</html>
