<x-tenant-layout>
    @php $tenant = app('current_tenant'); @endphp

    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Attendance</h2>
            <p class="mt-1 text-sm text-slate-500">Choose a course to start a QR session or review its history</p>
        </div>
    </x-slot>

    <div class="space-y-8">
        {{-- Live Sessions --}}
        @if($activeSessions->isNotEmpty())
            <div class="bg-white rounded-2xl border-2 border-emerald-200 overflow-hidden">
                <div class="px-6 py-3 border-b border-emerald-100 bg-emerald-50/50 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    <h3 class="text-sm font-semibold text-emerald-900">Live now ({{ $activeSessions->count() }})</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($activeSessions as $session)
                        <a href="{{ route('tenant.attendance.qr', [$tenant->slug, $session]) }}" class="flex items-center justify-between gap-4 px-6 py-3 hover:bg-emerald-50/30 transition">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $session->section->course->code }} — {{ $session->section->name }}</p>
                                <p class="text-xs text-slate-500">{{ ucfirst($session->session_type) }}{{ $session->week_number ? ' • Week ' . $session->week_number : '' }} • Started {{ $session->started_at->diffForHumans() }}</p>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <div class="text-right">
                                    <p class="text-lg font-bold text-emerald-600 leading-tight">{{ $session->attended_count }}</p>
                                    <p class="text-[11px] text-slate-400">checked in</p>
                                </div>
                                <span class="px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg">Open QR</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if($currentCourses->isEmpty() && $archivedCourses->isEmpty())
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-16 text-center">
                <div class="w-20 h-20 bg-gradient-to-br from-emerald-100 to-teal-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 mb-2">No courses yet</h3>
                <p class="text-sm text-slate-500 max-w-md mx-auto">Create a course with sections first to start taking attendance.</p>
            </div>
        @else
            @foreach(['Courses' => $currentCourses, 'Archived' => $archivedCourses] as $heading => $courses)
                @continue($courses->isEmpty())
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $heading }}</h3>
                        <span class="text-xs text-slate-400">{{ $courses->count() }} {{ Str::plural('course', $courses->count()) }}</span>
                    </div>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($courses as $course)
                            @php
                                $stats = $course->attendance_stats;
                                $rate = $stats['rate'];
                                $rateColor = $rate === null ? 'bg-slate-200' : ($rate >= 80 ? 'bg-emerald-500' : ($rate >= 60 ? 'bg-amber-500' : 'bg-red-500'));
                                $rateText = $rate === null ? 'text-slate-400' : ($rate >= 80 ? 'text-emerald-600' : ($rate >= 60 ? 'text-amber-600' : 'text-red-600'));
                            @endphp
                            <a href="{{ route('tenant.attendance.course', [$tenant->slug, $course]) }}"
                               class="group flex flex-col bg-white rounded-2xl border border-slate-200 hover:border-emerald-200 hover:shadow-md p-5 transition-all {{ $heading === 'Archived' ? 'opacity-75 hover:opacity-100' : '' }}">
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center flex-shrink-0 shadow-sm">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-xs font-bold text-emerald-600">{{ $course->code }}</p>
                                            @if($stats['live'] > 0)
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-[10px] font-semibold">
                                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                                    Live
                                                </span>
                                            @endif
                                        </div>
                                        <h4 class="text-sm font-semibold text-slate-900 truncate group-hover:text-emerald-700 transition">{{ $course->title }}</h4>
                                        <p class="text-xs text-slate-400 mt-0.5 truncate">
                                            {{ $stats['sections'] }} {{ Str::plural('section', $stats['sections']) }}
                                            @if($course->academicTerm) &middot; {{ $course->academicTerm->name }} @endif
                                        </p>
                                    </div>
                                    <svg class="w-5 h-5 text-slate-300 group-hover:text-emerald-400 transition flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </div>

                                <div class="mt-5 pt-4 border-t border-slate-100">
                                    <div class="flex items-baseline justify-between mb-1.5">
                                        <span class="text-xs text-slate-500">Avg. attendance</span>
                                        <span class="text-sm font-bold {{ $rateText }}">{{ $rate === null ? '—' : $rate . '%' }}</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full {{ $rateColor }}" style="width: {{ $rate ?? 0 }}%"></div>
                                    </div>
                                    <div class="flex items-center justify-between mt-3 text-xs text-slate-400">
                                        <span>{{ $stats['sessions'] }} {{ Str::plural('session', $stats['sessions']) }}</span>
                                        <span>{{ $stats['last'] ? 'Last ' . $stats['last']->diffForHumans() : 'No sessions yet' }}</span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</x-tenant-layout>
