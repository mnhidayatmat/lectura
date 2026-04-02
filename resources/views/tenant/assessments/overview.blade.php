<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Assessment</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Course Assessment Plans (CAP) across your courses</p>
            </div>
        </div>
    </x-slot>

    @if($courses->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
            <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No courses yet</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">Create a course first to start building your assessment plan.</p>
        </div>
    @else
        {{-- Summary stats --}}
        @php
            $totalCourses = $courses->count();
            $completeCap = $courses->where('cap_weightage', 100)->count();
            $avgCoverage = $courses->where('clos_total', '>', 0)->avg(fn($c) => ($c->clos_covered / max($c->clos_total, 1)) * 100);
        @endphp
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalCourses }}</p>
                <p class="text-[11px] text-slate-400 uppercase tracking-wider mt-0.5">{{ Str::plural('Course', $totalCourses) }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
                <p class="text-2xl font-bold {{ $completeCap === $totalCourses ? 'text-emerald-600' : 'text-amber-600' }}">{{ $completeCap }}/{{ $totalCourses }}</p>
                <p class="text-[11px] text-slate-400 uppercase tracking-wider mt-0.5">CAP Complete</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
                <p class="text-2xl font-bold text-teal-600 dark:text-teal-400">{{ number_format($avgCoverage ?? 0, 0) }}%</p>
                <p class="text-[11px] text-slate-400 uppercase tracking-wider mt-0.5">Avg CLO Coverage</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($courses as $course)
                @php
                    $isComplete = $course->cap_weightage == 100;
                    $hasAssessments = $course->assessments_count > 0;
                    $cloPct = $course->clos_total > 0 ? round(($course->clos_covered / $course->clos_total) * 100) : 0;
                @endphp
                <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden hover:shadow-lg hover:border-indigo-200 dark:hover:border-indigo-800 transition-all group">
                    {{-- Color accent bar --}}
                    <div class="h-1 {{ $isComplete ? 'bg-emerald-500' : ($hasAssessments ? 'bg-indigo-500' : 'bg-slate-200 dark:bg-slate-700') }}"></div>

                    <div class="p-5">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-xl {{ $isComplete ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-indigo-100 dark:bg-indigo-900/30' }} flex items-center justify-center flex-shrink-0">
                                    @if($isComplete)
                                        <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @else
                                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">{{ $course->code }}</h3>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-1">{{ $course->title }}</p>
                                </div>
                            </div>
                            @if($isComplete)
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Complete</span>
                            @elseif(!$hasAssessments)
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400">Not started</span>
                            @else
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">In progress</span>
                            @endif
                        </div>

                        {{-- Stats row --}}
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <div class="text-center p-2 bg-slate-50 dark:bg-slate-700/50 rounded-lg">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $course->assessments_count }}</p>
                                <p class="text-[9px] text-slate-400 uppercase">Assessments</p>
                            </div>
                            <div class="text-center p-2 bg-slate-50 dark:bg-slate-700/50 rounded-lg">
                                <p class="text-sm font-bold {{ $isComplete ? 'text-emerald-600' : 'text-indigo-600' }}">{{ number_format($course->cap_weightage, 0) }}%</p>
                                <p class="text-[9px] text-slate-400 uppercase">Weightage</p>
                            </div>
                            <div class="text-center p-2 bg-slate-50 dark:bg-slate-700/50 rounded-lg">
                                <p class="text-sm font-bold text-teal-600">{{ $course->clos_covered }}/{{ $course->clos_total }}</p>
                                <p class="text-[9px] text-slate-400 uppercase">CLOs</p>
                            </div>
                        </div>

                        {{-- Weightage progress --}}
                        <div class="h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 {{ $isComplete ? 'bg-emerald-500' : ($course->cap_weightage > 100 ? 'bg-red-500' : 'bg-indigo-500') }}" style="width: {{ min($course->cap_weightage, 100) }}%"></div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
