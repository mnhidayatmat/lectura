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
                <x-course-grid :heading="$heading" :count="$courses->count()">
                    @foreach($courses as $course)
                        @php $stats = $course->attendance_stats; @endphp
                        <x-course-card
                            :href="route('tenant.attendance.course', [$tenant->slug, $course])"
                            :course="$course"
                            accent="emerald"
                            icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                            :meta="$stats['sections'] . ' ' . Str::plural('section', $stats['sections']) . ($course->academicTerm ? ' · ' . $course->academicTerm->name : '')"
                            :live="$stats['live']"
                            metric-label="Avg. attendance"
                            :metric-value="$stats['rate'] === null ? null : $stats['rate'] . '%'"
                            :metric-percent="$stats['rate']"
                            :footer-left="$stats['sessions'] . ' ' . Str::plural('session', $stats['sessions'])"
                            :footer-right="$stats['last'] ? 'Last ' . $stats['last']->diffForHumans() : 'No sessions yet'" />
                    @endforeach
                </x-course-grid>
            @endforeach
        @endif
    </div>
</x-tenant-layout>
