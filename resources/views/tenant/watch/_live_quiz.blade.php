{{-- "Join the quiz" for an episode whose linked live quiz is running for the student --}}
@foreach($episodes as $episode)
    @continue(empty($episode['live_quiz']))
    <a href="{{ route('tenant.quizzes.play', [$tenant->slug, $episode['live_quiz']['id']]) }}"
       class="mb-4 flex items-center gap-3 rounded-2xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 hover:border-fuchsia-300 hover:shadow-md transition dark:border-fuchsia-800 dark:bg-fuchsia-900/20">
        <span class="w-9 h-9 rounded-lg bg-fuchsia-600 text-[#fff] flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </span>
        <span class="flex-1 min-w-0">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-fuchsia-700 dark:text-fuchsia-300">{{ $episode['live_quiz']['status'] === 'waiting' ? 'Quiz lobby open' : 'Quiz live now' }} · EP {{ $episode['episode_number'] }}</span>
            <span class="block text-sm font-semibold text-fuchsia-950 dark:text-fuchsia-100 truncate">{{ $episode['live_quiz']['title'] }}</span>
        </span>
        <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-fuchsia-600 text-[#fff] text-sm font-semibold flex-shrink-0">Join the quiz</span>
    </a>
@endforeach
