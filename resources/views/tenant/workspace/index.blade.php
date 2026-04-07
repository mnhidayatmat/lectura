<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">My Workspace</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Collaborative group workspaces for your project activities</p>
        </div>
    </x-slot>

    @if($sets->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
            <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No group workspaces yet</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">You will see your workspaces here once a lecturer assigns you to a group.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($sets as $courseId => $courseSets)
                @php
                    $course = $courseSets->first()?->course;
                    $term = $course?->academicTerm;
                @endphp
                @if(!$course)
                    @continue
                @endif

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                            <span class="text-xs font-bold text-indigo-700 dark:text-indigo-400">{{ strtoupper(substr($course->code, 0, 2)) }}</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $course->code }} — {{ $course->title }}</h3>
                            @if($term)
                                <p class="text-[11px] text-slate-400">{{ $term->name }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($courseSets as $gs)
                            @foreach($gs->groups as $group)
                                @php $membership = $group->members->firstWhere('user_id', auth()->id()); @endphp
                                <a href="{{ route('tenant.workspace.show', [app('current_tenant')->slug, $group]) }}"
                                   class="flex items-center justify-between px-6 py-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                    <div class="flex items-center gap-4">
                                        @if($group->color_tag)
                                            <div class="w-3 h-10 rounded-full flex-shrink-0" style="background-color: {{ $group->color_tag }}"></div>
                                        @else
                                            <div class="w-3 h-10 rounded-full flex-shrink-0 bg-indigo-400"></div>
                                        @endif
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $group->name }}</p>
                                            @if($group->project_title)
                                                <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-0.5">{{ $group->project_title }}</p>
                                            @else
                                                <p class="text-xs text-slate-400 mt-0.5">No project set yet</p>
                                            @endif
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                                    {{ ucfirst($group->groupSet->type) }}
                                                </span>
                                                @if($membership?->role === 'leader')
                                                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Leader</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-slate-400">
                                        <span>{{ $group->members->count() }} members</span>
                                        @if($group->score_released_at)
                                            <span class="text-emerald-600 dark:text-emerald-400 font-medium">
                                                Score: {{ number_format($group->score, 1) }}/{{ number_format($group->score_max, 1) }}
                                            </span>
                                        @endif
                                        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                </a>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
