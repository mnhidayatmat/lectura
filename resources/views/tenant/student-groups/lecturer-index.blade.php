<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Student Groups</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage student groups across all your courses</p>
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
        <div class="space-y-6">
            @foreach($courses as $course)
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-400">{{ strtoupper(substr($course->code, 0, 2)) }}</span>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $course->code }} — {{ $course->title }}</h3>
                                @if($course->academicTerm)
                                    <p class="text-[11px] text-slate-400">{{ $course->academicTerm->name }}</p>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('tenant.student-groups.create', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            New Group Set
                        </a>
                    </div>

                    @if($course->studentGroupSets->isEmpty())
                        <div class="px-6 py-8 text-center">
                            <p class="text-sm text-slate-400">No group sets created for this course.</p>
                            <a href="{{ route('tenant.student-groups.create', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-1 mt-2 text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                Create first group set
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    @else
                        <div class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($course->studentGroupSets as $set)
                                <a href="{{ route('tenant.student-groups.show', [$tenant->slug, $course, $set]) }}" class="flex items-center justify-between px-6 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                    <div class="flex items-center gap-3">
                                        <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full {{ match($set->type) { 'lecture' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'lab' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'tutorial' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' } }}">{{ ucfirst($set->type) }}</span>
                                        <div>
                                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $set->name }}</p>
                                            @if($set->description)
                                                <p class="text-[11px] text-slate-400 line-clamp-1">{{ $set->description }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-slate-400">
                                        <span>{{ $set->groups_count }} {{ Str::plural('group', $set->groups_count) }}</span>
                                        <span class="text-slate-300">&middot;</span>
                                        <span>{{ $set->creation_method === 'random' ? 'Random' : 'Manual' }}</span>
                                        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
