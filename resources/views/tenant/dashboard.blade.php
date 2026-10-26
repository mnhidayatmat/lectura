<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            @if($role === 'student')
                @php $hour = (int) now()->timezone($tenant->timezone ?: config('app.timezone'))->format('G'); @endphp
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ now()->timezone($tenant->timezone ?: config('app.timezone'))->format('l, j F Y') }}</p>
                    <h2 class="mt-1 text-2xl font-bold text-slate-900">{{ $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening') }}, {{ explode(' ', auth()->user()->name)[0] }}</h2>
                    <p class="mt-1 text-sm text-slate-500 truncate">Welcome back · {{ $tenant->name }}</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ route('tenant.live-hub', $tenant->slug) }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-medium rounded-xl transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Live session
                    </a>
                    <a href="{{ route('tenant.scan', $tenant->slug) }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        Scan QR
                    </a>
                </div>
            @else
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('nav.dashboard') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $tenant->name }} — {{ now()->format('l, j F Y') }}</p>
            </div>
            @endif
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
                    <p class="text-2xl font-bold text-slate-900">{{ $courseCount }}</p>
                    <p class="text-sm text-slate-500 mt-0.5">{{ __('nav.courses') }}</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-slate-900">{{ $studentCount }}</p>
                    <p class="text-sm text-slate-500 mt-0.5">Students</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-slate-900">{{ $avgAttendance !== null ? $avgAttendance . '%' : '--' }}</p>
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
                    @if($todaySchedule->isEmpty())
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
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach($todaySchedule as $slot)
                                @php
                                    $now = now()->format('H:i');
                                    $isNow = $now >= $slot->start_time && $now < $slot->end_time;
                                    $isPast = $now >= $slot->end_time;
                                    $typeColors = [
                                        'lecture' => 'bg-indigo-100 text-indigo-700',
                                        'tutorial' => 'bg-teal-100 text-teal-700',
                                        'lab' => 'bg-amber-100 text-amber-700',
                                        'other' => 'bg-slate-100 text-slate-600',
                                    ];
                                @endphp
                                <div class="flex items-center gap-4 px-6 py-4 {{ $isPast ? 'opacity-50' : '' }}">
                                    {{-- Time --}}
                                    <div class="flex flex-col items-center flex-shrink-0 w-16 text-center">
                                        <span class="text-sm font-semibold {{ $isNow ? 'text-indigo-600' : 'text-slate-900' }}">{{ $slot->start_time }}</span>
                                        <span class="text-[10px] text-slate-400">{{ $slot->end_time }}</span>
                                    </div>

                                    {{-- Indicator --}}
                                    <div class="flex flex-col items-center flex-shrink-0">
                                        <div class="w-3 h-3 rounded-full border-2 {{ $isNow ? 'border-indigo-500 bg-indigo-500 animate-pulse' : ($isPast ? 'border-slate-300 bg-slate-300' : 'border-indigo-400 bg-white') }}"></div>
                                    </div>

                                    {{-- Details --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $slot->course_code }}</p>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium {{ $typeColors[$slot->type] ?? $typeColors['other'] }}">{{ ucfirst($slot->type) }}</span>
                                            @if($isNow)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-700">Now</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-500 truncate mt-0.5">{{ $slot->course_title }} &middot; {{ $slot->section_name }}</p>
                                        @if($slot->location)
                                            <p class="text-xs text-slate-400 mt-0.5 flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                {{ $slot->location }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
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
        @php
            $tz = $tenant->timezone ?: config('app.timezone');
            $dueTones = [
                'urgent' => ['dot' => 'bg-red-500', 'text' => 'text-red-600 dark:text-red-400', 'badge' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30'],
                'soon' => ['dot' => 'bg-amber-500', 'text' => 'text-amber-600 dark:text-amber-400', 'badge' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'],
                'later' => ['dot' => 'bg-blue-500', 'text' => 'text-slate-500', 'badge' => 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10'],
                'submitted' => ['dot' => 'bg-emerald-500', 'text' => 'text-slate-500', 'badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'],
            ];
            $markTone = fn (?float $pct) => match (true) {
                $pct === null => 'text-slate-900',
                $pct >= 80 => 'text-emerald-600 dark:text-emerald-400',
                $pct >= 50 => 'text-slate-900',
                default => 'text-red-600 dark:text-red-400',
            };
            $latestMark = $recentMarks->first();
        @endphp

        <div class="space-y-6">

            {{-- Attendance open right now --}}
            @foreach($openCheckIns as $session)
                <a href="{{ route('tenant.scan', $tenant->slug) }}"
                   class="flex items-center gap-4 rounded-2xl bg-emerald-600 hover:bg-emerald-700 px-5 py-4 text-white shadow-sm transition">
                    <span class="relative flex h-3 w-3 flex-shrink-0">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#fff] opacity-60"></span>
                        <span class="relative inline-flex h-3 w-3 rounded-full bg-[#fff]"></span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold">Attendance is open · {{ $session->section?->course?->code }}</span>
                        <span class="block text-xs text-emerald-100 truncate">
                            {{ $session->section?->name }}@if($session->week_number) · Week {{ $session->week_number }}@endif · started {{ $session->started_at?->diffForHumans() }}
                        </span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-[#fff] px-3 py-2 text-xs font-semibold text-emerald-700 flex-shrink-0">Check in</span>
                </a>
            @endforeach

            {{-- At a glance --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5">
                    <p class="text-xs font-medium text-slate-500">Enrolled courses</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900 tabular-nums">{{ $enrolledCourses->count() }}</p>
                    <p class="mt-1 text-xs text-slate-400">This semester</p>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5">
                    <p class="text-xs font-medium text-slate-500">To submit</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums {{ $pendingCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900' }}">{{ $pendingCount }}</p>
                    <p class="mt-1 text-xs text-slate-400 truncate">
                        @php $next = $dueAssessments->firstWhere('submitted', false); @endphp
                        {{ $next ? 'Next due '.$next['assessment']->due_date->diffForHumans(['parts' => 1]) : 'Nothing pending' }}
                    </p>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5">
                    <p class="text-xs font-medium text-slate-500">Attendance</p>
                    @php $attendanceAlert = $attendanceWarning || $lowAttendanceCourses->isNotEmpty(); @endphp
                    <p class="mt-2 text-2xl font-bold tabular-nums {{ $attendanceAlert ? 'text-red-600 dark:text-red-400' : 'text-slate-900' }}">{{ $attendanceRate !== null ? $attendanceRate.'%' : '—' }}</p>
                    <p class="mt-1 text-xs truncate {{ $attendanceAlert ? 'text-red-600 dark:text-red-400 font-medium' : 'text-slate-400' }}">
                        @if($lowAttendanceCourses->isNotEmpty())
                            Low in {{ $lowAttendanceCourses->pluck('code')->join(', ') }}
                        @elseif($attendanceWarning)
                            Warning issued
                        @else
                            {{ $attendanceRate !== null ? 'Across all courses' : 'No classes yet' }}
                        @endif
                    </p>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5">
                    <p class="text-xs font-medium text-slate-500">Latest mark</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums {{ $markTone($latestMark['percentage'] ?? null) }}">
                        {{ $latestMark && $latestMark['percentage'] !== null ? round($latestMark['percentage']).'%' : '—' }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400 truncate">{{ $latestMark ? ($latestMark['course']?->code.' · '.$latestMark['title']) : 'None released yet' }}</p>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6 items-start">

                {{-- Main column --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Assessments due: submitted green, within a week amber, within 48 hours red --}}
                    @if($dueAssessments->isNotEmpty())
                        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-slate-900">Assessments due</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $pendingCount > 0 ? $pendingCount.' to submit' : 'All submitted' }}</p>
                                </div>
                                <a href="{{ route('tenant.my-assessments', $tenant->slug) }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium flex-shrink-0">View all</a>
                            </div>
                            <ul class="divide-y divide-slate-100">
                                @foreach($dueAssessments as $item)
                                    @php
                                        $a = $item['assessment'];
                                        $t = $dueTones[$item['tone']];
                                        $due = $a->due_date->copy()->timezone($tz);
                                    @endphp
                                    <li>
                                        <a href="{{ route('tenant.my-assessments.show', [$tenant->slug, $a->course, $a]) }}"
                                           class="flex items-center gap-4 px-5 py-3.5 hover:bg-slate-50 transition">
                                            <div class="w-12 flex-shrink-0 rounded-xl border border-slate-200 text-center py-1.5">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 leading-none">{{ $due->format('M') }}</p>
                                                <p class="text-lg font-bold text-slate-900 leading-tight tabular-nums">{{ $due->format('j') }}</p>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $a->title }}</p>
                                                <p class="text-xs mt-0.5 flex items-center gap-1.5 {{ $t['text'] }}">
                                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $t['dot'] }}"></span>
                                                    <span class="truncate">
                                                        {{ $a->course->code }} · {{ $due->format('D, g:i A') }}
                                                        @unless($item['submitted']) · {{ $a->due_date->diffForHumans(['parts' => 1]) }} @endunless
                                                    </span>
                                                </p>
                                            </div>
                                            @if($item['submitted'])
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ring-1 ring-inset flex-shrink-0 {{ $t['badge'] }}">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                    Submitted
                                                </span>
                                            @else
                                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold ring-1 ring-inset flex-shrink-0 {{ $t['badge'] }}">
                                                    {{ $item['tone'] === 'urgent' ? 'Due soon' : 'Submit' }}
                                                </span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Watch: the episode for this week, or the one due next --}}
                    @if($watchFeatured ?? null)
                        @php $wf = $watchFeatured; @endphp
                        <a href="{{ route($wf['is_available'] ? 'tenant.watch.episode' : 'tenant.watch.series', [$tenant->slug, $wf['is_available'] ? $wf['id'] : $wf['series_id']]) }}"
                           class="group relative block overflow-hidden rounded-2xl bg-slate-950 text-white">
                            @if($wf['poster_url'])
                                <img src="{{ $wf['poster_url'] }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-70 transition duration-300 group-hover:scale-105">
                            @endif
                            <span class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/75 to-transparent"></span>
                            <span class="relative flex items-center gap-4 p-5 sm:p-6 min-h-[8.5rem]">
                                <span class="w-12 h-12 shrink-0 rounded-full bg-[#fffffff2] flex items-center justify-center transition group-hover:scale-105"><svg class="w-5 h-5 text-slate-950 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
                                <span class="min-w-0">
                                    <span class="block text-[11px] font-extrabold uppercase tracking-[0.2em] text-amber-400">Watch · {{ $wf['series_title'] }}</span>
                                    <span class="block text-lg font-bold leading-tight mt-0.5">EP {{ $wf['episode_number'] }} · {{ $wf['title'] }}</span>
                                    <span class="block text-xs text-[#cbd5e1] mt-1">
                                        {{ $wf['course']['code'] }}
                                        @if($wf['week_number']) · Week {{ $wf['week_number'] }} @endif
                                        @if($wf['progress'] && ! $wf['progress']['completed']) · {{ $wf['progress']['watched_percent'] }}% watched @endif
                                    </span>
                                    @if($wf['progress'] && ! $wf['progress']['completed'] && $wf['progress']['watched_percent'] > 0)
                                        <span class="mt-2.5 block h-1 w-40 rounded-full bg-[#ffffff33] overflow-hidden"><span class="block h-full bg-amber-400" style="width: {{ $wf['progress']['watched_percent'] }}%"></span></span>
                                    @endif
                                </span>
                            </span>
                        </a>
                    @endif

                    {{-- Recent Marks --}}
                    <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="font-semibold text-slate-900">Recent marks</h3>
                            <a href="{{ route('tenant.marks', $tenant->slug) }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">View all</a>
                        </div>
                        @if($recentMarks->isEmpty())
                            <div class="px-5 py-10 text-center">
                                <p class="text-sm font-medium text-slate-700">No marks released yet</p>
                                <p class="text-xs text-slate-400 mt-1">Results and feedback appear here once your lecturer releases them.</p>
                            </div>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach($recentMarks as $mark)
                                    <li class="flex items-center gap-4 px-5 py-3.5">
                                        @if($mark['course'])
                                            <x-course-avatar :course="$mark['course']" size="sm" />
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $mark['title'] }}</p>
                                            <p class="text-xs text-slate-500 mt-0.5 truncate">
                                                {{ $mark['course']?->code }}@if($mark['released_at']) · Released {{ $mark['released_at']->diffForHumans() }}@endif
                                            </p>
                                            @if($mark['percentage'] !== null)
                                                <div class="mt-2 h-1.5 w-full max-w-[12rem] rounded-full bg-slate-100 overflow-hidden">
                                                    <div class="h-full rounded-full {{ $mark['percentage'] >= 80 ? 'bg-emerald-500' : ($mark['percentage'] >= 50 ? 'bg-indigo-500' : 'bg-red-500') }}" style="width: {{ min(100, max(0, $mark['percentage'])) }}%"></div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <p class="text-base font-bold tabular-nums {{ $markTone($mark['percentage']) }}">
                                                {{ $mark['percentage'] !== null ? round($mark['percentage']).'%' : '—' }}
                                            </p>
                                            <p class="text-xs text-slate-400 tabular-nums">
                                                {{ rtrim(rtrim(number_format($mark['raw'], 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($mark['max'], 2), '0'), '.') }}@if($mark['grade']) · {{ $mark['grade'] }}@endif
                                            </p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>

                {{-- Side column --}}
                <aside class="space-y-6">
                    <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="font-semibold text-slate-900">My courses</h3>
                            <a href="{{ route('tenant.my-courses', $tenant->slug) }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">View all</a>
                        </div>
                        @if($enrolledCourses->isEmpty())
                            <div class="p-5 text-center">
                                <p class="text-sm font-medium text-slate-900">No courses yet</p>
                                <p class="text-xs text-slate-400 mt-1">Ask your lecturer for a section invite code to enroll.</p>
                                <form method="POST" action="{{ route('tenant.my-courses.enroll', $tenant->slug) }}" class="mt-4">
                                    @csrf
                                    <div class="flex gap-2">
                                        <input type="text" name="invite_code" placeholder="Invite code" class="min-w-0 flex-1 px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 text-center uppercase tracking-widest" maxlength="20" required />
                                        <button type="submit" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Join</button>
                                    </div>
                                    @error('invite_code') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                                </form>
                            </div>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach($enrolledCourses->take(6) as $c)
                                    @php $att = $courseAttendance[$c->id] ?? null; @endphp
                                    <li>
                                        <a href="{{ route('tenant.my-courses.show', [$tenant->slug, $c]) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition">
                                            <x-course-avatar :course="$c" size="sm" />
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $c->code }}</p>
                                                <p class="text-xs text-slate-500 truncate">{{ $c->title }}</p>
                                            </div>
                                            @if($att && $att['total_sessions'] > 0)
                                                <span class="text-right flex-shrink-0">
                                                    <span class="block text-sm font-semibold tabular-nums {{ $att['low'] || $att['warning_level'] !== null ? 'text-red-600 dark:text-red-400' : 'text-slate-700' }}">{{ round($att['attendance_rate']) }}%</span>
                                                    <span class="block text-[10px] text-slate-400">attendance</span>
                                                </span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </aside>
            </div>
        </div>
    @endif
</x-tenant-layout>
