<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-bold text-slate-900">{{ $session->title }}</h2>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold
                            {{ $session->status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($session->status === 'reviewing' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700') }}">
                            <span class="w-1.5 h-1.5 rounded-full animate-pulse {{ $session->status === 'active' ? 'bg-emerald-500' : ($session->status === 'reviewing' ? 'bg-amber-500' : 'bg-indigo-500') }}"></span>
                            {{ ucfirst($session->status) }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-500">Join Code: <code class="bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded font-bold text-lg">{{ $session->join_code }}</code></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($session->status === 'waiting')
                    <form method="POST" action="{{ route('tenant.quizzes.start', [app('current_tenant')->slug, $session]) }}">
                        @csrf
                        <button class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            Start Quiz
                        </button>
                    </form>
                @elseif($session->status === 'active')
                    @if($phase === 'answering')
                        {{-- Step 1: close the question to reveal results --}}
                        <form method="POST" action="{{ route('tenant.quizzes.close', [app('current_tenant')->slug, $session]) }}">
                            @csrf
                            <button class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                Show Results
                            </button>
                        </form>
                    @elseif($phase === 'reveal')
                        {{-- Step 2: advance to next question --}}
                        <form method="POST" action="{{ route('tenant.quizzes.next', [app('current_tenant')->slug, $session]) }}">
                            @csrf
                            <button class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                                Next Question
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </button>
                        </form>
                    @endif
                @endif
                @if($session->isLive())
                    <form method="POST" action="{{ route('tenant.quizzes.end', [app('current_tenant')->slug, $session]) }}">
                        @csrf
                        <button onclick="return confirm('End this quiz?')" class="px-4 py-2.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 transition">End Quiz</button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">

        {{-- Stats bar --}}
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-2xl font-bold text-indigo-600">{{ $session->participants->count() }}</p>
                <p class="text-xs text-slate-500">Participants</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                @php
                    $displayNum = $currentIndex >= 0
                        ? $currentIndex + 1
                        : ($phase === 'reveal'
                            ? $revealIndex + 1
                            : ($session->status === 'waiting' ? 1 : $questions->count()));
                @endphp
                <p class="text-2xl font-bold text-slate-900">{{ $displayNum }} / {{ $questions->count() }}</p>
                <p class="text-xs text-slate-500">Question</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ $responseCount }}</p>
                <p class="text-xs text-slate-500">Responses</p>
            </div>
        </div>

        @if($session->status === 'waiting')
            {{-- Waiting room --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                <div class="w-20 h-20 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Waiting for students to join</h3>
                <p class="text-sm text-slate-500 mb-4">Share the join code with your students:</p>
                <div class="inline-block bg-indigo-50 rounded-xl px-8 py-4">
                    <p class="text-4xl font-extrabold text-indigo-700 tracking-[0.3em]">{{ $session->join_code }}</p>
                </div>
                <p class="text-sm text-slate-400 mt-6">{{ $session->participants->count() }} students joined. Click "Start Quiz" when ready.</p>
            </div>

        @elseif($phase === 'answering' && $activeQ)
            {{-- ANSWERING PHASE: no correct answer colors --}}
            @php $q = $activeQ->question; @endphp
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Accepting Answers
                        </span>
                        <span class="text-sm text-slate-500">Question {{ $currentIndex + 1 }} of {{ $questions->count() }}</span>
                    </div>
                    <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">{{ $q->time_limit_seconds }}s &middot; {{ $q->points }} pts</span>
                </div>
                <div class="p-6">
                    <p class="text-lg font-semibold text-slate-900 mb-6">{{ $q->text }}</p>

                    {{-- Distribution bars — no correct answer highlight yet --}}
                    <div class="space-y-3">
                        @foreach($q->options as $opt)
                            @php
                                $count = $activeQ->responses->where('selected_option_id', $opt->id)->count();
                                $totalResp = max($activeQ->responses->count(), 1);
                                $pct = round($count / $totalResp * 100);
                            @endphp
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold bg-slate-100 text-slate-600">{{ $opt->label }}</span>
                                <div class="flex-1">
                                    <div class="w-full bg-slate-100 rounded-lg h-10 relative overflow-hidden">
                                        <div class="h-full rounded-lg transition-all duration-500 bg-indigo-500" style="width: {{ $pct }}%"></div>
                                        <span class="absolute inset-0 flex items-center px-3 text-sm font-medium {{ $pct > 50 ? 'text-white' : 'text-slate-700' }}">{{ $opt->text }}</span>
                                    </div>
                                </div>
                                <span class="w-14 text-right text-sm font-bold text-slate-700">{{ $count }} <span class="text-xs font-normal text-slate-400">({{ $pct }}%)</span></span>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-400 mt-4 text-center">Correct answers will be revealed after you click "Show Results"</p>
                </div>
            </div>

        @elseif($phase === 'reveal' && $revealQ)
            {{-- REVEAL PHASE: show correct answer in green --}}
            @php $q = $revealQ->question; @endphp
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                            Results
                        </span>
                        <span class="text-sm text-slate-500">Question {{ $revealIndex + 1 }} of {{ $questions->count() }}</span>
                    </div>
                    <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">{{ $q->points }} pts</span>
                </div>
                <div class="p-6">
                    <p class="text-lg font-semibold text-slate-900 mb-6">{{ $q->text }}</p>

                    {{-- Distribution bars WITH correct answer highlight --}}
                    <div class="space-y-3">
                        @foreach($q->options as $opt)
                            @php
                                $count = $revealQ->responses->where('selected_option_id', $opt->id)->count();
                                $totalResp = max($revealQ->responses->count(), 1);
                                $pct = round($count / $totalResp * 100);
                            @endphp
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold {{ $opt->is_correct ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $opt->label }}</span>
                                <div class="flex-1">
                                    <div class="w-full bg-slate-100 rounded-lg h-10 relative overflow-hidden">
                                        <div class="h-full rounded-lg transition-all duration-500 {{ $opt->is_correct ? 'bg-emerald-500' : 'bg-slate-400' }}" style="width: {{ $pct }}%"></div>
                                        <span class="absolute inset-0 flex items-center px-3 text-sm font-medium {{ $pct > 50 ? 'text-white' : 'text-slate-700' }}">
                                            {{ $opt->text }}
                                            @if($opt->is_correct) <span class="ml-2 text-emerald-600">✓ Correct</span> @endif
                                        </span>
                                    </div>
                                </div>
                                <span class="w-14 text-right text-sm font-bold text-slate-700">{{ $count }} <span class="text-xs font-normal text-slate-400">({{ $pct }}%)</span></span>
                            </div>
                        @endforeach
                    </div>

                    @if($q->explanation)
                        <div class="mt-4 p-3 rounded-xl bg-amber-50 border border-amber-200">
                            <p class="text-xs font-semibold text-amber-700 mb-1">Explanation</p>
                            <p class="text-sm text-amber-800">{{ $q->explanation }}</p>
                        </div>
                    @endif
                </div>
            </div>

        @elseif($session->status === 'reviewing')
            <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center">
                <h3 class="text-xl font-bold text-slate-900 mb-2">All questions completed!</h3>
                <p class="text-sm text-slate-500">Click "End Quiz" to finalize scores and see results.</p>
            </div>
        @endif

        {{-- Leaderboard --}}
        @if($session->participants->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">{{ $session->is_anonymous ? 'Anonymous ' : '' }}Leaderboard</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($session->participants->sortByDesc('total_score')->take(10) as $i => $p)
                        <div class="px-6 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                                    {{ $i === 0 ? 'bg-amber-100 text-amber-700' : ($i === 1 ? 'bg-slate-200 text-slate-700' : ($i === 2 ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-500')) }}">
                                    {{ $i + 1 }}
                                </span>
                                <span class="text-sm font-medium text-slate-900">{{ $session->is_anonymous ? $p->display_name : $p->user->name }}</span>
                            </div>
                            <span class="text-sm font-bold text-indigo-600">{{ number_format($p->total_score, 1) }} pts</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-tenant-layout>
