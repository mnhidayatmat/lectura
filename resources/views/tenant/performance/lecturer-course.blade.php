<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.performance.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">{{ $course->code }} {{ __('performance.course_performance') }}</h2>
                    <p class="text-sm text-slate-500">{{ $course->title }}</p>
                </div>
            </div>

            {{-- Section filter dropdown --}}
            @if($data['sections']->isNotEmpty())
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" type="button" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        {{ $selectedSection ? $selectedSection->name : __('performance.all_sections') }}
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-transition class="absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-xl shadow-lg z-20 py-1">
                        <a href="{{ route('tenant.performance.course', [app('current_tenant')->slug, $course]) }}" class="block px-4 py-2 text-sm {{ !$selectedSection ? 'text-indigo-600 font-medium bg-indigo-50' : 'text-slate-700 hover:bg-slate-50' }}">
                            {{ __('performance.all_sections') }}
                        </a>
                        @foreach($data['sections'] as $section)
                            <a href="{{ route('tenant.performance.course', [app('current_tenant')->slug, $course, 'section' => $section->id]) }}" class="block px-4 py-2 text-sm {{ $selectedSection && $selectedSection->id === $section->id ? 'text-indigo-600 font-medium bg-indigo-50' : 'text-slate-700 hover:bg-slate-50' }}">
                                {{ $section->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="space-y-6">

        {{-- Overview Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            {{-- Students --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-slate-900">{{ $data['total_students'] }}</p>
                <p class="text-sm text-slate-500">{{ __('performance.students') }}</p>
            </div>

            {{-- Avg Mark --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold {{ $data['avg_mark'] !== null && $data['avg_mark'] >= 50 ? 'text-emerald-600' : ($data['avg_mark'] !== null ? 'text-red-600' : 'text-slate-900') }}">{{ $data['avg_mark'] !== null ? round($data['avg_mark'], 1) . '%' : '--' }}</p>
                <p class="text-sm text-slate-500">{{ __('performance.avg_mark') }}</p>
            </div>

            {{-- Avg Quiz --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold {{ $data['avg_quiz'] !== null && $data['avg_quiz'] >= 50 ? 'text-violet-600' : ($data['avg_quiz'] !== null ? 'text-red-600' : 'text-slate-900') }}">{{ $data['avg_quiz'] !== null ? round($data['avg_quiz'], 1) . '%' : '--' }}</p>
                <p class="text-sm text-slate-500">{{ __('performance.avg_quiz') }}</p>
            </div>

            {{-- Attendance Rate --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold {{ $data['attendance_rate'] !== null && $data['attendance_rate'] >= 80 ? 'text-teal-600' : ($data['attendance_rate'] !== null ? 'text-amber-600' : 'text-slate-900') }}">{{ $data['attendance_rate'] !== null ? round($data['attendance_rate'], 1) . '%' : '--' }}</p>
                <p class="text-sm text-slate-500">{{ __('performance.attendance_rate') }}</p>
            </div>

            {{-- At Risk --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-red-600">{{ $data['at_risk_count'] }}</p>
                <p class="text-sm text-slate-500">{{ __('performance.at_risk') }}</p>
            </div>
        </div>

        {{-- Student Performance Table --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden" x-data="{
            sortBy: 'composite_score',
            sortDir: 'desc',
            students: {{ Js::from($data['students']) }},
            get sortedStudents() {
                return [...this.students].sort((a, b) => {
                    let valA, valB;
                    if (this.sortBy === 'name') {
                        valA = (a.user?.name || '').toLowerCase();
                        valB = (b.user?.name || '').toLowerCase();
                        return this.sortDir === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                    }
                    valA = a[this.sortBy] ?? 0;
                    valB = b[this.sortBy] ?? 0;
                    return this.sortDir === 'asc' ? valA - valB : valB - valA;
                });
            },
            toggleSort(col) {
                if (this.sortBy === col) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortBy = col;
                    this.sortDir = col === 'name' ? 'asc' : 'desc';
                }
            },
            sortIcon(col) {
                if (this.sortBy !== col) return '';
                return this.sortDir === 'asc' ? '\u25B2' : '\u25BC';
            },
            compositeColor(score) {
                if (score === null || score === undefined) return 'bg-slate-100 text-slate-600';
                if (score >= 80) return 'bg-emerald-100 text-emerald-700';
                if (score >= 60) return 'bg-blue-100 text-blue-700';
                if (score >= 40) return 'bg-amber-100 text-amber-700';
                return 'bg-red-100 text-red-700';
            }
        }">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">{{ __('performance.students') }}</h3>
                <span class="text-sm text-slate-400" x-text="sortedStudents.length + ' {{ __('performance.students') }}'"></span>
            </div>
            @if($data['students']->isEmpty())
                <div class="p-8 text-center text-sm text-slate-400">{{ __('performance.no_data') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="text-left px-6 py-3 font-medium text-slate-500 cursor-pointer select-none" @click="toggleSort('name')">
                                    <span class="inline-flex items-center gap-1">{{ __('performance.student_name') }} <span class="text-xs" x-text="sortIcon('name')"></span></span>
                                </th>
                                <th class="text-center px-4 py-3 font-medium text-slate-500 cursor-pointer select-none" @click="toggleSort('avg_mark')">
                                    <span class="inline-flex items-center gap-1">{{ __('performance.marks') }} <span class="text-xs" x-text="sortIcon('avg_mark')"></span></span>
                                </th>
                                <th class="text-center px-4 py-3 font-medium text-slate-500 cursor-pointer select-none" @click="toggleSort('avg_quiz')">
                                    <span class="inline-flex items-center gap-1">{{ __('performance.quiz') }} <span class="text-xs" x-text="sortIcon('avg_quiz')"></span></span>
                                </th>
                                <th class="text-center px-4 py-3 font-medium text-slate-500 cursor-pointer select-none" @click="toggleSort('attendance_rate')">
                                    <span class="inline-flex items-center gap-1">{{ __('performance.attendance') }} <span class="text-xs" x-text="sortIcon('attendance_rate')"></span></span>
                                </th>
                                <th class="text-center px-4 py-3 font-medium text-slate-500">
                                    {{ __('performance.active_learning') }}
                                </th>
                                <th class="text-center px-4 py-3 font-medium text-slate-500 cursor-pointer select-none" @click="toggleSort('composite_score')">
                                    <span class="inline-flex items-center gap-1">{{ __('performance.composite') }} <span class="text-xs" x-text="sortIcon('composite_score')"></span></span>
                                </th>
                                <th class="text-center px-4 py-3 font-medium text-slate-500">{{ __('performance.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="student in sortedStudents" :key="student.user?.id">
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700" x-text="student.user?.name ? student.user.name.charAt(0).toUpperCase() : '?'"></div>
                                            <div>
                                                <p class="font-medium text-slate-900" x-text="student.user?.name"></p>
                                                <p class="text-xs text-slate-400">
                                                    <span x-text="(student.assessment_count || 0) + ' {{ strtolower(__('performance.assessment_count')) }}'"></span>
                                                    <span class="mx-1">&middot;</span>
                                                    <span x-text="(student.quiz_count || 0) + ' {{ strtolower(__('performance.quiz_count')) }}'"></span>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="student.avg_mark !== null && student.avg_mark >= 50 ? 'text-emerald-600 font-bold' : (student.avg_mark !== null ? 'text-red-600 font-bold' : 'text-slate-400')" x-text="student.avg_mark !== null ? Math.round(student.avg_mark * 10) / 10 + '%' : '--'"></span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="student.avg_quiz !== null && student.avg_quiz >= 50 ? 'text-violet-600 font-bold' : (student.avg_quiz !== null ? 'text-red-600 font-bold' : 'text-slate-400')" x-text="student.avg_quiz !== null ? Math.round(student.avg_quiz * 10) / 10 + '%' : '--'"></span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="student.attendance_rate !== null && student.attendance_rate >= 80 ? 'text-teal-600 font-bold' : (student.attendance_rate !== null && student.attendance_rate >= 60 ? 'text-amber-600 font-bold' : (student.attendance_rate !== null ? 'text-red-600 font-bold' : 'text-slate-400'))" x-text="student.attendance_rate !== null ? Math.round(student.attendance_rate * 10) / 10 + '%' : '--'"></span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-slate-600" x-text="(student.al_responses || 0) + ' {{ __('performance.responses') }}'"></td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="compositeColor(student.composite_score)" x-text="student.composite_score !== null && student.composite_score !== undefined ? Math.round(student.composite_score * 10) / 10 : '--'"></span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a :href="'{{ route('tenant.performance.student', [app('current_tenant')->slug, $course, '__ID__']) }}'.replace('__ID__', student.user?.id)" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-xs font-medium transition">
                                            {{ __('performance.view_detail') }}
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            {{-- Assignment Performance --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">{{ __('performance.assignment_marks') }}</h3>
                </div>
                @if($data['assignment_stats']->isEmpty())
                    <div class="p-8 text-center text-sm text-slate-400">{{ __('performance.no_assignments') }}</div>
                @else
                    <div class="p-6 space-y-4">
                        @foreach($data['assignment_stats'] as $stat)
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-sm font-medium text-slate-700">{{ $stat['title'] }}</span>
                                    <span class="text-sm font-bold {{ $stat['avg'] >= 50 ? 'text-emerald-600' : 'text-red-600' }}">{{ $stat['avg'] }}%</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-3 relative">
                                    {{-- Min-Max range --}}
                                    <div class="absolute h-3 bg-slate-200 rounded-full" style="left: {{ $stat['min'] }}%; width: {{ $stat['max'] - $stat['min'] }}%"></div>
                                    {{-- Average marker --}}
                                    <div class="absolute h-3 w-1 bg-indigo-600 rounded" style="left: {{ $stat['avg'] }}%"></div>
                                    {{-- Fill to average --}}
                                    <div class="h-3 rounded-full {{ $stat['avg'] >= 50 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $stat['avg'] }}%"></div>
                                </div>
                                <div class="flex justify-between mt-1 text-[10px] text-slate-400">
                                    <span>Min: {{ $stat['min'] }}%</span>
                                    <span>{{ $stat['count'] }} {{ strtolower(__('performance.students')) }}</span>
                                    <span>Max: {{ $stat['max'] }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- CLO Attainment --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">{{ __('performance.clo_attainment') }}</h3>
                </div>
                @if(empty($data['clo_attainment']))
                    <div class="p-8 text-center text-sm text-slate-400">{{ __('performance.no_data') }}</div>
                @else
                    <div class="p-6 space-y-4">
                        @foreach($data['clo_attainment'] as $clo)
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-semibold text-slate-700">{{ $clo['code'] }}</span>
                                        <span class="text-xs text-slate-400 ml-1.5 truncate">{{ Str::limit($clo['description'], 50) }}</span>
                                    </div>
                                    <span class="text-sm font-bold ml-3 {{ $clo['avg'] >= 60 ? 'text-emerald-600' : ($clo['avg'] >= 40 ? 'text-amber-600' : 'text-red-600') }}">{{ round($clo['avg'], 1) }}%</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-3">
                                    <div class="h-3 rounded-full {{ $clo['avg'] >= 60 ? 'bg-emerald-500' : ($clo['avg'] >= 40 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ min($clo['avg'], 100) }}%"></div>
                                </div>
                                <div class="flex justify-between mt-1 text-[10px] text-slate-400">
                                    <span>{{ $clo['count'] }} {{ strtolower(__('performance.assessment_count')) }}</span>
                                    <span>{{ $clo['student_count'] }} {{ strtolower(__('performance.students')) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Quiz Performance --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('performance.quiz_scores') }}</h3>
            </div>
            @if($data['quiz_stats']->isEmpty())
                <div class="p-8 text-center text-sm text-slate-400">{{ __('performance.no_quizzes') }}</div>
            @else
                <div class="p-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($data['quiz_stats'] as $quiz)
                        <div class="border border-slate-200 rounded-xl p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $quiz['title'] }}</p>
                                    @if(!empty($quiz['category']))
                                        <span class="inline-block mt-1 text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">{{ $quiz['category'] }}</span>
                                    @endif
                                </div>
                                <span class="text-lg font-bold {{ $quiz['avg_score'] >= 60 ? 'text-emerald-600' : ($quiz['avg_score'] >= 40 ? 'text-amber-600' : 'text-red-600') }}">{{ round($quiz['avg_score'], 1) }}%</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 mb-2">
                                <div class="h-2 rounded-full {{ $quiz['avg_score'] >= 60 ? 'bg-emerald-500' : ($quiz['avg_score'] >= 40 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ min($quiz['avg_score'], 100) }}%"></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-slate-400">
                                <span>{{ $quiz['participant_count'] }} {{ strtolower(__('performance.students')) }}</span>
                                @if(!empty($quiz['date']))
                                    <span>{{ \Carbon\Carbon::parse($quiz['date'])->format('d M Y') }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Attendance Trend --}}
        @if($data['attendance_sessions']->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">{{ __('performance.attendance_record') }} ({{ $data['attendance_sessions']->count() }} sessions)</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-end gap-1.5 h-32">
                        @foreach($data['attendance_sessions']->sortBy('started_at')->take(20) as $session)
                            @php
                                $present = $session->records->whereIn('status', ['present', 'late'])->count();
                                $total = max($session->records->count(), 1);
                                $pct = round($present / $total * 100);
                            @endphp
                            <div class="flex-1 flex flex-col items-center gap-1" title="{{ $session->started_at->format('d M') }}: {{ $pct }}%">
                                <div class="w-full rounded-t {{ $pct >= 80 ? 'bg-emerald-500' : ($pct >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" style="height: {{ $pct }}%"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-between mt-2 text-[10px] text-slate-400">
                        <span>{{ $data['attendance_sessions']->sortBy('started_at')->first()->started_at->format('d M') }}</span>
                        <span>{{ $data['attendance_sessions']->sortBy('started_at')->last()->started_at->format('d M') }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- AI Suggestions Panel --}}
        @php
            $tenant = app('current_tenant');
            $isPro = auth()->user()->isPro();
            $aiEnabled = $tenant->isAiEnabled();
        @endphp
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    <h3 class="font-semibold text-slate-900">{{ __('performance.ai_suggestions') }}</h3>
                </div>
                @if(!$isPro || !$aiEnabled)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-500">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        PRO
                    </span>
                @endif
            </div>

            <div class="p-6">
                @if(!$isPro || !$aiEnabled)
                    {{-- Pro locked state --}}
                    <div class="text-center py-6">
                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <p class="text-sm text-slate-500">{{ __('performance.ai_pro_required') }}</p>
                    </div>
                @elseif($latestSuggestion && $latestSuggestion->isProcessing())
                    {{-- Processing state with polling --}}
                    <div class="text-center py-6" x-data="{
                        polling: true,
                        init() {
                            this.poll();
                        },
                        poll() {
                            if (!this.polling) return;
                            setTimeout(() => {
                                fetch('{{ route('tenant.performance.ai-status', [app('current_tenant')->slug, $course]) }}')
                                    .then(r => r.json())
                                    .then(data => {
                                        if (data.status === 'completed') {
                                            window.location.reload();
                                        } else {
                                            this.poll();
                                        }
                                    })
                                    .catch(() => this.poll());
                            }, 5000);
                        }
                    }" x-init="init()">
                        <div class="flex items-center justify-center gap-3 mb-3">
                            <svg class="w-5 h-5 text-indigo-600 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm font-medium text-indigo-600">{{ __('performance.ai_generating') }}</span>
                        </div>
                    </div>
                @elseif($latestSuggestion && $latestSuggestion->isCompleted())
                    {{-- Completed AI suggestions --}}
                    @php $content = $latestSuggestion->content; @endphp
                    <div class="space-y-5">
                        {{-- Overall Assessment --}}
                        @if(!empty($content['overall_assessment']))
                            <div>
                                <h4 class="text-sm font-semibold text-slate-900 mb-2">{{ __('performance.overall_assessment') }}</h4>
                                <p class="text-sm text-slate-600 leading-relaxed">{{ $content['overall_assessment'] }}</p>
                            </div>
                        @endif

                        {{-- Strengths --}}
                        @if(!empty($content['strengths']))
                            <div>
                                <h4 class="text-sm font-semibold text-emerald-700 mb-2 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ __('performance.strengths') }}
                                </h4>
                                <ul class="space-y-1.5">
                                    @foreach($content['strengths'] as $strength)
                                        <li class="flex items-start gap-2 text-sm text-slate-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5 flex-shrink-0"></span>
                                            {{ $strength }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Weaknesses --}}
                        @if(!empty($content['weaknesses']))
                            <div>
                                <h4 class="text-sm font-semibold text-red-700 mb-2 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                    {{ __('performance.weaknesses') }}
                                </h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-slate-100">
                                                <th class="text-left py-2 pr-4 font-medium text-slate-500">{{ __('performance.weakness') ?? 'Area' }}</th>
                                                <th class="text-left py-2 pr-4 font-medium text-slate-500">{{ __('performance.detail') ?? 'Detail' }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach($content['weaknesses'] as $weakness)
                                                @if(is_array($weakness))
                                                    <tr>
                                                        <td class="py-2 pr-4 text-slate-700 font-medium">{{ $weakness['area'] ?? $weakness['title'] ?? '' }}</td>
                                                        <td class="py-2 pr-4 text-slate-600">{{ $weakness['detail'] ?? $weakness['description'] ?? '' }}</td>
                                                    </tr>
                                                @else
                                                    <tr>
                                                        <td class="py-2 pr-4 text-slate-600" colspan="2">
                                                            <span class="flex items-start gap-2">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 mt-1.5 flex-shrink-0"></span>
                                                                {{ $weakness }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Recommendations --}}
                        @if(!empty($content['recommendations']))
                            <div>
                                <h4 class="text-sm font-semibold text-indigo-700 mb-2 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    {{ __('performance.recommendations') }}
                                </h4>
                                <ul class="space-y-1.5">
                                    @foreach($content['recommendations'] as $rec)
                                        <li class="flex items-start gap-2 text-sm text-slate-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mt-1.5 flex-shrink-0"></span>
                                            {{ is_array($rec) ? ($rec['text'] ?? $rec['description'] ?? json_encode($rec)) : $rec }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- CQI Actions --}}
                        @if(!empty($content['cqi_actions']))
                            <div>
                                <h4 class="text-sm font-semibold text-amber-700 mb-2 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    {{ __('performance.cqi_actions') }}
                                </h4>
                                <ul class="space-y-1.5">
                                    @foreach($content['cqi_actions'] as $action)
                                        <li class="flex items-start gap-2 text-sm text-slate-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mt-1.5 flex-shrink-0"></span>
                                            {{ is_array($action) ? ($action['text'] ?? $action['description'] ?? json_encode($action)) : $action }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- At-Risk Interventions --}}
                        @if(!empty($content['at_risk_interventions']))
                            <div>
                                <h4 class="text-sm font-semibold text-red-700 mb-2 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ __('performance.at_risk_interventions') }}
                                </h4>
                                <ul class="space-y-1.5">
                                    @foreach($content['at_risk_interventions'] as $intervention)
                                        <li class="flex items-start gap-2 text-sm text-slate-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 mt-1.5 flex-shrink-0"></span>
                                            {{ is_array($intervention) ? ($intervention['text'] ?? $intervention['description'] ?? json_encode($intervention)) : $intervention }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Generated timestamp + regenerate --}}
                        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                            <span class="text-xs text-slate-400">{{ __('performance.date') }}: {{ $latestSuggestion->created_at->format('d M Y, H:i') }}</span>
                            <form action="{{ route('tenant.performance.ai-generate', [app('current_tenant')->slug, $course]) }}" method="POST">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    {{ __('performance.ai_generate') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    {{-- No suggestion yet, show generate button --}}
                    <div class="text-center py-6">
                        <div class="w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        </div>
                        <p class="text-sm text-slate-500 mb-4">{{ __('performance.ai_suggestions') }}</p>
                        <form action="{{ route('tenant.performance.ai-generate', [app('current_tenant')->slug, $course]) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                {{ __('performance.ai_generate') }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-tenant-layout>
