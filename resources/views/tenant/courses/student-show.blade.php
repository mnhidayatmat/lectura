<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.my-courses', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-bold text-slate-900">{{ $course->code }}</h2>
                    @php $badge = $course->statusBadge; @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700">{{ $badge['label'] }}</span>
                </div>
                <p class="mt-0.5 text-sm text-slate-500">{{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">

        {{-- Course Info --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-slate-100">
                <div class="p-5">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Lecturer</p>
                    <p class="text-sm font-semibold text-slate-900 mt-1.5">{{ $course->lecturer?->name ?? 'TBA' }}</p>
                </div>
                <div class="p-5">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Section</p>
                    <p class="text-sm font-semibold text-slate-900 mt-1.5">{{ $mySections->pluck('name')->implode(', ') }}</p>
                </div>
                <div class="p-5">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Credit Hours</p>
                    <p class="text-sm font-semibold text-slate-900 mt-1.5">{{ $course->credit_hours ?? '--' }}</p>
                </div>
                <div class="p-5">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Weeks</p>
                    <p class="text-sm font-semibold text-slate-900 mt-1.5">{{ $course->num_weeks }}</p>
                </div>
            </div>
            @if($course->description)
                <div class="px-5 py-4 border-t border-slate-100">
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $course->description }}</p>
                </div>
            @endif
        </div>

        {{-- My Attendance --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">My Attendance</h3>
            </div>
            @if($attendanceSummary['total'] > 0)
                <div class="p-5">
                    <div class="grid grid-cols-3 gap-4 text-center mb-4">
                        <div>
                            <p class="text-2xl font-bold text-emerald-600">{{ $attendanceSummary['present'] }}</p>
                            <p class="text-xs text-slate-400">Present</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-amber-600">{{ $attendanceSummary['late'] }}</p>
                            <p class="text-xs text-slate-400">Late</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-red-600">{{ $attendanceSummary['absent'] }}</p>
                            <p class="text-xs text-slate-400">Absent</p>
                        </div>
                    </div>
                    @php $rate = $attendanceSummary['total'] > 0 ? round(($attendanceSummary['present'] + $attendanceSummary['late']) / $attendanceSummary['total'] * 100) : 0; @endphp
                    <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ $rate >= 80 ? 'bg-emerald-500' : ($rate >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $rate }}%"></div>
                    </div>
                    <p class="text-xs text-slate-400 mt-2 text-center">{{ $rate }}% attendance rate ({{ $attendanceSummary['total'] }} sessions)</p>
                </div>
            @else
                <div class="p-8 text-center">
                    <p class="text-sm text-slate-400">No attendance records yet</p>
                </div>
            @endif
        </div>

        {{-- Quick Links --}}
        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('tenant.materials.student-course', [$tenant->slug, $course]) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-indigo-200 hover:shadow-sm p-4 flex items-center gap-3 transition-all">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-200 transition">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-900">Materials</p>
                    <p class="text-[11px] text-slate-400">Lecture notes & files</p>
                </div>
            </a>
            <a href="{{ route('tenant.assignments.index', $tenant->slug) }}" class="group bg-white rounded-2xl border border-slate-200 hover:border-amber-200 hover:shadow-sm p-4 flex items-center gap-3 transition-all">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0 group-hover:bg-amber-200 transition">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-900">Assignments</p>
                    <p class="text-[11px] text-slate-400">Submit & track</p>
                </div>
            </a>
        </div>

        {{-- Upcoming Assignments --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Upcoming Assignments</h3>
            </div>
            @if($upcomingAssignments->isEmpty())
                <div class="p-8 text-center">
                    <p class="text-sm text-slate-400">No upcoming assignments</p>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($upcomingAssignments as $assignment)
                        <div class="px-5 py-3.5 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ $assignment->title }}</p>
                                <p class="text-xs text-slate-400">{{ ucfirst($assignment->type) }} &middot; {{ $assignment->total_marks }} marks</p>
                            </div>
                            @if($assignment->deadline)
                                <span class="text-xs font-medium {{ $assignment->deadline->isPast() ? 'text-red-600' : ($assignment->deadline->diffInDays() <= 3 ? 'text-amber-600' : 'text-slate-500') }}">
                                    {{ $assignment->deadline->format('d M, H:i') }}
                                </span>
                            @else
                                <span class="text-xs text-slate-400">No deadline</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Weekly Topics --}}
        @if($course->topics->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Weekly Topics</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($course->topics->sortBy('week_number') as $topic)
                        <div class="px-5 py-3 flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-10 h-7 bg-teal-50 text-teal-700 text-xs font-bold rounded-md flex-shrink-0">W{{ $topic->week_number }}</span>
                            <span class="text-sm text-slate-700">{{ $topic->title }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Active Learning Plans --}}
        @if($activeLearningPlans->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Active Learning Plans</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($activeLearningPlans as $plan)
                        <div class="px-5 py-3.5 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">
                                    @if($plan->week_number) W{{ $plan->week_number }} @else -- @endif
                                </span>
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ $plan->title }}</p>
                                    <p class="text-xs text-slate-400">{{ $plan->activities_count }} activities &middot; {{ $plan->duration_minutes }} min</p>
                                </div>
                            </div>
                            @if($plan->activeSession())
                                <a href="{{ route('tenant.session.live', [$tenant->slug, $plan->activeSession()]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition">
                                    <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span>
                                    Join
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- CLOs --}}
        @if($course->learningOutcomes->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Learning Outcomes</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($course->learningOutcomes as $clo)
                        <div class="px-5 py-3 flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-12 h-7 bg-indigo-50 text-indigo-700 text-xs font-bold rounded-md flex-shrink-0">{{ $clo->code }}</span>
                            <span class="text-sm text-slate-600">{{ $clo->description }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-tenant-layout>
