@php
    $tenant = app('current_tenant');
    $prefix = $tenant ? '/' . $tenant->slug : '#';
    $active = fn($pattern) => request()->is($pattern);
@endphp

<nav class="fixed bottom-0 inset-x-0 z-40 bg-white/95 dark:bg-[#242d3d]/95 backdrop-blur-md border-t border-slate-200 dark:border-[#354158] safe-area-bottom">
    <div class="flex items-center justify-around h-16 max-w-lg mx-auto px-2">

        <a href="{{ $prefix }}/dashboard" class="flex flex-col items-center justify-center gap-0.5 w-16 py-1 rounded-xl transition {{ $active('*/dashboard') ? 'text-indigo-600' : 'text-slate-400 active:text-slate-600' }}">
            @if($active('*/dashboard'))
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2zm10-2a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1h-4a1 1 0 01-1-1v-5z"/></svg>
            @else
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2zm10-2a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1h-4a1 1 0 01-1-1v-5z"/></svg>
            @endif
            <span class="text-[10px] font-medium">{{ __('nav.home') }}</span>
        </a>

        <a href="{{ $prefix }}/scan" class="flex flex-col items-center justify-center gap-0.5 w-16 py-1 rounded-xl transition {{ $active('*/scan') ? 'text-indigo-600' : 'text-slate-400 active:text-slate-600' }}">
            <div class="w-11 h-11 -mt-5 rounded-2xl {{ $active('*/scan') ? 'bg-indigo-600 shadow-lg shadow-indigo-600/30' : 'bg-slate-900 shadow-lg shadow-slate-900/30' }} flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
            </div>
            <span class="text-[10px] font-medium mt-0.5">{{ __('nav.scan') }}</span>
        </a>

        <a href="{{ $prefix }}/my-courses" class="flex flex-col items-center justify-center gap-0.5 w-16 py-1 rounded-xl transition {{ $active('*/my-courses*') ? 'text-indigo-600' : 'text-slate-400 active:text-slate-600' }}">
            @if($active('*/my-courses*'))
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            @else
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            @endif
            <span class="text-[10px] font-medium">{{ __('nav.courses') }}</span>
        </a>

        <a href="{{ $prefix }}/marks" class="flex flex-col items-center justify-center gap-0.5 w-16 py-1 rounded-xl transition {{ $active('*/marks*') ? 'text-indigo-600' : 'text-slate-400 active:text-slate-600' }}">
            @if($active('*/marks*'))
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            @else
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            @endif
            <span class="text-[10px] font-medium">{{ __('nav.marks') }}</span>
        </a>

    </div>
</nav>
