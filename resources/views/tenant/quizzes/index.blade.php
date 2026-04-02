<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Quizzes</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Organise quizzes into folders and launch live or offline sessions</p>
            </div>
            <div class="flex items-center gap-2">
                {{-- New Folder button --}}
                <button x-data x-on:click="$dispatch('open-new-folder')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:border-slate-300 dark:hover:border-slate-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                    New Folder
                </button>
                <a href="{{ route('tenant.quizzes.create', app('current_tenant')->slug) }}"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Quiz
                </a>
            </div>
        </div>
    </x-slot>

    {{-- New Folder Modal --}}
    <div x-data="folderModal()" x-on:open-new-folder.window="open()"
         x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-black/50" @click="show = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6 space-y-5" @click.stop>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">New Folder</h3>
                <button @click="show = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('tenant.quizzes.folders.store', app('current_tenant')->slug) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Folder Name *</label>
                    <input type="text" name="name" required maxlength="100" placeholder="e.g. Midterm, Chapter 3, Week 1–5"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Colour</label>
                    <div class="flex gap-2 flex-wrap">
                        @foreach(['indigo','emerald','amber','rose','teal','purple','slate'] as $col)
                            @php $dots = ['indigo'=>'bg-indigo-500','emerald'=>'bg-emerald-500','amber'=>'bg-amber-500','rose'=>'bg-red-500','teal'=>'bg-teal-500','purple'=>'bg-purple-500','slate'=>'bg-slate-400']; @endphp
                            <label class="cursor-pointer">
                                <input type="radio" name="color" value="{{ $col }}" class="sr-only peer" {{ $col === 'indigo' ? 'checked' : '' }}>
                                <span class="w-8 h-8 rounded-full flex items-center justify-center ring-2 ring-transparent peer-checked:ring-offset-2 peer-checked:ring-slate-400 dark:peer-checked:ring-slate-500 transition {{ $dots[$col] }}"></span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Description <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input type="text" name="description" maxlength="255" placeholder="Short note about this folder"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="show = false" class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-800 transition">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition">Create Folder</button>
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if($courses->isEmpty() && $folders->isEmpty() && $unfoldered->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-10 text-center">
            <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">No quizzes yet.</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Create a folder to organise your quizzes, then add quiz sessions inside.</p>
        </div>
    @else
        <div class="space-y-4">

            {{-- ─── Folders ─── --}}
            @foreach($folders as $folder)
                @php $c = $folder->colorClasses(); @endphp
                <div x-data="{ expanded: true, editOpen: false }" class="rounded-2xl border {{ $c['border'] }} overflow-hidden">

                    {{-- Folder header --}}
                    <div class="flex items-center gap-3 px-5 py-3.5 {{ $c['bg'] }}">
                        {{-- Expand/collapse --}}
                        <button @click="expanded = !expanded" class="flex items-center gap-3 flex-1 min-w-0 text-left">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $c['dot'] }}"></span>
                            <svg class="w-5 h-5 {{ $c['text'] }} shrink-0 transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold {{ $c['text'] }} truncate">{{ $folder->name }}</p>
                                @if($folder->description)
                                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $folder->description }}</p>
                                @endif
                            </div>
                            <span class="ml-2 text-xs font-medium {{ $c['text'] }} shrink-0">
                                {{ $folder->sessions->count() }} {{ Str::plural('quiz', $folder->sessions->count()) }}
                            </span>
                        </button>
                        {{-- Folder actions --}}
                        <div class="flex items-center gap-1 shrink-0">
                            <button @click="editOpen = !editOpen" class="p-1.5 rounded-lg hover:bg-black/10 dark:hover:bg-white/10 transition {{ $c['text'] }}" title="Edit folder">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('tenant.quizzes.folders.destroy', [app('current_tenant')->slug, $folder]) }}"
                                  onsubmit="return confirm('Delete folder \'{{ addslashes($folder->name) }}\\'? Quizzes inside will move to Uncategorised.')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-lg hover:bg-black/10 dark:hover:bg-white/10 transition text-red-500" title="Delete folder">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Edit folder inline form --}}
                    <div x-show="editOpen" x-cloak class="border-t {{ $c['border'] }} px-5 py-4 bg-white dark:bg-slate-800">
                        <form method="POST" action="{{ route('tenant.quizzes.folders.update', [app('current_tenant')->slug, $folder]) }}" class="flex flex-wrap gap-3 items-end">
                            @csrf @method('PUT')
                            <div class="flex-1 min-w-40">
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Name</label>
                                <input type="text" name="name" value="{{ $folder->name }}" required maxlength="100"
                                    class="w-full rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Colour</label>
                                <div class="flex gap-1.5">
                                    @foreach(['indigo','emerald','amber','rose','teal','purple','slate'] as $col)
                                        @php $dots = ['indigo'=>'bg-indigo-500','emerald'=>'bg-emerald-500','amber'=>'bg-amber-500','rose'=>'bg-red-500','teal'=>'bg-teal-500','purple'=>'bg-purple-500','slate'=>'bg-slate-400']; @endphp
                                        <label class="cursor-pointer">
                                            <input type="radio" name="color" value="{{ $col }}" class="sr-only peer" {{ $folder->color === $col ? 'checked' : '' }}>
                                            <span class="w-6 h-6 rounded-full flex ring-2 ring-transparent peer-checked:ring-offset-2 peer-checked:ring-slate-400 transition {{ $dots[$col] }}"></span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex-1 min-w-40">
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Description</label>
                                <input type="text" name="description" value="{{ $folder->description }}" maxlength="255"
                                    class="w-full rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="editOpen = false" class="px-3 py-1.5 text-sm text-slate-500 hover:text-slate-700 transition">Cancel</button>
                                <button type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition">Save</button>
                            </div>
                        </form>
                    </div>

                    {{-- Sessions inside folder --}}
                    <div x-show="expanded" x-transition class="bg-white dark:bg-slate-800">
                        @if($folder->sessions->isEmpty())
                            <p class="text-sm text-slate-400 dark:text-slate-500 text-center py-5">No quizzes in this folder yet.
                                <a href="{{ route('tenant.quizzes.create', app('current_tenant')->slug) }}" class="text-indigo-600 hover:underline">Create one</a>
                                or move existing quizzes here.
                            </p>
                        @else
                            @php
                                $activeLive    = $folder->sessions->filter(fn($s) => $s->category === 'live' && $s->isLive());
                                $activeOffline = $folder->sessions->filter(fn($s) => $s->category === 'offline' && $s->status !== 'ended');
                                $allPast       = $folder->sessions->filter(fn($s) => $s->status === 'ended')->sortByDesc('ended_at');
                            @endphp

                            @if($activeLive->isNotEmpty())
                                <div class="border-t border-indigo-100 dark:border-indigo-800 bg-indigo-50/40 dark:bg-indigo-900/10">
                                    <div class="px-5 py-2.5 flex items-center gap-2">
                                        <span class="w-2 h-2 bg-indigo-500 rounded-full animate-pulse"></span>
                                        <span class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider">Live Now ({{ $activeLive->count() }})</span>
                                    </div>
                                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                                        @foreach($activeLive as $session)
                                            @include('tenant.quizzes._session-row-live', ['session' => $session])
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($activeOffline->isNotEmpty())
                                <div class="border-t border-teal-100 dark:border-teal-800 bg-teal-50/40 dark:bg-teal-900/10">
                                    <div class="px-5 py-2.5 flex items-center gap-2">
                                        <span class="w-2 h-2 bg-teal-500 rounded-full"></span>
                                        <span class="text-xs font-semibold text-teal-700 dark:text-teal-300 uppercase tracking-wider">Offline — Open ({{ $activeOffline->count() }})</span>
                                    </div>
                                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                                        @foreach($activeOffline as $session)
                                            @include('tenant.quizzes._session-row-offline', ['session' => $session])
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($allPast->isNotEmpty())
                                @include('tenant.quizzes._past-table', ['sessions' => $allPast, 'folders' => $folders])
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- ─── Uncategorised quizzes ─── --}}
            @if($unfoldered->isNotEmpty())
                @php
                    $activeLive    = $unfoldered->filter(fn($s) => $s->category === 'live' && $s->isLive());
                    $activeOffline = $unfoldered->filter(fn($s) => $s->category === 'offline' && $s->status !== 'ended');
                    $allPast       = $unfoldered->filter(fn($s) => $s->status === 'ended')->sortByDesc('ended_at');
                @endphp
                <div x-data="{ expanded: true }" class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <button @click="expanded = !expanded"
                        class="w-full flex items-center gap-3 px-5 py-3.5 bg-slate-50 dark:bg-slate-800/80 text-left hover:bg-slate-100 dark:hover:bg-slate-700/50 transition">
                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/></svg>
                        <span class="text-sm font-semibold text-slate-600 dark:text-slate-300 flex-1">Uncategorised</span>
                        <span class="text-xs text-slate-400">{{ $unfoldered->count() }} {{ Str::plural('quiz', $unfoldered->count()) }}</span>
                        <svg class="w-4 h-4 text-slate-400 transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="expanded" x-transition class="bg-white dark:bg-slate-800">
                        @if($activeLive->isNotEmpty())
                            <div class="border-t border-indigo-100 dark:border-indigo-800 bg-indigo-50/40 dark:bg-indigo-900/10">
                                <div class="px-5 py-2.5 flex items-center gap-2">
                                    <span class="w-2 h-2 bg-indigo-500 rounded-full animate-pulse"></span>
                                    <span class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider">Live Now ({{ $activeLive->count() }})</span>
                                </div>
                                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                                    @foreach($activeLive as $session)
                                        @include('tenant.quizzes._session-row-live', ['session' => $session])
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($activeOffline->isNotEmpty())
                            <div class="border-t border-teal-100 dark:border-teal-800 bg-teal-50/40 dark:bg-teal-900/10">
                                <div class="px-5 py-2.5 flex items-center gap-2">
                                    <span class="w-2 h-2 bg-teal-500 rounded-full"></span>
                                    <span class="text-xs font-semibold text-teal-700 dark:text-teal-300 uppercase tracking-wider">Offline — Open ({{ $activeOffline->count() }})</span>
                                </div>
                                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                                    @foreach($activeOffline as $session)
                                        @include('tenant.quizzes._session-row-offline', ['session' => $session])
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($allPast->isNotEmpty())
                            @include('tenant.quizzes._past-table', ['sessions' => $allPast, 'folders' => $folders])
                        @endif
                    </div>
                </div>
            @endif

            {{-- ─── Empty state (has courses but no quizzes at all) ─── --}}
            @if($folders->isEmpty() && $unfoldered->isEmpty())
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-10 text-center">
                    <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-3">
                        <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">No quizzes yet.</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Start with a folder, then create quizzes inside it.</p>
                </div>
            @endif
        </div>
    @endif

    <script>
        function folderModal() {
            return { show: false, open() { this.show = true } }
        }
    </script>
</x-tenant-layout>
