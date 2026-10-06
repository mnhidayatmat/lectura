<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('nav.active_learning') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('active_learning.all_plans_desc') }}</p>
            </div>
            @if($courses->isNotEmpty())
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm hover:shadow-md transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('active_learning.new_plan') }}
                        <svg class="w-3 h-3 ml-0.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click.away="open = false" x-transition class="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-slate-200 z-50 py-1 overflow-hidden">
                        <p class="px-4 py-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Select course</p>
                        @foreach($courses as $c)
                            <a href="{{ route('tenant.active-learning.create', [$tenant->slug, $c]) }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 transition">
                                <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    <span class="text-[10px] font-bold text-indigo-700">{{ strtoupper(substr($c->code, 0, 2)) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900 truncate">{{ $c->code }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">{{ $c->title }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-slot>

    @if($courses->isNotEmpty())
        @php [$archivedCourses, $currentCourses] = $courses->partition(fn ($c) => $c->status === 'archived'); @endphp
        <div class="space-y-8">
            @foreach(['Courses' => $currentCourses, 'Archived' => $archivedCourses] as $heading => $group)
                @continue($group->isEmpty())
                <x-course-grid :heading="$heading" :count="$group->count()">
                    @foreach($group as $course)
                        @php $stats = $course->plan_stats; @endphp
                        <x-course-card
                            :href="route('tenant.active-learning.index', [$tenant->slug, $course])"
                            :course="$course"
                            accent="teal"
                            icon="M13 10V3L4 14h7v7l9-11h-7z"
                            :meta="$course->sections_count . ' ' . Str::plural('section', $course->sections_count) . ($course->academicTerm ? ' · ' . $course->academicTerm->name : '')"
                            :footer-left="$stats['plans'] . ' ' . Str::plural('plan', $stats['plans']) . ' · ' . $stats['published'] . ' published'"
                            :footer-right="$stats['updated'] ? 'Updated ' . $stats['updated']->diffForHumans() : 'No plans yet'" />
                    @endforeach
                </x-course-grid>
            @endforeach
        </div>
    @else
        {{-- No courses at all --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
            <div class="w-20 h-20 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">No courses yet</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-6">Create a course first before adding active learning plans.</p>
            <a href="{{ route('tenant.courses.create', $tenant->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm hover:shadow-md transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create a Course
            </a>
        </div>
    @endif
</x-tenant-layout>
