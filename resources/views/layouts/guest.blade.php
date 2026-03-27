<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="darkMode()" x-bind:class="{ 'dark': dark }" @toggle-dark.window="toggle()">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Lectura') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; }
            .gradient-brand { background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 50%, #0F172A 100%); }
            .dot-pattern { background-image: radial-gradient(circle, rgba(255,255,255,0.04) 1px, transparent 1px); background-size: 20px 20px; }
        </style>
        <script>
            if (localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
            function darkMode() {
                return {
                    dark: localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches),
                    toggle() { this.dark = !this.dark; localStorage.setItem('darkMode', this.dark); }
                }
            }
        </script>
    </head>
    <body class="antialiased dark:bg-[#1c2333]">
        <div class="min-h-screen flex">

            {{-- Left Panel — Branding (hidden on mobile) --}}
            <div class="hidden lg:flex lg:w-1/2 gradient-brand dot-pattern relative overflow-hidden">
                <div class="absolute inset-0 flex flex-col justify-between p-12">
                    {{-- Logo --}}
                    <a href="/" class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-lg bg-indigo-600 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <span class="text-xl font-bold text-white">Lectura</span>
                    </a>

                    {{-- Center content --}}
                    <div class="max-w-md">
                        <h1 class="text-3xl xl:text-4xl font-bold text-white leading-tight">
                            Teach Smarter.
                            <span class="block mt-1 bg-gradient-to-r from-indigo-400 to-teal-400 bg-clip-text text-transparent">From Plan to Archive.</span>
                        </h1>
                        <p class="mt-5 text-slate-400 leading-relaxed">
                            The AI-powered teaching companion for university lecturers. Plan courses, track attendance, run quizzes, mark assessments, and manage everything in one platform.
                        </p>

                        {{-- Feature highlights --}}
                        <div class="mt-8 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-indigo-500/20 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                </div>
                                <span class="text-sm text-slate-300">AI-generated teaching plans and feedback</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-teal-500/20 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                </div>
                                <span class="text-sm text-slate-300">QR attendance with anti-fraud rotation</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <span class="text-sm text-slate-300">Live quizzes with real-time analytics</span>
                            </div>
                        </div>
                    </div>

                    {{-- Bottom testimonial --}}
                    <div class="bg-white/5 rounded-xl p-5 backdrop-blur-sm border border-white/10">
                        <p class="text-sm text-slate-300 italic leading-relaxed">"Lectura saved me 5 hours a week on attendance and marking alone. The AI feedback is surprisingly good — my students love the personalised comments."</p>
                        <div class="mt-3 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-indigo-500/30 flex items-center justify-center text-xs font-bold text-indigo-300">DS</div>
                            <div>
                                <p class="text-xs font-medium text-white">Dr. Siti Aminah</p>
                                <p class="text-xs text-slate-500">Senior Lecturer, Computer Science</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Decorative orbs --}}
                <div class="absolute -top-20 -right-20 w-64 h-64 bg-indigo-600/10 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-teal-600/10 rounded-full blur-3xl"></div>
            </div>

            {{-- Right Panel — Form --}}
            <div class="w-full lg:w-1/2 flex flex-col">
                {{-- Mobile header --}}
                <div class="lg:hidden flex items-center justify-between px-6 py-4 border-b bg-white">
                    <a href="/" class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <span class="text-lg font-bold text-slate-900">Lectura</span>
                    </a>
                </div>

                {{-- Form container --}}
                <div class="flex-1 flex items-center justify-center px-6 py-12 sm:px-12 bg-white">
                    <div class="w-full max-w-md">
                        {{ $slot }}
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 text-center text-xs text-slate-400 border-t bg-white">
                    &copy; {{ date('Y') }} Lectura. Made in Malaysia.
                </div>
            </div>
        </div>
    </body>
</html>
