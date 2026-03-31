<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.performance.course', [app('current_tenant')->slug, $course]) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $student->name }}</h2>
                <p class="text-sm text-slate-500">{{ $student->email }} &middot; {{ $course->code }} &mdash; {{ $course->title }}</p>
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

        <div class="grid lg:grid-cols-2 gap-6">
            {{-- Assignment Marks Table --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">{{ __('performance.assignment_marks') }}</h3>
                </div>
                @if($data['marks']->isEmpty())
                    <div class="p-8 text-center text-sm text-slate-400">{{ __('performance.no_marks') }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/50">
                                    <th class="text-left px-6 py-3 font-medium text-slate-500">{{ __('performance.assignment') }}</th>
                                    <th class="text-center px-6 py-3 font-medium text-slate-500">{{ __('performance.marks') }}</th>
                                    <th class="text-center px-6 py-3 font-medium text-slate-500">%</th>
                                    <th class="text-center px-6 py-3 font-medium text-slate-500">{{ __('performance.grade') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($data['marks'] as $mark)
                                    <tr>
                                        <td class="px-6 py-3 font-medium text-slate-900">{{ $mark->assignment->title }}</td>
                                        <td class="px-6 py-3 text-center text-slate-600">{{ $mark->total_marks }}/{{ $mark->max_marks }}</td>
                                        <td class="px-6 py-3 text-center font-bold {{ $mark->percentage >= 70 ? 'text-emerald-600' : ($mark->percentage >= 40 ? 'text-amber-600' : 'text-red-600') }}">
                                            {{ number_format($mark->percentage, 1) }}%
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold
                                                {{ $mark->percentage >= 70 ? 'bg-emerald-100 text-emerald-700' : ($mark->percentage >= 40 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                                {{ $mark->grade }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Quiz Scores Table --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">{{ __('performance.quiz_scores') }}</h3>
                </div>
                @if($data['quiz_participations']->isEmpty())
                    <div class="p-8 text-center text-sm text-slate-400">{{ __('performance.no_quizzes') }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/50">
                                    <th class="text-left px-6 py-3 font-medium text-slate-500">{{ __('performance.quiz') }}</th>
                                    <th class="text-center px-6 py-3 font-medium text-slate-500">{{ __('performance.score') }}</th>
                                    <th class="text-center px-6 py-3 font-medium text-slate-500">{{ __('performance.date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($data['quiz_participations'] as $participation)
                                    <tr>
                                        <td class="px-6 py-3 font-medium text-slate-900">{{ $participation->quizSession->title }}</td>
                                        <td class="px-6 py-3 text-center font-bold text-violet-600">{{ $participation->total_score }}</td>
                                        <td class="px-6 py-3 text-center text-slate-500">{{ $participation->quizSession->started_at->format('d M Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
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

        {{-- AI Suggestions --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-teal-600" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                    <h3 class="font-semibold text-slate-900">{{ __('performance.ai_suggestions') }}</h3>
                </div>
                <form method="POST" action="{{ route('tenant.performance.ai-generate', [app('current_tenant')->slug, $course]) }}">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $student->id }}">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                        {{ __('performance.generate_suggestions') }}
                    </button>
                </form>
            </div>

            @if($latestSuggestion)
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
            @else
                <div class="p-8 text-center">
                    <div class="w-12 h-12 bg-teal-50 rounded-xl flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-teal-400" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                    </div>
                    <p class="text-sm text-slate-500">{{ __('performance.no_suggestions') }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ __('performance.no_suggestions_hint') }}</p>
                </div>
            @endif
        </div>
    </div>
</x-tenant-layout>
