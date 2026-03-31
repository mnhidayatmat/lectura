<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Live Sessions</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Active quizzes and learning sessions</p>
            </div>
            <a href="{{ route('tenant.session.join', $tenant->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                Join by Code
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">

        {{-- Live Active Learning Sessions --}}
        @if($activeSessions->isNotEmpty())
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Active Learning ({{ $activeSessions->count() }})</h3>
                </div>
                <div class="space-y-3">
                    @foreach($activeSessions as $session)
                        <a href="{{ route('tenant.session.live', [$tenant->slug, $session]) }}" class="group flex items-center gap-4 p-4 bg-white dark:bg-slate-800 rounded-2xl border-2 border-emerald-200 dark:border-emerald-700 hover:border-emerald-300 hover:shadow-md transition-all">
                            <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-emerald-700 dark:group-hover:text-emerald-400 transition">{{ $session->plan->title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $session->plan->course->code }} — {{ $session->plan->course->title }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg">
                                    <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span>
                                    Join
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Live Quizzes --}}
        @if($liveQuizzes->isNotEmpty())
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2 h-2 bg-indigo-500 rounded-full animate-pulse"></span>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Live Quizzes ({{ $liveQuizzes->count() }})</h3>
                </div>
                <div class="space-y-3">
                    @foreach($liveQuizzes as $quiz)
                        <a href="{{ route('tenant.quizzes.play', [$tenant->slug, $quiz]) }}" class="group flex items-center gap-4 p-4 bg-white dark:bg-slate-800 rounded-2xl border-2 border-indigo-200 dark:border-indigo-700 hover:border-indigo-300 hover:shadow-md transition-all">
                            <div class="w-12 h-12 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-indigo-700 dark:group-hover:text-indigo-400 transition">{{ $quiz->title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $quiz->section->course->code }} — {{ $quiz->section->course->title }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs text-slate-400 dark:text-slate-500">Code: <code class="bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 px-1 rounded font-bold">{{ $quiz->join_code }}</code></span>
                                <span class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded-lg">
                                    <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span>
                                    Play
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Open Offline Quizzes --}}
        @if($offlineQuizzes->isNotEmpty())
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2 h-2 bg-teal-500 rounded-full"></span>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Offline Quizzes ({{ $offlineQuizzes->count() }})</h3>
                </div>
                <div class="space-y-3">
                    @foreach($offlineQuizzes as $quiz)
                        <a href="{{ route('tenant.quizzes.play', [$tenant->slug, $quiz]) }}" class="group flex items-center gap-4 p-4 bg-white dark:bg-slate-800 rounded-2xl border border-teal-200 dark:border-teal-700 hover:border-teal-300 hover:shadow-md transition-all">
                            <div class="w-12 h-12 rounded-xl bg-teal-100 dark:bg-teal-900/40 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-teal-700 dark:group-hover:text-teal-400 transition">{{ $quiz->title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $quiz->section->course->code }} — Due {{ $quiz->available_until->format('d M, H:i') }}</p>
                            </div>
                            <span class="px-3 py-1.5 text-xs font-semibold text-teal-700 dark:text-teal-300 bg-teal-50 dark:bg-teal-900/30 rounded-lg shrink-0">
                                Take Quiz
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Empty State --}}
        @if($activeSessions->isEmpty() && $liveQuizzes->isEmpty() && $offlineQuizzes->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-12 text-center">
                <div class="w-16 h-16 bg-slate-100 dark:bg-slate-700 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No live sessions right now</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Check back during class or join with a code from your lecturer.</p>
                <a href="{{ route('tenant.session.join', $tenant->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                    Join by Code
                </a>
            </div>
        @endif
    </div>
</x-tenant-layout>
