<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.quizzes.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Edit Quiz</h2>
                <p class="mt-1 text-sm text-slate-500">Modify questions before launching</p>
            </div>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('tenant.quizzes.update', [app('current_tenant')->slug, $session]) }}" x-data="quizBuilder()" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Settings --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-5">
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Quiz Title *</label>
                    <input type="text" name="title" required value="{{ old('title', $session->title) }}" placeholder="e.g. Week 3 Review Quiz" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Section *</label>
                    <select name="section_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($sections as $s)
                            <option value="{{ $s->id }}" @selected($s->id == old('section_id', $session->section_id))>{{ $s->course->code }} — {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Mode</label>
                    <select name="mode" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="formative" @selected(old('mode', $session->mode) === 'formative')>Formative (Practice)</option>
                        <option value="participation" @selected(old('mode', $session->mode) === 'participation')>Participation Mark</option>
                        <option value="graded" @selected(old('mode', $session->mode) === 'graded')>Graded Assessment</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 pb-2.5">
                        <input type="checkbox" name="is_anonymous" value="1" @checked(old('is_anonymous', $session->is_anonymous)) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-slate-600">Anonymous mode (hide names)</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Questions --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Questions (<span x-text="questions.length">0</span>)</h3>
                <div class="flex items-center gap-2">
                    <button type="button" @click="addQuestion('mcq')" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        MCQ
                    </button>
                    <button type="button" @click="addQuestion('true_false')" class="text-sm text-teal-600 hover:text-teal-700 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        True/False
                    </button>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <template x-if="questions.length === 0">
                    <p class="text-sm text-slate-400 text-center py-6">Add questions using the buttons above.</p>
                </template>

                <template x-for="(q, qi) in questions" :key="qi">
                    <div class="bg-slate-50 rounded-xl p-5 border border-slate-200">
                        <div class="flex items-start justify-between mb-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="q.type === 'mcq' ? 'bg-indigo-100 text-indigo-700' : 'bg-teal-100 text-teal-700'" x-text="q.type === 'mcq' ? 'MCQ' : 'True/False'"></span>
                            <button type="button" @click="questions.splice(qi, 1)" class="p-1 text-slate-400 hover:text-red-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <input type="hidden" :name="'questions['+qi+'][type]'" :value="q.type">

                        <textarea :name="'questions['+qi+'][text]'" x-model="q.text" rows="2" required placeholder="Enter your question..." class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 mb-3"></textarea>

                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div>
                                <label class="text-xs text-slate-500">Time (seconds)</label>
                                <input type="number" :name="'questions['+qi+'][time_limit]'" x-model="q.time_limit" min="5" max="300" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Points</label>
                                <input type="number" :name="'questions['+qi+'][points]'" x-model="q.points" min="0" step="0.5" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-xs text-slate-500">Explanation / Remark (shown after answering)</label>
                            <textarea :name="'questions['+qi+'][explanation]'" x-model="q.explanation" rows="2" placeholder="Explain why the correct answer is correct..." class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>

                        {{-- Options --}}
                        <div class="space-y-2">
                            <template x-for="(opt, oi) in q.options" :key="oi">
                                <div class="flex items-center gap-2">
                                    <input type="radio" :name="'q_correct_'+qi" :checked="opt.is_correct" @change="setCorrect(qi, oi)" class="text-indigo-600 focus:ring-indigo-500">
                                    <input type="hidden" :name="'questions['+qi+'][options]['+oi+'][is_correct]'" :value="opt.is_correct ? 1 : 0">
                                    <span class="w-7 h-7 rounded-md bg-white border border-slate-300 flex items-center justify-center text-xs font-bold text-slate-600" x-text="opt.label"></span>
                                    <input type="text" :name="'questions['+qi+'][options]['+oi+'][text]'" x-model="opt.text" required placeholder="Option text..." class="flex-1 px-3 py-1.5 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500" />
                                    <template x-if="q.type === 'mcq' && q.options.length > 2">
                                        <button type="button" @click="q.options.splice(oi, 1); relabel(qi)" class="p-1 text-slate-400 hover:text-red-500">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <template x-if="q.type === 'mcq' && q.options.length < 6">
                                <button type="button" @click="addOption(qi)" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium mt-1">+ Add option</button>
                            </template>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-2">Select the radio button next to the correct answer.</p>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('tenant.quizzes.index', app('current_tenant')->slug) }}" class="px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 rounded-xl transition">Cancel</a>
            <button type="submit" :disabled="questions.length === 0" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition disabled:opacity-50">
                Update Quiz
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        function quizBuilder() {
            return {
                questions: @json($existingQuestions),
                addQuestion(type) {
                    const labels = ['A', 'B', 'C', 'D'];
                    let options;
                    if (type === 'true_false') {
                        options = [
                            { label: 'A', text: 'True', is_correct: true },
                            { label: 'B', text: 'False', is_correct: false },
                        ];
                    } else {
                        options = labels.map((l, i) => ({ label: l, text: '', is_correct: i === 0 }));
                    }
                    this.questions.push({ type, text: '', time_limit: 30, points: 1, explanation: '', options });
                },
                addOption(qi) {
                    const next = String.fromCharCode(65 + this.questions[qi].options.length);
                    this.questions[qi].options.push({ label: next, text: '', is_correct: false });
                },
                setCorrect(qi, oi) {
                    this.questions[qi].options.forEach((o, i) => o.is_correct = i === oi);
                },
                relabel(qi) {
                    this.questions[qi].options.forEach((o, i) => o.label = String.fromCharCode(65 + i));
                },
            }
        }
    </script>
    @endpush
</x-tenant-layout>
