<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.quizzes.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $session->title }} — Results</h2>
                <p class="text-sm text-slate-500">{{ $session->section->course->code }} — {{ $session->section->name }} &middot; {{ $session->participants->count() }} participants</p>
            </div>
        </div>
    </x-slot>

    @php
        $totalQuestions = $session->sessionQuestions->count();
        $maxScore = $session->sessionQuestions->sum(fn($sq) => (float) $sq->question->points);
    @endphp

    <div class="space-y-6">
        {{-- Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-2xl font-bold text-slate-900">{{ $totalQuestions }}</p>
                <p class="text-xs text-slate-500">Questions</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-2xl font-bold text-indigo-600">{{ $leaderboard->count() }}</p>
                <p class="text-xs text-slate-500">Participants</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ $leaderboard->count() > 0 ? number_format($leaderboard->avg('total_score'), 1) : 0 }}</p>
                <p class="text-xs text-slate-500">Avg Score</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ number_format($maxScore, 1) }}</p>
                <p class="text-xs text-slate-500">Max Possible</p>
            </div>
        </div>

        {{-- Leaderboard --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Leaderboard</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 bg-slate-50/50">
                        <th class="text-center px-6 py-3 font-medium text-slate-500 w-16">Rank</th>
                        <th class="text-left px-6 py-3 font-medium text-slate-500">Student</th>
                        <th class="text-center px-6 py-3 font-medium text-slate-500">Score</th>
                        <th class="text-center px-6 py-3 font-medium text-slate-500">Accuracy</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($leaderboard as $i => $p)
                            @php
                                $correctCount = $p->responses->where('is_correct', true)->count();
                                $answeredCount = $p->responses->count();
                                $accuracy = $answeredCount > 0 ? round($correctCount / $answeredCount * 100) : 0;
                            @endphp
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-6 py-3 text-center">
                                    @if($i === 0)
                                        <span class="text-xl">🥇</span>
                                    @elseif($i === 1)
                                        <span class="text-xl">🥈</span>
                                    @elseif($i === 2)
                                        <span class="text-xl">🥉</span>
                                    @else
                                        <span class="text-sm font-medium text-slate-400">{{ $i + 1 }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">
                                            {{ strtoupper(substr($p->user->name, 0, 1)) }}
                                        </div>
                                        <span class="font-medium text-slate-900">{{ $session->is_anonymous ? $p->display_name : $p->user->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-center font-bold text-indigo-600">{{ number_format($p->total_score, 1) }} / {{ number_format($maxScore, 1) }}</td>
                                <td class="px-6 py-3 text-center">
                                    <div class="inline-flex items-center gap-2">
                                        <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full {{ $accuracy >= 70 ? 'bg-emerald-500' : ($accuracy >= 40 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $accuracy }}%"></div>
                                        </div>
                                        <span class="text-xs font-medium text-slate-600">{{ $accuracy }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Per-Question Analysis --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Question Analysis</h3>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($session->sessionQuestions as $i => $sq)
                    @php
                        $q = $sq->question;
                        $totalResp = $sq->responses->count();
                        $correctResp = $sq->responses->where('is_correct', true)->count();
                        $correctPct = $totalResp > 0 ? round($correctResp / $totalResp * 100) : 0;
                    @endphp
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between mb-2">
                            <p class="text-sm font-medium text-slate-900">Q{{ $i + 1 }}. {{ $q->text }}</p>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0 ml-4 {{ $correctPct >= 70 ? 'bg-emerald-100 text-emerald-700' : ($correctPct >= 40 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">{{ $correctPct }}% correct</span>
                        </div>
                        <div class="flex gap-2 mt-2">
                            @foreach($q->options as $opt)
                                @php $optCount = $sq->responses->where('selected_option_id', $opt->id)->count(); @endphp
                                <div class="flex-1 text-center">
                                    <div class="h-2 rounded-full mb-1 {{ $opt->is_correct ? 'bg-emerald-500' : 'bg-slate-200' }}"></div>
                                    <span class="text-xs {{ $opt->is_correct ? 'font-bold text-emerald-700' : 'text-slate-500' }}">{{ $opt->label }}: {{ $optCount }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-tenant-layout>
