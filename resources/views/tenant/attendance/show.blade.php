<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.attendance.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Attendance Report</h2>
                    <p class="text-sm text-slate-500">
                        {{ $session->section->course->code }} — {{ $session->section->name }}
                        &middot; {{ ucfirst($session->session_type) }}
                        &middot; {{ $session->started_at->format('d M Y, H:i') }}
                    </p>
                </div>
            </div>
            @if($session->status !== 'active')
                <form method="POST" action="{{ route('tenant.attendance.destroy', [app('current_tenant')->slug, $session]) }}" onsubmit="return confirm('Delete this session and all its records? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete Session
                    </button>
                </form>
            @endif
        </div>
    </x-slot>

    @php
        $present = $session->records->where('status', 'present')->count();
        $late = $session->records->where('status', 'late')->count();
        $absent = $session->records->where('status', 'absent')->count();
        $excused = $session->records->where('status', 'excused')->count();
        $total = $session->records->count();
    @endphp

    <div class="space-y-6">
        {{-- Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-2xl font-bold text-slate-900">{{ $total }}</p>
                <p class="text-xs text-slate-500">Total</p>
            </div>
            <div class="bg-white rounded-2xl border border-emerald-200 p-5 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ $present }}</p>
                <p class="text-xs text-slate-500">Present</p>
            </div>
            <div class="bg-white rounded-2xl border border-amber-200 p-5 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ $late }}</p>
                <p class="text-xs text-slate-500">Late</p>
            </div>
            <div class="bg-white rounded-2xl border border-red-200 p-5 text-center">
                <p class="text-2xl font-bold text-red-600">{{ $absent }}</p>
                <p class="text-xs text-slate-500">Absent</p>
            </div>
            <div class="bg-white rounded-2xl border border-blue-200 p-5 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ $excused }}</p>
                <p class="text-xs text-slate-500">Excused</p>
            </div>
        </div>

        {{-- Records Table --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Student Records</h3>
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span>Duration: {{ $session->started_at->shortAbsoluteDiffForHumans($session->ended_at ?? now()) }}</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="text-left px-6 py-3 font-medium text-slate-500">#</th>
                            <th class="text-left px-6 py-3 font-medium text-slate-500">Student</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Status</th>
                            <th class="text-center px-6 py-3 font-medium text-slate-500">Method</th>
                            <th class="text-right px-6 py-3 font-medium text-slate-500">Time</th>
                            <th class="text-right px-6 py-3 font-medium text-slate-500">Override</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($session->records->sortBy('user.name') as $i => $record)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-6 py-3 text-slate-400">{{ $i + 1 }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                                            {{ $record->status === 'present' ? 'bg-emerald-100 text-emerald-700' :
                                               ($record->status === 'late' ? 'bg-amber-100 text-amber-700' :
                                               ($record->status === 'excused' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700')) }}">
                                            {{ strtoupper(substr($record->user->name, 0, 1)) }}
                                        </div>
                                        <span class="font-medium text-slate-900">{{ $record->user->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $record->status === 'present' ? 'bg-emerald-100 text-emerald-700' :
                                           ($record->status === 'late' ? 'bg-amber-100 text-amber-700' :
                                           ($record->status === 'excused' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700')) }}">
                                        {{ ucfirst($record->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-center text-xs text-slate-400">{{ ucfirst($record->method) }}</td>
                                <td class="px-6 py-3 text-right text-xs text-slate-400">{{ $record->checked_in_at?->format('H:i:s') ?? '--' }}</td>
                                <td class="px-6 py-3 text-right">
                                    <form method="POST" action="{{ route('tenant.attendance.override', [app('current_tenant')->slug, $session, $record]) }}" class="inline-flex items-center gap-1" x-data="{ open: false }">
                                        @csrf @method('PUT')
                                        <button type="button" @click="open = !open" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Edit</button>
                                        <div x-show="open" x-cloak class="flex items-center gap-1 ml-2">
                                            <select name="status" class="text-xs px-2 py-1 rounded border border-slate-300 focus:ring-1 focus:ring-indigo-500">
                                                <option value="present" {{ $record->status === 'present' ? 'selected' : '' }}>Present</option>
                                                <option value="late" {{ $record->status === 'late' ? 'selected' : '' }}>Late</option>
                                                <option value="absent" {{ $record->status === 'absent' ? 'selected' : '' }}>Absent</option>
                                                <option value="excused" {{ $record->status === 'excused' ? 'selected' : '' }}>Excused</option>
                                            </select>
                                            <button type="submit" class="px-2 py-1 bg-indigo-600 text-white text-xs rounded">Save</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-tenant-layout>
