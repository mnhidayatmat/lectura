<x-tenant-layout>
    @php $tenant = app('current_tenant'); @endphp

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.attendance.index', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Attendance Report</h2>
                    <p class="mt-0.5 text-sm text-slate-500">{{ $course->code }} — {{ $course->title }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('tenant.attendance.report.pdf', [$tenant->slug, $course, 'section_id' => $section?->id]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                    PDF
                </a>
                <a href="{{ route('tenant.attendance.report.excel', [$tenant->slug, $course, 'section_id' => $section?->id]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                    Excel
                </a>
                <form method="POST" action="{{ route('tenant.attendance.report.sync', [$tenant->slug, $course]) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/></svg>
                        Save to Course Files
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">

        {{-- Section Filter --}}
        @if($sections->count() > 1)
            <div class="flex items-center gap-2">
                <span class="text-sm text-slate-500">Section:</span>
                <a href="{{ route('tenant.attendance.report', [$tenant->slug, $course]) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium {{ !$section ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                    All
                </a>
                @foreach($sections as $sec)
                    <a href="{{ route('tenant.attendance.report', [$tenant->slug, $course, 'section_id' => $sec->id]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $section?->id === $sec->id ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                        {{ $sec->name }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white rounded-2xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-slate-900">{{ $report['sessions']->count() }}</p>
                <p class="text-xs text-slate-400">Sessions</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-indigo-700">{{ $report['students']->count() }}</p>
                <p class="text-xs text-slate-400">Students</p>
            </div>
            @php
                $avgRate = $report['summary']->count() > 0
                    ? round($report['summary']->avg('rate'), 1)
                    : 0;
            @endphp
            <div class="bg-white rounded-2xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold {{ $avgRate >= 80 ? 'text-emerald-600' : ($avgRate >= 60 ? 'text-amber-600' : 'text-red-600') }}">{{ $avgRate }}%</p>
                <p class="text-xs text-slate-400">Avg Attendance</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4 text-center">
                @php $warningCount = $report['summary']->filter(fn($s) => $s['warning_level'])->count(); @endphp
                <p class="text-2xl font-bold {{ $warningCount > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $warningCount }}</p>
                <p class="text-xs text-slate-400">At Risk</p>
            </div>
        </div>

        {{-- Summary Table --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900 text-sm">Student Summary</h3>
            </div>
            @if($report['summary']->isEmpty())
                <div class="p-8 text-center text-sm text-slate-400">No attendance data yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="text-left px-5 py-3 font-medium text-slate-500">#</th>
                                <th class="text-left px-5 py-3 font-medium text-slate-500">Student</th>
                                <th class="text-center px-3 py-3 font-medium text-emerald-600">P</th>
                                <th class="text-center px-3 py-3 font-medium text-amber-600">L</th>
                                <th class="text-center px-3 py-3 font-medium text-red-600">A</th>
                                <th class="text-center px-3 py-3 font-medium text-blue-600">E</th>
                                <th class="text-center px-5 py-3 font-medium text-slate-500">Rate</th>
                                <th class="text-center px-3 py-3 font-medium text-slate-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($report['summary'] as $i => $row)
                                @php
                                    $rateColor = $row['rate'] >= 80 ? 'emerald' : ($row['rate'] >= 60 ? 'amber' : 'red');
                                @endphp
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-5 py-3 text-slate-400">{{ $i + 1 }}</td>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-slate-900">{{ $row['student']->name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $row['student']->email }}</p>
                                    </td>
                                    <td class="text-center px-3 py-3 text-emerald-600 font-semibold">{{ $row['present'] }}</td>
                                    <td class="text-center px-3 py-3 text-amber-600 font-semibold">{{ $row['late'] }}</td>
                                    <td class="text-center px-3 py-3 text-red-600 font-semibold">{{ $row['absent'] }}</td>
                                    <td class="text-center px-3 py-3 text-blue-600 font-semibold">{{ $row['excused'] }}</td>
                                    <td class="text-center px-5 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-{{ $rateColor }}-50 text-{{ $rateColor }}-700">{{ $row['rate'] }}%</span>
                                    </td>
                                    <td class="text-center px-3 py-3">
                                        @if($row['warning_level'])
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold
                                                {{ $row['warning_level'] >= 3 ? 'bg-red-100 text-red-700' : ($row['warning_level'] >= 2 ? 'bg-amber-100 text-amber-700' : 'bg-yellow-100 text-yellow-700') }}">
                                                W{{ $row['warning_level'] }}
                                            </span>
                                        @else
                                            <span class="text-emerald-500"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Session Detail Matrix --}}
        @if($report['sessions']->count() > 0 && $report['students']->count() > 0)
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900 text-sm">Session Detail</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">P = Present, L = Late, A = Absent, E = Excused</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="text-xs min-w-full">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="text-left px-4 py-2 font-medium text-slate-500 sticky left-0 bg-slate-50/50 min-w-[140px]">Student</th>
                                @foreach($report['sessions'] as $session)
                                    <th class="text-center px-2 py-2 font-medium text-slate-500 min-w-[60px]">
                                        <div>W{{ $session->week_number ?? '?' }}</div>
                                        <div class="text-[9px] text-slate-400 font-normal">{{ $session->started_at?->format('d/m') }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($report['students'] as $student)
                                <tr class="hover:bg-slate-50/30">
                                    <td class="px-4 py-2 font-medium text-slate-700 sticky left-0 bg-white truncate max-w-[140px]">{{ $student->name }}</td>
                                    @foreach($report['sessions'] as $session)
                                        @php
                                            $st = $report['matrix'][$student->id][$session->id] ?? null;
                                            $cellConfig = match($st) {
                                                'present' => ['P', 'text-emerald-600 bg-emerald-50'],
                                                'late' => ['L', 'text-amber-600 bg-amber-50'],
                                                'absent' => ['A', 'text-red-600 bg-red-50'],
                                                'excused' => ['E', 'text-blue-600 bg-blue-50'],
                                                default => ['-', 'text-slate-300'],
                                            };
                                        @endphp
                                        <td class="text-center px-2 py-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded text-[10px] font-bold {{ $cellConfig[1] }}">{{ $cellConfig[0] }}</span>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-tenant-layout>
