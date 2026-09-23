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
        @php [$archivedCourses, $currentCourses] = $courses->partition(fn ($c) => $c->status === 'archived'); @endphp
        <div class="space-y-8">
            @foreach(['Courses' => $currentCourses, 'Archived' => $archivedCourses] as $heading => $group)
                @continue($group->isEmpty())
                <x-course-grid :heading="$heading" :count="$group->count()">
                    @foreach($group as $course)
                        @php
                            $weight = (float) $course->cap_weightage;
                            $tone = $weight == 100 ? 'good' : ($weight > 100 ? 'bad' : ($course->assessments_count > 0 ? 'neutral' : 'none'));
                        @endphp
                        <x-course-card
                            :href="route('tenant.assessments.index', [$tenant->slug, $course])"
                            :course="$course"
                            accent="rose"
                            icon="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                            :meta="$course->sections_count . ' ' . Str::plural('section', $course->sections_count) . ($course->academicTerm ? ' · ' . $course->academicTerm->name : '')"
                            metric-label="CAP weightage"
                            :metric-value="number_format($weight, 0) . '%'"
                            :metric-percent="$weight"
                            :metric-tone="$tone"
                            :footer-left="$course->assessments_count . ' ' . Str::plural('assessment', $course->assessments_count)"
                            :footer-right="'CLO ' . $course->clos_covered . '/' . $course->clos_total . ' covered'" />
                    @endforeach
                </x-course-grid>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
