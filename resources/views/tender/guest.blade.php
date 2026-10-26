<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('tender.score_card') }} · {{ $session->label() }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body { font-family: 'Figtree', sans-serif; }</style>
</head>
<body class="antialiased bg-slate-50">
    <main class="max-w-3xl mx-auto px-4 py-8">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $game->course->code }} · {{ $game->title }}</p>
        <h1 class="text-2xl font-bold text-slate-900">{{ $session->label() }} — {{ __('tender.score_card') }}</h1>
        <p class="mt-1 text-sm text-slate-500 mb-6">{{ __('tender.guest_intro', ['name' => $judge->displayName()]) }}</p>

        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @include('tenant.tender._card-form')
    </main>
</body>
</html>
