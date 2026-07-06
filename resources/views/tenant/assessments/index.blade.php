<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.assessments.overview', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Course Assessment Plan</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} — {{ $course->title }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($course->programme_id)
                    <a href="{{ route('tenant.clo-plo.edit', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        CLO-PLO
                    </a>
                @endif
                <a href="{{ route('tenant.assessment-reports.course', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Reports
                </a>
                <a href="{{ route('tenant.assessments.create', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Assessment
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- Summary --}}
        @php
            $cloTotal = $course->learningOutcomes->count();
            $cloDone  = $coveredCloIds->count();
            $cloFull  = $cloTotal > 0 && $cloDone === $cloTotal;
        @endphp
        <div class="grid sm:grid-cols-2 gap-4">
            {{-- Weightage --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-baseline justify-between mb-3">
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Weightage</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums {{ $totalWeightage == 100 ? 'text-emerald-600 dark:text-emerald-400' : ($totalWeightage > 100 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white') }}">
                            {{ number_format($totalWeightage, 0) }}<span class="text-lg text-slate-400 font-semibold">%</span>
                        </p>
                    </div>
                    @if($totalWeightage == 100)
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Complete
                        </span>
                    @elseif($totalWeightage > 100)
                        <span class="text-[11px] font-semibold text-red-600 dark:text-red-400">Over by {{ number_format($totalWeightage - 100, 0) }}%</span>
                    @else
                        <span class="text-[11px] font-semibold text-slate-400">{{ number_format(100 - $totalWeightage, 0) }}% left</span>
                    @endif
                </div>
                <div class="h-1.5 bg-slate-100 dark:bg-slate-700/70 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-700 {{ $totalWeightage == 100 ? 'bg-emerald-500' : ($totalWeightage > 100 ? 'bg-red-500' : 'bg-indigo-500') }}" style="width: {{ min($totalWeightage, 100) }}%"></div>
                </div>
            </div>

            {{-- CLO coverage --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-baseline justify-between mb-3">
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">CLO Coverage</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums {{ $cloFull ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white' }}">
                            {{ $cloDone }}<span class="text-lg font-semibold text-slate-400">/{{ $cloTotal }}</span>
                        </p>
                    </div>
                    @if($cloFull)
                        <span class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">All mapped</span>
                    @endif
                </div>
                @if($cloTotal > 0)
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($course->learningOutcomes as $clo)
                            @php $covered = $coveredCloIds->contains($clo->id); @endphp
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold {{ $covered ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : 'bg-slate-50 text-slate-400 dark:bg-slate-700/50 dark:text-slate-500' }}">
                                {{ $clo->code }}
                                @if($covered)
                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-[11px] text-slate-400">No CLOs defined for this course yet.</p>
                @endif
            </div>
        </div>

        {{-- Assessment list --}}
        @if($course->assessments->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
                <div class="w-12 h-12 bg-slate-100 dark:bg-slate-700/50 rounded-xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
                </div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No assessments yet</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">Start building your Course Assessment Plan by adding assessment components.</p>
                <a href="{{ route('tenant.assessments.create', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add First Assessment
                </a>
            </div>
        @else
            @php
                $typeConfig = [
                    'quiz'         => ['label' => 'Quiz',         'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',                                                                                                                   'text' => 'text-amber-600 dark:text-amber-400'],
                    'assignment'   => ['label' => 'Assignment',   'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',                                                                                                                                                 'text' => 'text-blue-600 dark:text-blue-400'],
                    'test'         => ['label' => 'Test',         'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',                                                                                                                                               'text' => 'text-indigo-600 dark:text-indigo-400'],
                    'project'      => ['label' => 'Project',      'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',                                                                                                             'text' => 'text-violet-600 dark:text-violet-400'],
                    'presentation' => ['label' => 'Presentation', 'icon' => 'M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z',                                                                                                                                               'text' => 'text-rose-600 dark:text-rose-400'],
                    'lab'          => ['label' => 'Lab',          'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z', 'text' => 'text-teal-600 dark:text-teal-400'],
                    'final_exam'   => ['label' => 'Final Exam',   'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',                                                                                                                                                                                                                           'text' => 'text-red-600 dark:text-red-400'],
                    'other'        => ['label' => 'Other',        'icon' => 'M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z',                                                                                                                                             'text' => 'text-slate-500 dark:text-slate-400'],
                ];
                $statusStyle = fn ($status) => match ($status) {
                    'active'    => ['label' => 'Active',    'dot' => 'bg-indigo-500',                'text' => 'text-indigo-600 dark:text-indigo-400', 'bar' => 'bg-indigo-500'],
                    'completed' => ['label' => 'Completed', 'dot' => 'bg-emerald-500',               'text' => 'text-emerald-600 dark:text-emerald-400', 'bar' => 'bg-emerald-500'],
                    default     => ['label' => 'Draft',     'dot' => 'bg-slate-300 dark:bg-slate-600','text' => 'text-slate-500 dark:text-slate-400', 'bar' => 'bg-slate-200 dark:bg-slate-600'],
                };
                // Grading completion: graded students over total enrolled. Returns null
                // when there are no enrolled students to grade.
                $gradingStatus = function ($graded, $total) {
                    if ($total <= 0) {
                        return null;
                    }
                    $graded = min($graded, $total);
                    return [
                        'graded'   => $graded,
                        'total'    => $total,
                        'pct'      => (int) round($graded / $total * 100),
                        'complete' => $graded >= $total,
                        'started'  => $graded > 0,
                    ];
                };
            @endphp

            <div class="space-y-3">
                @foreach($course->assessments as $assessment)
                    @php
                        $cfg         = $typeConfig[$assessment->type] ?? $typeConfig['other'];
                        $st          = $statusStyle($assessment->status);
                        $hasChildren = $assessment->children->isNotEmpty();
                    @endphp

                    <div x-data="{ expanded: true }" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 transition overflow-hidden">
                        <div>
                            <div class="p-4 sm:p-5 min-w-0">
                                <div class="flex items-start gap-3.5">
                                    {{-- Type icon (neutral container, colored glyph) --}}
                                    <div class="mt-0.5 w-10 h-10 rounded-lg bg-slate-50 dark:bg-slate-700/50 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 {{ $cfg['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $cfg['icon'] }}"/></svg>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        {{-- Title row --}}
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <h4 class="text-[15px] font-semibold text-slate-900 dark:text-white truncate">{{ $assessment->title }}</h4>
                                                    <span class="text-[11px] font-medium text-slate-400 dark:text-slate-500">{{ $cfg['label'] }}</span>
                                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium {{ $st['text'] }}">
                                                        <span class="w-1.5 h-1.5 rounded-full {{ $st['dot'] }}"></span>{{ $st['label'] }}
                                                    </span>
                                                    @php
                                                        if ($hasChildren) {
                                                            // Parent has no scores of its own — grading lives on the children.
                                                            // Aggregate: complete only when every child is fully graded.
                                                            $childGrades = $assessment->children->map(fn ($c) => $gradingStatus($c->scores_count, $totalStudents))->filter();
                                                            $grade = $childGrades->isEmpty() ? null : [
                                                                'graded'   => $childGrades->sum('graded'),
                                                                'total'    => $childGrades->sum('total'),
                                                                'pct'      => (int) round($childGrades->avg('pct')),
                                                                'complete' => $childGrades->every(fn ($g) => $g['complete']),
                                                                'started'  => $childGrades->contains(fn ($g) => $g['started']),
                                                            ];
                                                        } else {
                                                            $grade = $gradingStatus($assessment->scores_count, $totalStudents);
                                                        }
                                                    @endphp
                                                    @if($grade)
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[11px] font-semibold {{ $grade['complete'] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : ($grade['started'] ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400') }}"
                                                              title="{{ $grade['graded'] }} of {{ $grade['total'] }} students graded">
                                                            @if($grade['complete'])
                                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                                Graded
                                                            @else
                                                                {{ $grade['pct'] }}% graded
                                                            @endif
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($assessment->description)
                                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-1">{{ $assessment->description }}</p>
                                                @endif
                                            </div>
                                            {{-- Weightage --}}
                                            <div class="text-right flex-shrink-0 leading-none">
                                                <div class="text-xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($assessment->weightage, 0) }}%</div>
                                                <div class="text-[10px] text-slate-400 mt-1">of course</div>
                                            </div>
                                        </div>

                                        {{-- Metadata line --}}
                                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 mt-2.5 text-[11px] text-slate-500 dark:text-slate-400">
                                            <span class="font-medium text-slate-600 dark:text-slate-300">{{ number_format($assessment->total_marks, 0) }} marks</span>
                                            @if($assessment->bloom_level)
                                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                                <span class="capitalize">{{ $assessment->bloom_level }}</span>
                                            @endif
                                            @if($assessment->clos->isNotEmpty())
                                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                                <span class="inline-flex items-center gap-1">
                                                    @foreach($assessment->clos as $clo)
                                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-semibold text-[10px]">{{ $clo->code }}</span>
                                                    @endforeach
                                                </span>
                                            @endif
                                            @if($assessment->requires_submission)
                                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                                <span class="inline-flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                                    Submission
                                                </span>
                                                @if($assessment->due_date)
                                                    <span class="text-slate-300 dark:text-slate-600">·</span>
                                                    <span class="{{ now()->gt($assessment->due_date) ? 'text-red-600 dark:text-red-400 font-medium' : '' }}">Due {{ $assessment->due_date->format('d M Y') }}</span>
                                                @endif
                                            @endif
                                            @if($assessment->items->isNotEmpty())
                                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                                <span>{{ $assessment->items->count() }} linked</span>
                                            @endif
                                        </div>

                                        {{-- Actions --}}
                                        <div class="flex items-center gap-1 mt-3 -ml-1.5">
                                            @if($assessment->requires_submission)
                                                <a href="{{ route('tenant.assessments.submissions.index', [$tenant->slug, $course, $assessment]) }}"
                                                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[12px] font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 rounded-lg transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                                    Submissions
                                                </a>
                                            @endif
                                            <a href="{{ route('tenant.assessments.scores.index', [$tenant->slug, $course, $assessment]) }}"
                                               class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[12px] font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 rounded-lg transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                                Scores
                                            </a>

                                            @if($hasChildren)
                                                <button type="button" @click="expanded = !expanded"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[12px] font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700/60 rounded-lg transition">
                                                    <svg class="w-3.5 h-3.5 transition-transform" :class="expanded ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                    {{ $assessment->children->count() }} parts
                                                </button>
                                            @endif

                                            <span class="flex-1"></span>

                                            <a href="{{ route('tenant.assessments.edit', [$tenant->slug, $course, $assessment]) }}"
                                               class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition" title="Edit">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </a>
                                            <form method="POST" action="{{ route('tenant.assessments.destroy', [$tenant->slug, $course, $assessment]) }}" onsubmit="return confirm('Delete this assessment?')">
                                                @csrf @method('DELETE')
                                                <button class="p-1.5 rounded-lg text-slate-400 hover:text-red-500 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition" title="Delete">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </div>

                                        {{-- Child parts --}}
                                        @if($hasChildren)
                                            @php $childWeightageUsed = $assessment->children->sum('weightage'); @endphp
                                            <div x-show="expanded" x-collapse class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/70">
                                                <div class="flex items-center justify-between mb-3">
                                                    <p class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Parts</p>
                                                    <span class="text-[11px] font-medium {{ $childWeightageUsed > 100 ? 'text-red-600 dark:text-red-400' : ($childWeightageUsed == 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400') }}">
                                                        {{ number_format($childWeightageUsed, 0) }}% of parent allocated
                                                    </span>
                                                </div>

                                                <div class="space-y-2">
                                                    @foreach($assessment->children as $child)
                                                        @php
                                                            $childCfg   = $typeConfig[$child->type] ?? $typeConfig['other'];
                                                            $childSt    = $statusStyle($child->status);
                                                            $childMarksPortion = $assessment->total_marks > 0
                                                                ? round(($child->weightage / 100) * $assessment->total_marks, 1)
                                                                : $child->total_marks;
                                                            $childSubmissionCount = $child->requires_submission ? $child->submissions->count() : 0;
                                                        @endphp
                                                        <div class="flex items-start gap-3 rounded-lg border border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40 p-3">
                                                            <div class="mt-0.5 w-8 h-8 rounded-md bg-white dark:bg-slate-700/60 border border-slate-100 dark:border-slate-700 flex items-center justify-center flex-shrink-0">
                                                                <svg class="w-4 h-4 {{ $childCfg['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $childCfg['icon'] }}"/></svg>
                                                            </div>
                                                            <div class="flex-1 min-w-0">
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div class="flex items-center gap-2 flex-wrap min-w-0">
                                                                        <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $child->title }}</p>
                                                                        <span class="text-[10px] font-medium text-slate-400 dark:text-slate-500">{{ $childCfg['label'] }}</span>
                                                                        <span class="inline-flex items-center gap-1 text-[10px] font-medium {{ $childSt['text'] }}">
                                                                            <span class="w-1.5 h-1.5 rounded-full {{ $childSt['dot'] }}"></span>{{ $childSt['label'] }}
                                                                        </span>
                                                                        @php $childGrade = $gradingStatus($child->scores_count, $totalStudents); @endphp
                                                                        @if($childGrade)
                                                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-semibold {{ $childGrade['complete'] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : ($childGrade['started'] ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400') }}"
                                                                                  title="{{ $childGrade['graded'] }} of {{ $childGrade['total'] }} students graded">
                                                                                @if($childGrade['complete'])
                                                                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                                                    Graded
                                                                                @else
                                                                                    {{ $childGrade['pct'] }}% graded
                                                                                @endif
                                                                            </span>
                                                                        @endif
                                                                    </div>
                                                                    <div class="text-right flex-shrink-0 leading-none">
                                                                        <div class="text-sm font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($child->weightage, 0) }}%</div>
                                                                        <div class="text-[10px] text-slate-400 mt-0.5">of parent</div>
                                                                    </div>
                                                                </div>
                                                                @if($child->description)
                                                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 line-clamp-1">{{ $child->description }}</p>
                                                                @endif
                                                                <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                                                                    <span>{{ number_format($childMarksPortion, 1) }} / {{ number_format($assessment->total_marks, 0) }} marks</span>
                                                                    <span class="text-slate-300 dark:text-slate-600">·</span>
                                                                    <span>≈ {{ number_format(($child->weightage / 100) * $assessment->weightage, 1) }}% course</span>
                                                                    @if($child->bloom_level)
                                                                        <span class="text-slate-300 dark:text-slate-600">·</span>
                                                                        <span class="capitalize">{{ $child->bloom_level }}</span>
                                                                    @endif
                                                                    @foreach($child->clos as $clo)
                                                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-semibold text-[10px]">{{ $clo->code }}</span>
                                                                    @endforeach
                                                                    @if($child->requires_submission)
                                                                        <span class="text-slate-300 dark:text-slate-600">·</span>
                                                                        <span class="inline-flex items-center gap-1">
                                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                                                            Submission · {{ $childSubmissionCount }}
                                                                        </span>
                                                                        @if($child->due_date)
                                                                            <span class="text-slate-300 dark:text-slate-600">·</span>
                                                                            <span class="{{ now()->gt($child->due_date) ? 'text-red-600 dark:text-red-400 font-medium' : '' }}">Due {{ $child->due_date->format('d M Y') }}</span>
                                                                        @endif
                                                                    @endif
                                                                </div>
                                                                {{-- Child actions --}}
                                                                <div class="flex items-center gap-1 mt-2 -ml-1.5">
                                                                    @if($child->requires_submission)
                                                                        <a href="{{ route('tenant.assessments.submissions.index', [$tenant->slug, $course, $child]) }}"
                                                                           class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 rounded-md transition">
                                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                                                            Submissions
                                                                        </a>
                                                                    @endif
                                                                    <a href="{{ route('tenant.assessments.scores.index', [$tenant->slug, $course, $child]) }}"
                                                                       class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 rounded-md transition">
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                                                        Scores
                                                                    </a>
                                                                    <span class="flex-1"></span>
                                                                    <a href="{{ route('tenant.assessments.edit', [$tenant->slug, $course, $child]) }}"
                                                                       class="p-1 rounded-md text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition" title="Edit">
                                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                                    </a>
                                                                    <form method="POST" action="{{ route('tenant.assessments.destroy', [$tenant->slug, $course, $child]) }}" onsubmit="return confirm('Delete this part?')">
                                                                        @csrf @method('DELETE')
                                                                        <button class="p-1 rounded-md text-slate-400 hover:text-red-500 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition" title="Delete">
                                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>

                                                {{-- Allocation bar --}}
                                                <div class="mt-3">
                                                    <div class="h-1 bg-slate-100 dark:bg-slate-700/70 rounded-full overflow-hidden">
                                                        <div class="h-full rounded-full transition-all duration-700 {{ $childWeightageUsed > 100 ? 'bg-red-500' : ($childWeightageUsed == 100 ? 'bg-emerald-500' : 'bg-indigo-400') }}" style="width: {{ min($childWeightageUsed, 100) }}%"></div>
                                                    </div>
                                                </div>

                                                {{-- Add part --}}
                                                <a href="{{ route('tenant.assessments.child.create', [$tenant->slug, $course, $assessment]) }}"
                                                   class="mt-3 flex items-center justify-center gap-2 w-full px-4 py-2 border border-dashed border-slate-300 dark:border-slate-600 hover:border-indigo-400 dark:hover:border-indigo-500 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 text-xs font-medium rounded-lg transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                    Add part
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-tenant-layout>
