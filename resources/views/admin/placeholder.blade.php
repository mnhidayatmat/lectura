<x-admin-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    </x-slot>

    <div class="bg-white rounded-2xl border border-slate-200 p-12 flex flex-col items-center text-center">
        <div class="w-20 h-20 bg-slate-100 rounded-2xl flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        </div>
        <h3 class="text-xl font-bold text-slate-900 mb-2">{{ $title }}</h3>
        <p class="text-sm text-slate-500 max-w-md">{{ $description }}</p>
        <div class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-red-50 text-red-700 rounded-xl text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Coming in the next update
        </div>
    </div>
</x-admin-layout>
