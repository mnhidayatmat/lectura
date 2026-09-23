<x-tenant-layout>
    @php $tenant = app('current_tenant'); @endphp

    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.attendance.index', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition flex-shrink-0">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="min-w-0">
                <p class="text-xs font-bold text-emerald-600">{{ $course->code }}</p>
                <h2 class="text-2xl font-bold text-slate-900 truncate">{{ $course->title }}</h2>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Summary --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @php
                $rate = $stats['rate'];
                $rateText = $rate === null ? 'text-slate-400' : ($rate >= 80 ? 'text-emerald-600' : ($rate >= 60 ? 'text-amber-600' : 'text-red-600'));
            @endphp
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <p class="text-xs text-slate-500">Avg. attendance</p>
                <p class="mt-1 text-2xl font-bold {{ $rateText }}">{{ $rate === null ? '—' : $rate . '%' }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <p class="text-xs text-slate-500">Sessions held</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['sessions'] }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <p class="text-xs text-slate-500">Students</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['students'] }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <p class="text-xs text-slate-500">Last session</p>
                <p class="mt-1 text-base font-semibold text-slate-900 truncate">{{ $stats['last'] ? $stats['last']->format('d M Y') : '—' }}</p>
                @if($stats['last'])<p class="text-xs text-slate-400">{{ $stats['last']->diffForHumans() }}</p>@endif
            </div>
        </div>

        {{-- Live Sessions --}}
        @if($activeSessions->isNotEmpty())
            <div class="bg-white rounded-2xl border-2 border-emerald-200 overflow-hidden">
                <div class="px-6 py-3 border-b border-emerald-100 bg-emerald-50/50 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    <h3 class="text-sm font-semibold text-emerald-900">Live now ({{ $activeSessions->count() }})</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($activeSessions as $session)
                        <a href="{{ route('tenant.attendance.qr', [$tenant->slug, $session]) }}" class="flex items-center justify-between gap-4 px-6 py-3 hover:bg-emerald-50/30 transition">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">{{ $session->section->name }}</p>
                                <p class="text-xs text-slate-500">{{ ucfirst($session->session_type) }}{{ $session->week_number ? ' • Week ' . $session->week_number : '' }} • Started {{ $session->started_at->diffForHumans() }}</p>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <div class="text-right">
                                    <p class="text-lg font-bold text-emerald-600 leading-tight">{{ $session->present_count + $session->late_count }}</p>
                                    <p class="text-[11px] text-slate-400">checked in</p>
                                </div>
                                <span class="px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg">Open QR</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid lg:grid-cols-3 gap-6 items-start">
            {{-- Session History --}}
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden"
                 x-data="{ section: 'all' }">
                <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">Session History</h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $pastSessions->count() }} {{ Str::plural('session', $pastSessions->count()) }}</p>
                    </div>
                    @if($sections->count() > 1 && $pastSessions->isNotEmpty())
                        <div class="flex flex-wrap gap-1 p-1 bg-slate-100 rounded-xl">
                            <button type="button" @click="section = 'all'"
                                    :class="section === 'all' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                    class="px-3 py-1 text-xs font-medium rounded-lg transition">All</button>
                            @foreach($sections as $section)
                                <button type="button" @click="section = '{{ $section->id }}'"
                                        :class="section === '{{ $section->id }}' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                        class="px-3 py-1 text-xs font-medium rounded-lg transition">{{ $section->name }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if($pastSessions->isEmpty())
                    <div class="p-10 text-center">
                        <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </div>
                        <p class="text-sm text-slate-500">No completed sessions yet.</p>
                        <p class="text-xs text-slate-400 mt-1">Start a session to take attendance.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 text-xs">
                                    <th class="text-left px-6 py-3 font-medium text-slate-500">Date</th>
                                    <th class="text-left px-4 py-3 font-medium text-slate-500">Section</th>
                                    <th class="text-center px-3 py-3 font-medium text-slate-500">Present</th>
                                    <th class="text-center px-3 py-3 font-medium text-slate-500">Late</th>
                                    <th class="text-center px-3 py-3 font-medium text-slate-500">Absent</th>
                                    <th class="text-left px-4 py-3 font-medium text-slate-500">Rate</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($pastSessions as $session)
                                    @php
                                        $sessionRate = $session->records_count > 0
                                            ? (int) round(($session->present_count + $session->late_count) / $session->records_count * 100)
                                            : null;
                                        $barColor = $sessionRate === null ? 'bg-slate-200' : ($sessionRate >= 80 ? 'bg-emerald-500' : ($sessionRate >= 60 ? 'bg-amber-500' : 'bg-red-500'));
                                    @endphp
                                    <tr x-show="section === 'all' || section === '{{ $session->section_id }}'"
                                        class="hover:bg-slate-50/50 transition cursor-pointer"
                                        onclick="window.location='{{ route('tenant.attendance.show', [$tenant->slug, $session]) }}'">
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <p class="font-medium text-slate-900">{{ $session->started_at->format('d M Y') }}</p>
                                            <p class="text-xs text-slate-400">
                                                {{ $session->started_at->format('H:i') }} &middot; {{ ucfirst($session->session_type) }}
                                                @if($session->week_number) &middot; W{{ $session->week_number }} @endif
                                            </p>
                                        </td>
                                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ $session->section->name }}</td>
                                        <td class="px-3 py-3 text-center font-medium text-emerald-600">{{ $session->present_count }}</td>
                                        <td class="px-3 py-3 text-center font-medium text-amber-600">{{ $session->late_count }}</td>
                                        <td class="px-3 py-3 text-center font-medium text-red-600">{{ $session->absent_count }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2 min-w-[90px]">
                                                <div class="h-1.5 flex-1 bg-slate-100 rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $sessionRate ?? 0 }}%"></div>
                                                </div>
                                                <span class="text-xs font-medium text-slate-600 w-9 text-right">{{ $sessionRate === null ? '—' : $sessionRate . '%' }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right" onclick="event.stopPropagation()">
                                            @if($session->isLocked())
                                                <span class="inline-flex p-1.5 text-slate-300" title="{{ $session->lockMessage() }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                </span>
                                            @else
                                            <form method="POST" action="{{ route('tenant.attendance.destroy', [$tenant->slug, $session]) }}" onsubmit="return confirm('Delete this session and all its records? This cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-1.5 rounded-lg text-slate-300 hover:text-red-600 hover:bg-red-50 transition" title="Delete session">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                {{-- Start New Session --}}
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="font-semibold text-slate-900">Start New Session</h3>
                    </div>
                    <div class="p-6">
                        @if($course->status === 'archived')
                            <p class="text-sm text-slate-400 text-center py-2">This course is archived, so no new sessions can be started.</p>
                        @elseif($activeSections->isEmpty())
                            <p class="text-sm text-slate-400 text-center py-2">No active sections in an open semester.</p>
                        @else
                            <form method="POST" action="{{ route('tenant.attendance.start', $tenant->slug) }}" class="space-y-4">
                                @csrf
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1.5">Section</label>
                                    <select name="section_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                        @foreach($activeSections as $section)
                                            <option value="{{ $section->id }}">{{ $section->name }}@if($section->term()) — {{ $section->term()->name }}@endif</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 mb-1.5">Session Type</label>
                                        <select name="session_type" required class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                            <option value="lecture">Lecture</option>
                                            <option value="tutorial">Tutorial</option>
                                            <option value="lab">Lab</option>
                                            <option value="extra">Extra Class</option>
                                            <option value="replacement">Replacement</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 mb-1.5">Week #</label>
                                        <input type="number" name="week_number" min="1" max="52" placeholder="Optional" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
                                    </div>
                                </div>
                                <button type="submit" class="w-full px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm transition flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                    Start Session
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Reports --}}
                @if($sections->isNotEmpty())
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100">
                            <h3 class="font-semibold text-slate-900">Attendance Reports</h3>
                            <p class="text-xs text-slate-500 mt-0.5">All sessions &amp; students per section</p>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach($sections as $section)
                                <div class="flex items-center justify-between gap-3 px-6 py-3">
                                    <p class="text-sm font-medium text-slate-900 truncate">{{ $section->name }}</p>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <a href="{{ route('tenant.attendance.report', [$tenant->slug, $course, 'section_id' => $section->id]) }}"
                                           class="px-2.5 py-1 text-xs font-medium text-slate-600 border border-slate-200 hover:bg-slate-50 rounded-lg transition">View</a>
                                        <a href="{{ route('tenant.attendance.report.excel', [$tenant->slug, $course, 'section_id' => $section->id]) }}"
                                           class="px-2.5 py-1 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition">Excel</a>
                                        <a href="{{ route('tenant.attendance.report.pdf', [$tenant->slug, $course, 'section_id' => $section->id]) }}"
                                           class="px-2.5 py-1 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition">PDF</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-tenant-layout>
