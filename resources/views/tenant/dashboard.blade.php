<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('nav.dashboard') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $tenant->name }} — {{ now()->format('l, j F Y') }}</p>
            </div>
            @if($role !== 'student')
                <div class="flex items-center gap-2">
                    <a href="{{ '/' . $tenant->slug }}/attendance" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        Start Attendance
                    </a>
                    <a href="{{ '/' . $tenant->slug }}/quizzes" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Launch Quiz
                    </a>
                </div>
            @endif
        </div>
    </x-slot>

    @if($role !== 'student')
        {{-- Lecturer Dashboard --}}
        <div class="space-y-8">

            {{-- Quick Stats --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl border border-slate-200 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <span class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Active</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900">0</p>
                    <p class="text-sm text-slate-500 mt-0.5">{{ __('nav.courses') }}</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-slate-900">0</p>
                    <p class="text-sm text-slate-500 mt-0.5">Students</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-slate-900">--</p>
                    <p class="text-sm text-slate-500 mt-0.5">Avg. Attendance</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <span class="text-xs font-medium text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Pending</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900">0</p>
                    <p class="text-sm text-slate-500 mt-0.5">To Mark</p>
                </div>
            </div>

            {{-- Main grid --}}
            <div class="grid lg:grid-cols-3 gap-6">

                {{-- Today's Schedule --}}
                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-900">Today's Schedule</h3>
                        <span class="text-xs text-slate-400">{{ now()->format('l') }}</span>
                    </div>
                    <div class="p-6">
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mb-4">
                                <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <p class="text-sm font-medium text-slate-900">No classes scheduled today</p>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs">Create your first course and set up section schedules to see today's classes here.</p>
                            <a href="{{ '/' . $tenant->slug }}/courses" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                Go to Courses
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Quick Actions --}}
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="font-semibold text-slate-900">Quick Actions</h3>
                    </div>
                    <div class="p-4 space-y-2">
                        <a href="{{ '/' . $tenant->slug }}/courses" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition group">
                            <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 transition">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-700">Create Course</p>
                                <p class="text-xs text-slate-400">Set up a new course & sections</p>
                            </div>
                            <svg class="w-4 h-4 text-slate-300 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ '/' . $tenant->slug }}/attendance" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition group">
                            <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center group-hover:bg-emerald-200 transition">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-700">Start Attendance</p>
                                <p class="text-xs text-slate-400">Generate QR for your class</p>
                            </div>
                            <svg class="w-4 h-4 text-slate-300 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ '/' . $tenant->slug }}/quizzes" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition group">
                            <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-700">Launch Live Quiz</p>
                                <p class="text-xs text-slate-400">Engage students in real-time</p>
                            </div>
                            <svg class="w-4 h-4 text-slate-300 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ '/' . $tenant->slug }}/files" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition group">
                            <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center group-hover:bg-violet-200 transition">
                                <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-700">Upload Files</p>
                                <p class="text-xs text-slate-400">Manage course materials</p>
                            </div>
                            <svg class="w-4 h-4 text-slate-300 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            {{-- AI Marking Queue & Course File Completeness --}}
            <div class="grid lg:grid-cols-2 gap-6">
                {{-- AI Marking Queue --}}
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-slate-900">AI Marking Queue</h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">0 pending</span>
                        </div>
                    </div>
                    <div class="p-6 flex flex-col items-center justify-center py-10 text-center">
                        <div class="w-12 h-12 bg-teal-50 rounded-2xl flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        </div>
                        <p class="text-sm text-slate-500">No submissions waiting for AI review</p>
                        <p class="text-xs text-slate-400 mt-1">Submissions will appear here after students submit assignments</p>
                    </div>
                </div>

                {{-- Course File Completeness --}}
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-900">Course File Completeness</h3>
                        <a href="{{ '/' . $tenant->slug }}/files" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">View all</a>
                    </div>
                    <div class="p-6 flex flex-col items-center justify-center py-10 text-center">
                        <div class="w-12 h-12 bg-violet-50 rounded-2xl flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        </div>
                        <p class="text-sm text-slate-500">No courses to track yet</p>
                        <p class="text-xs text-slate-400 mt-1">Create a course to track file completeness against your institution's checklist</p>
                    </div>
                </div>
            </div>

        </div>
    @else
        {{-- Student Dashboard --}}
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h4 class="font-semibold text-slate-900">{{ __('My Courses') }}</h4>
                <p class="mt-2 text-sm text-slate-500">{{ __('No courses enrolled yet.') }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h4 class="font-semibold text-slate-900">{{ __('Upcoming Deadlines') }}</h4>
                <p class="mt-2 text-sm text-slate-500">{{ __('No upcoming deadlines.') }}</p>
            </div>
        </div>
    @endif
</x-tenant-layout>
