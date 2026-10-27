// Music and sound effects for the live quiz board and player (original clips made by
// tool/quiz_sounds.py in the app repo, served from /sounds/quiz/). Web Audio so the
// lobby and question loops repeat without a gap. Browsers keep audio locked until the
// first click or key press, so `unlocked` stays false until then and the toggle says so.
// Sound is a bonus: every failure is swallowed.

const BASE = '/sounds/quiz/';
const NAMES = ['lobby', 'question', 'tick', 'timeup', 'correct', 'wrong', 'pop', 'leaderboard', 'podium_roll', 'fanfare'];
const FADE = 0.35;

export default function quizSounds({ storageKey, volume = 1, onChange = () => {} }) {
    let ctx = null;
    let master = null;
    const buffers = {};
    let loading = null;
    let loop = null;
    let wanted = null;
    const effects = new Set();

    const state = {
        muted: readMuted(storageKey),
        unlocked: false,
    };

    function context() {
        if (ctx) {
            return ctx;
        }
        const Ctor = window.AudioContext || window.webkitAudioContext;
        if (!Ctor) {
            return null;
        }
        ctx = new Ctor();
        master = ctx.createGain();
        master.gain.value = state.muted ? 0 : volume;
        master.connect(ctx.destination);
        return ctx;
    }

    function load() {
        const audio = context();
        if (!audio) {
            return Promise.resolve();
        }
        loading ??= Promise.all(NAMES.map(async (name) => {
            try {
                const response = await fetch(`${BASE}quiz_${name}.wav`);
                buffers[name] = await audio.decodeAudioData(await response.arrayBuffer());
            } catch {
                // that clip just stays silent
            }
        }));
        return loading;
    }

    async function unlock() {
        const audio = context();
        if (!audio) {
            return;
        }
        try {
            await audio.resume();
        } catch {
            return;
        }
        state.unlocked = audio.state === 'running';
        onChange(state);
        await load();
        if (wanted && !loop) {
            startLoop(wanted);
        }
    }

    function startLoop(name) {
        const buffer = buffers[name];
        if (!buffer || !state.unlocked) {
            return;
        }
        const gain = ctx.createGain();
        gain.gain.setValueAtTime(0, ctx.currentTime);
        gain.gain.linearRampToValueAtTime(1, ctx.currentTime + FADE);
        gain.connect(master);
        const source = ctx.createBufferSource();
        source.buffer = buffer;
        source.loop = true;
        source.connect(gain);
        source.start();
        loop = { name, source, gain };
    }

    function stopLoop() {
        if (!loop) {
            return;
        }
        const { source, gain } = loop;
        loop = null;
        try {
            gain.gain.cancelScheduledValues(ctx.currentTime);
            gain.gain.setValueAtTime(gain.gain.value, ctx.currentTime);
            gain.gain.linearRampToValueAtTime(0, ctx.currentTime + FADE);
            source.stop(ctx.currentTime + FADE + 0.05);
        } catch {
            // already stopped
        }
    }

    const onGesture = () => {
        unlock().then(() => {
            if (state.unlocked) {
                ['pointerdown', 'keydown', 'touchend'].forEach((type) => document.removeEventListener(type, onGesture, true));
            }
        });
    };
    ['pointerdown', 'keydown', 'touchend'].forEach((type) => document.addEventListener(type, onGesture, true));

    document.addEventListener('visibilitychange', () => {
        if (!ctx) {
            return;
        }
        if (document.hidden) {
            ctx.suspend().catch(() => {});
        } else if (state.unlocked) {
            ctx.resume().catch(() => {});
        }
    });

    return {
        state,

        /** Plays `name` as the looping background music, or stops it with null. */
        music(name) {
            wanted = name;
            if (loop?.name === name) {
                return;
            }
            stopLoop();
            if (name) {
                startLoop(name);
            }
        },

        /** Plays a one-off clip over the music. */
        play(name, gainValue = 1) {
            const buffer = buffers[name];
            if (!buffer || !state.unlocked || state.muted) {
                return;
            }
            try {
                const gain = ctx.createGain();
                gain.gain.value = gainValue;
                gain.connect(master);
                const source = ctx.createBufferSource();
                source.buffer = buffer;
                source.connect(gain);
                source.onended = () => effects.delete(source);
                effects.add(source);
                source.start();
            } catch {
                // ignore
            }
        },

        /** Stops the one-off clips (the podium drum roll when the winner is shown early). */
        stopEffects() {
            effects.forEach((source) => {
                try {
                    source.stop();
                } catch {
                    // already finished
                }
            });
            effects.clear();
        },

        toggle() {
            state.muted = !state.muted;
            try {
                localStorage.setItem(storageKey, state.muted ? '1' : '0');
            } catch {
                // the choice just lasts for this page
            }
            if (master) {
                master.gain.setTargetAtTime(state.muted ? 0 : volume, ctx.currentTime, 0.05);
            }
            onChange(state);
            if (!state.muted) {
                unlock();
            }
        },
    };
}

function readMuted(key) {
    try {
        return localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}
