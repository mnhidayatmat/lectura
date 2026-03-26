<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.analytics.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $course->code }} Analytics</h2>
                <p class="text-sm text-slate-500">{{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Overview Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-slate-900">{{ $totalStudents }}</p>
                <p class="text-sm text-slate-500">Students</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold {{ $avgMark !== null && $avgMark >= 50 ? 'text-emerald-600' : ($avgMark !== null ? 'text-red-600' : 'text-slate-900') }}">{{ $avgMark !== null ? $avgMark . '%' : '--' }}</p>
                <p class="text-sm text-slate-500">Avg Mark</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold {{ $attendanceRate !== null && $attendanceRate >= 80 ? 'text-teal-600' : ($attendanceRate !== null ? 'text-amber-600' : 'text-slate-900') }}">{{ $attendanceRate !== null ? $attendanceRate . '%' : '--' }}</p>
                <p class="text-sm text-slate-500">Attendance Rate</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-red-600">{{ $weakStudents->count() }}</p>
                <p class="text-sm text-slate-500">At Risk (&lt;40%)</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            {{-- Assignment Performance --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Assignment Performance</h3>
                </div>
                @if($assignmentStats->isEmpty())
                    <div class="p-8 text-center text-sm text-slate-400">No graded assignments yet.</div>
                @else
                    <div class="p-6 space-y-4">
                        @foreach($assignmentStats as $stat)
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
                                    <span>{{ $stat['count'] }} students</span>
                                    <span>Max: {{ $stat['max'] }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Top Students --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Top Students</h3>
                </div>
                @if($topStudents->isEmpty())
                    <div class="p-8 text-center text-sm text-slate-400">No marks data yet.</div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($topStudents as $i => $s)
                            <div class="px-6 py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold {{ $i === 0 ? 'bg-amber-100 text-amber-700' : ($i === 1 ? 'bg-slate-200 text-slate-700' : 'bg-slate-100 text-slate-500') }}">{{ $i + 1 }}</span>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">{{ $s['user']->name }}</p>
                                        <p class="text-xs text-slate-400">{{ $s['count'] }} assessments</p>
                                    </div>
                                </div>
                                <span class="text-sm font-bold text-emerald-600">{{ $s['avg_percentage'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- At-Risk Students --}}
        @if($weakStudents->isNotEmpty())
            <div class="bg-white rounded-2xl border-2 border-red-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-red-100 bg-red-50/50 flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <h3 class="font-semibold text-red-900">At-Risk Students ({{ $weakStudents->count() }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="text-left px-6 py-3 font-medium text-slate-500">Student</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Avg Mark</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Assessments</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Attendance</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Risk Level</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($weakStudents as $s)
                                @php $att = $studentAttendance[$s['user']->id] ?? 0; @endphp
                                <tr>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-xs font-bold text-red-700">{{ strtoupper(substr($s['user']->name, 0, 1)) }}</div>
                                            <span class="font-medium text-slate-900">{{ $s['user']->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-center font-bold text-red-600">{{ $s['avg_percentage'] }}%</td>
                                    <td class="px-6 py-3 text-center text-slate-600">{{ $s['count'] }}</td>
                                    <td class="px-6 py-3 text-center">
                                        <span class="{{ $att < 70 ? 'text-red-600 font-bold' : 'text-slate-600' }}">{{ $att }}%</span>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        @if($s['avg_percentage'] < 20)
                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Critical</span>
                                        @else
                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Warning</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Attendance Sessions --}}
        @if($sessions->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Attendance Trend ({{ $sessions->count() }} sessions)</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-end gap-1.5 h-32">
                        @foreach($sessions->sortBy('started_at')->take(20) as $s)
                            @php
                                $present = $s->records->whereIn('status', ['present', 'late'])->count();
                                $total = max($s->records->count(), 1);
                                $pct = round($present / $total * 100);
                            @endphp
                            <div class="flex-1 flex flex-col items-center gap-1" title="{{ $s->started_at->format('d M') }}: {{ $pct }}%">
                                <div class="w-full rounded-t {{ $pct >= 80 ? 'bg-emerald-500' : ($pct >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" style="height: {{ $pct }}%"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-between mt-2 text-[10px] text-slate-400">
                        <span>{{ $sessions->sortBy('started_at')->first()->started_at->format('d M') }}</span>
                        <span>{{ $sessions->sortBy('started_at')->last()->started_at->format('d M') }}</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-tenant-layout>
