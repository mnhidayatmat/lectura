{{-- The Judge Score Card (Student Guide, Appendix B) for staff at the Grand Final. --}}
@php($scores = $judge->scores->keyBy('criterion'))
@php($pitches = $judge->pitchScores->keyBy('consultant_id'))
@php($consultants = $game->members()->with('user')->where('status', 'consultant')->get())
@php($locked = $judge->submitted_at !== null || $session->status !== 'scoring')

@if($judge->submitted_at)
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ __('tender.card_in') }}</div>
@elseif($session->status !== 'scoring')
    <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">{{ __('tender.card_waiting') }}</div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if(! $judge->declared_at && ! $locked)
        <label class="flex items-start gap-3 bg-white rounded-2xl border border-slate-200 p-4 text-sm text-slate-700">
            <input type="checkbox" name="declare" value="1" required class="mt-0.5 rounded border-slate-300 text-indigo-600">
            <span>{{ __('tender.declaration') }}</span>
        </label>
    @endif

    @foreach($session->criteria() as $key => $max)
        @php($s = $scores[$key] ?? null)
        @php($rules = $game->rules())
        @php($min = $rules->minScore($key))
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <div class="flex items-baseline justify-between gap-3">
                <p class="font-medium text-slate-900">{{ $rules->label($key) }}</p>
                <p class="text-xs text-slate-500">{{ __('tender.max', ['max' => $max + 0]) }}</p>
            </div>
            <div class="mt-3 grid sm:grid-cols-2 gap-3">
                @foreach(['a' => 'A', 'b' => 'B'] as $field => $company)
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">{{ $game->companyName($company) }}</p>
                        @if($rules->isOphir())
                            <input type="number" name="scores[{{ $key }}][level_{{ $field }}]" min="0" max="{{ $rules->maxScore($max) }}" step="1"
                                   value="{{ old("scores.$key.level_$field", $s?->{'level_'.$field}) }}" @disabled($locked)
                                   class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm tabular-nums">
                        @else
                        <div class="flex gap-1.5">
                            @for($level = $min; $level <= 4; $level++)
                                <label class="flex-1">
                                    <input type="radio" class="peer sr-only" name="scores[{{ $key }}][level_{{ $field }}]" value="{{ $level }}"
                                           @checked(old("scores.$key.level_$field", $s?->{'level_'.$field}) == $level && old("scores.$key.level_$field", $s?->{'level_'.$field}) !== null) @disabled($locked)>
                                    <span class="block text-center py-2 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 cursor-pointer peer-checked:bg-indigo-600 peer-checked:border-indigo-600 peer-checked:text-[#fff] peer-disabled:cursor-default">{{ $level }}</span>
                                </label>
                            @endfor
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>
            <input name="scores[{{ $key }}][evidence]" maxlength="500" value="{{ old("scores.$key.evidence", $s?->evidence) }}" @disabled($locked)
                   placeholder="{{ __('tender.evidence_placeholder') }}"
                   class="mt-3 w-full px-3 py-2 rounded-xl border border-slate-300 text-sm">
        </div>
    @endforeach

    @if($consultants->isNotEmpty())
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <p class="font-medium text-slate-900">{{ __('tender.pitches') }}</p>
            <p class="text-xs text-slate-500 mb-3">{{ __('tender.pitch_hint') }}</p>
            @foreach($consultants as $c)
                @php($p = $pitches[$c->user_id] ?? null)
                <div class="py-2 border-t border-slate-100 first:border-0">
                    <p class="text-sm font-medium text-slate-800">{{ $c->user->name }}</p>
                    <div class="mt-1 grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach(['relevant', 'credible', 'standards', 'communication'] as $f)
                            <label class="text-xs text-slate-500">{{ __('tender.pitch_'.$f) }}
                                <select name="pitches[{{ $c->user_id }}][{{ $f }}]" @disabled($locked) class="mt-1 w-full px-2 py-1.5 rounded-lg border border-slate-300 text-sm">
                                    <option value="">—</option>
                                    @for($v = 1; $v <= 4; $v++)
                                        <option value="{{ $v }}" @selected((int) $p?->{$f} === $v)>{{ $v }}</option>
                                    @endfor
                                </select>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @unless($locked)
        <div class="flex flex-wrap gap-2">
            <button class="px-4 py-2.5 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-xl">{{ __('tender.save_draft') }}</button>
            <button name="submit" value="1" onclick="return confirm(@js(__('tender.submit_confirm')))"
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl">{{ __('tender.submit_card') }}</button>
        </div>
    @endunless
</form>
