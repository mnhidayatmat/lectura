<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Student Groups</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose a course to manage its group sets</p>
        </div>
    </x-slot>

    @if($courses->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
            <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No courses yet</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">Create courses first to manage student groups.</p>
        </div>
    @else
        @php [$archivedCourses, $currentCourses] = $courses->partition(fn ($c) => $c->status === 'archived'); @endphp
        <div class="space-y-8">
            @foreach(['Courses' => $currentCourses, 'Archived' => $archivedCourses] as $heading => $group)
                @continue($group->isEmpty())
                <x-course-grid :heading="$heading" :count="$group->count()">
                    @foreach($group as $course)
                        @php
                            $sets = $course->studentGroupSets;
                            $groupCount = $sets->sum('groups_count');
                        @endphp
                        <x-course-card
                            :href="route('tenant.student-groups.index', [$tenant->slug, $course])"
                            :course="$course"
                            accent="violet"
                            icon="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                            :meta="$course->sections_count . ' ' . Str::plural('section', $course->sections_count) . ($course->academicTerm ? ' · ' . $course->academicTerm->name : '')"
                            :footer-left="$sets->count() . ' ' . Str::plural('group set', $sets->count()) . ' · ' . $groupCount . ' ' . Str::plural('group', $groupCount)"
                            :footer-right="$sets->isNotEmpty() ? 'Latest ' . $sets->first()->created_at->diffForHumans() : 'No group sets yet'" />
                    @endforeach
                </x-course-grid>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
