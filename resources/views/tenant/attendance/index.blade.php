<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Attendance</h2>
                <p class="mt-1 text-sm text-slate-500">Manage QR attendance sessions</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-8">
        {{-- Start New Session --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Start New Session</h3>
            </div>
            <div class="p-6">
                @if($sections->isEmpty())
                    <p class="text-sm text-slate-400 text-center py-4">No sections available. Create a course with sections first.</p>
                @else
                    <form method="POST" action="{{ route('tenant.attendance.start', app('current_tenant')->slug) }}" class="grid sm:grid-cols-4 gap-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1.5">Section</label>
                            <select name="section_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach($sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->course->code }} — {{ $section->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1.5">Session Type</label>
                            <select name="session_type" required class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="lecture">Lecture</option>
                                <option value="tutorial">Tutorial</option>
                                <option value="lab">Lab</option>
                                <option value="extra">Extra Class</option>
                                <option value="replacement">Replacement</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1.5">Week #</label>
                            <input type="number" name="week_number" min="1" max="52" placeholder="Optional" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm transition flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                Start Session
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        {{-- Active Sessions --}}
        @if($activeSessions->isNotEmpty())
            <div class="bg-white rounded-2xl border-2 border-emerald-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-emerald-100 bg-emerald-50/50 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    <h3 class="font-semibold text-emerald-900">Active Sessions ({{ $activeSessions->count() }})</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($activeSessions as $session)
                        <a href="{{ route('tenant.attendance.qr', [app('current_tenant')->slug, $session]) }}" class="flex items-center justify-between px-6 py-4 hover:bg-emerald-50/30 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $session->section->course->code }} — {{ $session->section->name }}</p>
                                    <p class="text-xs text-slate-500">{{ ucfirst($session->session_type) }} {{ $session->week_number ? '• Week ' . $session->week_number : '' }} • Started {{ $session->started_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <p class="text-lg font-bold text-emerald-600">{{ $session->checkedInCount() }}</p>
                                    <p class="text-xs text-slate-400">checked in</p>
                                </div>
                                <span class="px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg">Open QR</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Past Sessions --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">Session History</h3>
            </div>
            @if($pastSessions->isEmpty() && $activeSessions->isEmpty())
                <div class="p-10 text-center">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                        <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </div>
                    <p class="text-sm text-slate-500">No attendance sessions yet.</p>
                    <p class="text-xs text-slate-400 mt-1">Start a session above to take attendance.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="text-left px-6 py-3 font-medium text-slate-500">Course / Section</th>
                                <th class="text-left px-6 py-3 font-medium text-slate-500">Type</th>
                                <th class="text-center px-6 py-3 font-medium text-slate-500">Present</th>
                                <th class="text-center px-6 py-3 font-medium text-slate-500">Late</th>
                                <th class="text-center px-6 py-3 font-medium text-slate-500">Absent</th>
                                <th class="text-right px-6 py-3 font-medium text-slate-500">Date</th>
                                <th class="text-right px-6 py-3 font-medium text-slate-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($pastSessions as $session)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-6 py-3 cursor-pointer" onclick="window.location='{{ route('tenant.attendance.show', [app('current_tenant')->slug, $session]) }}'">
                                        <p class="font-medium text-slate-900">{{ $session->section->course->code }} — {{ $session->section->name }}</p>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">{{ ucfirst($session->session_type) }}</span>
                                        @if($session->week_number)<span class="text-xs text-slate-400 ml-1">W{{ $session->week_number }}</span>@endif
                                    </td>
                                    <td class="px-6 py-3 text-center font-medium text-emerald-600">{{ $session->records->where('status', 'present')->count() }}</td>
                                    <td class="px-6 py-3 text-center font-medium text-amber-600">{{ $session->records->where('status', 'late')->count() }}</td>
                                    <td class="px-6 py-3 text-center font-medium text-red-600">{{ $session->records->where('status', 'absent')->count() }}</td>
                                    <td class="px-6 py-3 text-right text-slate-400 text-xs">{{ $session->started_at->format('d M Y, H:i') }}</td>
                                    <td class="px-6 py-3 text-right">
                                        <form method="POST" action="{{ route('tenant.attendance.destroy', [app('current_tenant')->slug, $session]) }}" onsubmit="return confirm('Delete this session and all its records? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg text-slate-300 hover:text-red-600 hover:bg-red-50 transition" title="Delete session">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-tenant-layout>
