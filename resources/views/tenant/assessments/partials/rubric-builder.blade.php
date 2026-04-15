@php
    // Seed the Alpine builder with: old() input, then the existing rubric, else one empty criterion.
    $rubricSeed = old('criteria');
    if (! $rubricSeed && isset($assessment) && $assessment->rubric) {
        $rubricSeed = $assessment->rubric->criteria->map(fn ($c) => [
            'title' => $c->title,
            'description' => $c->description,
            'max_marks' => (float) $c->max_marks,
            'levels' => $c->levels->map(fn ($l) => [
                'label' => $l->label,
                'description' => $l->description,
                'marks' => (float) $l->marks,
            ])->values()->all(),
        ])->values()->all();
    }
    if (! $rubricSeed) {
        $rubricSeed = [];
    }
@endphp

<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6"
     x-data="{
         enabled: {{ count($rubricSeed) > 0 ? 'true' : 'false' }},
         criteria: @js($rubricSeed),
         addCriterion() {
             this.criteria.push({ title: '', description: '', max_marks: 10, levels: [] });
         },
         removeCriterion(i) {
             this.criteria.splice(i, 1);
             if (this.criteria.length === 0) this.enabled = false;
         },
         addLevel(i) {
             this.criteria[i].levels.push({ label: '', description: '', marks: 0 });
         },
         removeLevel(i, j) {
             this.criteria[i].levels.splice(j, 1);
         },
         totalMax() {
             return this.criteria.reduce((s, c) => s + (parseFloat(c.max_marks) || 0), 0);
         },
         enable() {
             this.enabled = true;
             if (this.criteria.length === 0) this.addCriterion();
         }
     }">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Grading Rubric
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 uppercase tracking-wide">Optional</span>
            </h3>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                Break the total mark down into criteria with optional rating scales. When grading a submission, clicking a scale level fills in the marks for that criterion.
            </p>
        </div>
        <template x-if="enabled">
            <div class="text-right flex-shrink-0 ml-4">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Rubric Total</p>
                <p class="text-lg font-bold text-indigo-600 dark:text-indigo-400" x-text="totalMax()"></p>
            </div>
        </template>
    </div>

    {{-- Disabled / empty state --}}
    <div x-show="!enabled" class="rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-6 text-center">
        <p class="text-sm text-slate-500 dark:text-slate-400">No rubric attached. Grading will use a single total-marks input.</p>
        <button type="button" @click="enable()"
                class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add a rubric
        </button>
    </div>

    {{-- Builder --}}
    <div x-show="enabled" class="space-y-4">
        <template x-for="(c, i) in criteria" :key="i">
            <div class="rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/60 dark:bg-slate-700/20 p-4">
                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300 text-xs font-bold flex items-center justify-center flex-shrink-0" x-text="i + 1"></div>

                    <div class="flex-1 space-y-3">
                        <div class="grid sm:grid-cols-[1fr_120px] gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1">Criterion title *</label>
                                <input type="text" :name="`criteria[${i}][title]`" x-model="c.title" required
                                       placeholder="e.g. Problem analysis"
                                       class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1">Max marks *</label>
                                <input type="number" :name="`criteria[${i}][max_marks]`" x-model="c.max_marks" required min="0" step="0.5"
                                       class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1">Description <span class="normal-case font-normal text-slate-400">(optional)</span></label>
                            <textarea :name="`criteria[${i}][description]`" x-model="c.description" rows="2"
                                      placeholder="What is being evaluated…"
                                      class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs focus:ring-2 focus:ring-indigo-500 transition resize-none"></textarea>
                        </div>

                        {{-- Level scale --}}
                        <div class="rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide">Rating scale <span class="normal-case font-normal text-slate-400">(optional)</span></p>
                                <button type="button" @click="addLevel(i)"
                                        class="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 hover:underline">+ Add level</button>
                            </div>
                            <template x-if="c.levels.length === 0">
                                <p class="text-[11px] text-slate-400 text-center py-2">No levels yet. Add one to give the grader a quick scale to click.</p>
                            </template>
                            <div class="space-y-2">
                                <template x-for="(l, j) in c.levels" :key="j">
                                    <div class="grid grid-cols-[1fr_80px_auto] gap-2 items-start">
                                        <div>
                                            <input type="text" :name="`criteria[${i}][levels][${j}][label]`" x-model="l.label" placeholder="e.g. Excellent"
                                                   class="w-full px-2.5 py-1.5 rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs focus:ring-2 focus:ring-indigo-500 transition">
                                            <input type="text" :name="`criteria[${i}][levels][${j}][description]`" x-model="l.description" placeholder="Short descriptor (optional)"
                                                   class="mt-1 w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-[11px] focus:ring-1 focus:ring-indigo-500 transition">
                                        </div>
                                        <input type="number" :name="`criteria[${i}][levels][${j}][marks]`" x-model="l.marks" min="0" step="0.5"
                                               :max="c.max_marks"
                                               class="px-2.5 py-1.5 rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs text-center focus:ring-2 focus:ring-indigo-500 transition">
                                        <button type="button" @click="removeLevel(i, j)"
                                                class="w-7 h-7 rounded-md text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center justify-center transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <button type="button" @click="removeCriterion(i)"
                            class="w-8 h-8 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center justify-center flex-shrink-0 transition"
                            title="Remove criterion">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3"/></svg>
                    </button>
                </div>
            </div>
        </template>

        <div class="flex items-center justify-between pt-2">
            <button type="button" @click="addCriterion()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add criterion
            </button>
            <p class="text-[11px] text-slate-400">
                Rubric total (<span x-text="totalMax()"></span>) should match the assessment's total marks.
            </p>
        </div>
    </div>
</div>
