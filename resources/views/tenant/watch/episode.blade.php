<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ $preview['seriesUrl'] ?? route('tenant.watch.series', [$tenant->slug, $playback['series']['id']]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition" aria-label="Back to the series">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white truncate">EP {{ $playback['episode_number'] }} · {{ $playback['title'] }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ $playback['series']['title'] }} · {{ $playback['course']['code'] }}</p>
            </div>
        </div>
    </x-slot>

    @php extract(\App\Support\WatchView::helpers($tenant)); @endphp
    @php
        $next = $playback['next_episode'];
        $config = [
            'source' => $playback['source'],
            'youtubeId' => $playback['youtube']['video_id'] ?? null,
            'streamUrl' => $playback['stream_url'],
            'start' => ($playback['progress'] && ! $playback['progress']['completed']) ? $playback['progress']['position_seconds'] : 0,
            'duration' => $playback['duration_seconds'],
            'scenes' => $playback['scenes'],
            'checks' => $playback['checks'],
            'captions' => $playback['captions'],
            'preview' => isset($preview),
            'progressUrl' => isset($preview) ? null : route('tenant.watch.progress', [$tenant->slug, $playback['id']]),
            'answerUrl' => isset($preview) ? null : route('tenant.watch.answer', [$tenant->slug, '__CHECK__']),
            'nextUrl' => isset($preview) ? $preview['nextUrl'] : ($next && $next['is_available'] ? route('tenant.watch.episode', [$tenant->slug, $next['id']]) : null),
            'seriesUrl' => $preview['seriesUrl'] ?? route('tenant.watch.series', [$tenant->slug, $playback['series']['id']]),
            'csrf' => csrf_token(),
        ];
    @endphp

    @isset($preview)
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
            <span class="font-bold">Student preview</span>
            <span>Playing as a student would. Quick Checks reveal their answer; progress and answers aren't saved.</span>
            <a href="{{ $preview['manageUrl'] }}" class="ml-auto font-semibold underline">Back to Episodes</a>
        </div>
    @endisset
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6" x-data="watchPlayer(@js($config))" x-init="boot()" @keydown.window.escape="cancelUpNext()">
        <div class="xl:col-span-2 space-y-3">
            <div class="rounded-2xl bg-slate-950 p-2 sm:p-3 shadow-xl">
                <div class="relative aspect-video rounded-xl overflow-hidden bg-black">
                    @if($playback['source'] === 'youtube')
                        <div id="watch-yt" class="absolute inset-0 w-full h-full"></div>
                    @else
                        <video x-ref="video" class="absolute inset-0 w-full h-full" controls playsinline preload="metadata" src="{{ $playback['stream_url'] }}"></video>
                    @endif

                    {{-- Quick Check: shown only while the video is paused for it --}}
                    <div x-show="check" x-cloak x-transition.opacity class="absolute inset-0 z-10 flex items-center justify-center bg-slate-950/80 p-2 sm:p-4">
                        <div class="w-full max-w-lg max-h-full overflow-y-auto rounded-2xl bg-slate-800 ring-1 ring-white/10 p-4 sm:p-5 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="qc-prompt">
                            <p class="text-[11px] font-extrabold uppercase tracking-[0.15em] text-amber-400">Quick Check · <span x-text="fmt(check?.at_seconds)"></span></p>
                            <p id="qc-prompt" class="mt-1 text-base sm:text-lg font-bold text-white" x-text="check?.prompt"></p>
                            <div class="mt-3 grid gap-2">
                                <template x-for="option in (check?.options || [])" :key="option.id">
                                    <button type="button" @click="answer(option)" :disabled="result || sending"
                                            class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold ring-1 transition"
                                            :class="optionClass(option)">
                                        <span x-text="option.label"></span>
                                    </button>
                                </template>
                            </div>
                            <p x-show="error" class="mt-3 text-sm text-red-300" x-text="error"></p>
                            <div x-show="result" class="mt-3">
                                <p class="text-sm font-bold" :class="result?.is_correct ? 'text-emerald-300' : 'text-amber-300'" x-text="result?.is_correct ? 'Correct!' : 'Not quite.'"></p>
                                <p x-show="result?.explanation" class="mt-1 text-sm text-[#cbd5e1]" x-text="result?.explanation"></p>
                            </div>
                            <div class="mt-3 flex justify-end gap-2">
                                {{-- Answering is compulsory; skipping is only for an answer that could not be saved --}}
                                <button type="button" x-show="!result && error" @click="skipCheck()" class="px-4 py-2 rounded-xl text-sm font-semibold text-[#cbd5e1] hover:bg-white/10">Skip for now</button>
                                <button type="button" x-show="result" @click="closeCheck()" class="px-4 py-2 rounded-xl text-sm font-bold bg-violet-300 text-slate-950 hover:bg-violet-200">Continue</button>
                            </div>
                        </div>
                    </div>

                    {{-- End card --}}
                    <div x-show="ended" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-slate-950/85 p-4 text-center">
                        <div>
                            @if($next && ($next['is_available'] || isset($preview)))
                                <p class="text-xs font-bold uppercase tracking-[0.15em] text-[#94a3b8]" x-show="countdown > 0">Up next in <span x-text="countdown"></span></p>
                                <p class="mt-1 text-xl font-extrabold text-white">EP {{ $next['episode_number'] }} · {{ $next['title'] }}</p>
                                <div class="mt-4 flex justify-center gap-2">
                                    <a href="{{ $config['nextUrl'] }}" class="px-5 py-2.5 rounded-xl bg-[#ffffff] text-slate-950 text-sm font-bold hover:bg-[#e2e8f0]">Play now</a>
                                    <button type="button" x-show="countdown > 0" @click="cancelUpNext()" class="px-5 py-2.5 rounded-xl bg-white/15 text-white text-sm font-bold hover:bg-white/25">Cancel</button>
                                    <a href="{{ $config['seriesUrl'] }}" x-show="countdown === 0" class="px-5 py-2.5 rounded-xl bg-white/15 text-white text-sm font-bold hover:bg-white/25">Back to series</a>
                                </div>
                            @else
                                <p class="text-xl font-extrabold text-white">That's the end of this episode</p>
                                @if($next)<p class="mt-1 text-sm text-[#94a3b8]">EP {{ $next['episode_number'] }} {{ $next['available_at'] ? 'unlocks '.\Illuminate\Support\Carbon::parse($next['available_at'])->timezone($tz)->format('D j M') : 'is coming soon' }}.</p>@endif
                                <a href="{{ $config['seriesUrl'] }}" class="mt-4 inline-block px-5 py-2.5 rounded-xl bg-[#ffffff] text-slate-950 text-sm font-bold hover:bg-[#e2e8f0]">Back to series</a>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Caption band below the video, so it never covers the player's controls --}}
                <div x-show="captionLang" x-cloak class="min-h-[3rem] px-3 pt-3 text-center text-base sm:text-lg font-semibold text-white" aria-live="polite" x-text="captionText"></div>

                <div class="flex flex-wrap items-center gap-3 px-1 pt-3 text-sm text-[#cbd5e1]">
                    <span class="truncate"><span class="text-[#64748b]">Scene</span> <span class="font-semibold text-white" x-text="sceneTitle || '—'"></span></span>
                    <span class="ml-auto flex items-center gap-2">
                        <template x-if="captions.length">
                            <label class="flex items-center gap-2"><span class="sr-only">Captions</span>
                                <select x-model="captionLang" @change="loadCaptions()" class="rounded-lg bg-slate-800 border-0 text-sm text-slate-100 py-1.5">
                                    <option value="">CC off</option>
                                    <template x-for="c in captions" :key="c.language"><option :value="c.language" x-text="c.label"></option></template>
                                </select>
                            </label>
                        </template>
                        <span x-show="saveFailed" x-cloak class="text-xs text-amber-300">Progress not saved, check your connection</span>
                    </span>
                </div>
            </div>
            @if($playback['source'] === 'youtube')
                <p class="text-xs text-[#64748b] dark:text-slate-400">
                    Plays from YouTube. If it won't play, <a href="{{ $playback['youtube']['url'] }}" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline">open it on YouTube</a> and let your lecturer know.
                </p>
            @endif
        </div>

        <aside class="space-y-4">
            <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $playback['course']['code'] }}
                    @if($playback['week_number']) · Week {{ $playback['week_number'] }} @endif
                    @if($playback['duration_seconds']) · {{ $clock($playback['duration_seconds']) }} @endif
                </p>
                <h3 class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ $playback['title'] }}</h3>
                @if($playback['topic'])<p class="text-sm text-slate-600 dark:text-slate-300">{{ $playback['topic']['title'] }}</p>@endif
                @if($playback['synopsis'])<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $playback['synopsis'] }}</p>@endif
                @php $due = $deadline($playback['required_by']); @endphp
                @if($due && ! ($playback['progress']['completed'] ?? false))
                    <p class="mt-3 inline-flex px-2 py-0.5 rounded-md text-xs font-bold {{ $due === 'Overdue' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' }}">{{ $due }}</p>
                @endif
            </section>

            @if(count($playback['scenes']))
                <section class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-2">
                    <h3 class="px-3 pt-2 pb-1 text-sm font-bold text-slate-900 dark:text-white">Scenes</h3>
                    <ol>
                        @foreach($playback['scenes'] as $i => $scene)
                            <li>
                                <button type="button" @click="seek({{ $scene['start_seconds'] }})"
                                        class="w-full flex items-baseline gap-3 px-3 py-2 rounded-xl text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700"
                                        :class="sceneIndex === {{ $i }} ? 'bg-indigo-50 dark:bg-indigo-900/30' : ''">
                                    <span class="w-12 shrink-0 text-xs font-semibold tabular-nums text-slate-500 dark:text-slate-400">{{ $clock($scene['start_seconds']) }}</span>
                                    <span class="text-slate-800 dark:text-slate-200">{{ $scene['title'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </aside>
    </div>

    <script>
        function watchPlayer(cfg) {
            return {
                ...cfg,
                yt: null, playing: false, position: cfg.start || 0, lastPolled: null,
                check: null, result: null, sending: false, error: null, handled: {},
                sceneIndex: -1, sceneTitle: '', ended: false, countdown: 5, timer: null,
                captionLang: '', cues: [], captionText: '', saveFailed: false,
                rewinds: [], lastSaved: 0, poll: null,

                boot() {
                    // Checks already answered correctly never interrupt again.
                    this.checks.forEach(c => { if (c.my_answer && c.my_answer.is_correct) this.handled[c.id] = true; });
                    this.checks.forEach(c => { if (c.at_seconds < this.start) this.handled[c.id] = this.handled[c.id] || 'passed'; });
                    if (this.source === 'youtube') { this.bootYouTube(); } else { this.bootVideo(); }
                    this.poll = setInterval(() => this.tick(), 500);
                    window.addEventListener('pagehide', () => this.save(false, true));
                    document.addEventListener('visibilitychange', () => { if (document.hidden) { this.pause(); this.save(); } });
                },

                bootVideo() {
                    const v = this.$refs.video;
                    v.addEventListener('loadedmetadata', () => { if (this.start > 0 && this.start < v.duration - 5) v.currentTime = this.start; });
                    v.addEventListener('play', () => { this.playing = true; this.ended = false; });
                    v.addEventListener('pause', () => { this.playing = false; this.save(); });
                    v.addEventListener('ended', () => this.onEnded());
                },

                bootYouTube() {
                    const make = () => {
                        this.yt = new YT.Player('watch-yt', {
                            host: 'https://www.youtube-nocookie.com',
                            videoId: this.youtubeId,
                            width: '100%', height: '100%',
                            playerVars: { rel: 0, playsinline: 1, start: Math.floor(this.start || 0), cc_load_policy: 0 },
                            events: {
                                onStateChange: (e) => {
                                    if (e.data === YT.PlayerState.PLAYING) { this.playing = true; this.ended = false; }
                                    if (e.data === YT.PlayerState.PAUSED) { this.playing = false; this.save(); }
                                    if (e.data === YT.PlayerState.ENDED) { this.playing = false; this.onEnded(); }
                                },
                            },
                        });
                    };
                    if (window.YT && window.YT.Player) { make(); return; }
                    const previous = window.onYouTubeIframeAPIReady;
                    window.onYouTubeIframeAPIReady = () => { if (previous) previous(); make(); };
                    const tag = document.createElement('script');
                    tag.src = 'https://www.youtube.com/iframe_api';
                    document.head.appendChild(tag);
                },

                now() {
                    if (this.yt && this.yt.getCurrentTime) return this.yt.getCurrentTime() || 0;
                    return this.$refs.video ? this.$refs.video.currentTime : 0;
                },
                videoDuration() {
                    if (this.yt && this.yt.getDuration) return Math.round(this.yt.getDuration() || 0) || null;
                    const d = this.$refs.video ? this.$refs.video.duration : NaN;
                    return isFinite(d) ? Math.round(d) : null;
                },
                play() { if (this.yt && this.yt.playVideo) this.yt.playVideo(); else if (this.$refs.video) this.$refs.video.play(); },
                pause() { if (this.yt && this.yt.pauseVideo) this.yt.pauseVideo(); else if (this.$refs.video) this.$refs.video.pause(); },
                jumpTo(s) { if (this.yt && this.yt.seekTo) this.yt.seekTo(s, true); else if (this.$refs.video) this.$refs.video.currentTime = s; },
                seek(s) {
                    this.ended = false;
                    if (this.yt && this.yt.seekTo) { this.yt.seekTo(s, true); this.yt.playVideo(); }
                    else if (this.$refs.video) { this.$refs.video.currentTime = s; this.$refs.video.play(); }
                    // Seeking back before a check that was only skipped by resuming re-arms it.
                    this.checks.forEach(c => { if (this.handled[c.id] === 'passed' && c.at_seconds >= s) delete this.handled[c.id]; });
                },

                tick() {
                    const t = this.now();
                    if (this.lastPolled !== null && this.lastPolled - t >= 5 && this.rewinds.length < 50) {
                        this.rewinds.push({ from_seconds: Math.floor(this.lastPolled), to_seconds: Math.floor(t) });
                    }

                    // A seek (YouTube's scrubber, the video's own controls or a scene button) over a
                    // question the student never answered goes back to it: answering is compulsory.
                    if (!this.check && this.lastPolled !== null && t - this.lastPolled > 2) {
                        const from = this.lastPolled;
                        const jumped = this.checks
                            .filter(c => !c.my_answer && !this.handled[c.id] && c.at_seconds > from && c.at_seconds <= t)
                            .sort((a, b) => a.at_seconds - b.at_seconds)[0];
                        if (jumped) {
                            this.pause();
                            this.jumpTo(jumped.at_seconds);
                            this.lastPolled = this.position = jumped.at_seconds;
                            this.check = jumped; this.result = null; this.error = null;
                            return;
                        }
                    }
                    this.lastPolled = t;
                    this.position = t;

                    let idx = -1;
                    this.scenes.forEach((s, i) => { if (t >= s.start_seconds) idx = i; });
                    this.sceneIndex = idx; this.sceneTitle = idx >= 0 ? this.scenes[idx].title : '';

                    if (this.captionLang) {
                        const cue = this.cues.find(c => t >= c.start && t <= c.end);
                        this.captionText = cue ? cue.text : '';
                    }

                    if (this.playing && !this.check) {
                        const due = this.checks.find(c => !this.handled[c.id] && t >= c.at_seconds && t < c.at_seconds + 1.5);
                        if (due) { this.pause(); this.check = due; this.result = null; this.error = null; }
                    }

                    if (this.playing && Date.now() - this.lastSaved > 10000) this.save();
                },

                async answer(option) {
                    if (this.preview) {
                        const correct = this.check.correct_option_id;
                        this.result = { is_correct: option.id === correct, correct_option_id: correct, explanation: this.check.explanation, chosen: option.id };
                        return;
                    }
                    this.sending = true; this.error = null;
                    try {
                        const res = await fetch(this.answerUrl.replace('__CHECK__', this.check.id), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                            body: JSON.stringify({ option_id: option.id }),
                        });
                        const body = await res.json();
                        if (!res.ok) throw new Error(body.message || 'Your answer was not saved.');
                        this.result = { ...body.data, chosen: option.id };
                        this.check.my_answer = { option_id: option.id, is_correct: body.data.is_correct };
                    } catch (e) {
                        this.error = navigator.onLine ? e.message : "You're offline. Answer this when you're back online, or skip for now.";
                    } finally { this.sending = false; }
                },
                optionClass(option) {
                    if (!this.result) return 'bg-slate-700/60 ring-white/10 text-slate-100 hover:bg-slate-700';
                    if (option.id === this.result.correct_option_id) return 'bg-emerald-500/20 ring-emerald-400 text-emerald-200';
                    if (option.id === this.result.chosen) return 'bg-red-500/20 ring-red-400 text-red-200';
                    return 'bg-slate-700/40 ring-white/5 text-slate-400';
                },
                closeCheck() { this.handled[this.check.id] = true; this.check = null; this.play(); },
                skipCheck() { this.handled[this.check.id] = 'skipped'; this.check = null; this.play(); },

                onEnded() {
                    this.ended = true;
                    this.save(true);
                    if (!this.nextUrl) return;
                    this.countdown = 5;
                    this.timer = setInterval(() => {
                        this.countdown -= 1;
                        if (this.countdown <= 0) { clearInterval(this.timer); window.location.href = this.nextUrl; }
                    }, 1000);
                },
                cancelUpNext() { if (this.timer) { clearInterval(this.timer); this.timer = null; this.countdown = 0; } },

                async loadCaptions() {
                    this.cues = []; this.captionText = '';
                    const track = this.captions.find(c => c.language === this.captionLang);
                    if (!track) return;
                    try { this.cues = parseVtt(await (await fetch(track.url)).text()); } catch (e) { this.captionLang = ''; }
                },

                save(completed = false, beacon = false) {
                    if (!this.progressUrl) return; // student preview: nothing is saved
                    const position = Math.floor(this.now());
                    if (position <= 0 && !completed) return;
                    const payload = { position_seconds: position, completed: completed || undefined, duration_seconds: this.videoDuration() || undefined, rewinds: this.rewinds.length ? this.rewinds : undefined };
                    this.rewinds = [];
                    this.lastSaved = Date.now();
                    if (beacon && navigator.sendBeacon) {
                        const form = new FormData();
                        form.append('_token', this.csrf);
                        Object.entries(payload).forEach(([k, v]) => {
                            if (v === undefined) return;
                            if (k === 'rewinds') v.forEach((r, i) => { form.append(`rewinds[${i}][from_seconds]`, r.from_seconds); form.append(`rewinds[${i}][to_seconds]`, r.to_seconds); });
                            else form.append(k, k === 'completed' ? 1 : v);
                        });
                        navigator.sendBeacon(this.progressUrl, form);
                        return;
                    }
                    fetch(this.progressUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                        body: JSON.stringify(payload), keepalive: true,
                    }).then(r => { this.saveFailed = !r.ok; }).catch(() => { this.saveFailed = true; });
                },

                fmt(s) { if (s == null) return ''; s = Math.floor(s); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); },
            };
        }

        function parseVtt(text) {
            const toSec = (t) => { const p = t.trim().split(':').map(parseFloat); return p.length === 3 ? p[0] * 3600 + p[1] * 60 + p[2] : p[0] * 60 + p[1]; };
            return text.replace(/\r/g, '').split(/\n\n+/).map(block => {
                const lines = block.split('\n');
                const i = lines.findIndex(l => l.includes('-->'));
                if (i < 0) return null;
                const [a, b] = lines[i].split('-->');
                return { start: toSec(a), end: toSec(b.trim().split(/\s+/)[0]), text: lines.slice(i + 1).join(' ').replace(/<[^>]+>/g, '') };
            }).filter(Boolean);
        }
    </script>
</x-tenant-layout>
