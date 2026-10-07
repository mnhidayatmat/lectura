@props(['title', 'updated' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Lectura</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="antialiased bg-[#ffffff] text-slate-700">
    <header class="border-b border-slate-200">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <img src="/icons/icon-192x192.png" alt="" class="w-8 h-8 rounded-lg">
                <span class="text-lg font-bold text-slate-900">Lectura</span>
            </a>
            <nav class="flex gap-4 text-sm font-medium">
                <a href="{{ route('support') }}" class="hover:text-slate-900">Support</a>
                <a href="{{ route('privacy') }}" class="hover:text-slate-900">Privacy</a>
            </nav>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 py-10">
        <h1 class="text-3xl font-extrabold text-slate-900">{{ $title }}</h1>
        @if ($updated)
            <p class="mt-2 text-sm text-slate-500">Last updated {{ $updated }}</p>
        @endif
        <div class="mt-8 space-y-4 leading-relaxed [&_h2]:mt-10 [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-slate-900 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:space-y-1 [&_a]:text-indigo-700 [&_a]:underline">
            {{ $slot }}
        </div>
    </main>

    <footer class="border-t border-slate-200 py-8 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} Lectura
    </footer>
</body>
</html>
