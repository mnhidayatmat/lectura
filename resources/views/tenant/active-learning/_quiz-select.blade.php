<div>
    <label class="text-xs font-medium text-slate-500">{{ __('active_learning.quiz_link') }}</label>
    <select name="quiz_session_id" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500">
        <option value="">{{ __('active_learning.quiz_none') }}</option>
        @foreach($courseQuizzes as $quiz)
            <option value="{{ $quiz->id }}" @selected((int) ($selected ?? 0) === $quiz->id)>{{ $quiz->title }} ({{ $quiz->join_code }})</option>
        @endforeach
    </select>
    <p class="text-[10px] text-slate-400 mt-1">{{ __('active_learning.quiz_link_help') }}</p>
</div>
