<x-tenant-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">
            {{ __('nav.dashboard') }}
        </h2>
    </x-slot>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Welcome Card --}}
        <div class="col-span-full bg-white rounded-lg shadow-sm border p-6">
            <h3 class="text-lg font-medium text-gray-900">
                {{ __('Welcome') }}, {{ auth()->user()->name }}
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                {{ $tenant->name }} &mdash;
                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                    {{ ucfirst($role) }}
                </span>
            </p>
        </div>

        @if($role !== 'student')
            {{-- Lecturer/Admin Cards --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">0</p>
                        <p class="text-sm text-gray-500">{{ __('nav.courses') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">--</p>
                        <p class="text-sm text-gray-500">{{ __('nav.attendance') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">0</p>
                        <p class="text-sm text-gray-500">{{ __('Pending Marking') }}</p>
                    </div>
                </div>
            </div>
        @else
            {{-- Student Cards --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h4 class="font-medium text-gray-900">{{ __('My Courses') }}</h4>
                <p class="mt-2 text-sm text-gray-500">{{ __('No courses enrolled yet.') }}</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h4 class="font-medium text-gray-900">{{ __('Upcoming Deadlines') }}</h4>
                <p class="mt-2 text-sm text-gray-500">{{ __('No upcoming deadlines.') }}</p>
            </div>
        @endif
    </div>
</x-tenant-layout>
