<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.assessments.overview', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Course Assessment Plan</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} — {{ $course->title }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($course->programme_id)
                    <a href="{{ route('tenant.clo-plo.edit', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 rounded-xl transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        CLO-PLO
                    </a>
                @endif
                <a href="{{ route('tenant.assessment-reports.course', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Reports
                </a>
                <a href="{{ route('tenant.assessments.create', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Assessment
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- Summary cards --}}
        <div class="grid sm:grid-cols-2 gap-4">
            {{-- Weightage card --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl {{ $totalWeightage == 100 ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-indigo-100 dark:bg-indigo-900/30' }} flex items-center justify-center">
                            <svg class="w-4.5 h-4.5 {{ $totalWeightage == 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-indigo-600 dark:text-indigo-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Total Weightage</p>
                            <p class="text-lg font-bold {{ $totalWeightage == 100 ? 'text-emerald-600 dark:text-emerald-400' : ($totalWeightage > 100 ? 'text-red-600' : 'text-indigo-600 dark:text-indigo-400') }}">{{ number_format($totalWeightage, 0) }}%</p>
                        </div>
                    </div>
                    @if($totalWeightage == 100)
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/30 px-2.5 py-1 rounded-full">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Complete
                        </span>
                    @elseif($totalWeightage > 100)
                        <span class="text-[11px] font-semibold text-red-600 bg-red-50 dark:bg-red-900/20 px-2.5 py-1 rounded-full">Over by {{ number_format($totalWeightage - 100, 0) }}%</span>
                    @else
                        <span class="text-[11px] font-semibold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-2.5 py-1 rounded-full">{{ number_format(100 - $totalWeightage, 0) }}% left</span>
                    @endif
                </div>
                <div class="h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-700 {{ $totalWeightage == 100 ? 'bg-emerald-500' : ($totalWeightage > 100 ? 'bg-red-500' : 'bg-indigo-500') }}" style="width: {{ min($totalWeightage, 100) }}%"></div>
                </div>
            </div>

            {{-- CLO coverage card --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center">
                            <svg class="w-4.5 h-4.5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">CLO Coverage</p>
                            <p class="text-lg font-bold {{ $coveredCloIds->count() === $course->learningOutcomes->count() && $course->learningOutcomes->isNotEmpty() ? 'text-emerald-600 dark:text-emerald-400' : 'text-teal-600 dark:text-teal-400' }}">
                                {{ $coveredCloIds->count() }}<span class="text-sm font-medium text-slate-400">/{{ $course->learningOutcomes->count() }}</span>
                            </p>
                        </div>
                    </div>
                </div>
                @if($course->learningOutcomes->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($course->learningOutcomes as $clo)
                            @php $covered = $coveredCloIds->contains($clo->id); @endphp
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold {{ $covered ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-50 text-slate-400 dark:bg-slate-700 dark:text-slate-500 border border-slate-200 dark:border-slate-600' }}">
                                {{ $clo->code }}
                                @if($covered)
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-[11px] text-slate-400">No CLOs defined for this course yet.</p>
                @endif
            </div>
        </div>

        {{-- Assessment list --}}
        @if($course->assessments->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
                <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
                </div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No assessments yet</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">Start building your Course Assessment Plan by adding assessment components.</p>
                <a href="{{ route('tenant.assessments.create', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add First Assessment
                </a>
            </div>
        @else
            @php
                $typeConfig = [
                    'quiz'         => ['label' => 'Quiz',         'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',                                                                                                                   'bg' => 'bg-amber-100 dark:bg-amber-900/30',   'text' => 'text-amber-600 dark:text-amber-400',   'accent' => 'bg-amber-500'],
                    'assignment'   => ['label' => 'Assignment',   'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',                                                                                                                                                 'bg' => 'bg-blue-100 dark:bg-blue-900/30',     'text' => 'text-blue-600 dark:text-blue-400',     'accent' => 'bg-blue-500'],
                    'test'         => ['label' => 'Test',         'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',                                                                                                                                               'bg' => 'bg-indigo-100 dark:bg-indigo-900/30', 'text' => 'text-indigo-600 dark:text-indigo-400', 'accent' => 'bg-indigo-500'],
                    'project'      => ['label' => 'Project',      'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',                                                                                                             'bg' => 'bg-purple-100 dark:bg-purple-900/30', 'text' => 'text-purple-600 dark:text-purple-400', 'accent' => 'bg-purple-500'],
                    'presentation' => ['label' => 'Presentation', 'icon' => 'M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z',                                                                                                                                               'bg' => 'bg-rose-100 dark:bg-rose-900/30',     'text' => 'text-rose-600 dark:text-rose-400',     'accent' => 'bg-rose-500'],
                    'lab'          => ['label' => 'Lab',          'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z', 'bg' => 'bg-teal-100 dark:bg-teal-900/30',     'text' => 'text-teal-600 dark:text-teal-400',     'accent' => 'bg-teal-500'],
                    'final_exam'   => ['label' => 'Final Exam',   'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',                                                                                                                                                                                                                           'bg' => 'bg-red-100 dark:bg-red-900/30',       'text' => 'text-red-600 dark:text-red-400',       'accent' => 'bg-red-500'],
                    'other'        => ['label' => 'Other',        'icon' => 'M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z',                                                                                                                                             'bg' => 'bg-slate-100 dark:bg-slate-700',      'text' => 'text-slate-500 dark:text-slate-400',   'accent' => 'bg-slate-400'],
                ];
                $bloomColors = [
                    'remember'   => ['bg' => 'bg-slate-100 dark:bg-slate-700',    'text' => 'text-slate-600 dark:text-slate-400'],
                    'understand' => ['bg' => 'bg-sky-100 dark:bg-sky-900/30',     'text' => 'text-sky-600 dark:text-sky-400'],
                    'apply'      => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-600 dark:text-emerald-400'],
                    'analyze'    => ['bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-600 dark:text-amber-400'],
                    'evaluate'   => ['bg' => 'bg-orange-100 dark:bg-orange-900/30', 'text' => 'text-orange-600 dark:text-orange-400'],
                    'create'     => ['bg' => 'bg-rose-100 dark:bg-rose-900/30',   'text' => 'text-rose-600 dark:text-rose-400'],
                ];
            @endphp

            <div class="space-y-3">
                @foreach($course->assessments as $assessment)
                    @php
                        $cfg      = $typeConfig[$assessment->type] ?? $typeConfig['other'];
                        $badge    = $assessment->status_badge;
                        $bc       = $bloomColors[$assessment->bloom_level] ?? $bloomColors['remember'];
                        $hasChildren = $assessment->children->isNotEmpty();
                        $statusAccent = $assessment->status === 'completed' ? 'bg-emerald-500' : ($assessment->status === 'active' ? 'bg-indigo-500' : 'bg-slate-300 dark:bg-slate-600');
                    @endphp

                    <div x-data="{ expanded: false }" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden hover:shadow-md transition">

                        {{-- Main row --}}
                        <div class="flex items-stretch">
                            {{-- Status accent bar --}}
                            <div class="w-1.5 flex-shrink-0 {{ $statusAccent }}"></div>

                            {{-- Content area --}}
                            <div class="flex-1 p-4 sm:p-5 min-w-0">
                                <div class="flex items-start gap-4">
                                    {{-- Type icon --}}
                                    <div class="w-11 h-11 rounded-xl {{ $cfg['bg'] }} flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 {{ $cfg['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $cfg['icon'] }}"/></svg>
                                    </div>

                                    {{-- Title + badges --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex items-center gap-2 flex-wrap min-w-0">
                                                <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $assessment->title }}</h4>
                                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full {{ $cfg['bg'] }} {{ $cfg['text'] }} flex-shrink-0">{{ $cfg['label'] }}</span>
                                            </div>
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full flex-shrink-0 bg-{{ $badge['color'] }}-100 dark:bg-{{ $badge['color'] }}-900/30 text-{{ $badge['color'] }}-700 dark:text-{{ $badge['color'] }}-400">{{ $badge['label'] }}</span>
                                        </div>

                                        @if($assessment->description)
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-1">{{ $assessment->description }}</p>
                                        @endif

                                        {{-- Stats pills --}}
                                        <div class="flex flex-wrap items-center gap-2 mt-3">
                                            {{-- Weightage --}}
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 rounded-lg text-[11px] font-bold">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                                                {{ number_format($assessment->weightage, 0) }}% course
                                            </span>

                                            {{-- Marks --}}
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg text-[11px] font-semibold">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                                {{ number_format($assessment->total_marks, 0) }} marks
                                            </span>

                                            {{-- Bloom level --}}
                                            @if($assessment->bloom_level)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-semibold capitalize {{ $bc['bg'] }} {{ $bc['text'] }}">{{ $assessment->bloom_level }}</span>
                                            @endif

                                            {{-- CLOs --}}
                                            @if($assessment->clos->isNotEmpty())
                                                @foreach($assessment->clos as $clo)
                                                    <span class="px-2 py-0.5 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 rounded text-[10px] font-bold">{{ $clo->code }}</span>
                                                @endforeach
                                            @endif

                                            {{-- Submission badge --}}
                                            @if($assessment->requires_submission)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 rounded-lg text-[10px] font-medium">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                                    Submission
                                                </span>
                                                @if($assessment->due_date)
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 {{ now()->gt($assessment->due_date) ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : 'bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-400' }} rounded-lg text-[10px] font-medium">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                        Due {{ $assessment->due_date->format('d M Y') }}
                                                    </span>
                                                @endif
                                            @endif

                                            {{-- Linked items --}}
                                            @if($assessment->items->isNotEmpty())
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 rounded-lg text-[10px] font-medium">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                                    {{ $assessment->items->count() }} linked
                                                </span>
                                            @endif

                                            {{-- Child count / expand toggle --}}
                                            @if($hasChildren)
                                                <button type="button" @click="expanded = !expanded"
                                                    :class="expanded ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-600'"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-medium transition">
                                                    <svg class="w-3 h-3 transition-transform" :class="expanded ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                    {{ $assessment->children->count() }} parts
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Child Assessments --}}
                                @if($hasChildren)
                                    <div x-show="expanded" x-collapse class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 space-y-2">
                                        @php $childWeightageUsed = $assessment->children->sum('weightage'); @endphp
                                        @foreach($assessment->children as $child)
                                            @php
                                                $childCfg   = $typeConfig[$child->type] ?? $typeConfig['other'];
                                                $childBadge = $child->status_badge;
                                                $childMarksPortion = $assessment->total_marks > 0
                                                    ? round(($child->weightage / 100) * $assessment->total_marks, 1)
                                                    : $child->total_marks;
                                            @endphp
                                            <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-700 border-l-4 border-l-indigo-400">
                                                <div class="w-8 h-8 rounded-lg {{ $childCfg['bg'] }} flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 {{ $childCfg['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $childCfg['icon'] }}"/></svg>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $child->title }}</p>
                                                        <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0 bg-{{ $childBadge['color'] }}-100 dark:bg-{{ $childBadge['color'] }}-900/30 text-{{ $childBadge['color'] }}-700 dark:text-{{ $childBadge['color'] }}-400">{{ $childBadge['label'] }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                                                        <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400">{{ $child->weightage }}% of parent</span>
                                                        <span class="text-[10px] text-slate-400">&middot;</span>
                                                        <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ number_format($childMarksPortion, 1) }} / {{ number_format($assessment->total_marks, 0) }} marks</span>
                                                        <span class="text-[10px] text-slate-400">&middot;</span>
                                                        <span class="text-[10px] text-slate-400">≈ {{ number_format(($child->weightage / 100) * $assessment->weightage, 1) }}% course</span>
                                                    </div>
                                                </div>
                                                <a href="{{ route('tenant.assessments.edit', [$tenant->slug, $course, $child]) }}" class="p-1.5 rounded-lg hover:bg-white dark:hover:bg-slate-600 text-slate-400 hover:text-indigo-500 transition flex-shrink-0">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                            </div>
                                        @endforeach
                                        {{-- Child allocation progress --}}
                                        @if($assessment->children->count() > 0)
                                            <div class="px-1 pt-1">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="text-[10px] text-slate-400">Parent portion allocated</span>
                                                    <span class="text-[10px] font-semibold {{ $childWeightageUsed > 100 ? 'text-red-500' : ($childWeightageUsed == 100 ? 'text-emerald-600' : 'text-amber-600') }}">{{ number_format($childWeightageUsed, 0) }}%</span>
                                                </div>
                                                <div class="h-1 bg-slate-200 dark:bg-slate-600 rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full {{ $childWeightageUsed > 100 ? 'bg-red-500' : ($childWeightageUsed == 100 ? 'bg-emerald-500' : 'bg-amber-400') }}" style="width: {{ min($childWeightageUsed, 100) }}%"></div>
                                                </div>
                                            </div>
                                        @endif

                                        <a href="{{ route('tenant.assessments.child.create', [$tenant->slug, $course, $assessment]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            Add part
                                        </a>
                                    </div>
                                @endif
                            </div>

                            {{-- Action buttons --}}
                            <div class="flex flex-col items-center justify-center gap-1 px-3 border-l border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                <a href="{{ route('tenant.assessments.edit', [$tenant->slug, $course, $assessment]) }}" class="p-2 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-slate-400 hover:text-indigo-600 transition" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @if($assessment->requires_submission)
                                    <a href="{{ route('tenant.assessments.submissions.index', [$tenant->slug, $course, $assessment]) }}" class="p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 text-slate-400 hover:text-blue-600 transition" title="Submissions">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    </a>
                                @endif
                                <a href="{{ route('tenant.assessments.scores.index', [$tenant->slug, $course, $assessment]) }}" class="p-2 rounded-lg hover:bg-teal-50 dark:hover:bg-teal-900/20 text-slate-400 hover:text-teal-600 transition" title="Scores">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('tenant.assessments.destroy', [$tenant->slug, $course, $assessment]) }}" onsubmit="return confirm('Delete this assessment?')">
                                    @csrf @method('DELETE')
                                    <button class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-slate-400 hover:text-red-500 transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-tenant-layout>
