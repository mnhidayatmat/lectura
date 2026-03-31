<x-tenant-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-slate-900">My Attendance</h2>
        <p class="mt-0.5 text-sm text-slate-500">Track your attendance across all courses</p>
    </x-slot>

    <div class="space-y-4">
        @forelse($courses as $item)
            @php
                $s = $item->summary;
                $rate = $s['attendance_rate'];
                $rateColor = $rate >= 80 ? 'emerald' : ($rate >= 60 ? 'amber' : 'red');
            @endphp

            <a href="{{ route('tenant.my-attendance.course', [$tenant->slug, $item->course]) }}"
               class="block bg-white rounded-2xl border border-slate-200 hover:border-indigo-200 hover:shadow-sm transition-all overflow-hidden">
                <div class="p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-slate-900">{{ $item->course->code }}</h3>
                                @if($s['warning_level'])
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold
                                        {{ $s['warning_level'] >= 3 ? 'bg-red-100 text-red-700' : ($s['warning_level'] >= 2 ? 'bg-amber-100 text-amber-700' : 'bg-yellow-100 text-yellow-700') }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                        Warning {{ $s['warning_level'] }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-slate-500 mt-0.5 truncate">{{ $item->course->title }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ $item->course->lecturer?->name ?? 'TBA' }} &middot; {{ $item->sections->pluck('name')->implode(', ') }}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-2xl font-bold text-{{ $rateColor }}-600">{{ $rate }}%</p>
                            <p class="text-[10px] text-slate-400 uppercase tracking-wider">Attendance</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-4 gap-2 text-center">
                        <div class="py-2 rounded-xl bg-emerald-50">
                            <p class="text-lg font-bold text-emerald-600">{{ $s['present'] }}</p>
                            <p class="text-[10px] text-emerald-600/70 font-medium">Present</p>
                        </div>
                        <div class="py-2 rounded-xl bg-amber-50">
                            <p class="text-lg font-bold text-amber-600">{{ $s['late'] }}</p>
                            <p class="text-[10px] text-amber-600/70 font-medium">Late</p>
                        </div>
                        <div class="py-2 rounded-xl bg-red-50">
                            <p class="text-lg font-bold text-red-600">{{ $s['absent'] }}</p>
                            <p class="text-[10px] text-red-600/70 font-medium">Absent</p>
                        </div>
                        <div class="py-2 rounded-xl bg-blue-50">
                            <p class="text-lg font-bold text-blue-600">{{ $s['excused'] }}</p>
                            <p class="text-[10px] text-blue-600/70 font-medium">Excused</p>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-{{ $rateColor }}-500 transition-all" style="width: {{ $rate }}%"></div>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">{{ $s['total_sessions'] }} {{ Str::plural('session', $s['total_sessions']) }} recorded</p>
                    </div>
                </div>
            </a>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center">
                <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <p class="text-sm text-slate-500">No courses enrolled yet.</p>
                <p class="text-xs text-slate-400 mt-1">Enroll in a course to see your attendance.</p>
            </div>
        @endforelse
    </div>
</x-tenant-layout>
