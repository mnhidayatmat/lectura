<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="darkMode()" x-bind:class="{ 'dark': dark }" @toggle-dark.window="toggle()">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Lectura') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#4F46E5">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('styles')

        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; }
            [x-cloak] { display: none !important; }
        </style>
        <script>
            // Prevent FOUC: apply dark class before paint
            if (localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
            function darkMode() {
                return {
                    dark: localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches),
                    toggle() {
                        this.dark = !this.dark;
                        localStorage.setItem('darkMode', this.dark);
                    }
                }
            }
        </script>
    </head>
    <body class="antialiased text-slate-700 dark:text-slate-300 dark:bg-[#1c2333]">
        {{-- Impersonation Banner --}}
        @if(session('impersonator_id'))
            <div class="bg-amber-500 text-white text-center text-sm font-medium py-2 px-4 flex items-center justify-center gap-3 relative z-[60]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Viewing as <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }})
                <form method="POST" action="{{ route('admin.impersonate.stop') }}" class="inline">
                    @csrf
                    <button type="submit" class="ml-2 px-3 py-1 bg-white/20 hover:bg-white/30 rounded-lg text-xs font-semibold transition">
                        Stop Viewing
                    </button>
                </form>
            </div>
        @endif

        <div class="min-h-screen bg-slate-50 dark:bg-[#1c2333]" x-data="{ sidebarOpen: false }">

            @php
                $currentTenant = app('current_tenant');
                $userRole = auth()->user()?->roleInTenant($currentTenant?->id);
                $isStudent = $userRole === 'student';
            @endphp

            @if(! $isStudent)
                @include('layouts.partials.sidebar')

                <div class="lg:pl-72">
                    @include('layouts.partials.topbar')

                    @isset($header)
                        <header class="bg-white dark:bg-[#242d3d] border-b border-slate-200 dark:border-[#354158]">
                            <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset

                    <main class="py-8">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            @if(session('success'))
                                <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 flex items-center gap-3" x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)">
                                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 flex items-center gap-3">
                                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                                </div>
                            @endif

                            {{ $slot }}
                        </div>
                    </main>
                </div>
            @else
                {{-- Student: Sidebar on desktop, bottom nav on mobile --}}
                @include('layouts.partials.student-sidebar')

                <div class="lg:pl-72">
                    @include('layouts.partials.student-topbar')

                    @isset($header)
                        <header class="bg-white dark:bg-[#242d3d] border-b border-slate-200 dark:border-[#354158]">
                            <div class="max-w-5xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset

                    <main class="py-8 pb-24 lg:pb-8">
                        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                            @if(session('success'))
                                <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 flex items-center gap-3" x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)">
                                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 flex items-center gap-3">
                                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                                </div>
                            @endif

                            {{ $slot }}
                        </div>
                    </main>
                </div>

                {{-- Bottom nav: mobile only --}}
                <div class="lg:hidden">
                    @include('layouts.partials.bottom-nav')
                </div>
            @endif
        </div>

        @livewireScriptConfig
        @stack('scripts')
    </body>
</html>
