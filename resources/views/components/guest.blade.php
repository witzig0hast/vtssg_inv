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
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-900 to-slate-900 p-4">
<div class="w-full max-w-sm">
    <div class="mb-6 text-center text-white"><img src="/icon.svg" alt="" class="mx-auto mb-3 h-14 w-14"><div class="text-xl font-semibold">{{ \App\Models\Setting::get('company_name') }}</div><div class="text-sm text-slate-400">Inventarverwaltung</div></div>
    <div class="card card-b">{{ $slot }}</div>
</div>
</body>
</html>
