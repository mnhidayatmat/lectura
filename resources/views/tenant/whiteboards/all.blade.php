<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Whiteboards</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Choose a course to open its collaborative boards</p>
            </div>
        </div>
    </x-slot>

    @if($sections->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 dark:border-[#354158] p-12 text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-fuchsia-100 dark:bg-fuchsia-900/30 flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-fuchsia-600 dark:text-fuchsia-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </div>
            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">No whiteboards yet</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Open a course to create your first board.</p>
        </div>
    @else
        @php [$archivedSections, $currentSections] = $sections->partition(fn ($s) => $s['course']->status === 'archived'); @endphp
        <div class="space-y-8">
            @foreach(['Courses' => $currentSections, 'Archived' => $archivedSections] as $heading => $group)
                @continue($group->isEmpty())
                <x-course-grid :heading="$heading" :count="$group->count()">
                    @foreach($group as $section)
                        @php
                            $course = $section['course'];
                            $updated = $section['course_boards']->concat($section['group_boards'])->max('updated_at');
                        @endphp
                        <x-course-card
                            :href="route('tenant.whiteboards.index', [app('current_tenant')->slug, $course])"
                            :course="$course"
                            accent="fuchsia"
                            icon="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                            :meta="$course->sections_count . ' ' . Str::plural('section', $course->sections_count) . ($course->academicTerm ? ' · ' . $course->academicTerm->name : '')"
                            :footer-left="$section['course_boards']->count() . ' course-wide · ' . $section['group_boards']->count() . ' group'"
                            :footer-right="$updated ? 'Updated ' . $updated->diffForHumans() : 'No boards yet'" />
                    @endforeach
                </x-course-grid>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
