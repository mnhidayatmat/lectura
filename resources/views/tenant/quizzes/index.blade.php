<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Quizzes</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose a course to create, run and review its quizzes</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('tenant.quizzes.create', app('current_tenant')->slug) }}"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Quiz
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if($courses->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-10 text-center">
            <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">No quizzes yet.</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Create a course with sections first, then start creating quizzes.</p>
        </div>
    @else
        @php [$archivedCourses, $currentCourses] = $courses->partition(fn ($c) => $c->status === 'archived'); @endphp
        <div class="space-y-8">
            @foreach(['Courses' => $currentCourses, 'Archived' => $archivedCourses] as $heading => $group)
                @continue($group->isEmpty())
                <x-course-grid :heading="$heading" :count="$group->count()">
                    @foreach($group as $course)
                        @php $stats = $course->quiz_stats; @endphp
                        <x-course-card
                            :href="route('tenant.quizzes.course', [app('current_tenant')->slug, $course])"
                            :course="$course"
                            accent="amber"
                            icon="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            :meta="$course->sections_count . ' ' . Str::plural('section', $course->sections_count) . ($course->academicTerm ? ' · ' . $course->academicTerm->name : '')"
                            :live="$stats['live']"
                            :footer-left="$stats['quizzes'] . ' ' . Str::plural('quiz', $stats['quizzes']) . ($stats['open'] ? ' · ' . $stats['open'] . ' open' : '')"
                            :footer-right="$stats['last'] ? 'Last ' . $stats['last']->diffForHumans() : 'No quizzes yet'" />
                    @endforeach
                </x-course-grid>
            @endforeach
        </div>
    @endif

</x-tenant-layout>
