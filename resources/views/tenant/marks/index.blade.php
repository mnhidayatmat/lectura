<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">{{ __('nav.marks') }} & Feedback</h2>
            <p class="mt-1 text-sm text-slate-500">View your grades and lecturer feedback across all assignments</p>
        </div>
    </x-slot>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Assignments</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">{{ $totalAssignments }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-emerald-200 p-4">
            <p class="text-[10px] font-semibold text-emerald-600 uppercase tracking-wider">Graded</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1">{{ $gradedCount }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-indigo-200 p-4">
            <p class="text-[10px] font-semibold text-indigo-600 uppercase tracking-wider">Average</p>
            <p class="text-2xl font-bold text-indigo-700 mt-1">{{ $avgPercentage ? number_format($avgPercentage, 1) . '%' : '--' }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-amber-200 p-4">
            <p class="text-[10px] font-semibold text-amber-600 uppercase tracking-wider">Pending</p>
            <p class="text-2xl font-bold text-amber-700 mt-1">{{ $pendingCount }}</p>
        </div>
    </div>

    @if($assignments->isEmpty())
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-16 text-center">
            <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 mb-1">No assignments yet</h3>
            <p class="text-sm text-slate-500">Your marks and feedback will appear here once assignments are graded.</p>
        </div>
    @else
        {{-- Assignment List grouped by course --}}
        <div class="space-y-6" x-data="{ filter: 'all' }">
            {{-- Filter --}}
            <div class="flex items-center gap-1 bg-slate-100 rounded-xl p-1 w-fit">
                <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition">All</button>
                <button @click="filter = 'graded'" :class="filter === 'graded' ? 'bg-white shadow-sm text-emerald-700' : 'text-slate-500'" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition">Graded</button>
                <button @click="filter = 'pending'" :class="filter === 'pending' ? 'bg-white shadow-sm text-amber-700' : 'text-slate-500'" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition">Pending</button>
            </div>

            @foreach($assignments->groupBy('course_id') as $courseId => $courseAssignments)
                @php $course = $courseAssignments->first()->course; @endphp
                <div>
                    {{-- Course Header --}}
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center flex-shrink-0">
                            <span class="text-[10px] font-bold text-white">{{ strtoupper(substr($course->code, 0, 2)) }}</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">{{ $course->code }} — {{ $course->title }}</h3>
                            <p class="text-[11px] text-slate-400">{{ $courseAssignments->count() }} {{ Str::plural('assignment', $courseAssignments->count()) }}</p>
                        </div>
                    </div>

                    {{-- Assignment Cards --}}
                    <div class="space-y-2">
                        @foreach($courseAssignments as $assignment)
                            @php
                                $mark = $marks[$assignment->id] ?? null;
                                $submission = $submissions[$assignment->id] ?? null;
                                $isGraded = $mark && $mark->is_final;
                                $feedback = $submission?->feedback;
                                $hasFeedback = $feedback && $feedback->is_released;
                            @endphp
                            <div x-show="filter === 'all' || (filter === 'graded' && {{ $isGraded ? 'true' : 'false' }}) || (filter === 'pending' && {{ !$isGraded ? 'true' : 'false' }})"
                                 class="bg-white rounded-2xl border border-slate-200 hover:border-slate-300 transition overflow-hidden">
                                <div class="p-4 sm:p-5">
                                    <div class="flex items-start justify-between gap-4">
                                        {{-- Left: Assignment Info --}}
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 {{ $isGraded ? 'bg-emerald-100' : ($submission ? 'bg-blue-100' : 'bg-slate-100') }}">
                                                @if($isGraded)
                                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                @elseif($submission)
                                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                @else
                                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <h4 class="text-sm font-semibold text-slate-900">{{ $assignment->title }}</h4>
                                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                                    <span class="text-xs text-slate-400">{{ ucfirst($assignment->type) }}</span>
                                                    @if($assignment->deadline)
                                                        <span class="text-slate-300">&middot;</span>
                                                        <span class="text-xs text-slate-400">Due {{ $assignment->deadline->format('d M Y') }}</span>
                                                    @endif
                                                    @if($submission && $submission->is_late)
                                                        <span class="text-[10px] font-medium text-red-600 bg-red-50 px-1.5 py-0.5 rounded">Late</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Right: Mark/Status --}}
                                        <div class="text-right flex-shrink-0">
                                            @if($isGraded)
                                                <p class="text-lg font-bold {{ $mark->percentage >= 70 ? 'text-emerald-600' : ($mark->percentage >= 40 ? 'text-amber-600' : 'text-red-500') }}">
                                                    {{ number_format($mark->percentage, 1) }}%
                                                </p>
                                                <p class="text-[11px] text-slate-400">{{ $mark->total_marks }}/{{ $mark->max_marks }}</p>
                                            @elseif($submission)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-700">Submitted</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-500">Not submitted</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Feedback Preview (if released) --}}
                                    @if($hasFeedback)
                                        <div class="mt-3 pt-3 border-t border-slate-100" x-data="{ expanded: false }">
                                            <button @click="expanded = !expanded" class="flex items-center gap-2 text-xs font-medium text-indigo-600 hover:text-indigo-700 transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                                View Feedback
                                                <svg class="w-3 h-3 transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </button>

                                            <div x-show="expanded" x-cloak x-transition class="mt-3 space-y-3">
                                                {{-- Performance Level --}}
                                                @if($feedback->performance_level)
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Performance:</span>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                                                            {{ $feedback->performance_level === 'advanced' ? 'bg-emerald-100 text-emerald-700' :
                                                               ($feedback->performance_level === 'average' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                                            {{ ucfirst($feedback->performance_level) }}
                                                        </span>
                                                        @if($feedback->ai_generated)
                                                            <span class="inline-flex items-center gap-0.5 text-[10px] bg-teal-50 text-teal-700 px-1.5 py-0.5 rounded font-medium">
                                                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                                                                AI
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif

                                                {{-- Strengths --}}
                                                @if($feedback->strengths)
                                                    <div class="bg-emerald-50 rounded-xl p-3 border border-emerald-100">
                                                        <h5 class="text-[10px] font-semibold text-emerald-700 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                            Strengths
                                                        </h5>
                                                        <p class="text-xs text-emerald-800 leading-relaxed">{{ $feedback->strengths }}</p>
                                                    </div>
                                                @endif

                                                {{-- Areas for Improvement --}}
                                                @if($feedback->improvement_tips)
                                                    <div class="bg-amber-50 rounded-xl p-3 border border-amber-100">
                                                        <h5 class="text-[10px] font-semibold text-amber-700 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                                            Areas for Improvement
                                                        </h5>
                                                        <p class="text-xs text-amber-800 leading-relaxed">{{ $feedback->improvement_tips }}</p>
                                                    </div>
                                                @endif

                                                {{-- Missing Points --}}
                                                @if($feedback->missing_points)
                                                    <div class="bg-red-50 rounded-xl p-3 border border-red-100">
                                                        <h5 class="text-[10px] font-semibold text-red-700 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                                            Missing Points
                                                        </h5>
                                                        <p class="text-xs text-red-800 leading-relaxed">{{ $feedback->missing_points }}</p>
                                                    </div>
                                                @endif

                                                {{-- Revision Advice --}}
                                                @if($feedback->revision_advice)
                                                    <div class="bg-indigo-50 rounded-xl p-3 border border-indigo-100">
                                                        <h5 class="text-[10px] font-semibold text-indigo-700 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                                            Revision Advice
                                                        </h5>
                                                        <p class="text-xs text-indigo-800 leading-relaxed">{{ $feedback->revision_advice }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
