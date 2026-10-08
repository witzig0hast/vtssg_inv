@props(['title', 'sub' => null])
<div class="mb-5 flex flex-wrap items-start justify-between gap-3 print:hidden">
    <div><h1>{{ $title }}</h1>@if ($sub)<p class="mt-0.5 text-sm text-zinc-500">{{ $sub }}</p>@endif</div>
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
