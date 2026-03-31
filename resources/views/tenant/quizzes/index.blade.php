<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Quizzes</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create and manage live & offline quiz sessions</p>
            </div>
            <a href="{{ route('tenant.quizzes.create', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
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

    @if($courses->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-10 text-center">
            <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">No quizzes created yet.</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Create a quiz to engage your students.</p>
        </div>
    @else
        <div class="space-y-8">
            @foreach($courses as $course)
                @php
                    $courseSessions = $sessionsByCourse->get($course->id, collect());
                    $liveSessions = $courseSessions->where('category', 'live');
                    $offlineSessions = $courseSessions->where('category', 'offline');
                    $activeLive = $liveSessions->filter(fn($s) => $s->isLive());
                    $pastLive = $liveSessions->where('status', 'ended');
                    $activeOffline = $offlineSessions->filter(fn($s) => $s->status !== 'ended');
                    $pastOffline = $offlineSessions->where('status', 'ended');
                @endphp

                <div x-data="{ expanded: {{ $activeLive->isNotEmpty() || $activeOffline->isNotEmpty() ? 'true' : 'true' }} }">
                    {{-- Course Header --}}
                    <button @click="expanded = !expanded" class="w-full flex items-center justify-between px-5 py-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-600 transition mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center shrink-0">
                                <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400">{{ substr($course->code, 0, 2) }}</span>
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $course->code }} — {{ $course->name }}</p>
                                <p class="text-xs text-slate-400 dark:text-slate-500">
                                    {{ $courseSessions->count() }} {{ Str::plural('quiz', $courseSessions->count()) }}
                                    @if($activeLive->isNotEmpty())
                                        <span class="text-indigo-600 dark:text-indigo-400 font-medium">&middot; {{ $activeLive->count() }} live now</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-slate-400 transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="expanded" x-transition class="space-y-4 pl-2">
                        {{-- Active Live Sessions --}}
                        @if($activeLive->isNotEmpty())
                            <div class="bg-white dark:bg-slate-800 rounded-2xl border-2 border-indigo-200 dark:border-indigo-700 overflow-hidden">
                                <div class="px-5 py-3 border-b border-indigo-100 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-900/20 flex items-center gap-2">
                                    <span class="w-2 h-2 bg-indigo-500 rounded-full animate-pulse"></span>
                                    <h4 class="text-xs font-semibold text-indigo-900 dark:text-indigo-300 uppercase tracking-wider">Live Now ({{ $activeLive->count() }})</h4>
                                </div>
                                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                                    @foreach($activeLive as $session)
                                        @include('tenant.quizzes._session-row-live', ['session' => $session])
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Active Offline Sessions --}}
                        @if($activeOffline->isNotEmpty())
                            <div class="bg-white dark:bg-slate-800 rounded-2xl border-2 border-teal-200 dark:border-teal-700 overflow-hidden">
                                <div class="px-5 py-3 border-b border-teal-100 dark:border-teal-800 bg-teal-50/50 dark:bg-teal-900/20 flex items-center gap-2">
                                    <span class="w-2 h-2 bg-teal-500 rounded-full"></span>
                                    <h4 class="text-xs font-semibold text-teal-900 dark:text-teal-300 uppercase tracking-wider">Offline — Open ({{ $activeOffline->count() }})</h4>
                                </div>
                                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                                    @foreach($activeOffline as $session)
                                        @include('tenant.quizzes._session-row-offline', ['session' => $session])
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Past Sessions (both live & offline) --}}
                        @php $allPast = $pastLive->merge($pastOffline)->sortByDesc('ended_at'); @endphp
                        @if($allPast->isNotEmpty())
                            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                                <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700">
                                    <h4 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">History ({{ $allPast->count() }})</h4>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead><tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
                                            <th class="text-left px-5 py-2.5 font-medium text-slate-500 dark:text-slate-400">Quiz</th>
                                            <th class="text-center px-5 py-2.5 font-medium text-slate-500 dark:text-slate-400">Type</th>
                                            <th class="text-center px-5 py-2.5 font-medium text-slate-500 dark:text-slate-400">Mode</th>
                                            <th class="text-center px-5 py-2.5 font-medium text-slate-500 dark:text-slate-400">Participants</th>
                                            <th class="text-center px-5 py-2.5 font-medium text-slate-500 dark:text-slate-400">Date</th>
                                            <th class="text-right px-5 py-2.5 font-medium text-slate-500 dark:text-slate-400">Actions</th>
                                        </tr></thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                            @foreach($allPast as $session)
                                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                                    <td class="px-5 py-3">
                                                        <a href="{{ route('tenant.quizzes.results', [app('current_tenant')->slug, $session]) }}" class="block">
                                                            <p class="font-medium text-slate-900 dark:text-white">{{ $session->title }}</p>
                                                            <p class="text-xs text-slate-400 dark:text-slate-500">{{ $session->section->name }}</p>
                                                        </a>
                                                    </td>
                                                    <td class="px-5 py-3 text-center">
                                                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $session->category === 'live' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300' }}">
                                                            {{ ucfirst($session->category) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-5 py-3 text-center"><span class="text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-full">{{ ucfirst($session->mode) }}</span></td>
                                                    <td class="px-5 py-3 text-center font-medium text-slate-700 dark:text-slate-300">{{ $session->participants->count() }}</td>
                                                    <td class="px-5 py-3 text-center text-xs text-slate-400 dark:text-slate-500">{{ $session->ended_at?->format('d M Y') ?? $session->created_at->format('d M Y') }}</td>
                                                    <td class="px-5 py-3 text-right">
                                                        <div class="flex items-center justify-end gap-1">
                                                            <a href="{{ route('tenant.quizzes.results', [app('current_tenant')->slug, $session]) }}" class="p-1.5 text-slate-400 hover:text-indigo-600 transition" title="View Results">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                                            </a>
                                                            <form method="POST" action="{{ route('tenant.quizzes.replay', [app('current_tenant')->slug, $session]) }}" class="inline">
                                                                @csrf
                                                                <button type="submit" class="p-1.5 text-slate-400 hover:text-emerald-600 transition" title="Replay">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                                </button>
                                                            </form>
                                                            <a href="{{ route('tenant.quizzes.edit', [app('current_tenant')->slug, $session]) }}" class="p-1.5 text-slate-400 hover:text-indigo-600 transition" title="Edit">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                            </a>
                                                            <form method="POST" action="{{ route('tenant.quizzes.destroy', [app('current_tenant')->slug, $session]) }}" onsubmit="return confirm('Delete this quiz and all its data?')" class="inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="p-1.5 text-slate-400 hover:text-red-500 transition" title="Delete">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if($courseSessions->isEmpty())
                            <p class="text-sm text-slate-400 dark:text-slate-500 text-center py-4">No quizzes for this course yet.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
