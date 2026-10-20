// Lecturer's presenter screen for a live quiz (Mentimeter style): lobby with names
// popping in, the open question with a countdown, the answer reveal, the standings
// after every question and a podium at the end. State comes from polling the board
// endpoint; actions POST and get the new state straight back.

export const OPTION_COLORS = ['#7c3aed', '#0284c7', '#d97706', '#e11d48', '#059669', '#475569'];

const ROW_HEIGHT = 64;

export default function quizBoard({ stateUrl, actions, initial, joinUrl }) {
    return {
        s: initial,
        view: initial.phase,
        busy: false,
        error: '',
        remaining: null,
        deadline: null,
        autoClosing: false,
        rows: [],
        podiumStep: 0,
        confetti: [],
        freshIds: [],
        known: new Set((initial.participants || []).map((p) => p.id)),
        fullscreen: false,
        qrSvg: '',
        colors: OPTION_COLORS,
        rowHeight: ROW_HEIGHT,
        pollTimer: null,
        timers: [],

        init() {
            this.apply(initial, true);
            this.poll();
            setInterval(() => this.tick(), 250);
            document.addEventListener('keydown', (event) => this.onKey(event));
            document.addEventListener('fullscreenchange', () => {
                this.fullscreen = document.fullscreenElement === this.$refs.stage;
            });
            if (joinUrl && window.QRCode) {
                window.QRCode.toString(joinUrl, { type: 'svg', margin: 0, color: { dark: '#1e1b4b', light: '#ffffff' } })
                    .then((svg) => { this.qrSvg = svg; })
                    .catch(() => {});
            }
        },

        // ── State ──

        apply(state, first = false) {
            const before = this.s;
            const questionChanged = before.question?.session_question_id !== state.question?.session_question_id;

            const incoming = (state.participants || []).filter((p) => !this.known.has(p.id)).map((p) => p.id);
            incoming.forEach((id) => this.known.add(id));
            if (incoming.length && !first) {
                this.freshIds = [...this.freshIds, ...incoming];
                setTimeout(() => { this.freshIds = this.freshIds.filter((id) => !incoming.includes(id)); }, 2500);
            }

            this.s = state;

            if (state.phase === 'answering') {
                if (this.view !== 'answering' || questionChanged || first) {
                    this.autoClosing = false;
                }
                this.syncDeadline(state.question?.remaining_seconds);
                this.view = 'answering';
            } else if (state.phase === 'reveal') {
                const stored = this.storedView(state.question?.session_question_id);
                if (first && stored === 'leaderboard') {
                    this.showLeaderboard(false);
                } else if (this.view !== 'leaderboard' || questionChanged) {
                    if (this.view !== 'reveal' || questionChanged) {
                        this.view = 'reveal';
                    }
                }
            } else if (state.phase === 'finished') {
                if (this.view !== 'finished') {
                    this.view = 'finished';
                    this.startPodium(first);
                }
            } else {
                this.view = state.phase;
            }
        },

        async poll() {
            clearTimeout(this.pollTimer);
            if (this.s.phase === 'ended') {
                return;
            }
            try {
                const response = await fetch(stateUrl, { headers: { Accept: 'application/json' } });
                if (response.ok && !this.busy) {
                    this.apply(await response.json());
                }
            } catch {
                // network blip — try again on the next tick
            }
            const interval = this.view === 'answering' ? 1000 : (this.view === 'lobby' ? 1500 : 2500);
            this.pollTimer = setTimeout(() => this.poll(), interval);
        },

        async post(url) {
            if (this.busy) {
                return null;
            }
            this.busy = true;
            this.error = '';
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                if (!response.ok) {
                    throw new Error(String(response.status));
                }
                const data = await response.json();
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return data;
                }
                this.busy = false;
                this.apply(data);
                return data;
            } catch {
                this.error = 'Something went wrong. Check your connection and try again.';
                this.busy = false;
                return null;
            }
        },

        // ── Actions ──

        start() {
            this.post(actions.start);
        },

        closeQuestion() {
            this.autoClosing = true;
            this.post(actions.close);
        },

        next() {
            this.forgetView();
            this.post(actions.next);
        },

        end() {
            this.post(actions.end);
        },

        primary() {
            switch (this.view) {
                case 'lobby':
                    return this.start();
                case 'answering':
                    return this.closeQuestion();
                case 'reveal':
                    return this.showLeaderboard();
                case 'leaderboard':
                    return this.next();
                case 'finished':
                    if (this.podiumStep < 3) {
                        this.timers.forEach(clearTimeout);
                        this.podiumStep = 3;
                        this.burst();
                        return null;
                    }
                    return this.end();
                default:
                    return null;
            }
        },

        primaryLabel() {
            return {
                lobby: 'Start quiz',
                answering: 'Show results',
                reveal: 'Leaderboard',
                leaderboard: this.s.is_last_question ? 'Final results' : 'Next question',
                finished: this.podiumStep < 3 ? 'Reveal winner' : 'End quiz & save',
            }[this.view] || '';
        },

        onKey(event) {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.metaKey || event.ctrlKey) {
                return;
            }
            if (['ArrowRight', 'PageDown', ' ', 'Enter'].includes(event.key)) {
                event.preventDefault();
                this.primary();
            } else if (event.key === 'f' || event.key === 'F') {
                this.toggleFullscreen();
            }
        },

        toggleFullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen?.();
            } else {
                this.$refs.stage.requestFullscreen?.();
            }
        },

        // ── Countdown ──

        syncDeadline(seconds) {
            if (seconds === null || seconds === undefined) {
                this.deadline = null;
                return;
            }
            const target = Date.now() + seconds * 1000;
            if (this.deadline === null || Math.abs(target - this.deadline) > 1200) {
                this.deadline = target;
            }
        },

        tick() {
            if (this.view !== 'answering' || this.deadline === null) {
                this.remaining = null;
                return;
            }
            this.remaining = Math.max(0, Math.ceil((this.deadline - Date.now()) / 1000));
            const everyone = this.s.participant_count > 0 && this.s.answered_count >= this.s.participant_count;
            if (!this.autoClosing && !this.busy && (this.remaining === 0 || everyone)) {
                this.autoClosing = true;
                setTimeout(() => {
                    if (this.view === 'answering') {
                        this.post(actions.close);
                    }
                }, everyone && this.remaining > 0 ? 1200 : 400);
            }
        },

        timerFraction() {
            const limit = this.s.question?.time_limit || 30;
            return this.remaining === null ? 1 : Math.max(0, Math.min(1, this.remaining / limit));
        },

        // Long questions (e.g. matching lists) sit beside their options instead of above them.
        isLong() {
            const text = this.s.question?.text || '';
            return text.length > 180 || text.split('\n').length > 4;
        },

        // ── Reveal ──

        maxCount() {
            return Math.max(1, ...(this.s.question?.options || []).map((o) => o.count || 0));
        },

        correctCount() {
            return (this.s.question?.options || []).filter((o) => o.is_correct).reduce((sum, o) => sum + (o.count || 0), 0);
        },

        // ── Leaderboard ──

        showLeaderboard(animate = true) {
            const entries = this.s.leaderboard || [];
            const before = [...entries].sort((a, b) => ((a.previous_rank ?? a.rank) - (b.previous_rank ?? b.rank)) || (a.rank - b.rank));
            this.rows = entries.map((entry, index) => ({
                ...entry,
                pos: animate ? before.indexOf(entry) : index,
                shown: animate ? entry.points - entry.gained : entry.points,
                revealGain: !animate,
            }));
            this.view = 'leaderboard';
            this.rememberView('leaderboard');

            if (!animate) {
                return;
            }
            setTimeout(() => {
                this.rows.forEach((row, index) => {
                    row.pos = index;
                    row.revealGain = true;
                });
                this.countUp();
            }, 650);
        },

        countUp() {
            const startedAt = performance.now();
            const from = this.rows.map((row) => row.shown);
            const step = (now) => {
                const t = Math.min(1, (now - startedAt) / 1100);
                const eased = 1 - Math.pow(1 - t, 3);
                this.rows.forEach((row, index) => {
                    row.shown = Math.round(from[index] + (row.points - from[index]) * eased);
                });
                if (t < 1 && this.view === 'leaderboard') {
                    requestAnimationFrame(step);
                }
            };
            requestAnimationFrame(step);
        },

        topPoints() {
            return Math.max(1, ...this.rows.map((row) => row.points));
        },

        movement(row) {
            return row.previous_rank ? row.previous_rank - row.rank : 0;
        },

        headline() {
            const board = this.s.leaderboard || [];
            const leader = board[0];
            if (!leader) {
                return 'No points yet';
            }
            if (board[1] && board[1].rank === 1) {
                return "It's a tie at the top!";
            }
            if ((this.s.question?.number || 1) === 1) {
                return `${leader.name} is first off the mark!`;
            }
            return leader.previous_rank === 1 ? `${leader.name} stays on top!` : `${leader.name} takes the lead!`;
        },

        // ── Podium ──

        podium() {
            const board = this.s.leaderboard || [];
            return [board[1], board[0], board[2]];
        },

        startPodium(instant) {
            this.timers.forEach(clearTimeout);
            if (instant) {
                this.podiumStep = 3;
                return;
            }
            this.podiumStep = 0;
            const count = Math.min(3, (this.s.leaderboard || []).length);
            const steps = [[1, 900], [2, 2400], [3, 4200]].filter(([step]) => step > 3 - count);
            if (count < 3) {
                this.podiumStep = 3 - count;
            }
            this.timers = steps.map(([step, delay]) => setTimeout(() => {
                this.podiumStep = step;
                if (step === 3) {
                    this.burst();
                }
            }, delay));
        },

        // Place 3rd shows at step 1, 2nd at step 2, 1st at step 3.
        placeShown(place) {
            return this.podiumStep >= 4 - place;
        },

        burst() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }
            const palette = ['#facc15', '#f472b6', '#34d399', '#60a5fa', '#c084fc', '#ffffff'];
            this.confetti = Array.from({ length: 140 }, (_, i) => ({
                id: `${Date.now()}-${i}`,
                left: Math.random() * 100,
                delay: Math.random() * 0.6,
                duration: 2.4 + Math.random() * 1.8,
                size: 6 + Math.random() * 8,
                color: palette[i % palette.length],
                drift: (Math.random() - 0.5) * 240,
                spin: Math.random() * 720,
            }));
            setTimeout(() => { this.confetti = []; }, 5000);
        },

        // ── Helpers ──

        initials(name) {
            return (name || '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
        },

        chipColor(id) {
            return OPTION_COLORS[id % 5];
        },

        rememberView(view) {
            try {
                sessionStorage.setItem(`quiz-board-${this.s.question?.session_question_id}`, view);
            } catch {
                // storage unavailable — the view just restarts on reload
            }
        },

        forgetView() {
            try {
                sessionStorage.removeItem(`quiz-board-${this.s.question?.session_question_id}`);
            } catch {
                // ignore
            }
        },

        storedView(questionId) {
            try {
                return sessionStorage.getItem(`quiz-board-${questionId}`);
            } catch {
                return null;
            }
        },
    };
}
