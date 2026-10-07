@php
    $input = 'w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500';
    $label = 'block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1';
    $options = array_values($options ?? []);
    $rows = max(3, min(6, count($options) + 1));
@endphp
<form method="POST" action="{{ $action }}" class="space-y-3">
    @csrf
    <input type="hidden" name="form" value="{{ $prefix }}">
    @if($method !== 'POST') @method($method) @endif
    <div class="grid grid-cols-4 gap-3">
        <div>
            <label for="{{ $prefix }}_at" class="{{ $label }}">At</label>
            <input id="{{ $prefix }}_at" name="at" required value="{{ $at }}" placeholder="2:47" class="{{ $input }} tabular-nums">
        </div>
        <div class="col-span-3">
            <label for="{{ $prefix }}_prompt" class="{{ $label }}">Question</label>
            <input id="{{ $prefix }}_prompt" name="prompt" required maxlength="1000" value="{{ $prompt }}" placeholder="Which of these counts as piping under B31.3?" class="{{ $input }}">
        </div>
    </div>
    <fieldset>
        <legend class="{{ $label }}">Options · select the correct one</legend>
        <div class="space-y-2">
            @for($i = 0; $i < $rows; $i++)
                <div class="flex items-center gap-2">
                    <input type="radio" name="correct" value="{{ $i }}" id="{{ $prefix }}_correct_{{ $i }}" @checked($correct !== null && (int) $correct === $i) aria-label="Option {{ $i + 1 }} is correct">
                    <label for="{{ $prefix }}_option_{{ $i }}" class="sr-only">Option {{ $i + 1 }}</label>
                    <input id="{{ $prefix }}_option_{{ $i }}" name="options[{{ $i }}]" maxlength="255" value="{{ $options[$i] ?? '' }}" placeholder="Option {{ $i + 1 }}" class="{{ $input }}">
                </div>
            @endfor
        </div>
    </fieldset>
    <div>
        <label for="{{ $prefix }}_explanation" class="{{ $label }}">Explanation shown after answering (optional)</label>
        <textarea id="{{ $prefix }}_explanation" name="explanation" rows="2" maxlength="1000" class="{{ $input }}">{{ $explanation }}</textarea>
    </div>
    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl">{{ $submit }}</button>
</form>
