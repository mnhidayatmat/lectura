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
            // Weighted only when EVERY criterion has an explicit positive weight —
            // matches the controller logic so the live total reflects what gets saved.
            $isWeighted = $criteria->isNotEmpty() && $criteria->every(
                fn ($c) => $c->weightage !== null && (float) $c->weightage > 0
            );
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

    <form method="POST" action="{{ route('tenant.assessments.scores.store-manual', [$tenant->slug, $course, $assessment]) }}" enctype="multipart/form-data">
        @csrf

        @if($hasRubric)
            @php
                $totalMarks = (float) $assessment->total_marks;
                $cfgPayload = [
                    'isWeighted' => $isWeighted,
                    'totalMarks' => $totalMarks,
                    'criteria' => $criteria->map(fn ($c) => [
                        'id' => (string) $c->id,
                        'max' => (float) $c->max_marks,
                        'weight' => $c->weightage !== null ? (float) $c->weightage : null,
                    ])->values()->all(),
                ];
                // Pre-build initial values for the store: { studentId: { criterionId: value } }
                $initialValues = [];
                foreach ($students as $s) {
                    $row = [];
                    foreach ($criteria as $c) {
                        $existing = $existingCriteriaMarks[$s->id][$c->id] ?? null;
                        $row[(string) $c->id] = $existing !== null ? (float) $existing : '';
                    }
                    $initialValues[(string) $s->id] = $row;
                }
            @endphp

            <div>
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
                                    <th class="text-center px-3 py-3 font-medium text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wide whitespace-nowrap">
                                        <div class="font-bold text-slate-700 dark:text-slate-200">Answer Script</div>
                                        <div class="text-[10px] font-normal text-slate-400 mt-0.5 normal-case">PDF / Image</div>
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
                                                       x-model="$store.manualEntry.values['{{ $student->id }}']['{{ $criterion->id }}']"
                                                       min="0" max="{{ $criterion->max_marks }}" step="0.5"
                                                       placeholder="—"
                                                       class="w-20 px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-center focus:ring-2 focus:ring-indigo-500">
                                            </td>
                                        @endforeach
                                        <td class="px-3 py-2.5 text-center">
                                            <span class="text-sm font-bold"
                                                  :class="$store.manualEntry.rowPct('{{ $student->id }}') >= 70 ? 'text-emerald-600 dark:text-emerald-400'
                                                          : ($store.manualEntry.rowPct('{{ $student->id }}') >= 50 ? 'text-amber-600 dark:text-amber-400'
                                                          : 'text-slate-700 dark:text-slate-300')"
                                                  x-text="$store.manualEntry.rowTotal('{{ $student->id }}').toFixed(1)"></span>
                                            <span class="text-xs text-slate-400">/{{ number_format((float) $assessment->total_marks, 0) }}</span>
                                        </td>
                                        @include('tenant.assessments.scores.partials.answer-script-cell')
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-slate-50 dark:bg-slate-800/70 border-t-2 border-slate-200 dark:border-slate-700">
                                    <td></td>
                                    <td class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Class average</td>
                                    @foreach($criteria as $criterion)
                                        <td class="px-3 py-3 text-center">
                                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                                  x-text="$store.manualEntry.colAvg('{{ $criterion->id }}').toFixed(1)"></span>
                                            <span class="text-[10px] text-slate-400">/{{ rtrim(rtrim(number_format((float) $criterion->max_marks, 1), '0'), '.') }}</span>
                                            <div class="text-[10px] text-slate-400 mt-0.5">
                                                <span x-text="$store.manualEntry.colFilledCount('{{ $criterion->id }}')"></span> entered
                                            </div>
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-3 text-center">
                                        <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400" x-text="$store.manualEntry.classAvg().toFixed(1)"></span>
                                        <span class="text-[10px] text-slate-400">/{{ number_format((float) $assessment->total_marks, 0) }}</span>
                                        <div class="text-[10px] text-slate-400 mt-0.5">
                                            <span x-text="$store.manualEntry.filledRowCount()"></span> / {{ $students->count() }} entered
                                        </div>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
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
                                <th class="text-center px-5 py-3 font-medium text-slate-500 dark:text-slate-400">Answer Script</th>
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
                                    @include('tenant.assessments.scores.partials.answer-script-cell')
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

    @if($hasRubric)
        @push('scripts')
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.store('manualEntry', {
                        cfg: @json($cfgPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
                        values: @json($initialValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_FORCE_OBJECT),

                        _row(sid) {
                            return this.values[sid] || {};
                        },
                        _isFilled(v) {
                            return v !== '' && v !== null && v !== undefined && !isNaN(parseFloat(v));
                        },
                        _rowHasAnyValue(sid) {
                            const row = this._row(sid);
                            return Object.values(row).some(v => this._isFilled(v));
                        },

                        rowTotal(sid) {
                            const row = this._row(sid);
                            const cfg = this.cfg;
                            if (cfg.isWeighted) {
                                let t = 0;
                                for (const c of cfg.criteria) {
                                    const raw = row[c.id];
                                    const v = this._isFilled(raw) ? parseFloat(raw) : 0;
                                    if (c.max > 0 && c.weight && c.weight > 0) {
                                        t += (v / c.max) * (c.weight / 100) * cfg.totalMarks;
                                    }
                                }
                                return Math.min(t, cfg.totalMarks);
                            }
                            let s = 0;
                            for (const c of cfg.criteria) {
                                const raw = row[c.id];
                                s += this._isFilled(raw) ? parseFloat(raw) : 0;
                            }
                            return Math.min(s, cfg.totalMarks);
                        },
                        rowPct(sid) {
                            if (this.cfg.totalMarks <= 0) return 0;
                            return (this.rowTotal(sid) / this.cfg.totalMarks) * 100;
                        },

                        colAvg(cid) {
                            let sum = 0, n = 0;
                            for (const sid of Object.keys(this.values)) {
                                const v = this.values[sid][cid];
                                if (!this._isFilled(v)) continue;
                                sum += parseFloat(v);
                                n++;
                            }
                            return n > 0 ? sum / n : 0;
                        },
                        colFilledCount(cid) {
                            let n = 0;
                            for (const sid of Object.keys(this.values)) {
                                if (this._isFilled(this.values[sid][cid])) n++;
                            }
                            return n;
                        },
                        classAvg() {
                            let sum = 0, n = 0;
                            for (const sid of Object.keys(this.values)) {
                                if (!this._rowHasAnyValue(sid)) continue;
                                sum += this.rowTotal(sid);
                                n++;
                            }
                            return n > 0 ? sum / n : 0;
                        },
                        filledRowCount() {
                            let n = 0;
                            for (const sid of Object.keys(this.values)) {
                                if (this._rowHasAnyValue(sid)) n++;
                            }
                            return n;
                        },
                    });
                });
            </script>
        @endpush
    @endif
</x-tenant-layout>
