<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.my-performance', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $course->code }}</h2>
                <p class="text-sm text-slate-500">{{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Stat Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <p class="text-2xl font-bold {{ $data['avg_mark'] !== null && $data['avg_mark'] >= 50 ? 'text-emerald-600' : ($data['avg_mark'] !== null ? 'text-red-600' : 'text-slate-900') }}">
                    {{ $data['avg_mark'] !== null ? number_format($data['avg_mark'], 1) . '%' : '--' }}
                </p>
                <p class="text-sm text-slate-500">{{ __('performance.avg_mark') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-2xl font-bold {{ $data['avg_quiz'] !== null && $data['avg_quiz'] >= 50 ? 'text-violet-600' : ($data['avg_quiz'] !== null ? 'text-red-600' : 'text-slate-900') }}">
                    {{ $data['avg_quiz'] !== null ? number_format($data['avg_quiz'], 1) . '%' : '--' }}
                </p>
                <p class="text-sm text-slate-500">{{ __('performance.avg_quiz') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <p class="text-2xl font-bold {{ $data['attendance_rate'] !== null && $data['attendance_rate'] >= 80 ? 'text-teal-600' : ($data['attendance_rate'] !== null ? 'text-amber-600' : 'text-slate-900') }}">
                    {{ $data['attendance_rate'] !== null ? number_format($data['attendance_rate'], 1) . '%' : '--' }}
                </p>
                <p class="text-sm text-slate-500">{{ __('performance.attendance_rate') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </div>
                <p class="text-2xl font-bold text-indigo-600">
                    {{ $data['composite_score'] !== null ? number_format($data['composite_score'], 1) . '%' : '--' }}
                </p>
                <p class="text-sm text-slate-500">{{ __('performance.composite_score') }}</p>
            </div>
        </div>

        {{-- CLO Attainment --}}
        @if(!empty($data['clo_attainment']))
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">{{ __('performance.clo_attainment') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    @foreach($data['clo_attainment'] as $clo)
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-700">{{ $clo['code'] }}</span>
                                    <span class="text-xs text-slate-400 truncate max-w-xs hidden sm:inline">{{ $clo['description'] }}</span>
                                </div>
                                <span class="text-sm font-bold {{ $clo['avg'] >= 50 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($clo['avg'], 1) }}%</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-3">
                                <div class="h-3 rounded-full transition-all {{ $clo['avg'] >= 70 ? 'bg-emerald-500' : ($clo['avg'] >= 50 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ min($clo['avg'], 100) }}%"></div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1">{{ $clo['count'] }} {{ __('performance.assessments') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Marks & Feedback (Expandable) --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('nav.marks') }} & Feedback</h3>
            </div>

            @if($data['marks']->isEmpty())
                <div class="p-8 text-center text-sm text-slate-400">{{ __('performance.no_marks') }}</div>
            @else
                <div class="space-y-2 p-4" x-data="{ expanded: {} }">
                    @foreach($data['marks'] as $mark)
                        @php
                            $assignment = $mark->assignment;
                            $feedback = null;
                            // Try to get feedback from submission if exists
                            if ($assignment->submissions && $assignment->submissions->count() > 0) {
                                $submission = $assignment->submissions->first();
                                if ($submission->feedback && $submission->feedback->is_released) {
                                    $feedback = $submission->feedback;
                                }
                            }
                        @endphp
                        <div class="border border-slate-200 rounded-xl hover:border-slate-300 transition overflow-hidden"
                             x-data="{ open: false }">
                            {{-- Header (clickable) --}}
                            <button @click="open = !open"
                                    class="w-full flex items-center justify-between px-5 py-4 hover:bg-slate-50/50 transition text-left">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 {{ $mark->percentage >= 70 ? 'bg-emerald-100' : ($mark->percentage >= 40 ? 'bg-amber-100' : 'bg-red-100') }}">
                                        <svg class="w-5 h-5 {{ $mark->percentage >= 70 ? 'text-emerald-600' : ($mark->percentage >= 40 ? 'text-amber-600' : 'text-red-600') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-slate-900">{{ $assignment->title }}</p>
                                        <p class="text-xs text-slate-400 mt-0.5">{{ ucfirst($assignment->type) }} • Due {{ $assignment->deadline?->format('d M Y') ?? 'N/A' }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 flex-shrink-0 ml-4">
                                    <div class="text-right">
                                        <p class="font-bold {{ $mark->percentage >= 70 ? 'text-emerald-600' : ($mark->percentage >= 40 ? 'text-amber-600' : 'text-red-600') }}">
                                            {{ number_format($mark->percentage, 0) }}%
                                        </p>
                                        <p class="text-[10px] text-slate-400">{{ $mark->total_marks }}/{{ $mark->max_marks }}</p>
                                    </div>
                                    <svg class="w-4 h-4 text-slate-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                </div>
                            </button>

                            {{-- Expandable Content --}}
                            <div x-show="open" x-transition class="border-t border-slate-100 bg-slate-50/50 px-5 py-4 space-y-4">
                                {{-- Grade & Marks Details --}}
                                <div class="grid sm:grid-cols-3 gap-3">
                                    <div class="bg-white rounded-lg p-3 border border-slate-200">
                                        <p class="text-xs font-semibold text-slate-500 uppercase">Total Marks</p>
                                        <p class="text-lg font-bold text-slate-900 mt-1">{{ $mark->total_marks }}/{{ $mark->max_marks }}</p>
                                    </div>
                                    <div class="bg-white rounded-lg p-3 border border-slate-200">
                                        <p class="text-xs font-semibold text-slate-500 uppercase">Percentage</p>
                                        <p class="text-lg font-bold {{ $mark->percentage >= 70 ? 'text-emerald-600' : ($mark->percentage >= 40 ? 'text-amber-600' : 'text-red-600') }} mt-1">{{ number_format($mark->percentage, 1) }}%</p>
                                    </div>
                                    <div class="bg-white rounded-lg p-3 border border-slate-200">
                                        <p class="text-xs font-semibold text-slate-500 uppercase">Grade</p>
                                        <p class="text-lg font-bold text-slate-900 mt-1">{{ $mark->grade }}</p>
                                    </div>
                                </div>

                                {{-- Feedback Section --}}
                                @if($feedback)
                                    <div class="space-y-3">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11-1a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd"/></svg>
                                            <h4 class="font-semibold text-slate-900 text-sm">Feedback from Lecturer</h4>
                                        </div>

                                        {{-- Strengths --}}
                                        @if($feedback->strengths)
                                            <div class="bg-emerald-50 rounded-lg p-3 border border-emerald-200">
                                                <p class="text-xs font-semibold text-emerald-700 flex items-center gap-1 mb-1">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                    Strengths
                                                </p>
                                                <p class="text-sm text-emerald-800">{{ $feedback->strengths }}</p>
                                            </div>
                                        @endif

                                        {{-- Improvements --}}
                                        @if($feedback->improvements)
                                            <div class="bg-amber-50 rounded-lg p-3 border border-amber-200">
                                                <p class="text-xs font-semibold text-amber-700 flex items-center gap-1 mb-1">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                                    Areas for Improvement
                                                </p>
                                                <p class="text-sm text-amber-800">{{ $feedback->improvements }}</p>
                                            </div>
                                        @endif

                                        {{-- Missing Points --}}
                                        @if($feedback->missing_points)
                                            <div class="bg-red-50 rounded-lg p-3 border border-red-200">
                                                <p class="text-xs font-semibold text-red-700 flex items-center gap-1 mb-1">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                    Missing Points
                                                </p>
                                                <p class="text-sm text-red-800">{{ $feedback->missing_points }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="text-sm text-slate-500 italic">
                                        Feedback not yet released by lecturer.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Attendance Timeline --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('performance.attendance_timeline') }}</h3>
            </div>
            @if($data['attendance_timeline']->isEmpty())
                <div class="p-8 text-center text-sm text-slate-400">{{ __('performance.no_attendance') }}</div>
            @else
                <div class="p-6">
                    <div class="flex flex-wrap gap-2">
                        @foreach($data['attendance_timeline'] as $record)
                            @php
                                $colors = [
                                    'present' => 'bg-emerald-500',
                                    'late'    => 'bg-amber-400',
                                    'absent'  => 'bg-red-500',
                                    'excused' => 'bg-slate-300',
                                ];
                                $labels = [
                                    'present' => __('performance.present'),
                                    'late'    => __('performance.late'),
                                    'absent'  => __('performance.absent'),
                                    'excused' => __('performance.excused'),
                                ];
                                $color = $colors[$record->status] ?? 'bg-slate-300';
                                $label = $labels[$record->status] ?? $record->status;
                            @endphp
                            <div class="flex flex-col items-center gap-1" title="{{ $label }} &mdash; {{ $record->date->format('d M Y') }}">
                                <div class="w-8 h-8 rounded-full {{ $color }} flex items-center justify-center">
                                    <span class="text-[10px] font-bold text-white">{{ $record->week }}</span>
                                </div>
                                <span class="text-[10px] text-slate-400">W{{ $record->week }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-4 mt-4 pt-4 border-t border-slate-100">
                        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-full bg-emerald-500"></div><span class="text-[10px] text-slate-500">{{ __('performance.present') }}</span></div>
                        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-full bg-amber-400"></div><span class="text-[10px] text-slate-500">{{ __('performance.late') }}</span></div>
                        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-full bg-red-500"></div><span class="text-[10px] text-slate-500">{{ __('performance.absent') }}</span></div>
                        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-full bg-slate-300"></div><span class="text-[10px] text-slate-500">{{ __('performance.excused') }}</span></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- AI Suggestions (read-only for students) --}}
        @if($latestSuggestion && $latestSuggestion->status === 'completed')
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-teal-600" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                        <h3 class="font-semibold text-slate-900">{{ __('performance.ai_suggestions') }}</h3>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    @if($latestSuggestion->summary)
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                            <h4 class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">{{ __('performance.summary') }}</h4>
                            <p class="text-sm text-slate-700 leading-relaxed">{{ $latestSuggestion->summary }}</p>
                        </div>
                    @endif

                    @if($latestSuggestion->strengths)
                        <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
                            <h4 class="text-[10px] font-semibold text-emerald-700 uppercase tracking-wider mb-2 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ __('performance.strengths') }}
                            </h4>
                            <p class="text-sm text-emerald-800 leading-relaxed">{{ $latestSuggestion->strengths }}</p>
                        </div>
                    @endif

                    @if($latestSuggestion->weaknesses)
                        <div class="bg-amber-50 rounded-xl p-4 border border-amber-100">
                            <h4 class="text-[10px] font-semibold text-amber-700 uppercase tracking-wider mb-2 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                {{ __('performance.weaknesses') }}
                            </h4>
                            <p class="text-sm text-amber-800 leading-relaxed">{{ $latestSuggestion->weaknesses }}</p>
                        </div>
                    @endif

                    @if($latestSuggestion->improvement_plan)
                        <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100">
                            <h4 class="text-[10px] font-semibold text-indigo-700 uppercase tracking-wider mb-2 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                {{ __('performance.improvement_plan') }}
                            </h4>
                            <p class="text-sm text-indigo-800 leading-relaxed">{{ $latestSuggestion->improvement_plan }}</p>
                        </div>
                    @endif

                    @if($latestSuggestion->study_tips)
                        <div class="bg-teal-50 rounded-xl p-4 border border-teal-100">
                            <h4 class="text-[10px] font-semibold text-teal-700 uppercase tracking-wider mb-2 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                {{ __('performance.study_tips') }}
                            </h4>
                            <p class="text-sm text-teal-800 leading-relaxed">{{ $latestSuggestion->study_tips }}</p>
                        </div>
                    @endif

                    <p class="text-[10px] text-slate-400 text-right">
                        {{ __('performance.generated_at') }}: {{ $latestSuggestion->created_at->format('d M Y, H:i') }}
                    </p>
                </div>
            </div>
        @endif
    </div>
</x-tenant-layout>
