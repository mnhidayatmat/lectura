<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="darkMode()" x-bind:class="{ 'dark': dark }" @toggle-dark.window="toggle()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin — Lectura' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
    <script>
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
<body class="antialiased text-slate-700 dark:text-slate-300 dark:bg-[#1c2333]" x-data="{ sidebarOpen: false }">
    <div class="min-h-screen bg-slate-100 dark:bg-[#1c2333]">

        {{-- Mobile backdrop --}}
        <div x-show="sidebarOpen" x-cloak x-transition:enter="transition-opacity duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

        {{-- Admin sidebar --}}
        <div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-950 transition-transform duration-300 lg:translate-x-0 flex flex-col">

            {{-- Logo --}}
            <div class="flex h-16 items-center gap-3 px-6 border-b border-slate-800 flex-shrink-0">
                <div class="w-8 h-8 rounded-lg bg-red-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <span class="text-lg font-bold text-white">Lectura</span>
                    <p class="text-[10px] text-red-400 font-semibold uppercase tracking-widest leading-none">Super Admin</p>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-5 space-y-1.5">
                @php $active = fn($p) => request()->is($p); @endphp

                <a href="{{ route('admin.dashboard') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ $active('admin') && !$active('admin/*') ? 'bg-red-600 text-white shadow-lg shadow-red-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2zm10-2a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1h-4a1 1 0 01-1-1v-5z"/></svg>
                    Dashboard
                </a>

                <a href="{{ route('admin.tenants') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ $active('admin/tenants*') ? 'bg-red-600 text-white shadow-lg shadow-red-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Institutions
                </a>

                <a href="{{ route('admin.users') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ $active('admin/users*') ? 'bg-red-600 text-white shadow-lg shadow-red-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    All Users
                </a>

                <div class="pt-5 pb-1 px-3">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-600">System</p>
                </div>

                <a href="{{ route('admin.ai-settings') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ $active('admin/ai-settings*') ? 'bg-red-600 text-white shadow-lg shadow-red-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    AI Providers
                </a>

                <a href="{{ route('admin.ai-usage') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ $active('admin/ai-usage*') ? 'bg-red-600 text-white shadow-lg shadow-red-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    AI Usage
                </a>

                <a href="{{ route('admin.activity') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ $active('admin/activity*') ? 'bg-red-600 text-white shadow-lg shadow-red-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Activity Log
                </a>
            </nav>

            <div class="flex-shrink-0 border-t border-slate-800 p-4">
                <div class="flex items-center gap-3 px-2">
                    <div class="w-9 h-9 rounded-full bg-red-600/20 flex items-center justify-center text-sm font-bold text-red-400">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-200 truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-red-400">Super Admin</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="p-1.5 rounded-lg text-slate-500 hover:text-white hover:bg-slate-800 transition" title="Log Out">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Main content --}}
        <div class="lg:pl-72">
            {{-- Topbar --}}
            <header class="sticky top-0 z-30 h-16 border-b border-slate-200 dark:border-[#354158] bg-white/95 dark:bg-[#242d3d]/95 backdrop-blur-md px-4 sm:px-6 lg:px-8">
              <div class="flex h-full items-center gap-4">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden -m-2 p-2 rounded-lg text-slate-500 hover:bg-slate-100 transition flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                {{-- Left: User greeting --}}
                <div class="flex items-center gap-3 min-w-0 flex-shrink-0">
                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-xs font-bold text-red-700 flex-shrink-0">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="hidden sm:block">
                        <h3 class="text-sm font-semibold text-slate-900 leading-tight">{{ auth()->user()->name }}</h3>
                        <p class="text-[11px] text-red-500 font-medium leading-tight">Platform Admin</p>
                    </div>
                </div>

                <div class="flex-1"></div>

                {{-- Right: Actions --}}
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    {{-- Search --}}
                    <div class="hidden sm:block relative" x-data="{ open: false }">
                        <button @click="open = !open" class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </button>
                        <div x-show="open" x-cloak @click.away="open = false" x-transition
                            class="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl border border-slate-200 shadow-xl p-2">
                            <input type="text" placeholder="Search users, institutions..." autofocus
                                class="w-full px-3 py-2 text-sm border-0 bg-slate-50 rounded-lg focus:ring-2 focus:ring-red-500 placeholder-slate-400">
                        </div>
                    </div>

                    {{-- Notifications --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="relative p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </button>
                        <div x-show="open" x-cloak @click.away="open = false" x-transition
                            class="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl border border-slate-200 shadow-xl overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-100">
                                <p class="text-sm font-semibold text-slate-900">Notifications</p>
                            </div>
                            <div class="p-6 text-center">
                                <p class="text-sm text-slate-400">No new notifications</p>
                            </div>
                        </div>
                    </div>

                    {{-- Dark Mode Toggle --}}
                    <button @click="$dispatch('toggle-dark')" class="p-2 rounded-lg text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition" title="Toggle dark mode">
                        <svg x-show="!$root.closest('html').classList.contains('dark')" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                        <svg x-show="document.documentElement.classList.contains('dark')" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </button>

                    {{-- Divider --}}
                    <div class="hidden sm:block w-px h-6 bg-slate-200 dark:bg-slate-700"></div>

                    {{-- View As Switcher --}}
                    @php
                        $switchableUsers = \App\Models\User::where('is_super_admin', false)
                            ->whereHas('tenantUsers', fn($q) => $q->where('is_active', true))
                            ->with('tenantUsers.tenant')
                            ->orderBy('name')
                            ->get();
                    @endphp
                    @if($switchableUsers->isNotEmpty())
                        <div class="relative" x-data="{ open: false, search: '' }">
                            <button @click="open = !open" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                View As
                                <svg class="w-3 h-3 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-cloak @click.away="open = false" x-transition
                                class="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl border border-slate-200 shadow-xl overflow-hidden">
                                <div class="px-3 py-2 border-b border-slate-100">
                                    <input type="text" x-model="search" placeholder="Search users..." autofocus
                                        class="w-full px-3 py-1.5 text-sm border-0 bg-slate-50 rounded-lg focus:ring-2 focus:ring-indigo-500 placeholder-slate-400">
                                </div>
                                <div class="max-h-72 overflow-y-auto">
                                    @foreach($switchableUsers as $su)
                                        <form method="POST" action="{{ route('admin.users.impersonate', $su) }}"
                                            x-show="!search || '{{ strtolower($su->name . ' ' . $su->email) }}'.includes(search.toLowerCase())"
                                            class="block">
                                            @csrf
                                            <button type="submit" class="w-full px-4 py-2.5 flex items-center gap-3 hover:bg-slate-50 transition text-left">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0
                                                    {{ $su->tenantUsers->first()?->role === 'lecturer' ? 'bg-indigo-100 text-indigo-700' :
                                                       ($su->tenantUsers->first()?->role === 'student' ? 'bg-teal-100 text-teal-700' :
                                                       ($su->tenantUsers->first()?->role === 'admin' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')) }}">
                                                    {{ strtoupper(substr($su->name, 0, 2)) }}
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-medium text-slate-900 truncate">{{ $su->name }}</p>
                                                    <p class="text-[11px] text-slate-400 truncate">{{ $su->email }}</p>
                                                </div>
                                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full flex-shrink-0
                                                    {{ $su->tenantUsers->first()?->role === 'lecturer' ? 'bg-indigo-50 text-indigo-600' :
                                                       ($su->tenantUsers->first()?->role === 'student' ? 'bg-teal-50 text-teal-600' :
                                                       ($su->tenantUsers->first()?->role === 'admin' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600')) }}">
                                                    {{ ucfirst($su->tenantUsers->first()?->role ?? 'user') }}
                                                </span>
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Profile --}}
                    <a href="{{ route('profile.edit') }}" class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition" title="Profile Settings">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </a>
                </div>
              </div>
            </header>

            @isset($header)
                <header class="bg-white dark:bg-[#242d3d] border-b border-slate-200 dark:border-[#354158]">
                    <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
                </header>
            @endisset

            <main class="py-8">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">{{ $slot }}</div>
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
