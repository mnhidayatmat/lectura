<header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b bg-white px-4 sm:px-6 lg:px-8">
    {{-- Mobile menu button --}}
    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden -m-2.5 p-2.5 text-gray-700">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>

    <div class="flex flex-1 items-center justify-end gap-4">
        {{-- Language switcher --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="text-sm text-gray-500 hover:text-gray-700">
                {{ app()->getLocale() === 'ms' ? 'BM' : 'EN' }}
            </button>
            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-24 bg-white rounded-md shadow-lg border z-50">
                <a href="?lang=en" class="block px-4 py-2 text-sm hover:bg-gray-50">English</a>
                <a href="?lang=ms" class="block px-4 py-2 text-sm hover:bg-gray-50">Bahasa Melayu</a>
            </div>
        </div>

        {{-- Notifications bell --}}
        <button class="relative text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        </button>

        {{-- User menu --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-2 text-sm">
                <span class="text-gray-700">{{ auth()->user()->name }}</span>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border z-50">
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('nav.profile') }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('nav.logout') }}</button>
                </form>
            </div>
        </div>
    </div>
</header>
