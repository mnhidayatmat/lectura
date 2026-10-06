<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('random_wheel.live_title') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('random_wheel.live_subtitle') }}</p>
            </div>
        </div>
    </x-slot>

    <div x-data="liveWheel()" x-init="init()" class="max-w-2xl mx-auto">
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                        {{ __('random_wheel.watching') }}
                    </span>
                    <span x-show="spin" x-cloak class="text-xs text-slate-500 truncate" x-text="spin ? [spin.course?.code, spin.section_name].filter(Boolean).join(' — ') : ''"></span>
                </div>
                <button type="button" @click="toggleSound()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                    <svg x-show="soundOn" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M11 5L6 9H2v6h4l5 4V5z"/></svg>
                    <svg x-show="!soundOn" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5L6 9H2v6h4l5 4V5zM17 9l4 6m0-6l-4 6"/></svg>
                    <span x-text="soundOn ? @js(__('random_wheel.sound_on')) : @js(__('random_wheel.sound_off'))"></span>
                </button>
            </div>

            <div x-show="!spin" class="text-center py-16">
                <div class="w-20 h-20 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <p class="text-sm font-medium text-slate-700 mb-1">{{ __('random_wheel.waiting_title') }}</p>
                <p class="text-xs text-slate-400 max-w-xs mx-auto">{{ __('random_wheel.waiting_message') }}</p>
            </div>

            <div x-show="spin" x-cloak class="relative flex items-center justify-center">
                <div class="relative">
                    <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1 z-10">
                        <div class="w-0 h-0 border-l-[12px] border-r-[12px] border-t-[20px] border-l-transparent border-r-transparent border-t-red-500 drop-shadow-md"></div>
                    </div>
                    <canvas x-ref="wheelCanvas" width="380" height="380" class="max-w-full h-auto"></canvas>
                </div>
            </div>

            <p x-show="spin && spinning" x-cloak class="mt-4 text-center text-sm font-medium text-slate-500">{{ __('random_wheel.spinning') }}</p>
        </div>

        <div x-show="landed && spin" x-cloak x-transition
             class="mt-6 rounded-2xl p-6 text-center text-white relative overflow-hidden"
             :class="spin?.is_me ? 'bg-gradient-to-br from-amber-500 to-red-600 animate-pulse' : 'bg-gradient-to-br from-indigo-600 to-purple-600'">
            <p class="text-xs font-medium uppercase tracking-wider mb-2 opacity-80" x-text="spin?.is_me ? @js(__('random_wheel.its_you')) : @js(__('random_wheel.picked'))"></p>
            <p class="text-3xl font-bold" x-text="spin?.winner?.name"></p>
            <p x-show="spin?.is_me" class="mt-2 text-sm opacity-90">{{ __('random_wheel.its_you_message') }}</p>
        </div>
    </div>

    <script>
    function liveWheel() {
        const COLORS = ['#6366f1','#8b5cf6','#a855f7','#ec4899','#f43f5e','#ef4444','#f97316','#f59e0b','#eab308','#84cc16','#22c55e','#14b8a6','#06b6d4','#3b82f6','#6366f1','#8b5cf6'];
        const STATE_URL = @js(route('tenant.random-wheel.live-state', app('current_tenant')->slug, false));
        const POLL_MS = 2000;

        return {
            spin: null,
            angle: 0,
            spinning: false,
            landed: false,
            soundOn: false,
            audio: null,
            timer: null,
            polled: false,

            init() {
                this.poll();
                this.timer = setInterval(() => { if (!document.hidden) this.poll(); }, POLL_MS);
                document.addEventListener('visibilitychange', () => { if (!document.hidden) this.poll(); });
            },

            async poll() {
                try {
                    const res = await fetch(STATE_URL, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const next = (await res.json()).spin;
                    const first = !this.polled;
                    this.polled = true;
                    if (!next) { this.spin = null; return; }
                    if (this.spin && this.spin.id === next.id) return;
                    this.show(next, first);
                } catch (e) {
                    // The next poll retries.
                }
            },

            finalAngle(spin) {
                const n = spin.candidates.length;
                const arc = (2 * Math.PI) / n;
                const target = (2 * Math.PI) - (spin.winner_index * arc + arc / 2) + (3 * Math.PI / 2);
                return spin.turns * 2 * Math.PI + target;
            },

            show(spin, first) {
                this.spin = spin;
                this.landed = false;
                const final = this.finalAngle(spin);
                const remaining = spin.duration_ms - spin.elapsed_ms;

                // A spin that finished before this page opened is shown at rest, without the alarm.
                if (remaining <= 0) {
                    this.angle = final;
                    this.spinning = false;
                    this.landed = true;
                    this.$nextTick(() => this.draw());
                    if (!first) this.alarm();
                    return;
                }

                this.spinning = true;
                const startT = spin.elapsed_ms / spin.duration_ms;
                const start = performance.now() - spin.elapsed_ms;
                const animate = (now) => {
                    if (this.spin?.id !== spin.id) return;
                    const t = Math.min(Math.max((now - start) / spin.duration_ms, startT), 1);
                    this.angle = final * (1 - Math.pow(1 - t, 3));
                    this.draw();
                    if (t < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        this.spinning = false;
                        this.landed = true;
                        this.draw();
                        this.alarm();
                    }
                };
                this.$nextTick(() => requestAnimationFrame(animate));
            },

            draw() {
                const canvas = this.$refs.wheelCanvas;
                if (!canvas || !this.spin) return;
                const ctx = canvas.getContext('2d');
                const cx = canvas.width / 2, cy = canvas.height / 2, r = cx - 10;
                const names = this.spin.candidates.map(c => c.name);
                const n = names.length;
                const arc = (2 * Math.PI) / n;

                ctx.clearRect(0, 0, canvas.width, canvas.height);
                for (let i = 0; i < n; i++) {
                    const a = this.angle + i * arc;
                    ctx.beginPath();
                    ctx.moveTo(cx, cy);
                    ctx.arc(cx, cy, r, a, a + arc);
                    ctx.closePath();
                    ctx.fillStyle = COLORS[i % COLORS.length];
                    ctx.fill();
                    if (this.landed && i !== this.spin.winner_index) {
                        ctx.fillStyle = 'rgba(0,0,0,0.4)';
                        ctx.fill();
                    }
                    ctx.strokeStyle = 'rgba(255,255,255,0.3)';
                    ctx.lineWidth = 2;
                    ctx.stroke();

                    ctx.save();
                    ctx.translate(cx, cy);
                    ctx.rotate(a + arc / 2);
                    ctx.fillStyle = '#fff';
                    ctx.font = `bold ${n > 20 ? 9 : n > 12 ? 11 : 13}px system-ui, sans-serif`;
                    ctx.textAlign = 'right';
                    ctx.textBaseline = 'middle';
                    const maxLen = n > 15 ? 12 : 18;
                    const name = names[i];
                    ctx.fillText(name.length > maxLen ? name.substring(0, maxLen) + '…' : name, r - 14, 0);
                    ctx.restore();
                }

                ctx.beginPath();
                ctx.arc(cx, cy, 28, 0, 2 * Math.PI);
                ctx.fillStyle = '#fff';
                ctx.fill();
                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 3;
                ctx.stroke();
            },

            toggleSound() {
                this.soundOn = !this.soundOn;
                // Browsers only allow audio after a tap, so the context is created here.
                if (this.soundOn && !this.audio) {
                    try { this.audio = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { this.audio = null; }
                }
            },

            alarm() {
                const me = this.spin?.is_me;
                if (me && navigator.vibrate) navigator.vibrate([300, 120, 300, 120, 600]);
                if (!this.soundOn || !this.audio) return;
                const notes = me ? [880, 1175, 1568, 1175, 1568] : [660, 880];
                notes.forEach((freq, i) => {
                    const osc = this.audio.createOscillator();
                    const gain = this.audio.createGain();
                    const at = this.audio.currentTime + i * 0.18;
                    osc.frequency.value = freq;
                    gain.gain.setValueAtTime(0.25, at);
                    gain.gain.exponentialRampToValueAtTime(0.001, at + 0.16);
                    osc.connect(gain).connect(this.audio.destination);
                    osc.start(at);
                    osc.stop(at + 0.17);
                });
            },
        };
    }
    </script>
</x-tenant-layout>
