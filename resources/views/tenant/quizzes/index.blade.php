<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Quizzes</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Organise quizzes into folders and launch live or offline sessions</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('tenant.quizzes.create', app('current_tenant')->slug) }}"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Quiz
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if($courses->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-10 text-center">
            <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">No quizzes yet.</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Create a course with sections first, then start creating quizzes.</p>
        </div>
    @elseif($sessionsByCourse->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-10 text-center">
            <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">No quizzes yet.</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Create your first quiz to get started.</p>
        </div>
    @else
        <div>
            <div class="space-y-4">
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
                                        <a href="{{ route('tenant.quizzes.edit', [app('current_tenant')->slug, $session]) }}" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 transition" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('tenant.quizzes.replay', [app('current_tenant')->slug, $session]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="p-1.5 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 text-slate-400 hover:text-emerald-600 transition" title="Replay — create new session with same questions">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('tenant.quizzes.destroy', [app('current_tenant')->slug, $session]) }}" onsubmit="return confirm('Delete this quiz and all its responses?')" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-slate-400 hover:text-red-500 transition" title="Delete">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
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

        </div>
    @endif

</x-tenant-layout>
