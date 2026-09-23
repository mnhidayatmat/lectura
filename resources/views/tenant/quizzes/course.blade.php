<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('tenant.quizzes.index', app('current_tenant')->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition flex-shrink-0">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-amber-600 dark:text-amber-400">{{ $course->code }}</p>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white truncate">Quizzes</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ $course->title }}</p>
                </div>
            </div>
            <a href="{{ route('tenant.quizzes.create', [app('current_tenant')->slug, 'course' => $course->id]) }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Quiz
            </a>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @php
        $liveCount = $sessions->filter(fn ($s) => $s->category === 'live' && $s->isLive())->count();
    @endphp

    @if($sessions->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-12 text-center">
            <div class="w-14 h-14 bg-amber-50 dark:bg-amber-900/30 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">No quizzes for this course yet.</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Create your first quiz to get started.</p>
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-3">
                <h3 class="font-semibold text-slate-900 dark:text-white">All Quizzes</h3>
                <span class="text-xs text-slate-400">{{ $sessions->count() }} {{ Str::plural('quiz', $sessions->count()) }}</span>
                @if($liveCount > 0)
                    <span class="inline-flex items-center gap-1 text-[11px] text-indigo-600 dark:text-indigo-400 font-medium">
                        <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-pulse"></span>
                        {{ $liveCount }} live
                    </span>
                @endif
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($sessions as $session)
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
                @endforeach
            </div>
        </div>
    @endif
</x-tenant-layout>
