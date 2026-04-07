<x-tenant-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">My Assessments</h2>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    @if($assessments->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-10 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p class="text-slate-500 dark:text-slate-400 mt-3">No assessments requiring submission right now.</p>
        </div>
    @else
        @php $grouped = $assessments->groupBy('course_id'); @endphp

        <div class="space-y-8">
            @foreach($grouped as $courseId => $courseAssessments)
                @php $course = $courseAssessments->first()->course; @endphp
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 px-2.5 py-1 rounded-lg">{{ $course->code }}</span>
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $course->title }}</h3>
                    </div>

                    <div class="space-y-3">
                        @foreach($courseAssessments as $assessment)
                            @php
                                $sub = $submissions->get($assessment->id);
                                $score = $scores->get($assessment->id);
                                $isPastDue = $assessment->due_date && now()->isAfter($assessment->due_date);
                            @endphp
                            <a href="{{ route('tenant.my-assessments.show', [$tenant->slug, $course, $assessment]) }}"
                               class="block bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-indigo-300 dark:hover:border-indigo-600 transition group">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="font-semibold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">{{ $assessment->title }}</h4>
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400">{{ ucfirst(str_replace('_', ' ', $assessment->type)) }}</span>
                                        </div>
                                        <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                            <span>{{ $assessment->total_marks }} marks</span>
                                            <span>&middot;</span>
                                            <span>{{ $assessment->weightage }}%</span>
                                            @if($assessment->due_date)
                                                <span>&middot;</span>
                                                <span class="{{ $isPastDue && !$sub ? 'text-red-500 font-medium' : '' }}">
                                                    Due: {{ $assessment->due_date->format('d M Y, H:i') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="text-right ml-4 flex-shrink-0">
                                        @if($score)
                                            <div class="text-lg font-bold {{ $score->percentage >= 70 ? 'text-emerald-600' : ($score->percentage >= 40 ? 'text-amber-600' : 'text-red-600') }}">{{ $score->percentage }}%</div>
                                            <p class="text-[11px] text-slate-400">{{ $score->raw_marks }}/{{ $score->max_marks }}</p>
                                        @elseif($sub)
                                            @php $badge = $sub->status_badge; @endphp
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $badge['color'] }}-100 dark:bg-{{ $badge['color'] }}-900/20 text-{{ $badge['color'] }}-700 dark:text-{{ $badge['color'] }}-400">{{ $badge['label'] }}</span>
                                            @if($sub->is_late)
                                                <p class="text-[10px] text-red-500 mt-0.5">Late</p>
                                            @endif
                                        @else
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium {{ $isPastDue ? 'bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400' }}">
                                                {{ $isPastDue ? 'Overdue' : 'Not submitted' }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-tenant-layout>
