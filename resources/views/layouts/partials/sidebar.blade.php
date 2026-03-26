{{-- Mobile sidebar backdrop --}}
<div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-40 bg-gray-600/75 lg:hidden" @click="sidebarOpen = false">
</div>

{{-- Sidebar --}}
<div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
     class="fixed inset-y-0 left-0 z-50 w-64 bg-blue-900 text-white transition-transform duration-300 lg:translate-x-0">

    {{-- Logo --}}
    <div class="flex h-16 items-center gap-3 px-6 border-b border-blue-800">
        <span class="text-xl font-bold">Lectura</span>
        @if($currentTenant)
            <span class="text-xs text-blue-300 truncate">{{ $currentTenant->name }}</span>
        @endif
    </div>

    {{-- Navigation --}}
    <nav class="mt-4 px-3 space-y-1">
        @php
            $tenant = $currentTenant;
            $prefix = $tenant ? '/' . $tenant->slug : '#';
        @endphp

        <a href="{{ $prefix }}/dashboard" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm hover:bg-blue-800 {{ request()->is('*/dashboard') ? 'bg-blue-800' : '' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            {{ __('nav.dashboard') }}
        </a>

        <a href="{{ $prefix }}/courses" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm hover:bg-blue-800 {{ request()->is('*/courses*') ? 'bg-blue-800' : '' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            {{ __('nav.courses') }}
        </a>

        <div class="pt-4 pb-2 px-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-blue-400">{{ __('nav.teaching') }}</p>
        </div>

        <a href="{{ $prefix }}/attendance" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm hover:bg-blue-800">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            {{ __('nav.attendance') }}
        </a>

        <a href="{{ $prefix }}/quizzes" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm hover:bg-blue-800">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ __('nav.quizzes') }}
        </a>

        <a href="{{ $prefix }}/assignments" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm hover:bg-blue-800">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            {{ __('nav.assignments') }}
        </a>

        <div class="pt-4 pb-2 px-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-blue-400">{{ __('nav.management') }}</p>
        </div>

        <a href="{{ $prefix }}/files" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm hover:bg-blue-800">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            {{ __('nav.course_files') }}
        </a>

        @if(in_array($userRole, ['admin', 'coordinator']))
            <div class="pt-4 pb-2 px-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-blue-400">{{ __('nav.admin') }}</p>
            </div>

            <a href="{{ $prefix }}/admin/settings" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm hover:bg-blue-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                {{ __('nav.settings') }}
            </a>
        @endif
    </nav>
</div>
