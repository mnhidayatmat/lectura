<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900">{{ $session->title }}</h2>
            <span class="text-sm font-semibold text-indigo-600">Score: <span x-data x-text="window._quizScore || '0'">0</span> pts</span>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto" x-data="quizPlayer('{{ route('tenant.quizzes.student-state', [app('current_tenant')->slug, $session]) }}', '{{ route('tenant.quizzes.respond', [app('current_tenant')->slug, $session]) }}', '{{ csrf_token() }}')" x-init="startPolling()">

        {{-- Waiting --}}
        <div x-show="status === 'waiting'" class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <div class="animate-spin w-8 h-8 border-2 border-indigo-600 border-t-transparent rounded-full"></div>
            </div>
            <h3 class="text-lg font-bold text-slate-900">Waiting for quiz to start...</h3>
            <p class="text-sm text-slate-500 mt-2">Your lecturer will start the quiz shortly.</p>
            <p class="text-xs text-slate-400 mt-4">Joined as: {{ $participant->display_name }}</p>
        </div>

        {{-- Question --}}
        <div x-show="status === 'active' && question" x-cloak class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                {{-- Timer --}}
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs text-slate-500" x-text="'Time limit: ' + (question?.time_limit || 30) + 's'"></span>
                    <span class="text-xs font-medium" :class="answered ? 'text-emerald-600' : 'text-indigo-600'" x-text="answered ? 'Answered!' : (question?.points || 1) + ' pts'"></span>
                </div>

                {{-- Question text --}}
                <p class="text-lg font-semibold text-slate-900 mb-6" x-text="question?.text"></p>

                {{-- Options --}}
                <div class="space-y-3">
                    <template x-for="opt in question?.options || []" :key="opt.id">
                        <button type="button"
                                @click="submitAnswer(opt.id)"
                                :disabled="answered"
                                :class="[
                                    'w-full flex items-center gap-4 p-4 rounded-xl border-2 text-left transition',
                                    selectedId === opt.id && lastCorrect === true ? 'border-emerald-500 bg-emerald-50' :
                                    selectedId === opt.id && lastCorrect === false ? 'border-red-500 bg-red-50' :
                                    selectedId === opt.id ? 'border-indigo-500 bg-indigo-50' :
                                    answered ? 'border-slate-200 bg-slate-50 opacity-50' :
                                    'border-slate-200 hover:border-indigo-300 hover:bg-indigo-50/50'
                                ]">
                            <span class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold flex-shrink-0"
                                  :class="selectedId === opt.id ? (lastCorrect ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white') : 'bg-slate-100 text-slate-600'"
                                  x-text="opt.label"></span>
                            <span class="text-sm font-medium text-slate-700" x-text="opt.text"></span>
                        </button>
                    </template>
                </div>

                {{-- Feedback --}}
                <div x-show="answered" x-cloak class="mt-4 p-3 rounded-xl text-center text-sm font-medium"
                     :class="lastCorrect ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                     x-text="lastCorrect ? 'Correct! +' + lastPoints + ' pts' : 'Incorrect'">
                </div>

                {{-- Explanation --}}
                <div x-show="answered && explanation" x-cloak class="mt-3 p-3 rounded-xl bg-amber-50 border border-amber-200">
                    <p class="text-xs font-semibold text-amber-700 mb-1">Explanation</p>
                    <p class="text-sm text-amber-800" x-text="explanation"></p>
                </div>
            </div>
        </div>

        {{-- Reviewing / Ended --}}
        <div x-show="status === 'reviewing' || status === 'ended'" x-cloak class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900">Quiz Complete!</h3>
            <p class="text-3xl font-extrabold text-indigo-600 mt-3" x-text="score + ' pts'"></p>
            <p class="text-sm text-slate-500 mt-2">Your final score</p>
        </div>
    </div>

    @push('scripts')
    <script>
        function quizPlayer(stateUrl, respondUrl, csrfToken) {
            return {
                status: '{{ $session->status }}',
                question: null,
                answered: false,
                selectedId: null,
                lastCorrect: null,
                lastPoints: 0,
                explanation: null,
                score: 0,
                polling: null,

                startPolling() {
                    this.fetchState();
                    this.polling = setInterval(() => this.fetchState(), 2000);
                },

                async fetchState() {
                    try {
                        const res = await fetch(stateUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        this.status = data.status;
                        this.score = data.score;
                        window._quizScore = data.score;

                        if (data.question && (!this.question || this.question.session_question_id !== data.question.session_question_id)) {
                            this.question = data.question;
                            this.answered = data.answered;
                            this.selectedId = null;
                            this.lastCorrect = null;
                            this.explanation = null;
                        }

                        if (data.status === 'ended') {
                            clearInterval(this.polling);
                        }
                    } catch (e) {}
                },

                async submitAnswer(optionId) {
                    if (this.answered) return;
                    this.selectedId = optionId;

                    try {
                        const res = await fetch(respondUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                            body: JSON.stringify({
                                session_question_id: this.question.session_question_id,
                                selected_option_id: optionId,
                                response_time_ms: 0,
                            }),
                        });
                        const data = await res.json();
                        this.answered = true;
                        this.lastCorrect = data.is_correct;
                        this.lastPoints = data.points_earned || 0;
                        this.explanation = data.explanation || null;
                        this.score = parseFloat(this.score) + parseFloat(this.lastPoints);
                        window._quizScore = this.score;
                    } catch (e) {
                        this.selectedId = null;
                    }
                },
            }
        }
    </script>
    @endpush
</x-tenant-layout>
