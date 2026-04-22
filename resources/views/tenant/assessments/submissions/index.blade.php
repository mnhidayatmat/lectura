<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $assessment->title }}</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} &middot; {{ number_format($assessment->total_marks, 0) }} marks &middot; {{ ucfirst(str_replace('_', ' ', $assessment->type)) }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('tenant.assessments.scores.index', [$tenant->slug, $course, $assessment]) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-teal-300 rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Scores
                </a>
                <a href="{{ route('tenant.assessments.edit', [$tenant->slug, $course, $assessment]) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit Assessment
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl flex items-center gap-3">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Assessment info + instruction file banner --}}
    <div class="grid sm:grid-cols-2 gap-4 mb-6">
        {{-- Assessment details --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Assessment Details</h3>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Type</span>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ ucfirst(str_replace('_', ' ', $assessment->type)) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Total Marks</span>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ number_format($assessment->total_marks, 0) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Weightage</span>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ number_format($assessment->weightage, 0) }}%</span>
                </div>
                @if($assessment->due_date)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500 dark:text-slate-400">Due Date</span>
                        <span class="text-xs font-medium {{ now()->isAfter($assessment->due_date) ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-300' }}">
                            {{ $assessment->due_date->format('d M Y, H:i') }}
                            @if(now()->isAfter($assessment->due_date)) <span class="ml-1 text-[10px]">(Past due)</span> @endif
                        </span>
                    </div>
                @endif
                @if($assessment->description)
                    <div class="pt-2 mt-2 border-t border-slate-100 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-3">{{ $assessment->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Instruction file --}}
        @if($assessment->instruction_file_path)
            @php
                $ext = strtolower(pathinfo($assessment->instruction_file_name ?? '', PATHINFO_EXTENSION));
                $isPdf = $ext === 'pdf';
                $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                $fileIconBg = $isPdf ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'
                            : (in_array($ext, ['doc','docx']) ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
                            : 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400');
            @endphp
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-indigo-200 dark:border-indigo-700 p-5 flex flex-col justify-between">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Instruction / Task File</h3>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800">
                    <div class="w-10 h-10 rounded-xl {{ $fileIconBg }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $assessment->instruction_file_name }}</p>
                        <p class="text-[10px] text-indigo-500 uppercase tracking-wider">{{ strtoupper($ext) }}</p>
                    </div>
                </div>
                <div class="flex gap-2 mt-3">
                    @if($isPdf || $isImage)
                        <a href="{{ route('tenant.assessments.instruction.view', [$tenant->slug, $course, $assessment]) }}" target="_blank"
                           class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-white dark:bg-slate-700 border border-indigo-300 dark:border-indigo-600 text-indigo-600 dark:text-indigo-400 text-xs font-medium rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            View
                        </a>
                    @endif
                    <a href="{{ route('tenant.assessments.instruction.download', [$tenant->slug, $course, $assessment]) }}"
                       class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download
                    </a>
                </div>
            </div>
        @else
            {{-- Stat cards when no instruction file --}}
            <div class="grid grid-cols-2 gap-3">
                @foreach([
                    ['label' => 'Enrolled',   'value' => $stats['enrolled'],  'color' => 'slate'],
                    ['label' => 'Submitted',  'value' => $stats['submitted'], 'color' => 'blue'],
                    ['label' => 'Graded',     'value' => $stats['graded'],    'color' => 'amber'],
                    ['label' => 'Released',   'value' => $stats['released'],  'color' => 'emerald'],
                ] as $stat)
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
                        <p class="text-2xl font-bold text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400">{{ $stat['value'] }}</p>
                        <p class="text-[10px] text-slate-400 uppercase tracking-wide mt-0.5">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Stats row (always shown when instruction file is present) --}}
    @if($assessment->instruction_file_path)
        <div class="grid grid-cols-4 gap-3 mb-6">
            @foreach([
                ['label' => 'Enrolled',   'value' => $stats['enrolled'],  'color' => 'slate'],
                ['label' => 'Submitted',  'value' => $stats['submitted'], 'color' => 'blue'],
                ['label' => 'Graded',     'value' => $stats['graded'],    'color' => 'amber'],
                ['label' => 'Released',   'value' => $stats['released'],  'color' => 'emerald'],
            ] as $stat)
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 text-center">
                    <p class="text-2xl font-bold text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400">{{ $stat['value'] }}</p>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wide mt-0.5">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Bulk release --}}
    @if($scores->where('finalized_at', '!=', null)->where('is_released', false)->count() > 0)
        <div class="mb-4" x-data="{ confirmRelease: false }">
            <button type="button" @click="confirmRelease = true"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Release All Graded Marks ({{ $scores->where('finalized_at', '!=', null)->where('is_released', false)->count() }})
            </button>
            <div x-show="confirmRelease" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @keydown.escape.window="confirmRelease = false">
                <div @click.outside="confirmRelease = false" class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-6 max-w-sm w-full mx-4 space-y-4">
                    <h3 class="font-semibold text-slate-900 dark:text-white">Release All Marks?</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Students will be able to see their marks and feedback. Notifications will be sent.</p>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="confirmRelease = false" class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition">Cancel</button>
                        <form method="POST" action="{{ route('tenant.assessments.scores.release', [$tenant->slug, $course, $assessment]) }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition">Release</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Student / Submission table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if($enrolledStudents->isEmpty())
            <div class="p-12 text-center">
                <div class="w-12 h-12 bg-slate-100 dark:bg-slate-700 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">No students enrolled</p>
                <p class="text-xs text-slate-400">Students enrolled in this course will appear here once they submit.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                {{-- Group legend (only shown for group submissions) --}}
                @if($assessment->usesGroupSubmission() && ($assessment->studentGroupSet->groups ?? collect())->isNotEmpty())
                    @php
                        $legendPalette = ['indigo', 'emerald', 'amber', 'rose', 'sky', 'violet', 'teal', 'fuchsia', 'cyan', 'lime', 'orange', 'pink'];
                    @endphp
                    <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Groups:</span>
                            @foreach($assessment->studentGroupSet->groups as $i => $g)
                                @php $c = $legendPalette[$i % count($legendPalette)]; @endphp
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-{{ $c }}-100 dark:bg-{{ $c }}-900/30 text-{{ $c }}-700 dark:text-{{ $c }}-400 text-[11px] font-medium">
                                    <span class="w-2 h-2 rounded-full bg-{{ $c }}-500"></span>
                                    {{ $g->name }}
                                </span>
                            @endforeach
                            <span class="ml-auto inline-flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400">
                                <svg class="w-3 h-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
                                Leader badge marks the group's submitter
                            </span>
                        </div>
                    </div>
                @endif

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Student</th>
                            @if($assessment->usesGroupSubmission())
                                <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Group</th>
                            @endif
                            <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Status</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Submitted</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Files</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Mark</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Released</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($enrolledStudents as $student)
                            @php
                                $sub   = $submissions->get($student->id);
                                $score = $scores->get($student->id);
                                $groupInfo = ($groupInfoByUser ?? collect())->get($student->id);
                                $isLeader = $groupInfo && $groupInfo['role'] === 'leader';
                                $gColor = $groupInfo['color'] ?? null;
                            @endphp
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/20 transition {{ !$sub ? 'opacity-60' : '' }} {{ $gColor ? 'border-l-4 border-l-'.$gColor.'-400 dark:border-l-'.$gColor.'-500' : '' }}">
                                {{-- Student --}}
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        @if($student->avatar_url)
                                            <img src="{{ $student->avatar_url }}" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                                        @else
                                            <div class="w-8 h-8 rounded-full {{ $gColor ? 'bg-'.$gColor.'-100 dark:bg-'.$gColor.'-900/30' : 'bg-indigo-100 dark:bg-indigo-900/30' }} flex items-center justify-center flex-shrink-0">
                                                <span class="text-xs font-bold {{ $gColor ? 'text-'.$gColor.'-700 dark:text-'.$gColor.'-400' : 'text-indigo-700 dark:text-indigo-400' }}">{{ strtoupper(substr($student->name, 0, 1)) }}</span>
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <p class="font-medium text-slate-900 dark:text-white truncate">{{ $student->name }}</p>
                                                @if($isLeader)
                                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-[9px] font-bold uppercase tracking-wide" title="Group leader — submitted for the group">
                                                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 1l2.39 6.36H19l-5.2 3.78 2 6.36L10 13.73l-5.8 3.77 2-6.36L1 7.36h6.61z"/></svg>
                                                        Leader
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-[11px] text-slate-400 truncate">{{ $student->email }}</p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Group --}}
                                @if($assessment->usesGroupSubmission())
                                    <td class="px-4 py-3">
                                        @if($groupInfo)
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-{{ $gColor }}-100 dark:bg-{{ $gColor }}-900/30 text-{{ $gColor }}-700 dark:text-{{ $gColor }}-400 text-[11px] font-medium">
                                                <span class="w-1.5 h-1.5 rounded-full bg-{{ $gColor }}-500"></span>
                                                {{ $groupInfo['group_name'] }}
                                            </span>
                                        @else
                                            <span class="text-[11px] text-slate-400">—</span>
                                        @endif
                                    </td>
                                @endif

                                {{-- Status --}}
                                <td class="px-4 py-3 text-center">
                                    @if(!$sub)
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">Not Submitted</span>
                                    @elseif($sub->status === 'graded')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            Graded
                                        </span>
                                    @elseif($sub->is_late)
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">Late</span>
                                    @else
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">Submitted</span>
                                    @endif
                                </td>

                                {{-- Submitted at --}}
                                <td class="px-4 py-3 text-center text-xs text-slate-500 dark:text-slate-400">
                                    {{ $sub ? $sub->submitted_at->format('d M Y, H:i') : '—' }}
                                </td>

                                {{-- Files --}}
                                <td class="px-4 py-3 text-center">
                                    @if($sub && $sub->files->count() > 0)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                            {{ $sub->files->count() }}
                                        </span>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @endif
                                </td>

                                {{-- Mark --}}
                                <td class="px-4 py-3 text-center">
                                    @if($score && $score->finalized_at)
                                        <span class="text-sm font-bold text-slate-900 dark:text-white">{{ number_format($score->raw_marks, 1) }}</span>
                                        <span class="text-xs text-slate-400">/{{ number_format($score->max_marks, 0) }}</span>
                                        @php $pct = $score->percentage; @endphp
                                        <p class="text-[10px] font-semibold {{ $pct >= 70 ? 'text-emerald-600' : ($pct >= 50 ? 'text-amber-600' : 'text-red-600') }}">{{ number_format($pct, 1) }}%</p>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @endif
                                </td>

                                {{-- Released --}}
                                <td class="px-4 py-3 text-center">
                                    @if($score && $score->is_released)
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">Released</span>
                                            <form method="POST" action="{{ route('tenant.assessments.scores.unrelease', [$tenant->slug, $course, $assessment, $score]) }}">
                                                @csrf
                                                <button type="submit" class="text-slate-400 hover:text-red-500 transition" title="Retract">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878l4.242 4.242M21 21l-4.879-4.879"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    @elseif($score && $score->finalized_at)
                                        <span class="text-[10px] text-amber-600 dark:text-amber-400 font-medium">Pending</span>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @endif
                                </td>

                                {{-- Action --}}
                                <td class="px-5 py-3 text-right">
                                    @if($sub)
                                        @if($sub->status === 'graded')
                                            <a href="{{ route('tenant.assessments.submissions.show', [$tenant->slug, $course, $assessment, $sub]) }}"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-emerald-400 dark:border-emerald-600 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 text-xs font-semibold rounded-lg transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
                                                Edit Grade
                                            </a>
                                        @else
                                            <a href="{{ route('tenant.assessments.submissions.show', [$tenant->slug, $course, $assessment, $sub]) }}"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition shadow-sm">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Grade
                                            </a>
                                        @endif
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-tenant-layout>
