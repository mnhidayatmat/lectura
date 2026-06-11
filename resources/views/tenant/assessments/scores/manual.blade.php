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

    @if ($errors->any())
        <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-400 text-sm">
            <p class="font-semibold mb-1">Some entries couldn't be saved:</p>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless ($driveConnected)
        <div class="mb-4 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-amber-800 dark:text-amber-300 text-sm flex items-start gap-3">
            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <div class="flex-1">
                <p class="font-semibold">Connect your Google Drive to upload answer scripts.</p>
                <p class="text-xs mt-0.5">Marking scripts are stored only in your Drive — not on the server. Marks save normally without it.</p>
            </div>
            <a href="{{ route('tenant.settings', $tenant->slug) }}"
               class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold transition flex-shrink-0">
                Open Settings
            </a>
        </div>
    @endunless

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

            {{-- Desktop table --}}
            <div class="hidden lg:block">
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
                                                       min="0" max="{{ $criterion->max_marks }}" step="0.01"
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

            {{-- Mobile cards --}}
            <div class="lg:hidden space-y-3">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 px-4 py-3 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Class average</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <span x-text="$store.manualEntry.filledRowCount()"></span> / {{ $students->count() }} entered
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400" x-text="$store.manualEntry.classAvg().toFixed(1)"></p>
                        <p class="text-[10px] text-slate-400">/ {{ number_format((float) $assessment->total_marks, 0) }}</p>
                    </div>
                </div>

                @foreach($students as $i => $student)
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div class="px-4 py-3 flex items-center gap-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-800/40">
                            <span class="text-[10px] font-semibold text-slate-400 w-5 text-right flex-shrink-0">{{ $i + 1 }}</span>
                            @if($student->avatar_url)
                                <img src="{{ $student->avatar_url }}" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                            @else
                                <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ strtoupper(substr($student->name, 0, 1)) }}</span>
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm text-slate-900 dark:text-white truncate">{{ $student->name }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-base font-bold leading-tight"
                                   :class="$store.manualEntry.rowPct('{{ $student->id }}') >= 70 ? 'text-emerald-600 dark:text-emerald-400'
                                           : ($store.manualEntry.rowPct('{{ $student->id }}') >= 50 ? 'text-amber-600 dark:text-amber-400'
                                           : 'text-slate-700 dark:text-slate-300')"
                                   x-text="$store.manualEntry.rowTotal('{{ $student->id }}').toFixed(1)"></p>
                                <p class="text-[10px] text-slate-400 leading-tight">/ {{ number_format((float) $assessment->total_marks, 0) }}</p>
                            </div>
                        </div>

                        <div class="px-4 py-3 divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($criteria as $j => $criterion)
                                <div class="flex items-center gap-3 py-2 first:pt-0 last:pb-0">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Q{{ $j + 1 }}</span>
                                            <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ $criterion->title }}</span>
                                        </div>
                                        <p class="text-[10px] text-indigo-500 mt-0.5">
                                            / {{ rtrim(rtrim(number_format((float) $criterion->max_marks, 1), '0'), '.') }}
                                            @if($isWeighted && $criterion->weightage !== null)
                                                <span class="text-slate-400">· {{ rtrim(rtrim(number_format((float) $criterion->weightage, 1), '0'), '.') }}%</span>
                                            @endif
                                        </p>
                                    </div>
                                    <input type="number"
                                           inputmode="decimal"
                                           name="criteria_marks[{{ $student->id }}][{{ $criterion->id }}]"
                                           x-model="$store.manualEntry.values['{{ $student->id }}']['{{ $criterion->id }}']"
                                           min="0" max="{{ $criterion->max_marks }}" step="0.01"
                                           placeholder="—"
                                           class="w-20 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-base font-semibold text-center focus:ring-2 focus:ring-indigo-500 flex-shrink-0">
                                </div>
                            @endforeach
                        </div>

                        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-800/40">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">Answer Script <span class="font-normal normal-case text-slate-400">· PDF / Image</span></p>
                            @include('tenant.assessments.scores.partials.answer-script-cell', ['wrapAs' => 'div'])
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Desktop table (no rubric) --}}
            <div class="hidden lg:block bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
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
                                        <input type="number" name="marks[{{ $student->id }}]" value="{{ $existingScores[$student->id] ?? '' }}" min="0" max="{{ $assessment->total_marks }}" step="0.01" placeholder="—" class="w-24 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-center focus:ring-2 focus:ring-indigo-500">
                                    </td>
                                    @include('tenant.assessments.scores.partials.answer-script-cell')
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile cards (no rubric) --}}
            <div class="lg:hidden space-y-3">
                @foreach($students as $i => $student)
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div class="px-4 py-3 flex items-center gap-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-800/40">
                            <span class="text-[10px] font-semibold text-slate-400 w-5 text-right flex-shrink-0">{{ $i + 1 }}</span>
                            @if($student->avatar_url)
                                <img src="{{ $student->avatar_url }}" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                            @else
                                <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ strtoupper(substr($student->name, 0, 1)) }}</span>
                                </div>
                            @endif
                            <p class="font-semibold text-sm text-slate-900 dark:text-white truncate min-w-0 flex-1">{{ $student->name }}</p>
                        </div>
                        <div class="px-4 py-3 flex items-center gap-3">
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300 flex-1">
                                Marks <span class="text-slate-400">/ {{ number_format((float) $assessment->total_marks, 0) }}</span>
                            </label>
                            <input type="number" inputmode="decimal" name="marks[{{ $student->id }}]" value="{{ $existingScores[$student->id] ?? '' }}" min="0" max="{{ $assessment->total_marks }}" step="0.01" placeholder="—" class="w-24 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-base font-semibold text-center focus:ring-2 focus:ring-indigo-500 flex-shrink-0">
                        </div>
                        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-800/40">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">Answer Script <span class="font-normal normal-case text-slate-400">· PDF / Image</span></p>
                            @include('tenant.assessments.scores.partials.answer-script-cell', ['wrapAs' => 'div'])
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Sticky save bar on mobile, inline on desktop --}}
        <div class="mt-4 lg:relative sticky bottom-0 z-10 lg:bg-transparent bg-white/95 dark:bg-slate-900/95 backdrop-blur lg:backdrop-blur-none -mx-4 lg:mx-0 px-4 lg:px-0 py-3 lg:py-0 border-t lg:border-t-0 border-slate-200 dark:border-slate-700 flex items-center gap-3">
            <button type="submit" class="flex-1 lg:flex-none px-6 py-3 lg:py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">Save All Marks</button>
            @if($hasRubric)
                <p class="hidden sm:block text-xs text-slate-500 dark:text-slate-400">Rows with no scores entered are skipped.</p>
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
                                const weightSum = cfg.criteria.reduce(
                                    (s, c) => s + ((c.weight && c.weight > 0) ? c.weight : 0), 0
                                );
                                if (weightSum <= 0) return 0;
                                let t = 0;
                                for (const c of cfg.criteria) {
                                    const raw = row[c.id];
                                    const v = this._isFilled(raw) ? parseFloat(raw) : 0;
                                    if (c.max > 0 && c.weight && c.weight > 0) {
                                        t += (v / c.max) * (c.weight / weightSum) * cfg.totalMarks;
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
