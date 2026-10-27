<x-tenant-layout>
    {{-- Clips: a vertical scroll-snap feed, one clip per screen, like the app's Clips tab.
         The clips carry their title, captions and key point in the picture, so the video
         stays whole and the details sit below it. New clips come first and watched ones wait
         behind the "all caught up" card, as in the app. Always dark: fixed colours only. --}}
    @php
        $feedUrl = fn (array $query = []) => route('tenant.watch.clips', [$tenant->slug, ...array_filter(['course' => $courseId, ...$query])]);
    @endphp
    <div x-data="clipFeed({{ (int) $start }}, @js(csrf_token()))"
         @keydown.window.arrow-down.prevent="go(1)"
         @keydown.window.arrow-up.prevent="go(-1)"
         class="relative rounded-3xl bg-slate-950 text-slate-100 shadow-xl overflow-hidden">

        @if(! $hasClips)
            <div class="py-20 px-6 text-center">
                <div class="mx-auto w-14 h-14 rounded-2xl bg-slate-800 flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-[#94a3b8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 6v12a2 2 0 002 2h12a2 2 0 002-2V6M4 6l2-3h12l2 3M10 10l5 3-5 3v-6z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-[#ffffff]">{{ __('watch.no_clips') }}</h3>
                <p class="text-sm text-[#94a3b8] mt-1">{{ __('watch.no_clips_message') }}</p>
            </div>
        @else
            {{-- Sound is shared by every clip. Browsers only autoplay muted video, so it starts muted. --}}
            <button type="button" @click="toggleMute()"
                    class="absolute top-3 right-3 z-20 inline-flex items-center gap-2 h-10 px-3 rounded-full bg-[#000000]/60 text-[#ffffff] text-sm font-semibold backdrop-blur hover:bg-[#000000]/75 transition"
                    :aria-label="muted ? @js(__('watch.unmute')) : @js(__('watch.mute'))">
                <svg x-show="muted" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l4-4m0 4l-4-4"/></svg>
                <svg x-show="!muted" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
                <span x-show="muted">{{ __('watch.tap_for_sound') }}</span>
            </button>

            {{-- The episode picker, and the episode the feed is narrowed to. --}}
            <div class="absolute top-3 left-3 z-20 flex items-center gap-2 max-w-[calc(100%-9rem)]" x-data="{ open: false }" @keydown.escape.window="open = false">
                <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="true"
                        class="shrink-0 inline-flex items-center gap-2 h-10 px-3 rounded-full bg-[#000000]/60 text-[#ffffff] text-sm font-semibold backdrop-blur hover:bg-[#000000]/75 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h10"/></svg>
                    <span class="hidden sm:inline">{{ __('watch.choose_episode') }}</span>
                    <span class="sr-only sm:hidden">{{ __('watch.choose_episode') }}</span>
                </button>
                @if($episode)
                    <a href="{{ $feedUrl() }}" aria-label="{{ __('watch.all_episodes') }}"
                       class="min-w-0 inline-flex items-center gap-1.5 h-8 pl-3 pr-2 rounded-full bg-[#000000]/60 text-[#ffffff] text-xs font-bold backdrop-blur hover:bg-[#000000]/75 transition">
                        <span class="truncate">{{ __('watch.episode_chip', ['number' => $episode['number'], 'title' => $episode['title']]) }}</span>
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                <div x-show="open" x-cloak x-transition.opacity @click.outside="open = false"
                     class="absolute left-0 top-12 w-80 max-w-[calc(100vw-2.5rem)] max-h-[60vh] overflow-y-auto rounded-2xl bg-[#1e293b] p-2 shadow-2xl ring-1 ring-[#ffffff]/10">
                    <p class="px-3 pt-2 pb-1 text-xs font-extrabold uppercase tracking-widest text-[#94a3b8]">{{ __('watch.choose_episode') }}</p>
                    <a href="{{ $feedUrl() }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-[#334155] transition">
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-bold text-[#ffffff]">{{ __('watch.all_episodes') }}</span>
                            <span class="block text-xs text-[#94a3b8]">{{ __('watch.all_episodes_hint') }}</span>
                        </span>
                        @unless($episode)
                            <svg class="w-5 h-5 shrink-0 text-[#c4b5fd]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        @endunless
                    </a>
                    @foreach($episodes as $item)
                        <a href="{{ $feedUrl(['episode' => $item['id']]) }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-[#334155] transition">
                            <span class="shrink-0 px-1.5 py-0.5 rounded bg-[#0f172a] text-[10px] font-bold text-[#e2e8f0]">EP {{ $item['number'] }}</span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-bold text-[#ffffff] line-clamp-2">{{ $item['title'] }}</span>
                                <span class="block text-xs text-[#94a3b8]">{{ $multiCourse ? $item['course_code'].' · ' : '' }}{{ trans_choice('watch.clip_count', $item['count'], ['count' => $item['count']]) }}</span>
                            </span>
                            @if($item['new'])
                                <span class="shrink-0 px-2 py-0.5 rounded-full bg-[#14b8a6]/20 text-[11px] font-bold text-[#5eead4]">{{ __('watch.new') }}</span>
                            @elseif($item['watched'])
                                <span class="shrink-0 px-2 py-0.5 rounded-full bg-[#10b981]/20 text-[11px] font-bold text-[#6ee7b7]">{{ __('watch.watched') }}</span>
                            @endif
                            @if($episode && $episode['id'] === $item['id'])
                                <svg class="w-5 h-5 shrink-0 text-[#c4b5fd]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Previous / next for a mouse; touch swipes the feed. --}}
            <div class="hidden md:flex absolute right-4 top-1/2 -translate-y-1/2 z-20 flex-col gap-2">
                <button type="button" @click="go(-1)" :disabled="current === 0" aria-label="{{ __('watch.previous_clip') }}"
                        class="w-11 h-11 rounded-full bg-[#1e293b] text-[#ffffff] flex items-center justify-center hover:bg-[#334155] disabled:opacity-30 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                </button>
                <button type="button" @click="go(1)" :disabled="current >= {{ count($clips) - ($caughtUp ? 0 : 1) }}" aria-label="{{ __('watch.next_clip') }}"
                        class="w-11 h-11 rounded-full bg-[#1e293b] text-[#ffffff] flex items-center justify-center hover:bg-[#334155] disabled:opacity-30 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>

            <div x-ref="feed" class="h-[calc(100dvh-12rem)] lg:h-[calc(100dvh-8rem)] overflow-y-auto snap-y snap-mandatory overscroll-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach($clips as $index => $clip)
                    <section data-clip data-index="{{ $index }}" x-data="{ paused: true, keyPoint: false }"
                             class="relative h-full snap-start snap-always flex flex-col items-center px-3 pt-3 pb-4"
                             aria-label="{{ $clip['title'] }}">
                        <div class="flex-1 min-h-0 w-full flex items-center justify-center">
                            <div class="relative h-full max-w-full aspect-[9/16] rounded-2xl overflow-hidden bg-[#000000]">
                                <video src="{{ $clip['stream_url'] }}"
                                       @if($clip['poster_url']) poster="{{ $clip['poster_url'] }}" @endif
                                       class="absolute inset-0 w-full h-full object-contain cursor-pointer"
                                       playsinline loop muted preload="none"
                                       @play="paused = false" @pause="paused = true"
                                       @unless($clip['watched']) @timeupdate="watchProgress($el, @js(route('tenant.watch.clips.watched', [$tenant->slug, $clip['id']])))" @endunless
                                       @click="$el.paused ? $el.play() : $el.pause()"></video>
                                <span x-show="paused && current === {{ $index }}" x-cloak class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                    <span class="w-16 h-16 rounded-full bg-[#000000]/50 flex items-center justify-center">
                                        <svg class="w-8 h-8 text-[#ffffff] ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <div class="w-full max-w-[420px] pt-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="shrink-0 px-1.5 py-0.5 rounded bg-[#1e293b] text-[10px] font-bold text-[#e2e8f0]">{{ $clip['course']['code'] }}</span>
                                @if($clip['is_new'])
                                    <span class="shrink-0 px-2 py-0.5 rounded-full bg-[#14b8a6]/20 text-[11px] font-bold text-[#5eead4]">{{ __('watch.new') }}</span>
                                @elseif($clip['watched'])
                                    <span class="shrink-0 px-2 py-0.5 rounded-full bg-[#10b981]/20 text-[11px] font-bold text-[#6ee7b7]">{{ __('watch.watched') }}</span>
                                @endif
                                <p class="truncate text-xs text-[#94a3b8]">{{ __('watch.from_episode', ['number' => $clip['episode']['episode_number'], 'title' => $clip['episode']['title']]) }}</p>
                            </div>
                            <h3 class="mt-1.5 text-lg font-extrabold leading-tight text-[#ffffff] line-clamp-2">{{ $clip['title'] }}</h3>
                            @if($clip['topic'])
                                <p class="text-sm text-[#cbd5e1] truncate">{{ $clip['topic'] }}</p>
                            @endif
                            <div class="mt-3 grid {{ $clip['takeaway'] ? 'grid-cols-2' : 'grid-cols-1' }} gap-2.5">
                                <a href="{{ route('tenant.watch.episode', [$tenant->slug, $clip['episode']['id']]) }}"
                                   class="inline-flex items-center justify-center gap-2 h-11 rounded-xl bg-[#ffffff] text-[#020617] text-sm font-bold hover:bg-[#e2e8f0] transition">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    {{ __('watch.watch_episode') }}
                                </a>
                                @if($clip['takeaway'])
                                    <button type="button" @click="openKeyPoint($el)"
                                            class="inline-flex items-center justify-center gap-2 h-11 rounded-xl bg-[#1e293b] text-[#ffffff] text-sm font-bold hover:bg-[#334155] transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                        {{ __('watch.key_point') }}
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if($clip['takeaway'])
                            <div x-show="keyPoint" x-cloak x-transition.opacity
                                 class="absolute inset-0 z-30 flex items-end justify-center bg-[#000000]/60 p-3"
                                 @click.self="closeKeyPoint($el)" @keydown.escape.window="keyPoint && closeKeyPoint($el)">
                                <div class="w-full max-w-[420px] rounded-2xl bg-[#1e293b] p-5" role="dialog" aria-modal="true" aria-label="{{ __('watch.key_point') }}">
                                    <div class="flex items-center justify-between">
                                        <p class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-[#cbd5e1]">
                                            <svg class="w-4 h-4 text-[#e09a2b]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a7 7 0 00-4 12.74V17a1 1 0 001 1h6a1 1 0 001-1v-2.26A7 7 0 0012 2zm-2 18h4v1a1 1 0 01-1 1h-2a1 1 0 01-1-1v-1z"/></svg>
                                            {{ __('watch.key_point') }}
                                        </p>
                                        <button type="button" @click="closeKeyPoint($el)" class="text-[#94a3b8] hover:text-[#ffffff]" aria-label="{{ __('watch.close') }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    <p class="mt-3 text-lg font-bold leading-snug text-[#ffffff]">{{ $clip['takeaway'] }}</p>
                                    <p class="mt-2 text-xs text-[#94a3b8]">{{ $clip['title'] }}</p>
                                </div>
                            </div>
                        @endif
                    </section>
                @endforeach

                @if($caughtUp)
                    <section x-ref="end" data-index="{{ count($clips) }}" class="h-full snap-start snap-always flex items-center justify-center px-6">
                        <div class="max-w-sm text-center">
                            <svg class="mx-auto w-16 h-16 text-[#ffffff]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <h3 class="mt-4 text-2xl font-extrabold text-[#ffffff]">{{ __('watch.caught_up') }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-[#cbd5e1]">{{ __('watch.caught_up_message') }}</p>
                            @if($canWatchAgain)
                                <a href="{{ $feedUrl(['again' => 1]) }}"
                                   class="mt-6 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-[#ffffff] text-[#020617] text-sm font-bold hover:bg-[#e2e8f0] transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    {{ __('watch.watch_again') }}
                                </a>
                            @endif
                        </div>
                    </section>
                @endif
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            // Only the clip on screen plays; the rest pause and rewind. Sound is one switch for all.
            // Videos load only when on screen or next, so a long feed never opens every stream at once.
            function clipFeed(start, csrf) {
                return {
                    muted: true,
                    current: start,
                    watchSent: new Set(),
                    resumeAfterKeyPoint: false,
                    resumeOnShow: false,
                    sections() {
                        return [...this.$refs.feed.querySelectorAll('[data-clip]')];
                    },
                    videoOf(section) {
                        return section.querySelector('video');
                    },
                    warm(index) {
                        [index, index + 1].forEach((i) => {
                            const section = this.sections()[i];
                            const video = section && this.videoOf(section);
                            if (video && video.preload !== 'auto') {
                                video.preload = 'auto';
                                video.load();
                            }
                        });
                    },
                    init() {
                        if (!this.$refs.feed) return;
                        // A hidden browser tab keeps the clip paused until the student comes back.
                        document.addEventListener('visibilitychange', () => {
                            const section = this.sections()[this.current];
                            if (!section) return;
                            const video = this.videoOf(section);
                            if (document.hidden) {
                                this.resumeOnShow = !video.paused;
                                video.pause();
                            } else if (this.resumeOnShow) {
                                video.play().catch(() => {});
                            }
                        });
                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach((entry) => {
                                if (entry.target === this.$refs.end) {
                                    if (entry.isIntersecting && entry.intersectionRatio >= 0.6) this.current = Number(entry.target.dataset.index);
                                    return;
                                }
                                const video = this.videoOf(entry.target);
                                if (entry.isIntersecting && entry.intersectionRatio >= 0.6) {
                                    this.current = Number(entry.target.dataset.index);
                                    this.warm(this.current);
                                    video.muted = this.muted;
                                    video.play().catch(() => {});
                                } else if (!video.paused || video.currentTime > 0) {
                                    video.pause();
                                    video.currentTime = 0;
                                }
                            });
                        }, { root: this.$refs.feed, threshold: [0, 0.6] });
                        this.sections().forEach((section) => observer.observe(section));
                        if (this.$refs.end) observer.observe(this.$refs.end);
                        if (start > 0) {
                            this.$nextTick(() => this.sections()[start]?.scrollIntoView({ block: 'start' }));
                        }
                    },
                    go(delta) {
                        const target = this.sections()[this.current + delta] ?? (delta > 0 ? this.$refs.end : null);
                        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    },
                    toggleMute() {
                        this.muted = !this.muted;
                        this.sections().forEach((section) => { this.videoOf(section).muted = this.muted; });
                        const section = this.sections()[this.current];
                        if (section && !this.muted && this.videoOf(section).paused) this.videoOf(section).play().catch(() => {});
                    },
                    // Played to 90%, a clip is watched and the feed passes over it from the next visit.
                    watchProgress(video, url) {
                        if (this.watchSent.has(url) || !video.duration || video.currentTime < video.duration * 0.9) return;
                        this.watchSent.add(url);
                        fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
                            .then((res) => { if (!res.ok) this.watchSent.delete(url); })
                            .catch(() => this.watchSent.delete(url));
                    },
                    openKeyPoint(el) {
                        const section = el.closest('[data-clip]');
                        const video = this.videoOf(section);
                        this.resumeAfterKeyPoint = !video.paused;
                        video.pause();
                        Alpine.$data(section).keyPoint = true;
                    },
                    closeKeyPoint(el) {
                        const section = el.closest('[data-clip]');
                        Alpine.$data(section).keyPoint = false;
                        if (this.resumeAfterKeyPoint) this.videoOf(section).play().catch(() => {});
                    },
                };
            }
        </script>
    @endpush
</x-tenant-layout>
