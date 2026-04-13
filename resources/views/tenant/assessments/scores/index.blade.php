<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $assessment->title }} — Scores</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} &middot; {{ number_format($assessment->weightage, 0) }}% course weight &middot; {{ number_format($assessment->total_marks, 0) }} marks</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('tenant.assessments.scores.compute', [$tenant->slug, $course, $assessment]) }}">
                    @csrf
                    <button class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Compute
                    </button>
                </form>
                <a href="{{ route('tenant.assessments.scores.manual', [$tenant->slug, $course, $assessment]) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Manual Entry
                </a>
                @if($assessment->requires_submission)
                    <a href="{{ route('tenant.assessments.submissions.index', [$tenant->slug, $course, $assessment]) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 rounded-xl transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        Submissions
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    {{-- Summary stat cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $stats['total'] }}</p>
            <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Enrolled</p>
        </div>
        @if($assessment->requires_submission)
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['submitted'] }}</p>
                <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Submitted</p>
            </div>
        @endif
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $stats['graded'] }}</p>
            <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Graded</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['released'] }}</p>
            <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Released</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $stats['avg'] !== null ? number_format($stats['avg'], 1) . '%' : '—' }}</p>
            <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Average</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
            <p class="text-2xl font-bold text-teal-600 dark:text-teal-400">{{ $stats['graded'] > 0 ? $stats['passing'] . '/' . $stats['graded'] : '—' }}</p>
            <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Passing</p>
        </div>
    </div>

    {{-- Student list --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if($students->isEmpty())
            <div class="p-12 text-center text-sm text-slate-400">No enrolled students found for this course.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">#</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Student</th>
                            @if($assessment->requires_submission)
                                <th class="text-center px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Submission</th>
                            @endif
                            <th class="text-center px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Mark</th>
                            <th class="text-center px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">%</th>
                            <th class="text-center px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Weighted</th>
                            <th class="text-center px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Source</th>
                            <th class="text-center px-3 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Released</th>
                            <th class="px-3 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($students as $i => $student)
                            @php
                                $score      = $scores->get($student->id);
                                $submission = $submissions->get($student->id);
                                // show computed AND manual scores
                                $hasScore   = $score !== null;
                            @endphp
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/20 transition {{ !$hasScore ? 'opacity-75' : '' }}">
                                {{-- # --}}
                                <td class="px-4 py-3 text-xs text-slate-400">{{ $i + 1 }}</td>

                                {{-- Student --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        @if($student->avatar_url)
                                            <img src="{{ $student->avatar_url }}" class="w-7 h-7 rounded-full object-cover flex-shrink-0">
                                        @else
                                            <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0">
                                                <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400">{{ strtoupper(substr($student->name, 0, 1)) }}</span>
                                            </div>
                                        @endif
                                        <span class="font-medium text-slate-900 dark:text-white text-sm">{{ $student->name }}</span>
                                    </div>
                                </td>

                                {{-- Submission status --}}
                                @if($assessment->requires_submission)
                                    <td class="px-3 py-3 text-center">
                                        @if($submission)
                                            @if($submission->status === 'graded')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                    Graded
                                                </span>
                                            @elseif($submission->is_late)
                                                <div>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">Late</span>
                                                    <p class="text-[9px] text-slate-400 mt-0.5">{{ $submission->submitted_at->format('d M, H:i') }}</p>
                                                </div>
                                            @else
                                                <div>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">Submitted</span>
                                                    <p class="text-[9px] text-slate-400 mt-0.5">{{ $submission->submitted_at->format('d M, H:i') }}</p>
                                                </div>
                                            @endif
                                        @else
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">No submission</span>
                                        @endif
                                    </td>
                                @endif

                                {{-- Mark --}}
                                <td class="px-3 py-3 text-center">
                                    @if($hasScore)
                                        <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ number_format($score->raw_marks, 1) }}</span>
                                        <span class="text-xs text-slate-400">/{{ number_format($score->max_marks, 0) }}</span>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @endif
                                </td>

                                {{-- Percentage --}}
                                <td class="px-3 py-3 text-center">
                                    @if($hasScore)
                                        @php
                                            $pct = $score->percentage;
                                            $pctClass = $pct >= 70 ? 'text-emerald-600 dark:text-emerald-400'
                                                      : ($pct >= 50 ? 'text-amber-600 dark:text-amber-400'
                                                      : 'text-red-600 dark:text-red-400');
                                        @endphp
                                        <span class="text-sm font-bold {{ $pctClass }}">{{ number_format($pct, 1) }}%</span>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @endif
                                </td>

                                {{-- Weighted --}}
                                <td class="px-3 py-3 text-center text-xs text-slate-600 dark:text-slate-300">
                                    {{ $hasScore ? number_format($score->weighted_marks, 2) : '—' }}
                                </td>

                                {{-- Source --}}
                                <td class="px-3 py-3 text-center">
                                    @if($hasScore)
                                        @if($score->assessment_submission_id)
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400">Submission</span>
                                        @elseif($score->is_computed)
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">Auto</span>
                                        @else
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">Manual</span>
                                        @endif
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600 text-xs">—</span>
                                    @endif
                                </td>

                                {{-- Released --}}
                                <td class="px-3 py-3 text-center">
                                    @if($hasScore)
                                        @if($score->is_released)
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">Released</span>
                                        @else
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">Hidden</span>
                                        @endif
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600 text-xs">—</span>
                                    @endif
                                </td>

                                {{-- Action --}}
                                <td class="px-3 py-3 text-right">
                                    @if($submission)
                                        <a href="{{ route('tenant.assessments.submissions.show', [$tenant->slug, $course, $assessment, $submission]) }}"
                                           class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Review
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Release all button --}}
            @if($scores->where('is_released', false)->count() > 0)
                <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-800/50">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $scores->where('is_released', false)->count() }} score(s) not yet released to students.
                    </p>
                    <form method="POST" action="{{ route('tenant.assessments.scores.release', [$tenant->slug, $course, $assessment]) }}">
                        @csrf
                        <button class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-xl transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Release All to Students
                        </button>
                    </form>
                </div>
            @endif
        @endif
    </div>
</x-tenant-layout>
