<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('tenant.mentees.index', $tenant->slug) }}" class="text-xs text-indigo-600 hover:underline">← Back to mentees</a>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{{ $student->name }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $mentorship->roleLabel() }}{{ $mentorship->academicTerm ? ' · ' . $mentorship->academicTerm->name : '' }}</p>
            </div>
            @if($mentorship->isLiSupervisor())
                <a href="{{ route('tenant.mentees.li-details.edit', [$tenant->slug, $student]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    Edit LI Details
                </a>
            @endif
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Profile card --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex flex-col items-center text-center">
                    <div class="w-24 h-24 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-300 font-bold text-3xl mb-3">
                        @if($student->avatar_url)
                            <img src="{{ $student->avatar_url }}" alt="{{ $student->name }}" class="w-full h-full rounded-full object-cover">
                        @else
                            {{ strtoupper(substr($student->name, 0, 1)) }}
                        @endif
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $student->name }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 break-all">{{ $student->email }}</p>
                </div>

                <dl class="mt-5 pt-5 border-t border-slate-200 dark:border-slate-700 space-y-3 text-sm">
                    @php
                        $studentIdNumber = $student->tenantUsers->where('tenant_id', $tenant->id)->first()->student_id_number ?? null;
                    @endphp
                    @if($studentIdNumber)
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">Student ID</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $studentIdNumber }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Enrolled Courses</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $sections->count() }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Average Grade</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $avgPercentage ? number_format($avgPercentage, 1) . '%' : '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">Assigned Since</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ optional($mentorship->assigned_at)->format('M d, Y') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            @if($mentorship->isLiSupervisor() && $mentorship->liDetail)
                @php $d = $mentorship->liDetail; @endphp
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Industrial Training</h4>
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-slate-500">Company</dt>
                            <dd class="text-slate-900 dark:text-slate-100">{{ $d->company_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Period</dt>
                            <dd class="text-slate-900 dark:text-slate-100">
                                {{ $d->period_start ? $d->period_start->format('M d, Y') : '—' }}
                                –
                                {{ $d->period_end ? $d->period_end->format('M d, Y') : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Industry Supervisor</dt>
                            <dd class="text-slate-900 dark:text-slate-100">{{ $d->industry_supervisor_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Status</dt>
                            <dd>
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                    {{ str_replace('_', ' ', $d->placement_status) }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif

            @if($mentorship->notes)
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-2">Mentorship Notes</h4>
                    <p class="text-sm text-slate-600 dark:text-slate-400 whitespace-pre-line">{{ $mentorship->notes }}</p>
                </div>
            @endif
        </div>

        {{-- Right: Tabs --}}
        <div class="lg:col-span-2" x-data="{ tab: 'courses' }">
            <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl w-fit mb-4">
                <button type="button" @click="tab = 'courses'"
                    :class="tab === 'courses' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm' : 'text-slate-600 dark:text-slate-400'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition">Courses</button>
                <button type="button" @click="tab = 'attendance'"
                    :class="tab === 'attendance' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm' : 'text-slate-600 dark:text-slate-400'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition">Attendance</button>
                <button type="button" @click="tab = 'grades'"
                    :class="tab === 'grades' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm' : 'text-slate-600 dark:text-slate-400'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition">Grades</button>
            </div>

            {{-- Courses --}}
            <div x-show="tab === 'courses'" x-cloak class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                @if($sections->isEmpty())
                    <p class="p-8 text-sm text-slate-500 text-center">No active enrollments.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Course</th>
                                <th class="px-4 py-3 text-left">Section</th>
                                <th class="px-4 py-3 text-left">Term</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($sections as $section)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900 dark:text-slate-100">{{ $section->course->code }}</div>
                                        <div class="text-xs text-slate-500">{{ $section->course->title }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $section->name }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ optional($section->course->academicTerm)->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Attendance --}}
            <div x-show="tab === 'attendance'" x-cloak class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                @if(empty($attendanceByCourse))
                    <p class="p-8 text-sm text-slate-500 text-center">No attendance records.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Course</th>
                                <th class="px-4 py-3 text-right">Present/Total</th>
                                <th class="px-4 py-3 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($sections as $section)
                                @php $a = $attendanceByCourse[$section->course_id] ?? null; @endphp
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ $section->course->code }}</td>
                                    <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ $a ? "{$a['present']}/{$a['total']}" : '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if($a && $a['percentage'] !== null)
                                            <span class="font-semibold
                                                {{ $a['percentage'] >= 80 ? 'text-emerald-600' : ($a['percentage'] >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                                {{ $a['percentage'] }}%
                                            </span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Grades --}}
            <div x-show="tab === 'grades'" x-cloak class="space-y-4">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700">
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Assignment Marks</h4>
                    </div>
                    @if($marks->isEmpty())
                        <p class="p-8 text-sm text-slate-500 text-center">No graded assignments.</p>
                    @else
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-left">Assignment</th>
                                    <th class="px-4 py-3 text-left">Course</th>
                                    <th class="px-4 py-3 text-right">Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($marks as $mark)
                                    <tr>
                                        <td class="px-4 py-3 text-slate-900 dark:text-slate-100">{{ $mark->assignment->title ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-500">{{ optional($mark->assignment->course)->code ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">
                                            {{ $mark->percentage !== null ? number_format($mark->percentage, 1) . '%' : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                @if($assessmentScores->isNotEmpty())
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700">
                            <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Assessment Scores</h4>
                        </div>
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-left">Assessment</th>
                                    <th class="px-4 py-3 text-left">Course</th>
                                    <th class="px-4 py-3 text-right">Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($assessmentScores as $score)
                                    <tr>
                                        <td class="px-4 py-3 text-slate-900 dark:text-slate-100">{{ $score->assessment->title ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-500">{{ optional($score->assessment->course)->code ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">
                                            {{ $score->score !== null ? number_format((float) $score->score, 1) : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-tenant-layout>
