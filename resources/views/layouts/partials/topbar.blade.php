<header class="sticky top-0 z-30 h-16 border-b border-slate-200 dark:border-[#354158] bg-white/95 dark:bg-[#242d3d]/95 backdrop-blur-md px-4 sm:px-6 lg:px-8">
    <div class="flex h-full items-center gap-4">
        {{-- Mobile menu button --}}
        <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden -m-2 p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition flex-shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        {{-- Left: User greeting --}}
        <div class="flex items-center gap-3 min-w-0 flex-shrink-0">
            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700 flex-shrink-0">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div class="hidden sm:block">
                <h3 class="text-sm font-semibold text-slate-900 leading-tight">{{ auth()->user()->name }}</h3>
                <p class="text-[11px] text-indigo-500 font-medium leading-tight">{{ ucfirst($userRole ?? 'Lecturer') }}</p>
            </div>
        </div>

        {{-- Role Switcher --}}
        @php
            $allRoles = auth()->user()->rolesInTenant($currentTenant->id);
        @endphp
        @if(count($allRoles) > 1)
        <div x-data="{ open: false }" class="relative flex-shrink-0">
            <button @click="open = !open" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition border"
                :class="open ? 'bg-indigo-50 border-indigo-200 text-indigo-700 dark:bg-indigo-900/30 dark:border-indigo-700 dark:text-indigo-300' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100 dark:bg-[#2a3548] dark:border-[#354158] dark:text-slate-300 dark:hover:bg-[#354158]'"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                {{ ucfirst($userRole ?? 'admin') }}
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-cloak @click.away="open = false" x-transition class="absolute left-0 mt-1 w-44 bg-white dark:bg-[#242d3d] rounded-xl shadow-lg border border-slate-200 dark:border-[#354158] z-50 py-1">
                <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Switch Role</p>
                @foreach($allRoles as $r)
                    @if($r === ($userRole ?? 'admin'))
                        <div class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            {{ ucfirst($r) }}
                        </div>
                    @else
                        <form method="POST" action="{{ route('tenant.switch-role', $currentTenant->slug) }}">
                            @csrf
                            <input type="hidden" name="role" value="{{ $r }}">
                            <button type="submit" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#2a3548] transition">
                                <svg class="w-4 h-4 text-transparent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ ucfirst($r) }}
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>
        @endif

        {{-- Center: Search bar --}}
        <div class="hidden md:block flex-1 max-w-md mx-auto">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" placeholder="Search courses, students..." class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" />
            </div>
        </div>

        {{-- Spacer for mobile --}}
        <div class="flex-1 md:hidden"></div>

        {{-- Right: Actions --}}
        <div class="flex items-center gap-1.5 flex-shrink-0">
            {{-- Language switcher --}}
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="flex items-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-medium text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition">
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
            @php $unreadNotifications = auth()->user()->unreadNotifications()->count(); @endphp
            <a href="{{ route('tenant.notifications.index', $currentTenant->slug) }}" class="relative p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                @if($unreadNotifications > 0)
                    <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-red-500 rounded-full ring-2 ring-white flex items-center justify-center text-[10px] font-bold text-white">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
                @endif
            </a>

            {{-- Dark Mode Toggle --}}
            <button @click="$dispatch('toggle-dark')" class="p-2 rounded-lg text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition" title="Toggle dark mode">
                <svg x-show="!$root.classList.contains('dark')" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                <svg x-show="$root.classList.contains('dark')" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </button>

            {{-- Divider --}}
            <div class="hidden sm:block w-px h-6 bg-slate-200 dark:bg-slate-700"></div>

            {{-- User menu --}}
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="p-1.5 rounded-lg hover:bg-slate-100 transition">
                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
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
    </div>
</header>
