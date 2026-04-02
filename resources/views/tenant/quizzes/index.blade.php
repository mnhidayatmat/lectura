<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Quizzes</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Organise quizzes into folders and launch live or offline sessions</p>
            </div>
            <div class="flex items-center gap-2">
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
                    <input type="text" name="name" required maxlength="100" placeholder="e.g. Midterm, Chapter 3, Week 1-5"
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
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Create a course with sections first, then start creating quizzes.</p>
        </div>
    @else
        <div x-data="{ view: 'course' }">
            {{-- View toggle --}}
            <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-1 mb-6 w-fit">
                <button @click="view = 'course'" :class="view === 'course' ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-white' : 'text-slate-500 hover:text-slate-700'" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition">
                    By Course
                </button>
                <button @click="view = 'folder'" :class="view === 'folder' ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-white' : 'text-slate-500 hover:text-slate-700'" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition">
                    By Folder
                </button>
            </div>

            {{-- ════════════════════════════════════════════════════════════ --}}
            {{-- VIEW: BY COURSE                                             --}}
            {{-- ════════════════════════════════════════════════════════════ --}}
            <div x-show="view === 'course'" x-transition class="space-y-4">
                @foreach($courses as $course)
                    @php
                        $courseQuizzes = $sessionsByCourse->get($course->id, collect());
                        $liveCount = $courseQuizzes->filter(fn($s) => $s->category === 'live' && $s->isLive())->count();
                    @endphp
                    <div x-data="{ expanded: true }" class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        {{-- Course header --}}
                        <button @click="expanded = !expanded"
                            class="w-full flex items-center gap-3 px-5 py-4 bg-slate-50 dark:bg-slate-800/80 text-left hover:bg-slate-100 dark:hover:bg-slate-700/50 transition">
                            <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $course->code }} — {{ $course->title }}</p>
                                <div class="flex items-center gap-3 mt-0.5">
                                    <span class="text-[11px] text-slate-400">{{ $courseQuizzes->count() }} {{ Str::plural('quiz', $courseQuizzes->count()) }}</span>
                                    @if($liveCount > 0)
                                        <span class="inline-flex items-center gap-1 text-[11px] text-indigo-600 dark:text-indigo-400 font-medium">
                                            <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-pulse"></span>
                                            {{ $liveCount }} live
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-slate-400 transition-transform shrink-0" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        {{-- Quizzes in this course --}}
                        <div x-show="expanded" x-transition class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700">
                            @forelse($courseQuizzes as $session)
                                <div class="flex items-center gap-4 px-5 py-3 hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                                    {{-- Status indicator --}}
                                    <div class="flex-shrink-0">
                                        @if($session->category === 'live' && $session->isLive())
                                            <span class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                                                <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full animate-pulse"></span>
                                            </span>
                                        @elseif($session->category === 'offline' && $session->status !== 'ended')
                                            <span class="w-9 h-9 rounded-xl bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </span>
                                        @else
                                            <span class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Quiz info --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ $session->title }}</p>
                                            @if($session->folder)
                                                <span class="text-[9px] font-medium px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 flex-shrink-0">{{ $session->folder->name }}</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3 mt-0.5">
                                            <span class="text-[11px] text-slate-400">{{ $session->section?->name }}</span>
                                            <span class="text-[11px] {{ $session->category === 'live' ? 'text-indigo-500' : 'text-teal-500' }} font-medium capitalize">{{ $session->category }}</span>
                                            <span class="text-[11px] text-slate-400">{{ $session->sessionQuestions->count() }} {{ Str::plural('question', $session->sessionQuestions->count()) }}</span>
                                            <span class="text-[11px] text-slate-400">{{ $session->participants->count() }} {{ Str::plural('participant', $session->participants->count()) }}</span>
                                        </div>
                                    </div>

                                    {{-- Status badge --}}
                                    <div class="flex-shrink-0">
                                        @if($session->category === 'live' && $session->isLive())
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">Live</span>
                                        @elseif($session->status === 'ended')
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400">Ended</span>
                                        @elseif($session->category === 'offline')
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400">Open</span>
                                        @else
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ ucfirst($session->status) }}</span>
                                        @endif
                                    </div>

                                    {{-- Actions --}}
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        @if($session->category === 'live' && $session->isLive())
                                            <a href="{{ route('tenant.quizzes.control', [app('current_tenant')->slug, $session]) }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">Control</a>
                                        @elseif($session->status === 'ended')
                                            <a href="{{ route('tenant.quizzes.results', [app('current_tenant')->slug, $session]) }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Results</a>
                                        @endif
                                        <a href="{{ route('tenant.quizzes.edit', [app('current_tenant')->slug, $session]) }}" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                    </div>
                                </div>
                            @empty
                                <div class="px-5 py-6 text-center">
                                    <p class="text-xs text-slate-400">No quizzes for this course yet.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ════════════════════════════════════════════════════════════ --}}
            {{-- VIEW: BY FOLDER (original view)                             --}}
            {{-- ════════════════════════════════════════════════════════════ --}}
            <div x-show="view === 'folder'" x-cloak x-transition class="space-y-4">
                {{-- Folders --}}
                @foreach($folders as $folder)
                    @php $c = $folder->colorClasses(); @endphp
                    <div x-data="{ expanded: true, editOpen: false }" class="rounded-2xl border {{ $c['border'] }} overflow-hidden">
                        {{-- Folder header --}}
                        <div class="flex items-center gap-3 px-5 py-3.5 {{ $c['bg'] }}">
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

                        {{-- Edit folder inline --}}
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

                {{-- Uncategorised --}}
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
        </div>
    @endif

    <script>
        function folderModal() {
            return { show: false, open() { this.show = true } }
        }
    </script>
</x-tenant-layout>
