<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <title>{{ $title ?? 'Anmelden' }} · {{ \App\Models\Setting::get('company_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-zinc-50 p-4">
<div class="w-full max-w-sm">
    <div class="mb-8 text-center"><img src="/icon.svg" alt="" class="mx-auto mb-4 h-12 w-12"><div class="text-xl font-semibold tracking-tight text-zinc-950">{{ \App\Models\Setting::get('company_name') }}</div><div class="text-sm text-zinc-500">Inventarverwaltung</div></div>
    <div class="card card-b shadow-sm">{{ $slot }}</div>
</div>
</body>
</html>
