<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Lectura') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- PWA -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#1e40af">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-50" x-data="{ sidebarOpen: false }">

            @php
                $currentTenant = app('current_tenant');
                $userRole = auth()->user()?->roleInTenant($currentTenant?->id);
                $isStudent = $userRole === 'student';
            @endphp

            @if(! $isStudent)
                {{-- Lecturer/Admin: Sidebar layout --}}
                @include('layouts.partials.sidebar')

                <div class="lg:pl-64">
                    @include('layouts.partials.topbar')

                    @isset($header)
                        <header class="bg-white shadow-sm border-b">
                            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset

                    <main class="py-6">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            @if(session('success'))
                                <div class="mb-4 rounded-md bg-green-50 p-4" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="mb-4 rounded-md bg-red-50 p-4">
                                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                                </div>
                            @endif

                            {{ $slot }}
                        </div>
                    </main>
                </div>
            @else
                {{-- Student: Bottom nav layout --}}
                @include('layouts.partials.student-topbar')

                <main class="pb-20 pt-4">
                    <div class="max-w-lg mx-auto px-4">
                        @if(session('success'))
                            <div class="mb-4 rounded-md bg-green-50 p-4" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                                <p class="text-sm text-green-700">{{ session('success') }}</p>
                            </div>
                        @endif

                        {{ $slot }}
                    </div>
                </main>

                @include('layouts.partials.bottom-nav')
            @endif
        </div>

        @livewireScripts
        @stack('scripts')
    </body>
</html>
