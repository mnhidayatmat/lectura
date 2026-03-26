@php
    $tenant = app('current_tenant');
@endphp

<header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/95 backdrop-blur-md px-4 sm:px-6 lg:px-8">
    {{-- Mobile menu button (hidden on desktop where sidebar is always visible) --}}
    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden -m-2 p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>

    {{-- Logo (mobile only — desktop has sidebar logo) --}}
    <a href="{{ $tenant ? '/' . $tenant->slug . '/dashboard' : '/' }}" class="lg:hidden flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center">
            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        </div>
        <span class="text-base font-bold text-slate-900">Lectura</span>
    </a>

    {{-- Search (desktop) --}}
    <div class="hidden md:flex flex-1 max-w-sm">
        <div class="relative w-full">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" placeholder="Search courses, assignments..." class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" />
        </div>
    </div>

    <div class="flex flex-1 md:flex-none items-center justify-end gap-2">
        {{-- Notifications --}}
        <button class="relative p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white"></span>
        </button>

        <div class="hidden sm:block w-px h-6 bg-slate-200"></div>

        {{-- User menu --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-2.5 p-1.5 rounded-lg hover:bg-slate-100 transition">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="hidden sm:block text-left">
                    <p class="text-sm font-medium text-slate-700 leading-none">{{ auth()->user()->name }}</p>
                    <p class="text-[11px] text-slate-400 leading-none mt-0.5">Student</p>
                </div>
                <svg class="hidden sm:block w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-cloak @click.away="open = false" x-transition class="absolute right-0 mt-1 w-56 bg-white rounded-xl shadow-lg border border-slate-200 z-50 py-1">
                <div class="px-4 py-3 border-b border-slate-100">
                    <p class="text-sm font-medium text-slate-900">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
                </div>
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ __('nav.profile') }}
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        {{ __('nav.logout') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
