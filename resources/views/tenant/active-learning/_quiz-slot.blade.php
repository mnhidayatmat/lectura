@if($quiz = $activity->quizSession)
    @php
        $ended = $quiz->status === 'ended';
        $hasRun = $quiz->started_at !== null;
        $mine = $quiz->lecturer_id === auth()->id();
        $statusLabel = match (true) {
            in_array($quiz->status, ['active', 'reviewing'], true) => __('active_learning.quiz_status_live'),
            $quiz->status === 'waiting' => __('active_learning.quiz_status_lobby'),
            $ended && $hasRun => __('active_learning.quiz_status_last_run'),
            default => __('active_learning.quiz_status_ready'),
        };
    @endphp
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 {{ $class ?? '' }}" @click.stop>
        <span class="w-9 h-9 rounded-lg bg-fuchsia-600 text-[#fff] flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </span>
        <div class="flex-1 min-w-0">
            <p class="text-[10px] font-semibold text-fuchsia-700 uppercase tracking-wider">{{ __('active_learning.quiz_time') }}</p>
            <p class="text-sm font-semibold text-fuchsia-950 truncate">{{ $quiz->title }}</p>
            <p class="text-xs text-fuchsia-800">
                {{ trans_choice('active_learning.quiz_questions', $quiz->session_questions_count ?? $quiz->sessionQuestions()->count()) }}
                @unless($ended)
                    · {{ __('active_learning.quiz_join_code') }} <span class="font-mono font-semibold">{{ $quiz->join_code }}</span>
                @endunless
                · {{ $statusLabel }}
            </p>
        </div>
        @if($mine)
            <div class="flex items-center gap-2 flex-shrink-0">
                @if($ended && $hasRun)
                    <a href="{{ route('tenant.quizzes.results', [$tenant->slug, $quiz]) }}" class="text-xs font-medium text-fuchsia-700 hover:text-fuchsia-900 underline-offset-2 hover:underline">{{ __('active_learning.quiz_last_results') }}</a>
                @endif
                <form method="POST" action="{{ route('tenant.active-learning.activities.quiz', [$tenant->slug, $course, $activity->active_learning_plan_id, $activity]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-fuchsia-600 hover:bg-fuchsia-700 text-[#fff] text-sm font-semibold transition">
                        {{ $ended ? __('active_learning.quiz_start') : __('active_learning.quiz_open') }}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M6 12h12"/></svg>
                    </button>
                </form>
            </div>
        @endif
    </div>
@endif
