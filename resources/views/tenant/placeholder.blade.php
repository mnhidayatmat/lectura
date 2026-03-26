@php
    $icons = [
        'nav.attendance' => ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'color' => 'emerald'],
        'nav.quizzes' => ['icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'amber'],
        'nav.assignments' => ['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'color' => 'red'],
        'nav.course_files' => ['icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z', 'color' => 'violet'],
        'nav.settings' => ['icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', 'color' => 'slate'],
        'nav.scan' => ['icon' => 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z', 'color' => 'teal'],
    ];

    $key = collect($icons)->keys()->first(fn($k) => __($k) === $title) ?? 'nav.settings';
    $meta = $icons[$key] ?? $icons['nav.settings'];
    $color = $meta['color'];
    $iconPath = $meta['icon'];
@endphp

<x-tenant-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    </x-slot>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="p-12 flex flex-col items-center justify-center text-center">
            <div class="w-20 h-20 bg-{{ $color }}-50 rounded-2xl flex items-center justify-center mb-6 relative">
                <svg class="w-10 h-10 text-{{ $color }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"/></svg>
                <div class="absolute -top-1 -right-1 w-6 h-6 bg-indigo-600 rounded-full flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                </div>
            </div>

            <h3 class="text-xl font-bold text-slate-900 mb-2">{{ $title }}</h3>
            <p class="text-sm text-slate-500 max-w-md leading-relaxed mb-8">{{ $description }}</p>

            <div class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 rounded-xl text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Coming in the next update
            </div>

            <div class="mt-8 w-full max-w-sm">
                <div class="relative">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-slate-500">Development Progress</span>
                        <span class="text-xs font-medium text-indigo-600">Building</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-gradient-to-r from-indigo-500 to-teal-500 h-2 rounded-full animate-pulse" style="width: 30%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-tenant-layout>
