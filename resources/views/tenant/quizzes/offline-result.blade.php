<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $session->title }} — Your Results</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $session->section->course->code }} — {{ $session->section->name }}</p>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto space-y-6">
        {{-- Score Summary --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-8 text-center">
            <div class="w-16 h-16 bg-teal-50 dark:bg-teal-900/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Quiz Complete!</h3>
            <p class="text-3xl font-extrabold text-teal-600 dark:text-teal-400 mt-3">{{ number_format($participant->total_score, 1) }} / {{ number_format($maxScore, 1) }} pts</p>
            @php
                $correctCount = $responses->where('is_correct', true)->count();
                $totalQ = $session->sessionQuestions->count();
                $accuracy = $totalQ > 0 ? round($correctCount / $totalQ * 100) : 0;
            @endphp
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">{{ $correctCount }} / {{ $totalQ }} correct ({{ $accuracy }}%)</p>
        </div>

        {{-- Question Review --}}
        <div class="space-y-4">
            @foreach($session->sessionQuestions as $i => $sq)
                @php
                    $q = $sq->question;
                    $response = $responses->get($sq->id);
                    $isCorrect = $response?->is_correct;
                @endphp
                <div class="bg-white dark:bg-slate-800 rounded-2xl border overflow-hidden {{ $isCorrect ? 'border-emerald-200 dark:border-emerald-800' : 'border-red-200 dark:border-red-800' }}">
                    <div class="px-6 py-3 border-b flex items-center justify-between {{ $isCorrect ? 'border-emerald-100 dark:border-emerald-900 bg-emerald-50/50 dark:bg-emerald-900/10' : 'border-red-100 dark:border-red-900 bg-red-50/50 dark:bg-red-900/10' }}">
                        <span class="text-sm font-medium {{ $isCorrect ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                            Q{{ $i + 1 }} — {{ $isCorrect ? 'Correct' : 'Incorrect' }}
                        </span>
                        <span class="text-xs font-medium {{ $isCorrect ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ number_format($response?->points_earned ?? 0, 1) }} / {{ $q->points }} pts
                        </span>
                    </div>
                    <div class="p-6">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white mb-4">{{ $q->text }}</p>
                        <div class="space-y-2">
                            @foreach($q->options as $opt)
                                @php
                                    $isSelected = $response && $response->selected_option_id == $opt->id;
                                    $isCorrectOption = $opt->is_correct;
                                @endphp
                                <div class="flex items-center gap-3 p-3 rounded-xl border {{ $isCorrectOption ? 'border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-900/20' : ($isSelected ? 'border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/20' : 'border-slate-200 dark:border-slate-700') }}">
                                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold {{ $isCorrectOption ? 'bg-emerald-500 text-white' : ($isSelected ? 'bg-red-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300') }}">
                                        {{ $opt->label }}
                                    </span>
                                    <span class="text-sm {{ $isCorrectOption ? 'font-semibold text-emerald-700 dark:text-emerald-400' : ($isSelected ? 'text-red-700 dark:text-red-400' : 'text-slate-600 dark:text-slate-400') }}">
                                        {{ $opt->text }}
                                    </span>
                                    @if($isSelected && !$isCorrectOption)
                                        <span class="ml-auto text-xs text-red-500 font-medium">Your answer</span>
                                    @elseif($isCorrectOption)
                                        <span class="ml-auto text-xs text-emerald-500 font-medium">Correct</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if($q->explanation)
                            <div class="mt-3 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                                <p class="text-xs font-semibold text-amber-700 dark:text-amber-400 mb-0.5">Explanation</p>
                                <p class="text-xs text-amber-800 dark:text-amber-300">{{ $q->explanation }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-tenant-layout>
