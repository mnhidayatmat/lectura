<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.attendance.index', app('current_tenant')->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
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

    <div class="grid lg:grid-cols-2 gap-6" x-data="qrAttendance()" x-init="start()">

        {{-- QR Code Display --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-8 flex flex-col items-center">
            <div class="mb-4 flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                <span class="text-sm font-medium text-slate-700">QR Code — Scan to Check In</span>
            </div>

            <div class="w-72 h-72 bg-white rounded-2xl border-2 border-slate-100 p-4 flex items-center justify-center">
                <div id="qr-code" class="w-full h-full flex items-center justify-center">
                    <div class="text-center">
                        <div class="animate-spin w-8 h-8 border-2 border-indigo-600 border-t-transparent rounded-full mx-auto mb-2"></div>
                        <p class="text-xs text-slate-400">Generating QR...</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <span class="text-xs text-slate-400">Next refresh in</span>
                <span class="text-sm font-bold text-indigo-600 tabular-nums" x-text="countdown + 's'"></span>
                <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-indigo-500 rounded-full transition-all duration-1000" :style="'width: ' + (countdown / {{ $session->qr_rotation_seconds }} * 100) + '%'"></div>
                </div>
            </div>

            <p class="mt-3 text-xs text-slate-400">Rotates every {{ $session->qr_rotation_seconds }} seconds</p>
        </div>

        {{-- Live Stats & Check-ins --}}
        <div class="space-y-4">
            {{-- Counter --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
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

            {{-- Real-time Check-in List --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-900 text-sm">Check-ins</h3>
                    <span class="text-[10px] text-slate-400" x-text="'Updates every 5s'"></span>
                </div>
                <div class="max-h-[400px] overflow-y-auto divide-y divide-slate-50">
                    {{-- New check-in flash --}}
                    <template x-if="latestName">
                        <div class="px-5 py-3 bg-emerald-50 border-b border-emerald-100 flex items-center gap-3 animate-pulse">
                            <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-emerald-800" x-text="latestName + ' just checked in!'"></p>
                            </div>
                        </div>
                    </template>

                    {{-- Dynamic list --}}
                    <template x-for="(r, i) in records" :key="r.id">
                        <div class="px-5 py-3 flex items-center justify-between" :class="i === 0 && justUpdated ? 'bg-emerald-50/50' : ''">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                    :class="r.status === 'late' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'">
                                    <span x-text="r.name.charAt(0).toUpperCase()"></span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900" x-text="r.name"></p>
                                    <p class="text-xs text-slate-400" x-text="r.time"></p>
                                </div>
                            </div>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                :class="r.status === 'late' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'"
                                x-text="r.status.charAt(0).toUpperCase() + r.status.slice(1)">
                            </span>
                        </div>
                    </template>

                    {{-- Empty state --}}
                    <div x-show="!records.length" class="px-5 py-10 text-center">
                        <div class="w-12 h-12 bg-slate-50 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        </div>
                        <p class="text-sm text-slate-500">Waiting for students to scan...</p>
                        <p class="text-xs text-slate-400 mt-1">Names will appear here in real-time</p>
                    </div>
                </div>
            </div>

            {{-- Session Info --}}
            <div class="bg-slate-50 rounded-xl p-4 text-xs text-slate-500 space-y-1">
                <p>Started: {{ $session->started_at->format('H:i:s') }} ({{ $session->started_at->diffForHumans() }})</p>
                <p>Late after: {{ $session->late_threshold_minutes }} minutes</p>
                <p>Mode: {{ ucfirst($session->qr_mode) }} ({{ $session->qr_rotation_seconds }}s rotation)</p>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function qrAttendance() {
            const TOKEN_URL = '{{ route('tenant.attendance.token', [app('current_tenant')->slug, $session]) }}';
            const QR_INTERVAL = {{ $session->qr_rotation_seconds }} * 1000;
            const POLL_INTERVAL = 5000; // Poll check-ins every 5 seconds

            return {
                countdown: {{ $session->qr_rotation_seconds }},
                stats: { checkedIn: {{ $checkedIn }}, total: {{ $totalStudents }} },
                records: [],
                latestName: null,
                justUpdated: false,
                prevCount: {{ $checkedIn }},

                async start() {
                    await this.refreshQR();
                    await this.pollRecords();

                    // QR refresh on its own interval
                    setInterval(() => this.refreshQR(), QR_INTERVAL);

                    // Check-in list polls every 5 seconds
                    setInterval(() => this.pollRecords(), POLL_INTERVAL);

                    // Countdown timer
                    setInterval(() => {
                        this.countdown = Math.max(0, this.countdown - 1);
                    }, 1000);
                },

                async refreshQR() {
                    try {
                        const res = await fetch(TOKEN_URL, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const data = await res.json();

                        // Update QR code
                        const el = document.getElementById('qr-code');
                        el.innerHTML = '';
                        const canvas = document.createElement('canvas');
                        await QRCode.toCanvas(canvas, data.payload, {
                            width: 240, margin: 2,
                            color: { dark: '#0F172A', light: '#FFFFFF' }
                        });
                        el.appendChild(canvas);

                        this.countdown = data.rotation_seconds;

                        // Also update records from QR response
                        this.updateRecords(data);
                    } catch (e) {
                        console.error('QR refresh failed:', e);
                    }
                },

                async pollRecords() {
                    try {
                        const res = await fetch(TOKEN_URL, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const data = await res.json();
                        this.updateRecords(data);
                    } catch (e) {
                        // Silent fail for polling
                    }
                },

                updateRecords(data) {
                    const newCount = data.checked_in;

                    // Flash new student name if count increased
                    if (newCount > this.prevCount && data.records && data.records.length > 0) {
                        const newest = data.records[0]; // Already sorted desc by time
                        this.latestName = newest.name;
                        this.justUpdated = true;

                        // Clear flash after 3 seconds
                        setTimeout(() => {
                            this.latestName = null;
                            this.justUpdated = false;
                        }, 3000);
                    }

                    this.prevCount = newCount;
                    this.stats.checkedIn = newCount;
                    this.stats.total = data.total;

                    if (data.records) {
                        this.records = data.records;
                    }
                },
            };
        }
    </script>
    @endpush
</x-tenant-layout>
