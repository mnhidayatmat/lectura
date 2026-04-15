<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            {{-- Back + title --}}
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.assessments.submissions.index', [$tenant->slug, $course, $assessment]) }}"
                   class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Grade Submission</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $assessment->title }} &middot; {{ $course->code }}</p>
                </div>
            </div>

            {{-- Progress + prev/next --}}
            <div class="flex items-center gap-3">
                {{-- Grading progress --}}
                <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-slate-100 dark:bg-slate-700 rounded-xl">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $gradedCount }}/{{ $totalCount }} graded</span>
                    <div class="w-16 h-1.5 bg-slate-200 dark:bg-slate-600 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full transition-all"
                             style="width: {{ $totalCount > 0 ? round(($gradedCount / $totalCount) * 100) : 0 }}%"></div>
                    </div>
                </div>

                @if($prev)
                    <a href="{{ route('tenant.assessments.submissions.show', [$tenant->slug, $course, $assessment, $prev]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 rounded-xl transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Prev
                    </a>
                @endif
                @if($next)
                    <a href="{{ route('tenant.assessments.submissions.show', [$tenant->slug, $course, $assessment, $next]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 rounded-xl transition">
                        Next
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl flex items-center gap-3">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="grid lg:grid-cols-5 gap-6">

        {{-- ── LEFT: submission detail (3 cols) ── --}}
        <div class="lg:col-span-3 space-y-4">

            {{-- Student info --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        @if($submission->user->avatar_url)
                            <img src="{{ $submission->user->avatar_url }}" class="w-12 h-12 rounded-full object-cover">
                        @else
                            <div class="w-12 h-12 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-lg font-bold text-indigo-700 dark:text-indigo-400">
                                {{ strtoupper(substr($submission->user->name, 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">{{ $submission->user->name }}</h3>
                            <p class="text-xs text-slate-400">{{ $submission->user->email }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Submitted</p>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $submission->submitted_at->format('d M Y') }}</p>
                        <p class="text-xs text-slate-400">{{ $submission->submitted_at->format('H:i') }}</p>
                        @if($submission->is_late)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400 mt-1">Late submission</span>
                        @endif
                    </div>
                </div>
                @if($submission->notes)
                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1.5">Student Notes</p>
                        <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line bg-slate-50 dark:bg-slate-700/40 rounded-xl p-3">{{ $submission->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Submitted files --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    Submitted Files
                    <span class="ml-auto text-xs font-normal text-slate-400">{{ $submission->files->count() }} file(s)</span>
                </h3>
                @if($submission->files->isEmpty())
                    <p class="text-sm text-slate-400 text-center py-4">No files attached to this submission.</p>
                @else
                    <div class="space-y-2">
                        @foreach($submission->files as $file)
                            @php
                                $isPdf = str_contains($file->file_type ?? '', 'pdf');
                                $isImg = str_contains($file->file_type ?? '', 'image');
                                $fileBg = $isPdf ? 'bg-red-100 dark:bg-red-900/20 text-red-600 dark:text-red-400'
                                        : ($isImg ? 'bg-emerald-100 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400'
                                        : 'bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400');
                            @endphp
                            <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/30 border border-slate-100 dark:border-slate-600">
                                <div class="w-9 h-9 rounded-lg {{ $fileBg }} flex items-center justify-center flex-shrink-0">
                                    @if($isPdf)
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    @elseif($isImg)
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300 truncate">{{ $file->file_name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ number_format($file->file_size_bytes / 1024, 0) }} KB</p>
                                </div>
                                <a href="{{ route('tenant.assessments.submissions.download', [$tenant->slug, $course, $assessment, $file]) }}"
                                   class="p-2 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex-shrink-0" title="Download">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                            </div>
                        @endforeach
                    </div>

                    {{-- Inline PDF preview --}}
                    @php $pdfFile = $submission->files->first(fn($f) => str_contains($f->file_type ?? '', 'pdf')); @endphp
                    @if($pdfFile)
                        <div class="mt-4 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700">
                            <div class="px-4 py-2.5 bg-slate-50 dark:bg-slate-700/40 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                <span class="text-xs font-medium text-slate-600 dark:text-slate-300">{{ $pdfFile->file_name }}</span>
                                <a href="{{ route('tenant.assessments.submissions.view-file', [$tenant->slug, $course, $assessment, $pdfFile]) }}"
                                   target="_blank" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Open in tab</a>
                            </div>
                            <iframe src="{{ route('tenant.assessments.submissions.view-file', [$tenant->slug, $course, $assessment, $pdfFile]) }}#toolbar=1"
                                    class="w-full bg-slate-100 dark:bg-slate-900" style="height: 560px;"></iframe>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Assessment instruction file (reference for grading) --}}
            @if($assessment->instruction_file_path)
                @php
                    $iExt = strtolower(pathinfo($assessment->instruction_file_name ?? '', PATHINFO_EXTENSION));
                    $iViewable = in_array($iExt, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp']);
                @endphp
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-indigo-200 dark:border-indigo-800 p-5"
                     x-data="{ showInstruction: false }">
                    <button type="button" @click="showInstruction = !showInstruction"
                            class="w-full flex items-center justify-between gap-3 text-left">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-500 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">Assessment Instructions</span>
                            <span class="text-[10px] text-indigo-500 uppercase">{{ strtoupper($iExt) }}</span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform" :class="showInstruction ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="showInstruction" x-collapse class="mt-4">
                        <div class="flex gap-2 mb-3">
                            @if($iViewable)
                                <a href="{{ route('tenant.assessments.instruction.view', [$tenant->slug, $course, $assessment]) }}" target="_blank"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-indigo-300 dark:border-indigo-600 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-xs font-medium rounded-lg transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    Open in tab
                                </a>
                            @endif
                            <a href="{{ route('tenant.assessments.instruction.download', [$tenant->slug, $course, $assessment]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Download
                            </a>
                        </div>
                        @if($iExt === 'pdf')
                            <iframe src="{{ route('tenant.assessments.instruction.view', [$tenant->slug, $course, $assessment]) }}"
                                    class="w-full rounded-xl border border-indigo-100 dark:border-indigo-800 bg-slate-100 dark:bg-slate-900" style="height: 400px;"></iframe>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- ── RIGHT: grading panel (2 cols, sticky) ── --}}
        <div class="lg:col-span-2">
            <div class="sticky top-6 space-y-4">

                {{-- Current grade banner (if already graded) --}}
                @if($submission->score && $submission->score->finalized_at)
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-2xl p-4">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wide">Current Grade</span>
                            @if($submission->score->is_released)
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-200 dark:bg-emerald-800 text-emerald-800 dark:text-emerald-300">Released to student</span>
                            @else
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">Not yet released</span>
                            @endif
                        </div>
                        <div class="flex items-end gap-3">
                            <div>
                                <span class="text-3xl font-bold text-emerald-700 dark:text-emerald-400">{{ number_format($submission->score->raw_marks, 1) }}</span>
                                <span class="text-sm text-emerald-600 dark:text-emerald-500"> / {{ number_format($submission->score->max_marks, 0) }}</span>
                            </div>
                            @php $pct = $submission->score->percentage; @endphp
                            <span class="text-lg font-bold mb-0.5 {{ $pct >= 70 ? 'text-emerald-600' : ($pct >= 50 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ number_format($pct, 1) }}%
                            </span>
                        </div>
                        @if(!$submission->score->is_released)
                            <form method="POST" action="{{ route('tenant.assessments.scores.release', [$tenant->slug, $course, $assessment]) }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="score_ids[]" value="{{ $submission->score->id }}">
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Release to Student
                                </button>
                            </form>
                        @endif
                        @if($submission->score->feedback)
                            <div class="mt-3 pt-3 border-t border-emerald-200 dark:border-emerald-700">
                                <p class="text-[11px] text-emerald-700 dark:text-emerald-400 font-semibold uppercase tracking-wide mb-1">Feedback</p>
                                <p class="text-xs text-emerald-800 dark:text-emerald-300 whitespace-pre-line">{{ $submission->score->feedback }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Grading form --}}
                @php
                    $hasRubric = $assessment->rubric && $assessment->rubric->criteria->isNotEmpty();
                    $oldCriteriaMarks = old('criteria_marks', []);
                    $initialCriteriaMarks = [];
                    $criterionMax = [];
                    $criterionWeight = [];
                    $isWeighted = false;
                    if ($hasRubric) {
                        foreach ($assessment->rubric->criteria as $c) {
                            $initialCriteriaMarks[$c->id] = isset($oldCriteriaMarks[$c->id]) && $oldCriteriaMarks[$c->id] !== ''
                                ? (float) $oldCriteriaMarks[$c->id]
                                : null;
                            $criterionMax[$c->id] = (float) $c->max_marks;
                            $criterionWeight[$c->id] = $c->weightage !== null ? (float) $c->weightage : 0.0;
                            if ($criterionWeight[$c->id] > 0) {
                                $isWeighted = true;
                            }
                        }
                    }
                @endphp
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden"
                     x-data="{
                         hasRubric: {{ $hasRubric ? 'true' : 'false' }},
                         isWeighted: {{ $isWeighted ? 'true' : 'false' }},
                         criteriaMarks: @js($initialCriteriaMarks),
                         criterionMax: @js($criterionMax),
                         criterionWeight: @js($criterionWeight),
                         manualMarks: {{ json_encode(old('raw_marks', $submission->score?->raw_marks) !== null ? (float) old('raw_marks', $submission->score?->raw_marks) : '') }},
                         maxMarks: {{ (float) $assessment->total_marks }},
                         setCriterionMarks(id, value) {
                             this.criteriaMarks[id] = value;
                         },
                         criterionScore(id) {
                             const v = this.criteriaMarks[id];
                             return (v === null || v === '' || isNaN(v)) ? 0 : parseFloat(v);
                         },
                         criterionContribution(id) {
                             const score = this.criterionScore(id);
                             if (this.isWeighted) {
                                 const max = this.criterionMax[id] || 0;
                                 const w = this.criterionWeight[id] || 0;
                                 if (max <= 0) return 0;
                                 return (score / max) * (w / 100) * this.maxMarks;
                             }
                             return score;
                         },
                         get marks() {
                             if (this.hasRubric) {
                                 return Object.keys(this.criteriaMarks)
                                     .reduce((s, id) => s + this.criterionContribution(id), 0);
                             }
                             return this.manualMarks === '' || this.manualMarks === null ? 0 : parseFloat(this.manualMarks);
                         },
                         get percentage() {
                             if (this.maxMarks <= 0) return 0;
                             return Math.round((this.marks / this.maxMarks) * 100 * 10) / 10;
                         },
                         get grade() {
                             const p = this.percentage;
                             if (p >= 80) return { label: 'A', color: 'text-emerald-600' };
                             if (p >= 70) return { label: 'B', color: 'text-teal-600' };
                             if (p >= 60) return { label: 'C', color: 'text-blue-600' };
                             if (p >= 50) return { label: 'D', color: 'text-amber-600' };
                             return { label: 'F', color: 'text-red-600' };
                         }
                     }">

                    {{-- Card header --}}
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                                    {{ $submission->score ? 'Update Grade' : 'Grade Submission' }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    @if($hasRubric)
                                        Rubric-based grading · {{ $assessment->rubric->criteria->count() }} {{ Str::plural('criterion', $assessment->rubric->criteria->count()) }}
                                    @else
                                        Enter marks out of {{ number_format($assessment->total_marks, 0) }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('tenant.assessments.submissions.mark', [$tenant->slug, $course, $assessment, $submission]) }}"
                          class="p-6 space-y-6">
                        @csrf

                        @if($hasRubric)
                            {{-- Rubric-driven marks --}}
                            <div class="space-y-4">
                                @foreach($assessment->rubric->criteria as $criterion)
                                    <div class="rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50/60 dark:bg-slate-700/20 p-5 transition hover:border-indigo-200 dark:hover:border-indigo-800">
                                        <div class="flex items-start justify-between gap-4 mb-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                                    <p class="text-sm font-bold text-slate-900 dark:text-slate-100 leading-snug">{{ $criterion->title }}</p>
                                                    @if($criterion->weightage !== null && (float) $criterion->weightage > 0)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[10px] font-bold uppercase tracking-wide">
                                                            {{ rtrim(rtrim(number_format((float) $criterion->weightage, 2), '0'), '.') }}% weight
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($criterion->description)
                                                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">{{ $criterion->description }}</p>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <input type="number"
                                                       name="criteria_marks[{{ $criterion->id }}]"
                                                       x-model.number="criteriaMarks[{{ $criterion->id }}]"
                                                       min="0" max="{{ $criterion->max_marks }}" step="0.5"
                                                       placeholder="0"
                                                       class="w-20 px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-base font-bold text-center focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                                <span class="text-xs text-slate-400 font-medium">/ {{ rtrim(rtrim(number_format((float) $criterion->max_marks, 1), '0'), '.') }}</span>
                                            </div>
                                        </div>

                                        @if($isWeighted && $criterion->weightage !== null && (float) $criterion->weightage > 0)
                                            <div class="mb-3 px-3 py-2 rounded-lg bg-white/70 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-600">
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                                    Contributes
                                                    <span class="font-bold text-indigo-600 dark:text-indigo-400"
                                                          x-text="(Math.round(criterionContribution({{ $criterion->id }}) * 10) / 10)"></span>
                                                    of {{ rtrim(rtrim(number_format($assessment->total_marks * (float) $criterion->weightage / 100, 2), '0'), '.') }}
                                                    pts to the assessment total
                                                </p>
                                            </div>
                                        @endif

                                        @if($criterion->levels->isNotEmpty())
                                            <div>
                                                <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">Rating scale</p>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                                    @foreach($criterion->levels as $level)
                                                        <button type="button"
                                                                @click="setCriterionMarks({{ $criterion->id }}, {{ (float) $level->marks }})"
                                                                :class="parseFloat(criteriaMarks[{{ $criterion->id }}]) === {{ (float) $level->marks }}
                                                                    ? 'bg-indigo-600 border-indigo-600 text-white shadow-md ring-2 ring-indigo-200 dark:ring-indigo-900/50'
                                                                    : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:border-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10'"
                                                                class="flex flex-col items-start gap-0.5 px-3 py-2.5 border rounded-xl text-xs font-medium transition-all text-left"
                                                                @if($level->description) title="{{ $level->description }}" @endif>
                                                            <span class="font-semibold">{{ $level->label }}</span>
                                                            <span class="text-[10px] opacity-80">{{ rtrim(rtrim(number_format((float) $level->marks, 1), '0'), '.') }} pts</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        @error('criteria_marks.'.$criterion->id) <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                            </div>

                            {{-- Live total --}}
                            <div class="rounded-2xl bg-gradient-to-br from-indigo-50 to-indigo-100/50 dark:from-indigo-900/30 dark:to-indigo-900/10 border border-indigo-200 dark:border-indigo-800 p-5">
                                <div class="flex items-end justify-between gap-4 mb-3">
                                    <div>
                                        <p class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-1">Total Score</p>
                                        <p class="text-3xl font-bold text-indigo-700 dark:text-indigo-300 leading-none">
                                            <span x-text="Math.round(marks * 10) / 10"></span><span class="text-base font-semibold text-indigo-500 dark:text-indigo-400">/{{ number_format($assessment->total_marks, 0) }}</span>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-3xl font-bold leading-none" :class="grade.color" x-text="percentage + '%'"></p>
                                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 uppercase tracking-wide">Grade <span class="font-bold text-sm" :class="grade.color" x-text="grade.label"></span></p>
                                    </div>
                                </div>
                                <div class="h-2 bg-white/60 dark:bg-slate-800/40 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-500"
                                         :class="percentage >= 50 ? 'bg-emerald-500' : 'bg-red-400'"
                                         :style="'width: ' + Math.min(percentage, 100) + '%'"></div>
                                </div>
                            </div>
                            @error('raw_marks') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        @else
                            {{-- Marks input (no rubric) --}}
                            <div class="rounded-2xl bg-slate-50/60 dark:bg-slate-700/20 border border-slate-200 dark:border-slate-600 p-5">
                                <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-3">
                                    Marks <span class="font-medium text-slate-400">/ {{ number_format($assessment->total_marks, 0) }}</span>
                                </label>
                                <div class="flex items-center gap-4">
                                    <input type="number"
                                           name="raw_marks"
                                           x-model="manualMarks"
                                           required min="0" max="{{ $assessment->total_marks }}" step="0.5"
                                           class="w-32 px-4 py-3.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-2xl font-bold text-center focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                    <div class="flex-1">
                                        <p class="text-3xl font-bold leading-none" :class="grade.color" x-text="percentage + '%'"></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Grade <span class="font-bold" :class="grade.color" x-text="grade.label"></span></p>
                                    </div>
                                </div>
                                {{-- Visual bar --}}
                                <div class="mt-4 h-2 bg-slate-200/60 dark:bg-slate-700 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-500"
                                         :class="percentage >= 50 ? 'bg-emerald-500' : 'bg-red-400'"
                                         :style="'width: ' + Math.min(percentage, 100) + '%'"></div>
                                </div>
                                @error('raw_marks') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        {{-- Feedback --}}
                        <div class="rounded-2xl bg-slate-50/60 dark:bg-slate-700/20 border border-slate-200 dark:border-slate-600 p-5">
                            <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest mb-3">
                                Feedback <span class="font-medium normal-case text-slate-400">(optional)</span>
                            </label>
                            <textarea name="feedback" rows="6"
                                      class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm leading-relaxed focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition resize-none"
                                      placeholder="Write feedback for {{ $submission->user->name }}...">{{ old('feedback', $submission->score?->feedback) }}</textarea>
                            @error('feedback') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Submit buttons --}}
                        <div class="pt-2 space-y-3">
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold rounded-xl shadow-sm hover:shadow-md transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ $submission->score ? 'Update Grade' : 'Save Grade' }}
                            </button>

                            @if($next)
                                <button type="submit" name="next_id" value="{{ $next->id }}"
                                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-semibold border border-slate-200 dark:border-slate-600 rounded-xl transition">
                                    Save & Grade Next
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            @endif

                            <p class="text-[11px] text-slate-400 dark:text-slate-500 text-center pt-1">
                                Marks are <strong class="text-slate-500 dark:text-slate-400">not visible</strong> to the student until you release them.
                            </p>
                        </div>
                    </form>
                </div>

                {{-- Assessment context --}}
                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-700 p-4">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-2">Assessment</p>
                    <div class="space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
                        <div class="flex justify-between"><span>Total marks</span><span class="font-semibold text-slate-900 dark:text-white">{{ number_format($assessment->total_marks, 0) }}</span></div>
                        <div class="flex justify-between"><span>Course weight</span><span class="font-semibold text-slate-900 dark:text-white">{{ number_format($assessment->weightage, 0) }}%</span></div>
                        @if($assessment->due_date)
                            <div class="flex justify-between">
                                <span>Due date</span>
                                <span class="font-semibold {{ now()->isAfter($assessment->due_date) ? 'text-red-500' : 'text-slate-900 dark:text-white' }}">{{ $assessment->due_date->format('d M Y') }}</span>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-tenant-layout>
