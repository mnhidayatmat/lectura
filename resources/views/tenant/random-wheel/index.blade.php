<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Random Present Student Wheel</h2>
                <p class="mt-1 text-sm text-slate-500">Randomly select a present student for in-class participation</p>
            </div>
            <button onclick="document.getElementById('wheel-container')?.requestFullscreen?.()" class="hidden sm:inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                Fullscreen
            </button>
        </div>
    </x-slot>

    <div id="wheel-container" class="fullscreen:bg-slate-950 fullscreen:p-8" x-data="randomWheel()" x-init="init()">
        {{-- Controls --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-6">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                {{-- Course --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5">Course</label>
                    <select x-model="courseId" @change="onCourseChange()" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select course...</option>
                        @foreach($courses as $c)
                            <option value="{{ $c->id }}" data-sections='@json($c->sections->map(fn($s) => ["id" => $s->id, "name" => $s->name]))'>{{ $c->code }} — {{ $c->title }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Section --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5">Section</label>
                    <select x-model="sectionId" @change="onSectionChange()" :disabled="!courseId" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">
                        <option value="">Select section...</option>
                        <template x-for="s in sections" :key="s.id">
                            <option :value="s.id" x-text="s.name"></option>
                        </template>
                    </select>
                </div>

                {{-- Session --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5">Attendance Session</label>
                    <select x-model="sessionId" :disabled="!sectionId || loadingSessions" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">
                        <option value="">Select session...</option>
                        <template x-for="s in attendanceSessions" :key="s.id">
                            <option :value="s.id" x-text="s.label"></option>
                        </template>
                    </select>
                </div>

                {{-- Load Button --}}
                <div>
                    <button @click="loadStudents()" :disabled="!sessionId || loadingStudents"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg x-show="!loadingStudents" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <svg x-show="loadingStudents" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Load Present Students
                    </button>
                </div>
            </div>

            {{-- Options Row --}}
            <div class="flex items-center gap-6 mt-4 pt-4 border-t border-slate-100">
                <label class="inline-flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                    <input type="checkbox" x-model="autoRemove" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Auto-remove selected student
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                    <input type="checkbox" x-model="includeLate" @change="if(sessionId) loadStudents()">
                    Include late students
                </label>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="grid lg:grid-cols-5 gap-6">
            {{-- Wheel (3 cols) --}}
            <div class="lg:col-span-3">
                <div class="bg-white rounded-2xl border border-slate-200 p-6 relative overflow-hidden">
                    {{-- Session Info Badge --}}
                    <template x-if="sessionInfo">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold" :class="sessionInfo.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                                    <span x-show="sessionInfo.is_active" class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                                    <span x-text="sessionInfo.is_active ? 'Live Session' : 'Ended Session'"></span>
                                </span>
                                <span class="text-xs text-slate-400" x-text="sessionInfo.section + ' — W' + (sessionInfo.week ?? '?') + ' ' + sessionInfo.type"></span>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs font-semibold">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span x-text="wheelStudents.length"></span> on wheel
                            </span>
                        </div>
                    </template>

                    {{-- Wheel Canvas --}}
                    <div class="relative flex items-center justify-center" style="min-height: 380px;">
                        {{-- Empty States --}}
                        <div x-show="!allStudents.length && !loadingStudents" class="text-center py-12">
                            <div class="w-20 h-20 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </div>
                            <p class="text-sm font-medium text-slate-700 mb-1">No students loaded</p>
                            <p class="text-xs text-slate-400 max-w-xs mx-auto">Select a course, section, and attendance session, then click "Load Present Students".</p>
                        </div>

                        <div x-show="allStudents.length && !wheelStudents.length" x-cloak class="text-center py-12">
                            <div class="w-20 h-20 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <p class="text-sm font-medium text-slate-700 mb-1">All students selected!</p>
                            <p class="text-xs text-slate-400 mb-4">Every present student has been picked. Reset to start again.</p>
                            <button @click="resetWheel()" class="px-4 py-2 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition">Reset Wheel</button>
                        </div>

                        {{-- The Wheel --}}
                        <div x-show="wheelStudents.length" x-cloak class="relative">
                            {{-- Pointer --}}
                            <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1 z-10">
                                <div class="w-0 h-0 border-l-[12px] border-r-[12px] border-t-[20px] border-l-transparent border-r-transparent border-t-red-500 drop-shadow-md"></div>
                            </div>
                            <canvas x-ref="wheelCanvas" width="380" height="380" class="max-w-full h-auto"></canvas>
                        </div>
                    </div>

                    {{-- Spin Button --}}
                    <div x-show="wheelStudents.length" x-cloak class="mt-6 flex items-center justify-center gap-3">
                        <button @click="spin()" :disabled="spinning"
                            class="inline-flex items-center gap-2 px-8 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white text-sm font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg x-show="!spinning" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <svg x-show="spinning" x-cloak class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="spinning ? 'Spinning...' : 'Spin the Wheel'"></span>
                        </button>
                        <button @click="resetWheel()" class="px-4 py-3 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition">
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            {{-- Right Panel (2 cols) --}}
            <div class="lg:col-span-2 space-y-4">
                {{-- Winner Banner --}}
                <div x-show="winner" x-cloak x-transition class="bg-gradient-to-br from-indigo-600 to-purple-600 rounded-2xl p-6 text-center text-white relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10">
                        <div class="absolute top-2 left-4 text-6xl">&#9733;</div>
                        <div class="absolute bottom-2 right-4 text-4xl">&#9733;</div>
                        <div class="absolute top-8 right-12 text-3xl">&#9733;</div>
                    </div>
                    <p class="text-xs font-medium text-indigo-200 uppercase tracking-wider mb-2">Selected Student</p>
                    <p class="text-2xl font-bold" x-text="winner?.name"></p>
                    <div class="flex items-center justify-center gap-2 mt-4" x-show="!autoRemove">
                        <button @click="removeWinner()" class="px-3 py-1.5 text-xs font-medium bg-white/20 hover:bg-white/30 rounded-lg transition">Remove from Wheel</button>
                        <button @click="winner = null" class="px-3 py-1.5 text-xs font-medium bg-white/10 hover:bg-white/20 rounded-lg transition">Keep</button>
                    </div>
                </div>

                {{-- Present Students List --}}
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">Present Students</h3>
                        <span class="text-xs text-slate-400" x-text="wheelStudents.length + ' remaining'"></span>
                    </div>
                    <div class="max-h-52 overflow-y-auto divide-y divide-slate-50">
                        <template x-for="(student, i) in wheelStudents" :key="student.id">
                            <div class="px-4 py-2.5 flex items-center gap-3">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0" :style="'background-color:' + getColor(i)">
                                    <span x-text="student.name.charAt(0)"></span>
                                </div>
                                <span class="text-sm text-slate-700 truncate" x-text="student.name"></span>
                                <span x-show="student.status === 'late'" class="text-[9px] bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded font-medium ml-auto">Late</span>
                            </div>
                        </template>
                        <div x-show="!wheelStudents.length" class="px-4 py-6 text-center text-xs text-slate-400">No students on wheel</div>
                    </div>
                </div>

                {{-- Spin History --}}
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">Spin History</h3>
                        <button @click="clearHistory()" x-show="history.length" class="text-[10px] font-medium text-red-500 hover:text-red-700 transition">Clear</button>
                    </div>
                    <div class="max-h-52 overflow-y-auto divide-y divide-slate-50">
                        <template x-for="(h, i) in history.slice().reverse()" :key="i">
                            <div class="px-4 py-2.5 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-bold" x-text="history.length - i"></span>
                                    <span class="text-sm font-medium text-slate-700" x-text="h.name"></span>
                                </div>
                                <span class="text-[10px] text-slate-400" x-text="h.time"></span>
                            </div>
                        </template>
                        <div x-show="!history.length" class="px-4 py-6 text-center text-xs text-slate-400">No spins yet</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function randomWheel() {
        const COLORS = ['#6366f1','#8b5cf6','#a855f7','#ec4899','#f43f5e','#ef4444','#f97316','#f59e0b','#eab308','#84cc16','#22c55e','#14b8a6','#06b6d4','#3b82f6','#6366f1','#8b5cf6'];
        const SLUG = '{{ $tenant->slug }}';
        const CSRF = '{{ csrf_token() }}';
        const LATEST_DEFAULTS = @json($latestDefaults);

        return {
            courseId: '',
            sectionId: '',
            sessionId: '',
            sections: [],
            attendanceSessions: [],
            allStudents: [],
            wheelStudents: [],
            winner: null,
            spinning: false,
            autoRemove: true,
            includeLate: false,
            history: [],
            sessionInfo: null,
            loadingSessions: false,
            loadingStudents: false,
            currentAngle: 0,

            async init() {
                if (LATEST_DEFAULTS) {
                    // Auto-fill course
                    this.courseId = LATEST_DEFAULTS.courseId;
                    const opt = this.$el.querySelector(`select option[value="${this.courseId}"]`);
                    try {
                        this.sections = JSON.parse(opt?.dataset?.sections || '[]').map(s => ({...s, id: String(s.id)}));
                    } catch(e) {
                        this.sections = [];
                    }

                    // Auto-fill section and load sessions
                    this.sectionId = LATEST_DEFAULTS.sectionId;
                    this.loadingSessions = true;
                    try {
                        const res = await fetch(`/${SLUG}/random-wheel/sessions?section_id=${this.sectionId}`);
                        if (res.ok) {
                            const data = await res.json();
                            this.attendanceSessions = data.map(s => ({...s, id: String(s.id)}));
                        }
                    } catch(e) {
                        console.error(e);
                    } finally {
                        this.loadingSessions = false;
                    }

                    // Auto-fill session and load students
                    this.sessionId = LATEST_DEFAULTS.sessionId;
                    await this.loadStudents();
                }
            },

            getColor(i) {
                return COLORS[i % COLORS.length];
            },

            onCourseChange() {
                this.sectionId = '';
                this.sessionId = '';
                this.attendanceSessions = [];
                this.allStudents = [];
                this.wheelStudents = [];
                this.winner = null;
                this.sessionInfo = null;

                if (!this.courseId) { this.sections = []; return; }
                const opt = this.$el.querySelector(`select option[value="${this.courseId}"]`);
                try {
                    this.sections = JSON.parse(opt?.dataset?.sections || '[]').map(s => ({...s, id: String(s.id)}));
                } catch(e) {
                    this.sections = [];
                }
            },

            async onSectionChange() {
                this.sessionId = '';
                this.allStudents = [];
                this.wheelStudents = [];
                this.winner = null;
                this.sessionInfo = null;
                if (!this.sectionId) { this.attendanceSessions = []; return; }

                this.loadingSessions = true;
                try {
                    const res = await fetch(`/${SLUG}/random-wheel/sessions?section_id=${this.sectionId}`);
                    if (!res.ok) throw new Error('Failed to load sessions');
                    const data = await res.json();
                    // Ensure IDs are strings for select binding
                    this.attendanceSessions = data.map(s => ({...s, id: String(s.id)}));
                    // Auto-select active session
                    const live = this.attendanceSessions.find(s => s.is_active);
                    if (live) this.sessionId = String(live.id);
                } catch(e) {
                    console.error(e);
                    this.attendanceSessions = [];
                } finally {
                    this.loadingSessions = false;
                }
            },

            async loadStudents() {
                if (!this.sessionId) return;
                this.loadingStudents = true;
                this.winner = null;
                try {
                    const res = await fetch(`/${SLUG}/random-wheel/present-students?session_id=${this.sessionId}&include_late=${this.includeLate ? '1' : '0'}`);
                    if (!res.ok) throw new Error('Failed to load students');
                    const data = await res.json();
                    this.allStudents = data.students || [];
                    this.wheelStudents = [...this.allStudents];
                    this.sessionInfo = data.session;
                    this.history = [];
                    this.currentAngle = 0;
                    this.saveState();
                    this.$nextTick(() => this.drawWheel());
                } catch(e) {
                    console.error(e);
                    this.allStudents = [];
                    this.wheelStudents = [];
                } finally {
                    this.loadingStudents = false;
                }
            },

            drawWheel() {
                const canvas = this.$refs.wheelCanvas;
                if (!canvas || !this.wheelStudents.length) return;
                const ctx = canvas.getContext('2d');
                const cx = canvas.width / 2, cy = canvas.height / 2, r = cx - 10;
                const n = this.wheelStudents.length;
                const arc = (2 * Math.PI) / n;

                ctx.clearRect(0, 0, canvas.width, canvas.height);

                // Draw segments
                for (let i = 0; i < n; i++) {
                    const angle = this.currentAngle + i * arc;
                    ctx.beginPath();
                    ctx.moveTo(cx, cy);
                    ctx.arc(cx, cy, r, angle, angle + arc);
                    ctx.closePath();
                    ctx.fillStyle = COLORS[i % COLORS.length];
                    ctx.fill();
                    ctx.strokeStyle = 'rgba(255,255,255,0.3)';
                    ctx.lineWidth = 2;
                    ctx.stroke();

                    // Label
                    ctx.save();
                    ctx.translate(cx, cy);
                    ctx.rotate(angle + arc / 2);
                    ctx.fillStyle = '#fff';
                    ctx.font = `bold ${n > 20 ? 9 : n > 12 ? 11 : 13}px system-ui, sans-serif`;
                    ctx.textAlign = 'right';
                    ctx.textBaseline = 'middle';
                    const name = this.wheelStudents[i].name;
                    const maxLen = n > 15 ? 12 : 18;
                    ctx.fillText(name.length > maxLen ? name.substring(0, maxLen) + '…' : name, r - 14, 0);
                    ctx.restore();
                }

                // Center circle
                ctx.beginPath();
                ctx.arc(cx, cy, 28, 0, 2 * Math.PI);
                ctx.fillStyle = '#fff';
                ctx.fill();
                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 3;
                ctx.stroke();

                ctx.font = 'bold 11px system-ui, sans-serif';
                ctx.fillStyle = '#475569';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('SPIN', cx, cy);
            },

            spin() {
                if (this.spinning || !this.wheelStudents.length) return;
                this.spinning = true;
                this.winner = null;

                const n = this.wheelStudents.length;
                const arc = (2 * Math.PI) / n;

                // Crypto-random winner index
                const rng = new Uint32Array(1);
                crypto.getRandomValues(rng);
                const winnerIdx = rng[0] % n;

                // Calculate target: pointer is at top (3π/2), we need the winner segment under it
                const targetAngle = (2 * Math.PI) - (winnerIdx * arc + arc / 2) + (3 * Math.PI / 2);
                const spins = 5 + Math.random() * 3; // 5-8 full rotations
                const finalAngle = this.currentAngle + spins * 2 * Math.PI + (targetAngle - (this.currentAngle % (2 * Math.PI)));

                const startAngle = this.currentAngle;
                const totalDelta = finalAngle - startAngle;
                const duration = 4500 + Math.random() * 1500; // 4.5–6s
                const start = performance.now();

                const animate = (now) => {
                    const elapsed = now - start;
                    const t = Math.min(elapsed / duration, 1);
                    // Ease out cubic
                    const ease = 1 - Math.pow(1 - t, 3);
                    this.currentAngle = startAngle + totalDelta * ease;
                    this.drawWheel();

                    if (t < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        this.currentAngle = finalAngle % (2 * Math.PI);
                        this.spinning = false;
                        this.winner = this.wheelStudents[winnerIdx];
                        this.history.push({ name: this.winner.name, time: new Date().toLocaleTimeString() });

                        if (this.autoRemove) {
                            this.wheelStudents = this.wheelStudents.filter((_, i) => i !== winnerIdx);
                            this.currentAngle = 0;
                            this.$nextTick(() => this.drawWheel());
                        }
                        this.saveState();
                    }
                };
                requestAnimationFrame(animate);
            },

            removeWinner() {
                if (!this.winner) return;
                this.wheelStudents = this.wheelStudents.filter(s => s.id !== this.winner.id);
                this.winner = null;
                this.currentAngle = 0;
                this.$nextTick(() => this.drawWheel());
                this.saveState();
            },

            resetWheel() {
                this.wheelStudents = [...this.allStudents];
                this.winner = null;
                this.currentAngle = 0;
                this.$nextTick(() => this.drawWheel());
                this.saveState();
            },

            clearHistory() {
                this.history = [];
                this.saveState();
            },

            storageKey() {
                return `rw_${this.courseId}_${this.sectionId}_${this.sessionId}`;
            },

            saveState() {
                if (!this.sessionId) return;
                const data = {
                    wheelStudents: this.wheelStudents,
                    allStudents: this.allStudents,
                    history: this.history,
                    sessionInfo: this.sessionInfo,
                    currentAngle: this.currentAngle,
                };
                sessionStorage.setItem(this.storageKey(), JSON.stringify(data));
            },

            restoreState() {
                // No-op on initial load since no session is selected yet
            },
        };
    }
    </script>
</x-tenant-layout>
