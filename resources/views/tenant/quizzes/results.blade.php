<x-tenant-layout>
    @php
        $tenantSlug = app('current_tenant')->slug;
        $pctTone = fn (?int $pct) => $pct === null
            ? 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'
            : ($pct >= 70 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
            : ($pct >= 40 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'));
        $barTone = fn (int $pct) => $pct >= 70 ? 'bg-emerald-500' : ($pct >= 40 ? 'bg-amber-500' : 'bg-red-500');
        $runDate = $session->ended_at ?? $session->started_at;
    @endphp

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <a href="{{ route('tenant.quizzes.course', [$tenantSlug, $session->section->course_id]) }}"
                   class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition flex-shrink-0" title="Back to quizzes">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-amber-600 dark:text-amber-400">{{ $session->section->course->code }} · {{ $session->section->name }}</p>
                    <h2 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white truncate">{{ $session->title }}</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Results
                        @if($runDate) · {{ $runDate->format('d M Y, H:i') }} @endif
                        · {{ ucfirst($session->mode) }}
                        @if($session->is_anonymous) · Anonymous @endif
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                @foreach($linkedPlans as $plan)
                    <a href="{{ route('tenant.active-learning.show', [$tenantSlug, $plan->course_id, $plan->id]) }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-fuchsia-700 dark:text-fuchsia-300 bg-fuchsia-50 dark:bg-fuchsia-900/20 border border-fuchsia-200 dark:border-fuchsia-800 rounded-xl hover:bg-fuchsia-100 dark:hover:bg-fuchsia-900/30 transition"
                       title="Back to the active learning plan">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span class="truncate max-w-[14rem]">{{ $plan->title }}</span>
                    </a>
                @endforeach
                @if($isOwner)
                    <form method="POST" action="{{ route('tenant.quizzes.replay', [$tenantSlug, $session]) }}" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <button type="submit" :disabled="busy" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-medium rounded-xl transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            {{ $neverRun ? 'Start quiz' : 'Run again' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- Summary --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 sm:p-5">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Participants</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $summary['participants'] }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500">{{ $summary['questions'] }} {{ Str::plural('question', $summary['questions']) }} · max {{ rtrim(rtrim(number_format($summary['max_score'], 1), '0'), '.') }} pts</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 sm:p-5">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Average score</p>
                <p class="text-2xl font-bold mt-1 {{ $summary['avg_pct'] === null ? 'text-slate-400' : ($summary['avg_pct'] >= 70 ? 'text-emerald-600 dark:text-emerald-400' : ($summary['avg_pct'] >= 40 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400')) }}">
                    {{ $summary['avg_pct'] === null ? '—' : $summary['avg_pct'].'%' }}
                </p>
                <p class="text-xs text-slate-400 dark:text-slate-500">of the maximum score</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 sm:p-5">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Completion</p>
                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $summary['completion_pct'] === null ? '—' : $summary['completion_pct'].'%' }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500">questions answered</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 sm:p-5">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Hardest question</p>
                @if($summary['hardest'])
                    <a href="#question-{{ $summary['hardest']->number }}" class="block">
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">Q{{ $summary['hardest']->number }}</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">{{ $summary['hardest']->correct_pct }}% correct</p>
                    </a>
                @else
                    <p class="text-2xl font-bold text-slate-400 mt-1">—</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500">no answers yet</p>
                @endif
            </div>
        </div>

        @if($summary['participants'] === 0)
            {{-- Empty state --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-10 text-center">
                <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                @if($neverRun)
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">This quiz has not been run yet.</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">It is kept as a master copy. Start it to open a fresh lobby with a join code — results appear here after the run.</p>
                @else
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">No one took part in this run.</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Run it again and share the join code with your students.</p>
                @endif
            </div>
        @else
            {{-- Re-teach --}}
            @if($reteach->isNotEmpty())
                <div class="bg-red-50 dark:bg-red-900/10 rounded-2xl border border-red-200 dark:border-red-900/40 p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        <h3 class="font-semibold text-red-800 dark:text-red-300">Worth re-teaching</h3>
                        <span class="text-xs text-red-600/80 dark:text-red-400/80">below 60% correct</span>
                    </div>
                    <ul class="space-y-2">
                        @foreach($reteach as $item)
                            <li>
                                <a href="#question-{{ $item->number }}" class="flex items-start gap-3 rounded-xl bg-white/70 dark:bg-slate-800/60 px-3 py-2 hover:bg-white dark:hover:bg-slate-800 transition">
                                    <span class="text-xs font-bold text-red-700 dark:text-red-400 mt-0.5 flex-shrink-0">Q{{ $item->number }}</span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block text-sm text-slate-800 dark:text-slate-200">{{ $item->question->text }}</span>
                                        @if($item->top_wrong)
                                            <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">Most common wrong answer: <strong>{{ $item->top_wrong['label'] }} – {{ $item->top_wrong['text'] }}</strong> ({{ $item->top_wrong['count'] }})</span>
                                        @endif
                                    </span>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0 {{ $pctTone($item->correct_pct) }}">{{ $item->correct_pct }}%</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Leaderboard --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
                    <h3 class="font-semibold text-slate-900 dark:text-white">Leaderboard</h3>
                    @if($session->is_anonymous)
                        <span class="text-[11px] text-slate-400">Anonymous quiz — display names only</span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 text-slate-500 dark:text-slate-400">
                                <th class="text-center px-4 py-3 font-medium w-14">Rank</th>
                                <th class="text-left px-4 py-3 font-medium">Student</th>
                                <th class="text-center px-4 py-3 font-medium">Score</th>
                                <th class="text-center px-4 py-3 font-medium">Correct</th>
                                <th class="text-center px-4 py-3 font-medium hidden sm:table-cell">Accuracy</th>
                                <th class="text-center px-4 py-3 font-medium hidden md:table-cell">Avg time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($leaderboard as $row)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                    <td class="px-4 py-3 text-center">
                                        @if($row->rank <= 3)
                                            <span class="text-lg" title="Rank {{ $row->rank }}">{{ ['🥇', '🥈', '🥉'][$row->rank - 1] }}</span>
                                        @else
                                            <span class="text-sm font-medium text-slate-400">{{ $row->rank }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-xs font-bold text-indigo-700 dark:text-indigo-300 flex-shrink-0">
                                                {{ Str::upper(Str::substr($row->name, 0, 1)) }}
                                            </div>
                                            <span class="font-medium text-slate-900 dark:text-white truncate">{{ $row->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                                        {{ rtrim(rtrim(number_format($row->score, 1), '0'), '.') }}<span class="text-xs font-normal text-slate-400"> / {{ rtrim(rtrim(number_format($summary['max_score'], 1), '0'), '.') }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                        {{ $row->correct }} / {{ $summary['questions'] }}
                                        @if($row->unanswered > 0)
                                            <span class="block text-[10px] text-slate-400">{{ $row->unanswered }} unanswered</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center hidden sm:table-cell">
                                        <div class="inline-flex items-center gap-2">
                                            <div class="w-16 h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full {{ $barTone($row->accuracy) }}" style="width: {{ $row->accuracy }}%"></div>
                                            </div>
                                            <span class="text-xs font-medium text-slate-600 dark:text-slate-300">{{ $row->accuracy }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-xs text-slate-500 dark:text-slate-400 hidden md:table-cell">{{ $row->avg_seconds !== null ? $row->avg_seconds.' s' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Per-question breakdown --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                <h3 class="font-semibold text-slate-900 dark:text-white">Question by question</h3>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($questionStats as $stat)
                    @php $q = $stat->question; @endphp
                    <div id="question-{{ $stat->number }}" class="px-5 sm:px-6 py-5 scroll-mt-20">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">
                                <span class="text-slate-400 mr-1">Q{{ $stat->number }}.</span>{{ $q->text }}
                            </p>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0 {{ $pctTone($stat->correct_pct) }}">
                                {{ $stat->correct_pct === null ? 'No answers' : $stat->correct_pct.'% correct' }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                            {{ str_replace('_', ' ', ucfirst($q->question_type)) }}
                            · {{ $stat->answered }} answered @if($stat->skipped > 0) · {{ $stat->skipped }} skipped @endif
                            @if($stat->avg_seconds !== null) · avg {{ $stat->avg_seconds }} s @endif
                            · {{ $q->time_limit_seconds }} s limit
                        </p>

                        @if($stat->options->isNotEmpty())
                            <div class="mt-3 space-y-1.5">
                                @foreach($stat->options as $option)
                                    @php $share = $stat->answered > 0 ? (int) round($option['count'] / $stat->answered * 100) : 0; @endphp
                                    <div class="flex items-center gap-3">
                                        <span class="w-6 h-6 rounded-md text-xs font-bold flex items-center justify-center flex-shrink-0 {{ $option['is_correct'] ? 'bg-emerald-500 text-[#fff]' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300' }}">{{ $option['label'] }}</span>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2 text-xs">
                                                <span class="truncate {{ $option['is_correct'] ? 'font-semibold text-emerald-700 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300' }}">
                                                    {{ $option['text'] }} @if($option['is_correct']) ✓ @endif
                                                </span>
                                                <span class="text-slate-400 flex-shrink-0">{{ $option['count'] }} · {{ $share }}%</span>
                                            </div>
                                            <div class="h-1.5 mt-1 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full {{ $option['is_correct'] ? 'bg-emerald-500' : 'bg-slate-400 dark:bg-slate-500' }}" style="width: {{ $share }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @elseif($stat->text_answers->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach($stat->text_answers as $answer => $count)
                                    <span class="text-xs px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $answer }} @if($count > 1)<strong>×{{ $count }}</strong>@endif</span>
                                @endforeach
                            </div>
                        @endif

                        @if($q->explanation)
                            <div class="mt-3 p-2.5 rounded-lg bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-900/40">
                                <p class="text-xs font-semibold text-amber-700 dark:text-amber-400 mb-0.5">Explanation</p>
                                <p class="text-xs text-amber-800 dark:text-amber-300">{{ $q->explanation }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-tenant-layout>
