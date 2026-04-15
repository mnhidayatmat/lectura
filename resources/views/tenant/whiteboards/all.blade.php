<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Whiteboards</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Collaborative boards across all your courses</p>
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
        <div class="space-y-6" x-data="{ openCourse: {{ $sections->first()['course']->id ?? 'null' }} }">
            @foreach($sections as $section)
                @php $course = $section['course']; @endphp
                <div class="bg-white dark:bg-[#242d3d] rounded-2xl border border-slate-200 dark:border-[#354158] overflow-hidden">
                    {{-- Course header (collapsible) --}}
                    <button
                        type="button"
                        @click="openCourse = (openCourse === {{ $course->id }} ? null : {{ $course->id }})"
                        class="w-full flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-[#2a3445] transition"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-fuchsia-100 dark:bg-fuchsia-900/30 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-fuchsia-600 dark:text-fuchsia-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </div>
                            <div class="text-left min-w-0">
                                <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $course->code }} — {{ $course->title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $section['course_boards']->count() }} course-wide ·
                                    {{ $section['group_boards']->count() }} group
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <a
                                href="{{ route('tenant.whiteboards.index', [app('current_tenant')->slug, $course]) }}"
                                @click.stop
                                class="text-xs font-medium text-fuchsia-600 hover:text-fuchsia-700 dark:text-fuchsia-400"
                            >
                                Manage →
                            </a>
                            <svg
                                class="w-4 h-4 text-slate-400 transition-transform"
                                :class="openCourse === {{ $course->id }} ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </button>

                    {{-- Boards under this course --}}
                    <div x-show="openCourse === {{ $course->id }}" x-cloak x-transition>
                        <div class="px-5 pb-5 pt-1 space-y-4">
                            {{-- Course-wide --}}
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Course-wide</p>
                                @if($section['course_boards']->isEmpty())
                                    <p class="text-xs text-slate-400 italic">No course-wide boards.</p>
                                @else
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        @foreach($section['course_boards'] as $board)
                                            <a
                                                href="{{ route('tenant.whiteboards.show', [app('current_tenant')->slug, $board]) }}"
                                                class="group block rounded-xl border border-slate-200 dark:border-[#354158] hover:border-fuchsia-300 dark:hover:border-fuchsia-700 hover:shadow-sm transition p-3"
                                            >
                                                <p class="text-sm font-semibold text-slate-900 dark:text-white truncate group-hover:text-fuchsia-600">{{ $board->title }}</p>
                                                <p class="text-[11px] text-slate-400 mt-0.5">Updated {{ $board->updated_at->diffForHumans() }}</p>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Group boards --}}
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Group boards</p>
                                @if($section['group_boards']->isEmpty())
                                    <p class="text-xs text-slate-400 italic">No group boards.</p>
                                @else
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        @foreach($section['group_boards'] as $board)
                                            <a
                                                href="{{ route('tenant.whiteboards.show', [app('current_tenant')->slug, $board]) }}"
                                                class="group block rounded-xl border border-slate-200 dark:border-[#354158] hover:border-fuchsia-300 dark:hover:border-fuchsia-700 hover:shadow-sm transition p-3"
                                            >
                                                <p class="text-sm font-semibold text-slate-900 dark:text-white truncate group-hover:text-fuchsia-600">{{ $board->title }}</p>
                                                <p class="text-[11px] text-slate-400 mt-0.5">
                                                    {{ $board->group?->name ?? 'Group' }} · {{ $board->updated_at->diffForHumans() }}
                                                </p>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
