<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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

        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; }
            [x-cloak] { display: none !important; }
        </style>
    </head>
    <body class="antialiased text-slate-700">
        <div class="min-h-screen bg-slate-50" x-data="{ sidebarOpen: false }">

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
                        <header class="bg-white border-b border-slate-200">
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
                        <header class="bg-white border-b border-slate-200">
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

        @livewireScripts
        @stack('scripts')
    </body>
</html>
