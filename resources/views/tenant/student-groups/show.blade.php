<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.student-groups.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $set->name }}</h2>
                        <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full {{ match($set->type) { 'lecture' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'lab' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'tutorial' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' } }}">{{ ucfirst($set->type) }}</span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }}{{ $set->section ? ' — ' . $set->section->name : '' }} — {{ $set->groups->count() }} {{ Str::plural('group', $set->groups->count()) }} &middot; {{ $enrolledCount }} enrolled</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                {{-- Random arrange --}}
                <form method="POST" action="{{ route('tenant.student-groups.arrange-random', [$tenant->slug, $course, $set]) }}" x-data="{ size: {{ $set->max_group_size ?? 4 }} }" class="flex items-center gap-2">
                    @csrf
                    <input type="number" name="group_size" x-model="size" min="2" max="20" class="w-16 px-2 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-center">
                    <button type="submit" onclick="return confirm('This will replace all existing groups. Continue?')" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Randomise
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- Add group form --}}
        <div x-data="{ adding: false, name: '' }" class="flex items-center gap-3">
            <button x-show="!adding" @click="adding = true; $nextTick(() => $refs.groupName.focus())" class="inline-flex items-center gap-2 px-4 py-2 text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 border border-dashed border-slate-300 dark:border-slate-600 hover:border-indigo-400 rounded-xl transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Group
            </button>
            <form x-show="adding" x-cloak method="POST" action="{{ route('tenant.student-groups.groups.store', [$tenant->slug, $course, $set]) }}" class="flex items-center gap-2">
                @csrf
                <input x-ref="groupName" type="text" name="name" x-model="name" placeholder="Group name..." required @keydown.escape="adding = false; name = ''" class="px-3 py-2 rounded-xl border border-indigo-300 dark:border-indigo-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500">
                <button type="submit" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Create</button>
                <button type="button" @click="adding = false; name = ''" class="px-3 py-2 text-slate-500 text-sm rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition">Cancel</button>
            </form>
        </div>

        {{-- Unassigned students --}}
        @if($unassigned->isNotEmpty())
            <div class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-700 rounded-2xl p-4">
                <h4 class="text-xs font-semibold text-amber-800 dark:text-amber-400 uppercase tracking-wider mb-2">Unassigned Students ({{ $unassigned->count() }})</h4>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($unassigned as $student)
                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-white dark:bg-slate-800 border border-amber-200 dark:border-amber-600 rounded-lg text-xs text-slate-700 dark:text-slate-300">
                            <span class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-600 flex items-center justify-center text-[9px] font-bold text-slate-500 dark:text-slate-300">{{ strtoupper(substr($student->name, 0, 1)) }}</span>
                            {{ $student->name }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Group cards --}}
        @if($set->groups->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-12 text-center">
                <p class="text-sm text-slate-500 dark:text-slate-400">No groups yet. Add groups manually or use Randomise to auto-create them.</p>
            </div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($set->groups as $group)
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        {{-- Group header --}}
                        <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                @if($group->color_tag)
                                    <span class="w-3 h-3 rounded-full" style="background: {{ $group->color_tag }}"></span>
                                @endif
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $group->name }}</h3>
                                <span class="text-[10px] text-slate-400">({{ $group->members->count() }})</span>
                            </div>
                            <form method="POST" action="{{ route('tenant.student-groups.groups.destroy', [$tenant->slug, $course, $set, $group]) }}" onsubmit="return confirm('Delete this group?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-slate-400 hover:text-red-500 transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>

                        {{-- Members --}}
                        <div class="p-4 space-y-1.5">
                            @forelse($group->members as $member)
                                <div class="flex items-center justify-between py-0.5">
                                    <div class="flex items-center gap-2">
                                        <span class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-500 dark:text-slate-300">{{ strtoupper(substr($member->user->name, 0, 2)) }}</span>
                                        <div>
                                            <p class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $member->user->name }}</p>
                                            @if($member->role === 'leader')
                                                <span class="text-[9px] text-amber-600 font-semibold">Leader</span>
                                            @endif
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('tenant.student-groups.members.remove', [$tenant->slug, $course, $set, $group, $member->user]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1 text-slate-400 hover:text-red-500 dark:text-slate-500 dark:hover:text-red-400 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20" title="Remove {{ $member->user->name }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 text-center py-2">No members</p>
                            @endforelse
                        </div>

                        {{-- Add member --}}
                        @if($unassigned->isNotEmpty())
                            <div class="px-4 pb-3">
                                <form method="POST" action="{{ route('tenant.student-groups.members.add', [$tenant->slug, $course, $set, $group]) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <select name="user_id" class="flex-1 px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs">
                                        @foreach($unassigned as $s)
                                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="px-2 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-medium rounded-lg transition">Add</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Pending Swap Requests --}}
        @if($pendingSwaps->count())
            <div class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-700 rounded-2xl p-5">
                <h4 class="text-sm font-semibold text-amber-800 dark:text-amber-400 mb-3">Pending Swap Requests ({{ $pendingSwaps->count() }})</h4>
                <div class="space-y-3">
                    @foreach($pendingSwaps as $swap)
                        <div class="flex items-center justify-between bg-white dark:bg-slate-800 rounded-xl p-3 border border-amber-100 dark:border-amber-800">
                            <div class="text-sm">
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ $swap->requester->name }}</span>
                                <span class="text-slate-500"> wants to swap with </span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ $swap->targetUser->name }}</span>
                                <span class="text-slate-500"> ({{ $swap->fromGroup->name }} ↔ {{ $swap->toGroup->name }})</span>
                            </div>
                            <div class="flex gap-2 ml-4 flex-shrink-0">
                                <form method="POST" action="{{ route('tenant.workspace.swaps.decide', [$tenant->slug, $swap]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    <button class="px-3 py-1.5 text-xs font-medium bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('tenant.workspace.swaps.decide', [$tenant->slug, $swap]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <button class="px-3 py-1.5 text-xs font-medium bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg transition">Reject</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Anonymous Reports --}}
        @if($sleepingReports->count())
            <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-700 rounded-2xl p-5">
                <h4 class="text-sm font-semibold text-red-800 dark:text-red-400 mb-3">Anonymous Reports ({{ $sleepingReports->count() }})</h4>
                <div class="space-y-2">
                    @foreach($sleepingReports as $report)
                        <div class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-red-100 dark:border-red-800">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                    Reported: <strong>{{ $report->reportedUser->name }}</strong>
                                    <span class="text-xs text-slate-400 ml-1">({{ $report->group->name }})</span>
                                </p>
                                <form method="POST" action="{{ route('tenant.workspace.reports.store', [$tenant->slug, $report->group]) }}" style="display:none">
                                    {{-- reviewed via inline patch below --}}
                                </form>
                                {{-- Mark reviewed button --}}
                                <form method="POST" action="{{ url($tenant->slug . '/workspace/reports/' . $report->id . '/reviewed') }}" class="hidden"></form>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-400">{{ $report->description }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-slate-400 mt-2">Reporter identities are not stored.</p>
            </div>
        @endif
    </div>
</x-tenant-layout>
