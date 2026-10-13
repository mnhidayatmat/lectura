<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="darkMode()" x-bind:class="{ 'dark': dark }" @toggle-dark.window="toggle()">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Lectura') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        @include('layouts.partials.pwa-head')

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            [x-cloak] { display: none !important; }
            body { font-family: 'Figtree', sans-serif; }
            .ink-panel { background: radial-gradient(50rem 35rem at 90% -10%, rgba(131, 82, 252, .45), transparent 60%), radial-gradient(35rem 25rem at -10% 110%, rgba(109, 52, 224, .3), transparent 60%), #14112e; }
            .dot-pattern { background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px); background-size: 22px 22px; }
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
            <div class="hidden lg:flex lg:w-1/2 xl:w-[55%] ink-panel relative overflow-hidden">
                <div class="dot-pattern absolute inset-0 pointer-events-none"></div>
                <div class="relative flex flex-col justify-between w-full p-12 xl:p-16">
                    <a href="/" class="flex items-center gap-2.5 self-start">
                        <img src="/icons/icon-192x192.png" alt="Lectura" class="w-9 h-9 rounded-xl">
                        <span class="text-xl font-extrabold text-white tracking-tight">Lectura</span>
                    </a>

                    <div class="flex items-center gap-10 xl:gap-14">
                        <div class="max-w-sm">
                            <h1 class="text-4xl xl:text-5xl font-black text-white leading-[1.05] tracking-tight">
                                Your class,<br><span class="text-violet-300">in your pocket.</span>
                            </h1>
                            <p class="mt-5 text-violet-100/70 leading-relaxed">
                                One account for the web and the Lectura Go app — episodes, check-in, quizzes and marks for students, the whole room for lecturers.
                            </p>
                        </div>
                        <div class="hidden xl:block shrink-0 w-52 rotate-[4deg]" aria-hidden="true">
                            <div class="rounded-[2.2rem] p-2 bg-gradient-to-br from-[#3a3460] to-[#15122c] shadow-2xl shadow-black/50 ring-1 ring-white/10">
                                <img src="/images/landing/student-home.jpg" alt="" class="rounded-[1.8rem] block w-full">
                            </div>
                        </div>
                    </div>

                    @php
                        $guestQuotes = [
                            ['This app is really useful — easy for catching up faster. The video is compact and more detailed than the slides. Saves time!', 'Kabinesh R.'],
                            ['Modern look, smooth UI, user friendly.', 'Abdul Khaleq A.'],
                            ['The app is very good and easy to use. Easy to learn before entering class.', 'Syahir'],
                        ];
                    @endphp
                    <figure class="max-w-lg rounded-3xl bg-white/[.06] ring-1 ring-white/10 p-6 backdrop-blur-sm"
                            x-data="{ i: 0, n: {{ count($guestQuotes) }} }"
                            x-init="if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) setInterval(() => i = (i + 1) % n, 6000)">
                        <div class="grid min-h-[6.5rem]">
                            @foreach ($guestQuotes as $q => [$quote, $name])
                                <div class="[grid-area:1/1]" x-show="i === {{ $q }}" x-transition:enter="transition-opacity duration-700" x-transition:enter-start="opacity-0" x-transition:leave="transition-opacity duration-300" x-transition:leave-end="opacity-0" @if ($q > 0) style="display:none" @endif>
                                    <blockquote class="text-[15px] leading-relaxed text-white">“{{ $quote }}”</blockquote>
                                    <figcaption class="mt-4 text-xs text-violet-100/60"><span class="font-bold text-white">{{ $name }}</span> · Student, BTG3333 Piping</figcaption>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 flex gap-1.5" aria-hidden="true">
                            @foreach ($guestQuotes as $q => $_)
                                <button type="button" @click="i = {{ $q }}" class="h-1.5 rounded-full transition-all" :class="i === {{ $q }} ? 'w-6 bg-violet-300' : 'w-1.5 bg-white/25'" tabindex="-1"></button>
                            @endforeach
                        </div>
                    </figure>
                </div>
            </div>

            {{-- Right Panel — Form --}}
            <div class="w-full lg:w-1/2 flex flex-col">
                {{-- Mobile header --}}
                <div class="lg:hidden flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1c2333]">
                    <a href="/" class="flex items-center gap-2">
                        <img src="/icons/icon-192x192.png" alt="Lectura" class="w-8 h-8 rounded-lg">
                        <span class="text-lg font-bold text-slate-900 dark:text-white">Lectura</span>
                    </a>
                </div>

                {{-- Form container --}}
                <div class="flex-1 flex items-center justify-center px-5 py-8 sm:px-12 sm:py-12 bg-white dark:bg-[#1c2333]">
                    <div class="w-full max-w-md">
                        {{ $slot }}
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 text-center text-xs text-slate-400 dark:text-slate-500 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-[#1c2333]">
                    &copy; {{ date('Y') }} Lectura. Made in Malaysia.
                </div>
            </div>
        </div>

        @livewireScriptConfig
    </body>
</html>
