<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Assessment</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Course Assessment Plans across your courses</p>
            </div>
        </div>
    </x-slot>

    @if($courses->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
            <div class="w-12 h-12 bg-slate-100 dark:bg-slate-700/50 rounded-xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No courses yet</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">Create a course first to start building your assessment plan.</p>
        </div>
    @else
        {{-- Summary --}}
        @php
            $totalCourses = $courses->count();
            $completeCap = $courses->where('cap_weightage', 100)->count();
            $avgCoverage = $courses->where('clos_total', '>', 0)->avg(fn($c) => ($c->clos_covered / max($c->clos_total, 1)) * 100);
        @endphp
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ Str::plural('Course', $totalCourses) }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $totalCourses }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">CAP Complete</p>
                <p class="mt-1 text-2xl font-bold tabular-nums {{ $completeCap === $totalCourses ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white' }}">{{ $completeCap }}<span class="text-lg font-semibold text-slate-400">/{{ $totalCourses }}</span></p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Avg CLO Coverage</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($avgCoverage ?? 0, 0) }}<span class="text-lg font-semibold text-slate-400">%</span></p>
            </div>
        </div>

        {{-- Course cards --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($courses as $course)
                @php
                    $isComplete = $course->cap_weightage == 100;
                    $isOver = $course->cap_weightage > 100;
                    $hasAssessments = $course->assessments_count > 0;
                    $status = $isComplete
                        ? ['label' => 'Complete',    'dot' => 'bg-emerald-500',                 'text' => 'text-emerald-600 dark:text-emerald-400']
                        : (!$hasAssessments
                            ? ['label' => 'Not started', 'dot' => 'bg-slate-300 dark:bg-slate-600', 'text' => 'text-slate-400']
                            : ['label' => 'In progress', 'dot' => 'bg-indigo-500',                'text' => 'text-indigo-600 dark:text-indigo-400']);
                    $weightText = $isComplete ? 'text-emerald-600 dark:text-emerald-400' : ($isOver ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white');
                    $weightBar  = $isComplete ? 'bg-emerald-500' : ($isOver ? 'bg-red-500' : ($hasAssessments ? 'bg-indigo-500' : 'bg-slate-200 dark:bg-slate-600'));
                @endphp
                <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}"
                   class="group block bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 transition p-5">
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition truncate">{{ $course->code }}</h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-1 mt-0.5">{{ $course->title }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium flex-shrink-0 {{ $status['text'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $status['dot'] }}"></span>{{ $status['label'] }}
                        </span>
                    </div>

                    {{-- Weightage + meta --}}
                    <div class="flex items-end justify-between gap-3 mt-5">
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                            <span>{{ $course->assessments_count }} {{ Str::plural('assessment', $course->assessments_count) }}</span>
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <span>CLO {{ $course->clos_covered }}/{{ $course->clos_total }}</span>
                        </div>
                        <div class="text-right leading-none flex-shrink-0">
                            <span class="text-lg font-bold tabular-nums {{ $weightText }}">{{ number_format($course->cap_weightage, 0) }}%</span>
                        </div>
                    </div>

                    {{-- Progress --}}
                    <div class="h-1.5 bg-slate-100 dark:bg-slate-700/70 rounded-full overflow-hidden mt-2">
                        <div class="h-full rounded-full transition-all duration-500 {{ $weightBar }}" style="width: {{ min($course->cap_weightage, 100) }}%"></div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
