{{-- Mobile sidebar backdrop --}}
<div x-show="sidebarOpen" x-cloak x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false">
</div>

@php
    $tenant = $currentTenant;
    $prefix = $tenant ? '/' . $tenant->slug : '#';
    $active = fn($pattern) => request()->is($pattern);
@endphp

{{-- Student Sidebar — hidden on mobile (bottom nav used instead), visible on desktop --}}
<div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
     class="fixed inset-y-0 left-0 z-50 w-72 bg-white dark:bg-[#242d3d] border-r border-slate-200 dark:border-[#354158] transition-transform duration-300 ease-in-out lg:translate-x-0 flex flex-col">

    {{-- Logo --}}
    <div class="flex h-16 items-center gap-3 px-6 border-b border-slate-200 flex-shrink-0">
        <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        </div>
        <div class="min-w-0">
            <span class="text-lg font-bold text-slate-900">Lectura</span>
            @if($tenant)
                <p class="text-[11px] text-slate-400 truncate leading-none mt-0.5">{{ $tenant->name }}</p>
            @endif
        </div>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto px-4 py-5 space-y-1.5">

        <a href="{{ $prefix }}/dashboard"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active('*/dashboard') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
            <svg class="w-5 h-5 {{ $active('*/dashboard') ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2zm10-2a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1h-4a1 1 0 01-1-1v-5z"/></svg>
            {{ __('nav.dashboard') }}
        </a>

        <a href="{{ $prefix }}/scan"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active('*/scan') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
            <svg class="w-5 h-5 {{ $active('*/scan') ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
            {{ __('nav.scan') }}
        </a>

        <a href="{{ $prefix }}/live"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active('*/live') || $active('*/session*') ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700' }}">
            <svg class="w-5 h-5 {{ $active('*/live') || $active('*/session*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 group-hover:text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ __('nav.live_session') }}
        </a>

        <div class="pt-5 pb-1 px-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Learning</p>
        </div>

        <a href="{{ $prefix }}/my-courses"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active('*/my-courses*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
            <svg class="w-5 h-5 {{ $active('*/my-courses*') ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            {{ __('nav.courses') }}
        </a>

        <a href="{{ $prefix }}/my-attendance"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active('*/my-attendance*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
            <svg class="w-5 h-5 {{ $active('*/my-attendance*') ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            My Attendance
        </a>

        <a href="{{ $prefix }}/my-materials"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active('*/my-materials*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
            <svg class="w-5 h-5 {{ $active('*/my-materials*') ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            Course Materials
        </a>

        <a href="{{ $prefix }}/marks"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active('*/marks*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
            <svg class="w-5 h-5 {{ $active('*/marks*') ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            {{ __('nav.marks') }} & Feedback
        </a>

        <a href="{{ $prefix }}/my-performance"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active('*/my-performance*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
            <svg class="w-5 h-5 {{ $active('*/my-performance*') ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ __('performance.my_performance') }}
        </a>

        <a href="{{ $prefix }}/workspace"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active('*/workspace*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
            <svg class="w-5 h-5 {{ $active('*/workspace*') ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            My Workspace
        </a>

        <a href="{{ $prefix }}/my-groups"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  {{ $active('*/my-groups*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
            <svg class="w-5 h-5 {{ $active('*/my-groups*') ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            My Groups
        </a>

        <div class="pt-5 pb-1 px-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Assessments</p>
        </div>

        <a href="{{ $prefix }}/my-courses"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all duration-150">
            <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Assignments
        </a>

        <a href="{{ $prefix }}/my-courses"
           class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all duration-150">
            <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Quiz History
        </a>
    </nav>

    {{-- Sidebar footer --}}
    <div class="flex-shrink-0 border-t border-slate-200 p-4">
        <div class="flex items-center gap-3 px-2">
            <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-700">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-900 truncate">{{ auth()->user()->name }}</p>
                <p class="text-xs text-slate-400 truncate">Student</p>
            </div>
        </div>
    </div>
</div>
