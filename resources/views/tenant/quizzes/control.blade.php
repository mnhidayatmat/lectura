<x-tenant-layout>
    @php
        $tenantSlug = app('current_tenant')->slug;
        $joinHost = preg_replace('#^https?://#', '', route('tenant.quizzes.join', $tenantSlug));
    @endphp

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-bold text-amber-600 dark:text-amber-400">{{ $session->section?->course?->code }}</p>
                <div class="flex items-center gap-2 min-w-0">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white truncate">{{ $session->title }}</h2>
                    @unless($session->isLive())
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 flex-shrink-0">
                            {{ $session->hasNeverRun() ? 'Not run yet' : 'Ended' }}
                        </span>
                    @endunless
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($session->isLive())
                    <form method="POST" action="{{ route('tenant.quizzes.end', [$tenantSlug, $session]) }}" onsubmit="return confirm('End this quiz now? Scores are saved and students see their final result.')">
                        @csrf
                        <button class="px-4 py-2.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300 transition">End Quiz</button>
                    </form>
                @else
                    @unless($session->hasNeverRun())
                        <a href="{{ route('tenant.quizzes.results', [$tenantSlug, $session]) }}" class="px-4 py-2.5 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition">Results</a>
                    @endunless
                    <form method="POST" action="{{ route('tenant.quizzes.replay', [$tenantSlug, $session]) }}" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <button type="submit" :disabled="busy" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            {{ $session->hasNeverRun() ? 'Start quiz' : 'Run again' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    @unless($session->isLive())
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-8 text-center">
            @if($session->hasNeverRun())
                <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Not run yet</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">This is a master copy with {{ $board['question_total'] }} {{ Str::plural('question', $board['question_total']) }}. Press "Start quiz" to open a lobby with its own join code.</p>
            @else
                <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">This run has ended</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">See the results, or run it again as a new session with the same questions.</p>
            @endif
        </div>
    @else
        <div x-data="quizBoard({
                stateUrl: @js(route('tenant.quizzes.state', [$tenantSlug, $session])),
                actions: {
                    start: @js(route('tenant.quizzes.start', [$tenantSlug, $session])),
                    close: @js(route('tenant.quizzes.close', [$tenantSlug, $session])),
                    next: @js(route('tenant.quizzes.next', [$tenantSlug, $session])),
                    end: @js(route('tenant.quizzes.end', [$tenantSlug, $session])),
                },
                initial: @js($board),
                joinUrl: @js($joinUrl),
             })">
            <div x-ref="stage"
                 class="qb-stage relative overflow-hidden text-white flex flex-col select-none"
                 :class="fullscreen ? 'h-screen rounded-none' : 'min-h-[calc(100vh-12rem)] rounded-3xl'">

                {{-- Top bar --}}
                <div class="relative z-10 flex flex-wrap items-center gap-3 px-5 sm:px-8 pt-5">
                    <div class="flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-sm">
                        <span class="text-white/60">Join at</span>
                        <span class="font-semibold">{{ $joinHost }}</span>
                        <span class="text-white/40">·</span>
                        <span class="font-mono font-bold tracking-[0.2em] text-amber-300">{{ $session->join_code }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-sm text-white/70">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span x-text="s.participant_count"></span>
                    </div>
                    <button type="button" @click="toggleFullscreen()" class="ml-auto p-2 rounded-lg text-white/60 hover:text-white hover:bg-white/10 transition" :title="fullscreen ? 'Exit full screen (F)' : 'Full screen (F)'">
                        <svg x-show="!fullscreen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/></svg>
                        <svg x-show="fullscreen" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4v4H4M16 4v4h4M8 20v-4H4M16 20v-4h4"/></svg>
                    </button>
                </div>

                <div class="relative z-10 flex-1 flex flex-col px-5 sm:px-8 py-6 min-h-0">

                    {{-- LOBBY --}}
                    <template x-if="view === 'lobby'">
                        <div class="flex-1 grid lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] gap-8 items-start">
                            <div class="rounded-3xl bg-white text-slate-900 p-6 text-center shadow-2xl shadow-black/30">
                                <p class="text-sm font-medium text-slate-500">Go to</p>
                                <p class="text-lg font-bold text-slate-900 break-all">{{ $joinHost }}</p>
                                <p class="mt-4 text-sm font-medium text-slate-500">and enter the code</p>
                                <p class="mt-1 font-mono text-5xl font-black tracking-[0.18em] text-indigo-700">{{ $session->join_code }}</p>
                                <div class="mt-5 mx-auto w-44 h-44 [&_svg]:w-full [&_svg]:h-full" x-html="qrSvg"></div>
                                <p class="mt-4 text-xs text-slate-500">Lectura Go app: <span class="font-semibold text-slate-700">Live → Join with code</span></p>
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-end gap-3 mb-5">
                                    <p class="text-6xl font-black tabular-nums leading-none" x-text="s.participant_count"></p>
                                    <p class="text-xl text-white/70 pb-1" x-text="s.participant_count === 1 ? 'player joined' : 'players joined'"></p>
                                </div>
                                <div x-show="s.participants.length === 0" class="flex items-center gap-3 text-white/60 text-lg">
                                    <span class="flex gap-1">
                                        <span class="qb-dot w-2 h-2 rounded-full bg-white/60"></span>
                                        <span class="qb-dot w-2 h-2 rounded-full bg-white/60" style="animation-delay: .15s"></span>
                                        <span class="qb-dot w-2 h-2 rounded-full bg-white/60" style="animation-delay: .3s"></span>
                                    </span>
                                    Waiting for players to join…
                                </div>
                                <div class="flex flex-wrap gap-2.5 max-h-[52vh] overflow-y-auto pr-1">
                                    <template x-for="p in s.participants" :key="p.id">
                                        <span class="inline-flex items-center gap-2 rounded-full pl-1.5 pr-4 py-1.5 text-base font-semibold shadow-lg shadow-black/20"
                                              :class="freshIds.includes(p.id) ? 'qb-pop ring-2 ring-white' : ''"
                                              :style="`background:${chipColor(p.id)}`">
                                            <span class="w-7 h-7 rounded-full bg-white/25 flex items-center justify-center text-xs font-bold" x-text="initials(p.name)"></span>
                                            <span x-text="p.name"></span>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- ANSWERING --}}
                    <template x-if="view === 'answering' && s.question">
                        <div class="flex-1 flex flex-col gap-6 min-h-0">
                            <div class="flex items-center gap-4">
                                <span class="rounded-full bg-white/10 px-3 py-1 text-sm font-semibold" x-text="`Question ${s.question.number} of ${s.question_total}`"></span>
                                <div class="ml-auto flex items-center gap-5">
                                    <div class="text-right">
                                        <p class="text-3xl font-black tabular-nums leading-none"><span x-text="s.answered_count"></span><span class="text-white/40 text-xl"> / <span x-text="s.participant_count"></span></span></p>
                                        <p class="text-xs text-white/60 mt-1">answered</p>
                                    </div>
                                    <div class="relative w-20 h-20">
                                        <svg class="w-20 h-20 -rotate-90" viewBox="0 0 80 80">
                                            <circle cx="40" cy="40" r="34" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="8"/>
                                            <circle cx="40" cy="40" r="34" fill="none" stroke-width="8" stroke-linecap="round"
                                                    :stroke="remaining !== null && remaining <= 5 ? '#fb7185' : '#fbbf24'"
                                                    stroke-dasharray="213.6" :stroke-dashoffset="213.6 * (1 - timerFraction())"
                                                    style="transition: stroke-dashoffset .25s linear"/>
                                        </svg>
                                        <span class="absolute inset-0 flex items-center justify-center text-2xl font-black tabular-nums"
                                              :class="remaining !== null && remaining <= 5 ? 'text-rose-300' : ''"
                                              x-text="remaining ?? '–'"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex-1 flex flex-col gap-6 min-h-0" :class="isLong() ? 'lg:grid lg:grid-cols-2 lg:gap-10 lg:items-center' : ''">
                            <p class="font-bold whitespace-pre-line leading-snug max-w-5xl"
                               :class="isLong() ? 'text-lg sm:text-xl' : 'text-3xl sm:text-5xl'"
                               x-text="s.question.text"></p>

                            <div class="mt-auto grid gap-3" :class="isLong() ? 'lg:mt-0' : (s.question.options.length === 2 ? 'grid-cols-2' : 'sm:grid-cols-2')">
                                <template x-for="(opt, i) in s.question.options" :key="opt.id">
                                    <div class="flex items-center gap-4 rounded-2xl px-5 py-4 shadow-lg shadow-black/20" :style="`background:${colors[i % colors.length]}`">
                                        <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-lg font-black flex-shrink-0" x-text="opt.label"></span>
                                        <span class="text-lg sm:text-xl font-semibold leading-snug" x-text="opt.text"></span>
                                    </div>
                                </template>
                            </div>
                            </div>

                            <div class="h-1.5 rounded-full bg-white/10 overflow-hidden">
                                <div class="h-full bg-emerald-400 transition-all duration-500" :style="`width:${s.participant_count ? Math.min(100, s.answered_count / s.participant_count * 100) : 0}%`"></div>
                            </div>
                        </div>
                    </template>

                    {{-- REVEAL --}}
                    <template x-if="view === 'reveal' && s.question">
                        <div class="flex-1 flex flex-col gap-5 min-h-0">
                            <div class="flex items-center gap-3">
                                <span class="rounded-full bg-white/10 px-3 py-1 text-sm font-semibold" x-text="`Question ${s.question.number} of ${s.question_total}`"></span>
                                <span class="rounded-full bg-emerald-400/20 text-emerald-200 px-3 py-1 text-sm font-semibold"
                                      x-text="`${correctCount()} of ${s.answered_count} answered correctly`"></span>
                            </div>
                            <div class="flex flex-col gap-5" :class="isLong() ? 'lg:grid lg:grid-cols-2 lg:gap-10 lg:items-start' : ''">
                            <p class="font-bold whitespace-pre-line leading-snug max-w-5xl text-white/90" :class="isLong() ? 'text-lg sm:text-xl' : 'text-xl sm:text-2xl'" x-text="s.question.text"></p>

                            <div class="space-y-3 max-w-5xl w-full">
                                <template x-for="(opt, i) in s.question.options" :key="opt.id">
                                    <div class="flex items-center gap-4" :class="opt.is_correct ? '' : 'opacity-60'">
                                        <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg font-black flex-shrink-0"
                                              :class="opt.is_correct ? 'bg-emerald-400 text-emerald-950' : 'bg-white/15'" x-text="opt.label"></span>
                                        <div class="relative flex-1 h-14 rounded-xl bg-white/10 overflow-hidden">
                                            <div class="qb-grow absolute inset-y-0 left-0 rounded-xl"
                                                 :class="opt.is_correct ? 'bg-emerald-500' : 'bg-white/25'"
                                                 :style="`width:${(opt.count / maxCount()) * 100}%`"></div>
                                            <div class="relative h-full flex items-center gap-2 px-4">
                                                <span class="font-semibold truncate" x-text="opt.text"></span>
                                                <svg x-show="opt.is_correct" class="w-6 h-6 text-emerald-100 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                        </div>
                                        <span class="w-12 text-right text-2xl font-black tabular-nums" x-text="opt.count"></span>
                                    </div>
                                </template>
                            </div>
                            </div>

                            <div x-show="s.question.explanation" class="max-w-5xl rounded-2xl bg-white/10 px-5 py-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-amber-300 mb-1">Why</p>
                                <p class="text-base sm:text-lg text-white/90 leading-relaxed" x-text="s.question.explanation"></p>
                            </div>
                        </div>
                    </template>

                    {{-- LEADERBOARD --}}
                    <template x-if="view === 'leaderboard'">
                        <div class="flex-1 flex flex-col items-center min-h-0">
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300" x-text="`Leaderboard · after question ${s.question?.number} of ${s.question_total}`"></p>
                            <h3 class="qb-pop mt-2 text-3xl sm:text-5xl font-black text-center" x-text="headline()"></h3>

                            <div class="relative w-full max-w-3xl mt-8" :style="`height:${rows.length * rowHeight}px`">
                                <p x-show="rows.length === 0" class="text-center text-white/60 text-lg">Nobody has joined this quiz.</p>
                                <template x-for="row in rows" :key="row.participant_id">
                                    <div class="absolute inset-x-0 transition-transform duration-700 ease-[cubic-bezier(.2,.8,.2,1)]"
                                         :style="`transform:translateY(${row.pos * rowHeight}px); height:${rowHeight - 8}px`">
                                        <div class="relative h-full flex items-center gap-4 rounded-2xl bg-white/10 px-4 overflow-hidden">
                                            <div class="absolute inset-y-0 left-0 bg-white/10 transition-[width] duration-700" :style="`width:${row.shown / topPoints() * 100}%`"></div>
                                            <span class="relative w-10 h-10 rounded-full flex items-center justify-center text-lg font-black flex-shrink-0"
                                                  :class="row.rank === 1 ? 'bg-amber-300 text-amber-950' : (row.rank === 2 ? 'bg-slate-200 text-slate-800' : (row.rank === 3 ? 'bg-orange-300 text-orange-950' : 'bg-white/15'))"
                                                  x-text="row.rank"></span>
                                            <span class="relative flex-1 min-w-0 text-xl font-bold truncate" x-text="row.name"></span>
                                            <span class="relative w-12 text-sm font-bold text-center flex-shrink-0"
                                                  :class="movement(row) > 0 ? 'text-emerald-300' : (movement(row) < 0 ? 'text-rose-300' : 'text-white/30')"
                                                  x-text="row.revealGain ? (movement(row) > 0 ? `▲ ${movement(row)}` : (movement(row) < 0 ? `▼ ${-movement(row)}` : '–')) : ''"></span>
                                            <span class="relative w-20 text-right text-sm font-bold text-emerald-300 flex-shrink-0 transition-opacity duration-500"
                                                  :class="row.revealGain && row.gained > 0 ? 'opacity-100' : 'opacity-0'"
                                                  x-text="`+${row.gained}`"></span>
                                            <span class="relative w-24 text-right text-2xl font-black tabular-nums flex-shrink-0" x-text="row.shown.toLocaleString()"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- FINAL PODIUM --}}
                    <template x-if="view === 'finished'">
                        <div class="flex-1 flex flex-col items-center min-h-0">
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">Final results</p>
                            <h3 class="mt-2 text-3xl sm:text-5xl font-black text-center"
                                x-text="podiumStep >= 3 && s.leaderboard?.[0] ? `🏆 ${s.leaderboard[0].name} wins!` : 'And the winner is…'"></h3>

                            <div class="mt-auto w-full max-w-3xl grid grid-cols-3 items-end gap-3 sm:gap-5 pt-8">
                                <template x-for="(entry, slot) in podium()" :key="slot">
                                    <div class="flex flex-col items-center">
                                        <div class="flex flex-col items-center mb-3 transition-all duration-700"
                                             :class="entry && placeShown([2, 1, 3][slot]) ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                                            <span class="w-14 h-14 sm:w-20 sm:h-20 rounded-full flex items-center justify-center text-xl sm:text-2xl font-black shadow-xl ring-4 ring-white/20"
                                                  :style="`background:${entry ? chipColor(entry.participant_id) : 'transparent'}`"
                                                  x-text="entry ? initials(entry.name) : ''"></span>
                                            <span class="mt-2 text-base sm:text-xl font-bold text-center line-clamp-2" x-text="entry?.name"></span>
                                            <span class="text-sm sm:text-base text-white/70 tabular-nums" x-text="entry ? `${entry.points.toLocaleString()} pts` : ''"></span>
                                        </div>
                                        <div class="w-full rounded-t-2xl flex items-start justify-center pt-3 text-4xl sm:text-6xl font-black transition-all duration-700 origin-bottom"
                                             :class="[
                                                 ['h-32 sm:h-40 bg-gradient-to-b from-slate-200 to-slate-400 text-slate-700', 'h-44 sm:h-56 bg-gradient-to-b from-amber-200 to-amber-500 text-amber-900', 'h-24 sm:h-28 bg-gradient-to-b from-orange-200 to-orange-500 text-orange-900'][slot],
                                                 entry ? 'opacity-100' : 'opacity-20',
                                             ]"
                                             x-text="[2, 1, 3][slot]"></div>
                                    </div>
                                </template>
                            </div>

                            <div x-show="podiumStep >= 3 && (s.leaderboard || []).length > 3" x-transition.opacity.duration.700ms class="w-full max-w-3xl mt-6 grid sm:grid-cols-2 gap-2">
                                <template x-for="entry in (s.leaderboard || []).slice(3)" :key="entry.participant_id">
                                    <div class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-2.5">
                                        <span class="w-7 text-center font-bold text-white/60" x-text="entry.rank"></span>
                                        <span class="flex-1 truncate font-semibold" x-text="entry.name"></span>
                                        <span class="font-bold tabular-nums" x-text="entry.points.toLocaleString()"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- ENDED ELSEWHERE --}}
                    <template x-if="view === 'ended'">
                        <div class="flex-1 flex flex-col items-center justify-center text-center">
                            <h3 class="text-3xl font-black">This quiz has ended</h3>
                            <a href="{{ route('tenant.quizzes.results', [$tenantSlug, $session]) }}" class="mt-4 inline-flex rounded-xl bg-white text-indigo-800 px-5 py-2.5 font-semibold">See results</a>
                        </div>
                    </template>
                </div>

                {{-- Bottom bar --}}
                <div class="relative z-10 flex items-center gap-4 px-5 sm:px-8 pb-5">
                    <div class="flex items-center gap-1.5" x-show="s.question_total > 0">
                        <template x-for="n in s.question_total" :key="n">
                            <span class="h-1.5 rounded-full transition-all"
                                  :class="n === s.question?.number && view !== 'finished' ? 'w-6 bg-amber-300' : (n < (s.question?.number || 0) || view === 'finished' ? 'w-1.5 bg-white/70' : 'w-1.5 bg-white/20')"></span>
                        </template>
                    </div>
                    <p x-show="error" x-text="error" class="text-sm text-rose-300"></p>
                    <div class="ml-auto flex items-center gap-3">
                        <span class="hidden md:inline text-xs text-white/40">Space / → to continue · F for full screen</span>
                        <button type="button" x-show="view !== 'ended'" @click="primary()" :disabled="busy"
                                class="inline-flex items-center gap-2 rounded-2xl bg-white px-6 py-3 text-base font-bold text-indigo-900 shadow-xl shadow-black/30 hover:bg-amber-100 disabled:opacity-60 transition">
                            <span x-text="primaryLabel()"></span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Confetti --}}
                <div class="pointer-events-none absolute inset-0 z-20 overflow-hidden">
                    <template x-for="piece in confetti" :key="piece.id">
                        <span class="qb-confetti absolute top-0 rounded-sm"
                              :style="`left:${piece.left}%; width:${piece.size}px; height:${piece.size * 0.45}px; background:${piece.color}; animation-delay:${piece.delay}s; animation-duration:${piece.duration}s; --drift:${piece.drift}px; --spin:${piece.spin}deg`"></span>
                    </template>
                </div>
            </div>
        </div>
    @endunless

    @push('styles')
        <style>
            .qb-stage {
                background:
                    radial-gradient(1200px 600px at 10% -10%, rgba(124, 58, 237, .55), transparent 60%),
                    radial-gradient(900px 500px at 110% 110%, rgba(2, 132, 199, .45), transparent 60%),
                    #17123a;
            }
            .qb-pop { animation: qb-pop .5s cubic-bezier(.2, 1.4, .4, 1) both; }
            @keyframes qb-pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }
            .qb-dot { animation: qb-dot 1.2s ease-in-out infinite; }
            @keyframes qb-dot { 0%, 100% { opacity: .25; transform: translateY(0); } 50% { opacity: 1; transform: translateY(-4px); } }
            .qb-grow { animation: qb-grow .9s cubic-bezier(.2, .8, .2, 1) both; transform-origin: left; }
            @keyframes qb-grow { from { transform: scaleX(0); } to { transform: scaleX(1); } }
            .qb-confetti { animation-name: qb-fall; animation-timing-function: cubic-bezier(.25, .6, .4, 1); animation-fill-mode: both; }
            @keyframes qb-fall {
                from { transform: translate3d(0, -20px, 0) rotate(0); opacity: 1; }
                to { transform: translate3d(var(--drift), 105vh, 0) rotate(var(--spin)); opacity: .9; }
            }
            @media (prefers-reduced-motion: reduce) {
                .qb-pop, .qb-grow, .qb-dot { animation: none; }
            }
        </style>
    @endpush
</x-tenant-layout>
