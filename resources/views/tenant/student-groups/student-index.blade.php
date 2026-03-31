<x-tenant-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">My Groups</h2>
    </x-slot>

    @if($memberships->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
            <div class="w-16 h-16 bg-slate-50 dark:bg-slate-700 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-slate-300 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No groups yet</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">You haven't been assigned to any groups.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($memberships as $courseId => $members)
                @php
                    $firstMember = $members->first();
                    $course = $firstMember?->group?->groupSet?->course;
                @endphp
                @if(!$course)
                    @continue
                @endif
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $course->code }} — {{ $course->title }}</h3>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($members as $membership)
                            @php $group = $membership->group; $gs = $group?->groupSet; @endphp
                            @if(!$group || !$gs)
                                @continue
                            @endif
                            <div class="px-5 py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    @if($group->color_tag)
                                        <span class="w-3 h-3 rounded-full" style="background: {{ $group->color_tag }}"></span>
                                    @endif
                                    <div>
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $group->name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $gs->name }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full {{ match($gs->type) { 'lecture' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'lab' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'tutorial' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' } }}">{{ ucfirst($gs->type) }}</span>
                                    @if($membership->role === 'leader')
                                        <span class="text-[10px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 rounded-full">Leader</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
