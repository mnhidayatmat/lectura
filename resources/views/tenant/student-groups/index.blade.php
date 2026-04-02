<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.courses.show', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Student Groups</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} — {{ $course->title }}</p>
                </div>
            </div>
            <a href="{{ route('tenant.student-groups.create', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Group Set
            </a>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div x-data="{ tab: 'all' }">
        {{-- Type tabs --}}
        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-1 mb-6 w-fit">
            <button @click="tab = 'all'" :class="tab === 'all' ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-white' : 'text-slate-500 hover:text-slate-700'" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition">
                All <span class="ml-1 text-slate-400">{{ $sets->count() }}</span>
            </button>
            @foreach(['lecture' => 'Lecture', 'lab' => 'Lab', 'tutorial' => 'Tutorial'] as $key => $label)
                <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-white' : 'text-slate-500 hover:text-slate-700'" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition">
                    {{ $label }} <span class="ml-1 text-slate-400">{{ $sets->where('type', $key)->count() }}</span>
                </button>
            @endforeach
        </div>

        @if($sets->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
                <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No group sets yet</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">Create your first group set to organise students into lecture, lab, or tutorial groups.</p>
                <a href="{{ route('tenant.student-groups.create', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create First Group Set
                </a>
            </div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($sets as $set)
                    <div x-show="tab === 'all' || tab === '{{ $set->type }}'" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden hover:shadow-md transition group">
                        <a href="{{ route('tenant.student-groups.show', [$tenant->slug, $course, $set]) }}" class="block p-5">
                            <div class="flex items-start justify-between mb-3">
                                <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full {{ match($set->type) { 'lecture' => 'bg-blue-100 text-blue-700', 'lab' => 'bg-emerald-100 text-emerald-700', 'tutorial' => 'bg-amber-100 text-amber-700' } }}">{{ ucfirst($set->type) }}</span>
                                <span class="text-[10px] text-slate-400">{{ $set->creation_method === 'random' ? 'Random' : 'Manual' }}</span>
                            </div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">{{ $set->name }}</h3>
                            @if($set->section)
                                <p class="text-[10px] text-indigo-500 dark:text-indigo-400 font-medium mb-1">{{ $set->section->name }}</p>
                            @endif
                            @if($set->description)
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-3 line-clamp-2">{{ $set->description }}</p>
                            @endif
                            <div class="flex items-center gap-3 text-[11px] text-slate-400">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ $set->groups_count }} {{ Str::plural('group', $set->groups_count) }}
                                </span>
                                <span>{{ $set->created_at->format('d M Y') }}</span>
                            </div>
                        </a>
                        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                            <a href="{{ route('tenant.student-groups.show', [$tenant->slug, $course, $set]) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-700 transition">Manage</a>
                            <form method="POST" action="{{ route('tenant.student-groups.destroy', [$tenant->slug, $course, $set]) }}" onsubmit="return confirm('Delete this group set and all its groups?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-slate-400 hover:text-red-500 transition opacity-0 group-hover:opacity-100">Delete</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-tenant-layout>
