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
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($courses as $course)
                <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:shadow-md transition group">
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        </div>
                        <span class="text-xs font-medium {{ $course->cap_weightage == 100 ? 'text-emerald-600' : ($course->cap_weightage > 0 ? 'text-amber-600' : 'text-slate-400') }}">
                            {{ number_format($course->cap_weightage, 0) }}% weightage
                        </span>
                    </div>

                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-0.5">{{ $course->code }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-3 line-clamp-1">{{ $course->title }}</p>

                    <div class="flex items-center gap-4 text-[11px] text-slate-400">
                        <span>{{ $course->assessments_count }} {{ Str::plural('assessment', $course->assessments_count) }}</span>
                        <span>{{ $course->clos_covered }}/{{ $course->clos_total }} CLOs</span>
                    </div>

                    {{-- Weightage progress bar --}}
                    <div class="mt-3 h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all {{ $course->cap_weightage == 100 ? 'bg-emerald-500' : 'bg-indigo-500' }}" style="width: {{ min($course->cap_weightage, 100) }}%"></div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
