<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.courses.show', [app('current_tenant')->slug, $course]) }}" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Whiteboards</h2>
                    <p class="text-sm text-slate-500">{{ $course->code }} — {{ $course->title }}</p>
                </div>
            </div>
            <button
                type="button"
                x-data
                @click="$dispatch('open-create-board')"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-fuchsia-600 rounded-lg hover:bg-fuchsia-700 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Whiteboard
            </button>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-8">
        {{-- Course-wide boards --}}
        <section>
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Course-wide</h3>
                <span class="text-xs text-slate-400">Visible to everyone enrolled</span>
            </div>
            @if($courseBoards->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                    No course-wide boards yet.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($courseBoards as $board)
                        @include('tenant.whiteboards._card', ['board' => $board])
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Group boards --}}
        <section>
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Group boards</h3>
                <span class="text-xs text-slate-400">Visible only to group members</span>
            </div>
            @if($groupBoards->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                    No group boards yet.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($groupBoards as $board)
                        @include('tenant.whiteboards._card', ['board' => $board])
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    {{-- Create Modal --}}
    <div
        x-data="{ open: false, scope: 'course', groupId: '' }"
        @open-create-board.window="open = true"
        @keydown.escape.window="open = false"
        x-cloak
    >
        <div x-show="open" class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6" @click.outside="open = false">
                <h3 class="text-lg font-semibold text-slate-900 mb-1">New Whiteboard</h3>
                <p class="text-xs text-slate-500 mb-4">Pick a scope and give it a title.</p>
                <form method="POST" action="{{ route('tenant.whiteboards.store', [app('current_tenant')->slug, $course]) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Title</label>
                        <input
                            type="text"
                            name="title"
                            required
                            maxlength="120"
                            placeholder="Brainstorm session…"
                            class="w-full rounded-lg border-slate-300 focus:border-fuchsia-500 focus:ring-fuchsia-500 text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Scope</label>
                        <div class="grid grid-cols-2 gap-2">
                            @if($isLecturer)
                                <label class="cursor-pointer">
                                    <input type="radio" name="scope" value="course" x-model="scope" class="sr-only peer" checked>
                                    <div class="rounded-lg border border-slate-200 p-3 text-center text-sm peer-checked:border-fuchsia-500 peer-checked:bg-fuchsia-50 peer-checked:text-fuchsia-700">
                                        <div class="font-semibold">Course-wide</div>
                                        <div class="text-[11px] text-slate-500">All students</div>
                                    </div>
                                </label>
                            @endif
                            <label class="cursor-pointer">
                                <input type="radio" name="scope" value="group" x-model="scope" class="sr-only peer" @if(!$isLecturer) checked @endif>
                                <div class="rounded-lg border border-slate-200 p-3 text-center text-sm peer-checked:border-fuchsia-500 peer-checked:bg-fuchsia-50 peer-checked:text-fuchsia-700">
                                    <div class="font-semibold">Group only</div>
                                    <div class="text-[11px] text-slate-500">Members only</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div x-show="scope === 'group'" x-transition>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Group</label>
                        @if($availableGroups->isEmpty())
                            <p class="text-xs text-amber-600">No groups available. Create groups in an Active Learning activity first.</p>
                        @else
                            <select
                                name="active_learning_group_id"
                                x-model="groupId"
                                :required="scope === 'group'"
                                class="w-full rounded-lg border-slate-300 focus:border-fuchsia-500 focus:ring-fuchsia-500 text-sm"
                            >
                                <option value="">— pick a group —</option>
                                @foreach($availableGroups as $g)
                                    <option value="{{ $g->id }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" @click="open = false" class="px-3 py-1.5 text-sm text-slate-600 hover:text-slate-900">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-fuchsia-600 rounded-lg hover:bg-fuchsia-700">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-tenant-layout>
