<header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b bg-white px-4">
    <span class="text-lg font-bold text-blue-900">Lectura</span>

    <div class="flex items-center gap-3">
        {{-- Notifications --}}
        <button class="relative text-gray-500 hover:text-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        </button>

        {{-- User avatar --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-medium text-blue-700">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
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
