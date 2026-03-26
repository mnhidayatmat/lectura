<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.attendance.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-bold text-slate-900">{{ $session->section->course->code }}</h2>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                            Live
                        </span>
                    </div>
                    <p class="text-sm text-slate-500">{{ $session->section->name }} &middot; {{ ucfirst($session->session_type) }} {{ $session->week_number ? '• Week ' . $session->week_number : '' }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('tenant.attendance.end', [app('current_tenant')->slug, $session]) }}">
                @csrf
                <button type="submit" onclick="return confirm('End this attendance session?')" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
                    End Session
                </button>
            </form>
        </div>
    </x-slot>

    <div class="grid lg:grid-cols-2 gap-8" x-data="qrAttendance({{ $session->id }}, '{{ route('tenant.attendance.token', [app('current_tenant')->slug, $session]) }}')" x-init="startRefresh()">

        {{-- QR Code Display --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-8 flex flex-col items-center">
            <div class="mb-4 flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                <span class="text-sm font-medium text-slate-700">QR Code — Scan to Check In</span>
            </div>

            {{-- QR Container --}}
            <div class="w-72 h-72 bg-white rounded-2xl border-2 border-slate-100 p-4 flex items-center justify-center relative">
                <div id="qr-code" class="w-full h-full flex items-center justify-center">
                    <div class="text-center">
                        <div class="animate-spin w-8 h-8 border-2 border-indigo-600 border-t-transparent rounded-full mx-auto mb-2"></div>
                        <p class="text-xs text-slate-400">Generating QR...</p>
                    </div>
                </div>
            </div>

            {{-- Timer --}}
            <div class="mt-4 flex items-center gap-3">
                <span class="text-xs text-slate-400">Next refresh in</span>
                <span class="text-sm font-bold text-indigo-600 tabular-nums" x-text="countdown + 's'"></span>
                <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-indigo-500 rounded-full transition-all duration-1000" :style="'width: ' + (countdown / {{ $session->qr_rotation_seconds }} * 100) + '%'"></div>
                </div>
            </div>

            <p class="mt-3 text-xs text-slate-400">Rotates every {{ $session->qr_rotation_seconds }} seconds</p>
        </div>

        {{-- Live Stats --}}
        <div class="space-y-6">
            {{-- Counter --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="text-3xl font-bold text-emerald-600" x-text="stats.checkedIn">{{ $checkedIn }}</p>
                        <p class="text-xs text-slate-500 mt-1">Checked In</p>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-slate-300">/</p>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-slate-700" x-text="stats.total">{{ $totalStudents }}</p>
                        <p class="text-xs text-slate-500 mt-1">Total Students</p>
                    </div>
                </div>
                <div class="mt-4 w-full bg-slate-100 rounded-full h-3">
                    <div class="bg-emerald-500 h-3 rounded-full transition-all duration-500" :style="'width: ' + (stats.total > 0 ? (stats.checkedIn / stats.total * 100) : 0) + '%'"></div>
                </div>
            </div>

            {{-- Recent Check-ins --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Check-ins</h3>
                </div>
                <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                    @forelse($session->records->whereIn('status', ['present', 'late'])->sortByDesc('checked_in_at') as $record)
                        <div class="px-6 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full {{ $record->status === 'late' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }} flex items-center justify-center text-xs font-bold">
                                    {{ strtoupper(substr($record->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ $record->user->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $record->checked_in_at?->format('H:i:s') }}</p>
                                </div>
                            </div>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $record->status === 'late' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ ucfirst($record->status) }}
                            </span>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-sm text-slate-400">
                            Waiting for students to scan...
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Session Info --}}
            <div class="bg-slate-50 rounded-xl p-4 text-xs text-slate-500 space-y-1">
                <p>Started: {{ $session->started_at->format('H:i:s') }}</p>
                <p>Late after: {{ $session->late_threshold_minutes }} minutes</p>
                <p>Mode: {{ ucfirst($session->qr_mode) }} ({{ $session->qr_rotation_seconds }}s)</p>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js"></script>
    <script>
        function qrAttendance(sessionId, tokenUrl) {
            return {
                countdown: 30,
                stats: { checkedIn: {{ $checkedIn }}, total: {{ $totalStudents }} },
                interval: null,
                timerInterval: null,

                async startRefresh() {
                    await this.refresh();
                    this.interval = setInterval(() => this.refresh(), {{ $session->qr_rotation_seconds }} * 1000);
                    this.timerInterval = setInterval(() => {
                        this.countdown = Math.max(0, this.countdown - 1);
                    }, 1000);
                },

                async refresh() {
                    try {
                        const res = await fetch(tokenUrl, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const data = await res.json();

                        // Update QR
                        const el = document.getElementById('qr-code');
                        el.innerHTML = '';
                        const canvas = document.createElement('canvas');
                        await QRCode.toCanvas(canvas, data.payload, {
                            width: 240,
                            margin: 2,
                            color: { dark: '#0F172A', light: '#FFFFFF' }
                        });
                        el.appendChild(canvas);

                        // Update stats
                        this.stats.checkedIn = data.checked_in;
                        this.stats.total = data.total;
                        this.countdown = data.rotation_seconds;
                    } catch (e) {
                        console.error('QR refresh failed:', e);
                    }
                }
            }
        }
    </script>
    @endpush
</x-tenant-layout>
