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
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5"
                     x-data="{
                         marks: {{ json_encode(old('raw_marks', $submission->score?->raw_marks) !== null ? (float) old('raw_marks', $submission->score?->raw_marks) : '') }},
                         maxMarks: {{ (float) $assessment->total_marks }},
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

                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">
                        {{ $submission->score ? 'Update Grade' : 'Grade Submission' }}
                    </h3>

                    <form method="POST" action="{{ route('tenant.assessments.submissions.mark', [$tenant->slug, $course, $assessment, $submission]) }}"
                          class="space-y-4">
                        @csrf

                        {{-- Marks input --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-2">
                                Marks <span class="normal-case text-slate-400">/ {{ number_format($assessment->total_marks, 0) }}</span>
                            </label>
                            <div class="flex items-center gap-3">
                                <input type="number"
                                       name="raw_marks"
                                       x-model="marks"
                                       required min="0" max="{{ $assessment->total_marks }}" step="0.5"
                                       class="w-28 px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xl font-bold text-center focus:ring-2 focus:ring-indigo-500 transition">
                                <div>
                                    <p class="text-2xl font-bold" :class="grade.color" x-text="percentage + '%'"></p>
                                    <p class="text-xs text-slate-400">Grade: <span class="font-bold" :class="grade.color" x-text="grade.label"></span></p>
                                </div>
                            </div>
                            {{-- Visual bar --}}
                            <div class="mt-2 h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-300"
                                     :class="percentage >= 50 ? 'bg-emerald-500' : 'bg-red-400'"
                                     :style="'width: ' + Math.min(percentage, 100) + '%'"></div>
                            </div>
                            @error('raw_marks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Feedback --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-2">
                                Feedback <span class="normal-case font-normal text-slate-400">(optional)</span>
                            </label>
                            <textarea name="feedback" rows="6"
                                      class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition resize-none"
                                      placeholder="Write feedback for {{ $submission->user->name }}...">{{ old('feedback', $submission->score?->feedback) }}</textarea>
                            @error('feedback') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Submit buttons --}}
                        <div class="space-y-2">
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ $submission->score ? 'Update Grade' : 'Save Grade' }}
                            </button>

                            @if($next)
                                <button type="submit" name="next_id" value="{{ $next->id }}"
                                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition">
                                    Save & Grade Next
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            @endif
                        </div>

                        <p class="text-[11px] text-slate-400 text-center">
                            Marks are <strong>not visible</strong> to the student until you release them.
                        </p>
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
