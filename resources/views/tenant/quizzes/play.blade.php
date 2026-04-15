<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900">{{ $session->title }}</h2>
            <span class="text-sm font-semibold text-indigo-600">Score: <span x-data x-text="window._quizScore || '0'">0</span> pts</span>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto" x-data="quizPlayer(
        '{{ route('tenant.quizzes.student-state', [app('current_tenant')->slug, $session]) }}',
        '{{ route('tenant.quizzes.respond', [app('current_tenant')->slug, $session]) }}',
        '{{ csrf_token() }}'
    )" x-init="startPolling()">

        {{-- Waiting --}}
        <div x-show="status === 'waiting'" class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <div class="animate-spin w-8 h-8 border-2 border-indigo-600 border-t-transparent rounded-full"></div>
            </div>
            <h3 class="text-lg font-bold text-slate-900">Waiting for quiz to start...</h3>
            <p class="text-sm text-slate-500 mt-2">Your lecturer will start the quiz shortly.</p>
            <div class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-50 text-indigo-700">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-sm font-semibold"><span x-text="participantCount">0</span> joined</span>
            </div>
            <p class="text-xs text-slate-400 mt-4">Joined as: {{ $participant->display_name }}</p>
        </div>

        {{-- ANSWERING PHASE --}}
        <div x-show="phase === 'answering' && question" x-cloak class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs text-slate-500" x-text="'Time limit: ' + (question?.time_limit || 30) + 's'"></span>
                    <span class="text-xs font-medium" :class="answered ? 'text-emerald-600' : 'text-indigo-600'" x-text="answered ? 'Answered!' : (question?.points || 1) + ' pts'"></span>
                </div>

                <p class="text-lg font-semibold text-slate-900 mb-6" x-text="question?.text"></p>

                <div class="space-y-3">
                    <template x-for="opt in question?.options || []" :key="opt.id">
                        <button type="button"
                                @click="submitAnswer(opt.id)"
                                :disabled="answered"
                                :class="[
                                    'w-full flex items-center gap-4 p-4 rounded-xl border-2 text-left transition',
                                    selectedId === opt.id
                                        ? 'border-indigo-500 bg-indigo-50'
                                        : answered
                                            ? 'border-slate-200 bg-slate-50 opacity-50 cursor-not-allowed'
                                            : 'border-slate-200 hover:border-indigo-300 hover:bg-indigo-50/50'
                                ]">
                            <span class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold flex-shrink-0"
                                  :class="selectedId === opt.id ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-600'"
                                  x-text="opt.label"></span>
                            <span class="text-sm font-medium text-slate-700" x-text="opt.text"></span>
                        </button>
                    </template>
                </div>

                <div x-show="answered" x-cloak class="mt-4 p-3 rounded-xl text-center text-sm font-medium bg-indigo-50 text-indigo-700">
                    Answer submitted! Waiting for the lecturer to show results...
                </div>

                <div x-show="!answered" x-cloak class="mt-4 p-3 rounded-xl text-center text-sm text-slate-400">
                    Select your answer above
                </div>
            </div>

            {{-- Current standings (persisted from last reveal, shown while waiting after answering) --}}
            <template x-if="answered && leaderboard && leaderboard.length > 0">
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-900">Current Standings</h3>
                        <span class="text-xs text-slate-400 bg-slate-100 px-2 py-1 rounded-full">After last question</span>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <template x-for="(entry, i) in leaderboard" :key="i">
                            <div class="px-4 py-2.5 flex items-center justify-between"
                                 :class="entry.rank === myRank ? 'bg-indigo-50 border-l-4 border-indigo-400' : ''">
                                <div class="flex items-center gap-3">
                                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                                          :class="entry.rank === 1 ? 'bg-amber-100 text-amber-700' : (entry.rank === 2 ? 'bg-slate-200 text-slate-600' : (entry.rank === 3 ? 'bg-orange-100 text-orange-600' : 'bg-slate-100 text-slate-500'))"
                                          x-text="entry.rank === 1 ? '🥇' : (entry.rank === 2 ? '🥈' : (entry.rank === 3 ? '🥉' : entry.rank))"></span>
                                    <span class="text-sm font-medium" :class="entry.rank === myRank ? 'text-indigo-700 font-semibold' : 'text-slate-900'" x-text="entry.name"></span>
                                    <template x-if="entry.rank === myRank">
                                        <span class="text-xs bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded font-medium">You</span>
                                    </template>
                                </div>
                                <span class="text-sm font-bold" :class="entry.rank === myRank ? 'text-indigo-600' : 'text-slate-600'" x-text="entry.score.toFixed(1) + ' pts'"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- REVEAL PHASE: leaderboard + correct/wrong result --}}
        <div x-show="phase === 'reveal'" x-cloak class="space-y-4">

            {{-- Result card --}}
            <div class="bg-white rounded-2xl border-2 p-8 text-center"
                 :class="isCorrect === true ? 'border-emerald-300 bg-emerald-50' : (isCorrect === false ? 'border-red-300 bg-red-50' : 'border-slate-200')">
                <template x-if="isCorrect === true">
                    <div>
                        <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-9 h-9 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="text-2xl font-extrabold text-emerald-700">Correct!</p>
                        <p class="text-sm text-emerald-600 mt-1" x-text="'+' + lastPoints + ' pts'"></p>
                    </div>
                </template>
                <template x-if="isCorrect === false">
                    <div>
                        <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-9 h-9 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <p class="text-2xl font-extrabold text-red-600">Incorrect</p>
                        <p class="text-sm text-red-500 mt-1">Better luck next question!</p>
                    </div>
                </template>
                <template x-if="isCorrect === null">
                    <div>
                        <p class="text-xl font-bold text-slate-700">Time's up!</p>
                        <p class="text-sm text-slate-500 mt-1">No answer recorded</p>
                    </div>
                </template>

                <div class="mt-4 flex items-center justify-center gap-6 text-sm">
                    <div>
                        <p class="text-2xl font-extrabold text-slate-900" x-text="score + ' pts'"></p>
                        <p class="text-xs text-slate-400">Your Score</p>
                    </div>
                    <div class="w-px h-10 bg-slate-200"></div>
                    <div>
                        <p class="text-2xl font-extrabold text-indigo-600" x-text="myRank ? '#' + myRank : '–'"></p>
                        <p class="text-xs text-slate-400">Your Rank</p>
                    </div>
                </div>
            </div>

            {{-- Leaderboard --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-900">Rankings</h3>
                    <span class="text-xs text-slate-400">Top 10</span>
                </div>
                <div class="divide-y divide-slate-100">
                    <template x-for="(entry, i) in leaderboard || []" :key="i">
                        <div class="px-4 py-3 flex items-center justify-between transition-colors"
                             :class="entry.rank === myRank ? 'bg-indigo-50 border-l-4 border-indigo-500' : ''">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm"
                                      :class="entry.rank === 1 ? 'bg-amber-400 text-white text-base' : (entry.rank === 2 ? 'bg-slate-300 text-slate-700' : (entry.rank === 3 ? 'bg-orange-300 text-white' : 'bg-slate-100 text-slate-500'))"
                                      x-text="entry.rank === 1 ? '🥇' : (entry.rank === 2 ? '🥈' : (entry.rank === 3 ? '🥉' : entry.rank))"></span>
                                <div>
                                    <span class="text-sm font-medium block" :class="entry.rank === myRank ? 'text-indigo-700 font-semibold' : 'text-slate-900'" x-text="entry.name"></span>
                                    <template x-if="entry.rank === myRank">
                                        <span class="text-xs text-indigo-500 font-medium">← You</span>
                                    </template>
                                </div>
                            </div>
                            <span class="text-sm font-bold" :class="entry.rank === myRank ? 'text-indigo-600' : 'text-slate-600'" x-text="entry.score.toFixed(1) + ' pts'"></span>
                        </div>
                    </template>
                </div>
            </div>

            <p class="text-center text-xs text-slate-400 animate-pulse pb-4">Waiting for next question...</p>
        </div>

        {{-- Reviewing / Ended --}}
        <div x-show="status === 'reviewing' || status === 'ended'" x-cloak class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900">Quiz Complete!</h3>
            <p class="text-3xl font-extrabold text-indigo-600 mt-3" x-text="score + ' pts'"></p>
            <p class="text-sm text-slate-500 mt-2">Your final score</p>
            <template x-if="myRank">
                <p class="text-lg font-bold text-indigo-500 mt-1" x-text="'Final Rank: #' + myRank"></p>
            </template>
        </div>
    </div>

    @push('scripts')
    <script>
        function quizPlayer(stateUrl, respondUrl, csrfToken) {
            return {
                status: '{{ $session->status }}',
                phase: 'answering',
                question: null,
                answered: false,
                selectedId: null,
                score: 0,
                isCorrect: null,
                myRank: null,
                leaderboard: [],
                lastPoints: 0,
                participantCount: 0,
                polling: null,
                pollActive: false,

                startPolling() {
                    this.fetchState();
                    this.polling = setInterval(() => {
                        if (!this.pollActive) this.fetchState();
                    }, 3000);
                },

                async fetchState() {
                    this.pollActive = true;
                    try {
                        const res = await fetch(stateUrl, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const data = await res.json();

                        this.status = data.status;
                        this.phase  = data.phase || 'answering';
                        this.score  = data.score ?? this.score;
                        if (typeof data.participant_count === 'number') {
                            this.participantCount = data.participant_count;
                        }
                        window._quizScore = this.score;

                        if (this.phase === 'answering' && data.question) {
                            // New question arrived — reset answer state but keep leaderboard/rank
                            // so "Current Standings" remains visible while the student answers
                            if (!this.question || this.question.session_question_id !== data.question.session_question_id) {
                                this.question   = data.question;
                                this.answered   = data.answered;
                                this.selectedId = null;
                                this.isCorrect  = null;
                                // leaderboard & myRank intentionally kept from last reveal
                            } else if (!this.answered) {
                                // Only update from server if student hasn't clicked locally
                                this.answered = data.answered;
                            }
                        }

                        if (this.phase === 'reveal') {
                            this.isCorrect   = data.is_correct;
                            this.myRank      = data.my_rank;
                            this.leaderboard = data.leaderboard || [];
                            if (data.is_correct === true) {
                                this.lastPoints = data.score - (this.lastScoreBeforeReveal ?? 0);
                            }
                        }

                        if (this.phase === 'answering' && !this.answered) {
                            this.lastScoreBeforeReveal = this.score;
                        }

                        if (data.status === 'ended') {
                            clearInterval(this.polling);
                        }
                    } catch (e) {
                    } finally {
                        this.pollActive = false;
                    }
                },

                async submitAnswer(optionId) {
                    if (this.answered) return;
                    this.answered = true;
                    this.selectedId = optionId;
                    this.lastScoreBeforeReveal = this.score;

                    try {
                        await fetch(respondUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                session_question_id: this.question.session_question_id,
                                selected_option_id: optionId,
                                response_time_ms: 0,
                            }),
                        });
                    } catch (e) {
                        this.answered = false;
                        this.selectedId = null;
                    }
                },
            }
        }
    </script>
    @endpush
</x-tenant-layout>
