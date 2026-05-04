<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assessments.scores.index', [$tenant->slug, $course, $assessment]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Manual Score Entry</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $assessment->title }} &middot; Max {{ number_format($assessment->total_marks, 0) }} marks
                    @if($hasRubric)
                        &middot;
                        <span class="text-indigo-600 dark:text-indigo-400 font-medium">{{ $criteria->count() }} {{ Str::plural('question', $criteria->count()) }}</span>
                    @endif
                </p>
            </div>
        </div>
    </x-slot>

    @if($hasRubric)
        @php
            $isWeighted = $criteria->contains(fn ($c) => $c->weightage !== null && (float) $c->weightage > 0);
            $rubricMaxSum = (float) $criteria->sum('max_marks');
        @endphp

        <div class="mb-4 px-4 py-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 text-xs text-indigo-700 dark:text-indigo-300">
            @if($isWeighted)
                Each question's weight scales the final mark to <span class="font-semibold">{{ number_format((float) $assessment->total_marks, 0) }}</span>. Per-question scores are saved alongside the total.
            @else
                Total per student = sum of question scores (capped at <span class="font-semibold">{{ number_format((float) $assessment->total_marks, 0) }}</span>). Rubric question max sums to <span class="font-semibold">{{ number_format($rubricMaxSum, 1) }}</span>.
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('tenant.assessments.scores.store-manual', [$tenant->slug, $course, $assessment]) }}">
        @csrf

        @if($hasRubric)
            @php
                $totalMarks = (float) $assessment->total_marks;
                $weightingState = json_encode([
                    'isWeighted' => $isWeighted,
                    'totalMarks' => $totalMarks,
                    'criteria' => $criteria->map(fn ($c) => [
                        'id' => (int) $c->id,
                        'max' => (float) $c->max_marks,
                        'weight' => $c->weightage !== null ? (float) $c->weightage : null,
                    ])->values()->all(),
                ]);
            @endphp

            <div x-data='{
                cfg: @json($weightingState, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
                rowTotal(row) {
                    if (!row) return 0;
                    if (this.cfg.isWeighted) {
                        let t = 0;
                        for (const c of this.cfg.criteria) {
                            const v = parseFloat(row[c.id] || 0) || 0;
                            if (c.max > 0 && c.weight && c.weight > 0) {
                                t += (v / c.max) * (c.weight / 100) * this.cfg.totalMarks;
                            }
                        }
                        return Math.min(t, this.cfg.totalMarks);
                    }
                    let s = 0;
                    for (const c of this.cfg.criteria) {
                        s += parseFloat(row[c.id] || 0) || 0;
                    }
                    return Math.min(s, this.cfg.totalMarks);
                },
                rowPct(row) {
                    if (this.cfg.totalMarks <= 0) return 0;
                    return (this.rowTotal(row) / this.cfg.totalMarks) * 100;
                },
                marks: @json($students->mapWithKeys(function ($s) use ($criteria, $existingCriteriaMarks) {
                    $row = [];
                    foreach ($criteria as $c) {
                        $existing = $existingCriteriaMarks[$s->id][$c->id] ?? null;
                        $row[$c->id] = $existing !== null ? (float) $existing : '';
                    }
                    return [$s->id => $row];
                })),
            }'>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                    <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wide">#</th>
                                    <th class="text-left px-4 py-3 font-medium text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wide">Student</th>
                                    @foreach($criteria as $i => $criterion)
                                        <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wide whitespace-nowrap">
                                            <div class="font-bold text-slate-700 dark:text-slate-200">Q{{ $i + 1 }}</div>
                                            <div class="text-[10px] font-normal text-slate-400 mt-0.5 normal-case max-w-[140px] mx-auto truncate" title="{{ $criterion->title }}">{{ $criterion->title }}</div>
                                            <div class="text-[10px] font-semibold text-indigo-500 mt-0.5 normal-case">/ {{ rtrim(rtrim(number_format((float) $criterion->max_marks, 1), '0'), '.') }}@if($isWeighted && $criterion->weightage !== null) <span class="text-slate-400">· {{ rtrim(rtrim(number_format((float) $criterion->weightage, 1), '0'), '.') }}%</span>@endif</div>
                                        </th>
                                    @endforeach
                                    <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wide whitespace-nowrap">
                                        <div class="font-bold text-slate-700 dark:text-slate-200">Total</div>
                                        <div class="text-[10px] font-semibold text-indigo-500 mt-0.5">/ {{ number_format((float) $assessment->total_marks, 0) }}</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                @foreach($students as $i => $student)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                        <td class="px-4 py-2.5 text-xs text-slate-400">{{ $i + 1 }}</td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                @if($student->avatar_url)
                                                    <img src="{{ $student->avatar_url }}" class="w-6 h-6 rounded-full object-cover flex-shrink-0">
                                                @else
                                                    <div class="w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0">
                                                        <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400">{{ strtoupper(substr($student->name, 0, 1)) }}</span>
                                                    </div>
                                                @endif
                                                <span class="font-medium text-slate-900 dark:text-white text-xs">{{ $student->name }}</span>
                                            </div>
                                        </td>
                                        @foreach($criteria as $criterion)
                                            <td class="px-3 py-2.5 text-center">
                                                <input type="number"
                                                       name="criteria_marks[{{ $student->id }}][{{ $criterion->id }}]"
                                                       x-model="marks[{{ $student->id }}][{{ $criterion->id }}]"
                                                       min="0" max="{{ $criterion->max_marks }}" step="0.5"
                                                       placeholder="—"
                                                       class="w-20 px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-center focus:ring-2 focus:ring-indigo-500">
                                            </td>
                                        @endforeach
                                        <td class="px-3 py-2.5 text-center">
                                            <span class="text-sm font-bold"
                                                  :class="rowPct(marks[{{ $student->id }}]) >= 70 ? 'text-emerald-600 dark:text-emerald-400'
                                                          : (rowPct(marks[{{ $student->id }}]) >= 50 ? 'text-amber-600 dark:text-amber-400'
                                                          : 'text-slate-700 dark:text-slate-300')"
                                                  x-text="rowTotal(marks[{{ $student->id }}]).toFixed(1)"></span>
                                            <span class="text-xs text-slate-400">/{{ number_format((float) $assessment->total_marks, 0) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                <th class="text-left px-5 py-3 font-medium text-slate-500 dark:text-slate-400">#</th>
                                <th class="text-left px-5 py-3 font-medium text-slate-500 dark:text-slate-400">Student</th>
                                <th class="text-center px-5 py-3 font-medium text-slate-500 dark:text-slate-400">Marks (/ {{ number_format((float) $assessment->total_marks, 0) }})</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($students as $i => $student)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                    <td class="px-5 py-2.5 text-xs text-slate-400">{{ $i + 1 }}</td>
                                    <td class="px-5 py-2.5 font-medium text-slate-900 dark:text-white text-xs">{{ $student->name }}</td>
                                    <td class="px-5 py-2.5 text-center">
                                        <input type="number" name="marks[{{ $student->id }}]" value="{{ $existingScores[$student->id] ?? '' }}" min="0" max="{{ $assessment->total_marks }}" step="0.5" placeholder="—" class="w-24 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-center focus:ring-2 focus:ring-indigo-500">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Save All Marks</button>
            @if($hasRubric)
                <p class="text-xs text-slate-500 dark:text-slate-400">Rows with no scores entered are skipped.</p>
            @endif
        </div>
    </form>
</x-tenant-layout>
