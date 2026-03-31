<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $session->title }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $session->section->course->code }} — {{ $session->section->name }}</p>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-400 dark:text-slate-500">Due: {{ $session->available_until->format('d M Y, H:i') }}</span>
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto" x-data="offlineQuiz()">
        <form method="POST" action="{{ route('tenant.quizzes.submit-offline', [app('current_tenant')->slug, $session]) }}">
            @csrf

            {{-- Quiz Info Banner --}}
            <div class="bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800 rounded-2xl p-4 mb-6 flex items-center gap-3">
                <svg class="w-5 h-5 text-teal-600 dark:text-teal-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm text-teal-700 dark:text-teal-300">
                    Answer all <strong>{{ $session->sessionQuestions->count() }}</strong> questions below, then click Submit at the bottom.
                    Total: <strong>{{ number_format($session->sessionQuestions->sum(fn($sq) => (float)$sq->question->points), 1) }} pts</strong>
                </p>
            </div>

            {{-- Questions --}}
            <div class="space-y-5">
                @foreach($session->sessionQuestions as $i => $sq)
                    @php $q = $sq->question; @endphp
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                            <span class="text-sm font-medium text-slate-500 dark:text-slate-400">Question {{ $i + 1 }}</span>
                            <span class="text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-full">{{ $q->points }} pts</span>
                        </div>
                        <div class="p-6">
                            <p class="text-base font-semibold text-slate-900 dark:text-white mb-5">{{ $q->text }}</p>

                            <div class="space-y-3">
                                @foreach($q->options as $opt)
                                    <label class="flex items-center gap-4 p-4 rounded-xl border-2 cursor-pointer transition"
                                           :class="answers['{{ $sq->id }}'] == '{{ $opt->id }}' ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20' : 'border-slate-200 dark:border-slate-600 hover:border-teal-300 dark:hover:border-teal-600'">
                                        <input type="radio" name="answers[{{ $sq->id }}]" value="{{ $opt->id }}"
                                               x-model="answers['{{ $sq->id }}']"
                                               class="sr-only">
                                        <span class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold flex-shrink-0 transition"
                                              :class="answers['{{ $sq->id }}'] == '{{ $opt->id }}' ? 'bg-teal-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'">
                                            {{ $opt->label }}
                                        </span>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $opt->text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Submit --}}
            <div class="mt-8 flex items-center justify-between">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Answered: <span class="font-bold text-slate-900 dark:text-white" x-text="answeredCount">0</span> / {{ $session->sessionQuestions->count() }}
                </p>
                <button type="submit"
                        onclick="return confirm('Submit your answers? You cannot change them after submission.')"
                        class="px-8 py-3 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                    Submit Quiz
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function offlineQuiz() {
            return {
                answers: {},
                get answeredCount() {
                    return Object.values(this.answers).filter(v => v).length;
                },
            }
        }
    </script>
    @endpush
</x-tenant-layout>
