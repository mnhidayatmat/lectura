<header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/95 backdrop-blur-md px-4 sm:px-6 lg:px-8">
    {{-- Mobile menu button --}}
    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden -m-2 p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>

    {{-- Search bar (desktop) --}}
    <div class="hidden md:flex flex-1 max-w-md">
        <div class="relative w-full">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" placeholder="Search courses, students..." class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" />
        </div>
    </div>

    <div class="flex flex-1 md:flex-none items-center justify-end gap-2">
        {{-- Language switcher --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>
                {{ app()->getLocale() === 'ms' ? 'BM' : 'EN' }}
            </button>
            <div x-show="open" x-cloak @click.away="open = false" x-transition class="absolute right-0 mt-1 w-36 bg-white rounded-xl shadow-lg border border-slate-200 z-50 py-1">
                <a href="?lang=en" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50 {{ app()->getLocale() === 'en' ? 'text-indigo-600 font-medium' : 'text-slate-600' }}">
                    English
                    @if(app()->getLocale() === 'en')<svg class="w-4 h-4 ml-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>@endif
                </a>
                <a href="?lang=ms" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50 {{ app()->getLocale() === 'ms' ? 'text-indigo-600 font-medium' : 'text-slate-600' }}">
                    Bahasa Melayu
                    @if(app()->getLocale() === 'ms')<svg class="w-4 h-4 ml-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>@endif
                </a>
            </div>
        </div>

        {{-- Notifications --}}
        <button class="relative p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white"></span>
        </button>

        {{-- Divider --}}
        <div class="hidden sm:block w-px h-6 bg-slate-200"></div>

        {{-- User menu --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-2.5 p-1.5 rounded-lg hover:bg-slate-100 transition">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="hidden sm:block text-left">
                    <p class="text-sm font-medium text-slate-700 leading-none">{{ auth()->user()->name }}</p>
                    <p class="text-[11px] text-slate-400 leading-none mt-0.5">{{ ucfirst($userRole ?? '') }}</p>
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
