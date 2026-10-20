<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-bold text-amber-600 dark:text-amber-400">{{ $session->section?->course?->code }}</p>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white truncate">{{ $session->title }}</h2>
            </div>
        </div>
    </x-slot>

    <div class="max-w-xl mx-auto"
         x-data="quizPlayer({
            stateUrl: @js(route('tenant.quizzes.student-state', [app('current_tenant')->slug, $session])),
            respondUrl: @js(route('tenant.quizzes.respond', [app('current_tenant')->slug, $session])),
            nicknameUrl: @js(route('tenant.quizzes.nickname', [app('current_tenant')->slug, $session])),
            initialName: @js($participant->display_name),
            anonymous: @js((bool) $session->is_anonymous),
         })">
        <div class="qp-stage rounded-3xl text-white overflow-hidden shadow-xl min-h-[70vh] flex flex-col">

            {{-- Status strip --}}
            <div class="flex items-center gap-2 px-5 pt-5 text-sm">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 font-semibold truncate max-w-[55%]">
                    <span class="w-6 h-6 -ml-1.5 rounded-full bg-white/20 flex items-center justify-center text-[10px] font-bold" x-text="(savedName || '?').trim().charAt(0).toUpperCase()"></span>
                    <span class="truncate" x-text="savedName"></span>
                </span>
                <span class="ml-auto inline-flex items-center gap-1 rounded-full bg-amber-300/15 text-amber-200 px-3 py-1 font-bold tabular-nums">
                    <span x-text="(me.points || 0).toLocaleString()"></span> pts
                </span>
            </div>

            <div class="flex-1 flex flex-col px-5 py-6">
                {{-- Loading --}}
                <div x-show="phase === 'loading'" class="flex-1 flex items-center justify-center">
                    <div class="w-10 h-10 rounded-full border-4 border-white/20 border-t-white animate-spin"></div>
                </div>

                {{-- LOBBY --}}
                <div x-show="phase === 'lobby'" x-cloak class="flex-1 flex flex-col items-center justify-center text-center">
                    <div class="qp-pulse w-20 h-20 rounded-full bg-white/10 flex items-center justify-center mb-5">
                        <svg class="w-10 h-10 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 class="text-2xl font-black">You're in!</h3>
                    <p class="mt-1 text-white/70">See your name on the screen? The quiz starts soon.</p>

                    <template x-if="!anonymous">
                        <form @submit.prevent="saveName()" class="mt-6 w-full max-w-xs">
                            <label for="qp-name" class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-2">Your name on the board</label>
                            <div class="flex gap-2">
                                <input id="qp-name" type="text" x-model="nickname" @focus="editingName = true" maxlength="30" autocomplete="nickname"
                                       class="flex-1 min-w-0 rounded-xl border-0 bg-white text-slate-900 px-4 py-3 text-base font-semibold focus:ring-4 focus:ring-amber-300/50" placeholder="Name or nickname">
                                <button type="submit" :disabled="savingName || !nickname.trim() || nickname.trim() === savedName"
                                        class="rounded-xl bg-amber-300 text-amber-950 px-4 font-bold disabled:opacity-40 transition">Save</button>
                            </div>
                            <p x-show="nameError" x-text="nameError" class="mt-2 text-sm text-rose-300"></p>
                        </form>
                    </template>

                    <p class="mt-8 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-semibold">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span x-text="`${s?.participant_count || 0} joined`"></span>
                    </p>
                </div>

                {{-- ANSWERING --}}
                <div x-show="phase === 'answering' && question" x-cloak class="flex-1 flex flex-col">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-white/70" x-text="question ? `Question ${question.number} of ${question.total}` : ''"></span>
                        <span class="font-black tabular-nums text-lg" :class="remaining !== null && remaining <= 5 ? 'text-rose-300' : 'text-amber-300'" x-text="remaining !== null ? `${remaining}s` : ''"></span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-white/10 overflow-hidden">
                        <div class="h-full rounded-full transition-[width] duration-300 ease-linear" :class="remaining !== null && remaining <= 5 ? 'bg-rose-400' : 'bg-amber-300'" :style="`width:${timerFraction() * 100}%`"></div>
                    </div>

                    <p class="mt-5 text-lg sm:text-xl font-bold leading-snug whitespace-pre-line" x-text="question?.text"></p>

                    <div class="mt-5 grid gap-3" :class="question?.options?.length === 2 ? 'grid-cols-2' : ''">
                        <template x-for="(opt, i) in question?.options || []" :key="opt.id">
                            <button type="button" @click="answer(opt.id)" :disabled="locked || pendingId !== null || remaining === 0"
                                    class="relative flex items-center gap-3 rounded-2xl px-4 py-4 text-left shadow-lg shadow-black/20 transition active:scale-[.98]"
                                    :class="selectedId === null ? '' : (selectedId === opt.id ? 'ring-4 ring-white scale-[1.02]' : 'opacity-30')"
                                    :style="`background:${colors[i % colors.length]}`">
                                <span class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center font-black flex-shrink-0" x-text="opt.label"></span>
                                <span class="font-semibold leading-snug" x-text="opt.text"></span>
                            </button>
                        </template>
                    </div>

                    <div class="mt-auto pt-5 text-center text-sm">
                        <p x-show="locked" class="rounded-2xl bg-white/10 px-4 py-3 font-semibold">Answer locked in! Look at the screen…</p>
                        <p x-show="!locked && remaining === 0" class="rounded-2xl bg-white/10 px-4 py-3 font-semibold">Time's up!</p>
                        <p x-show="!locked && remaining !== 0 && pendingId === null" class="text-white/60">Faster correct answers score more points.</p>
                        <p x-show="submitError" x-text="submitError" class="mt-2 text-rose-300"></p>
                    </div>
                </div>

                {{-- REVEAL --}}
                <div x-show="phase === 'reveal'" x-cloak class="flex-1 flex flex-col">
                    <div class="qp-pop rounded-3xl px-5 py-6 text-center"
                         :class="me.is_correct === true ? 'bg-emerald-500' : (me.is_correct === false ? 'bg-rose-500' : 'bg-white/10')">
                        <p class="text-3xl font-black" x-text="me.is_correct === true ? 'Correct!' : (me.is_correct === false ? 'Not this time' : 'Time\'s up!')"></p>
                        <p class="mt-1 text-lg font-bold" x-text="me.is_correct === true ? `+${(me.points_gained || 0).toLocaleString()} pts` : (me.is_correct === false ? 'No points this round' : 'No answer recorded')"></p>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-center">
                        <div class="rounded-2xl bg-white/10 py-4">
                            <p class="text-3xl font-black tabular-nums" x-text="(me.points || 0).toLocaleString()"></p>
                            <p class="text-xs text-white/60 mt-1">Total points</p>
                        </div>
                        <div class="rounded-2xl bg-white/10 py-4">
                            <p class="text-3xl font-black">
                                <span x-text="rankText()"></span>
                                <span x-show="movement() > 0" class="text-base text-emerald-300" x-text="`▲${movement()}`"></span>
                                <span x-show="movement() < 0" class="text-base text-rose-300" x-text="`▼${-movement()}`"></span>
                            </p>
                            <p class="text-xs text-white/60 mt-1" x-text="`of ${s?.participant_count || 0}`"></p>
                        </div>
                    </div>

                    <div x-show="question" class="mt-4 rounded-2xl bg-white/10 p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-white/50">Correct answer</p>
                        <template x-for="opt in (question?.options || []).filter(o => o.is_correct)" :key="opt.id">
                            <p class="mt-1 font-semibold"><span class="text-emerald-300" x-text="opt.label + '.'"></span> <span x-text="opt.text"></span></p>
                        </template>
                        <p x-show="question?.explanation" class="mt-2 text-sm text-white/75 leading-relaxed" x-text="question?.explanation"></p>
                    </div>

                    <p class="mt-auto pt-5 text-center text-sm text-white/50 animate-pulse">Waiting for the next question…</p>
                </div>

                {{-- FINISHED --}}
                <div x-show="phase === 'finished'" x-cloak class="flex-1 flex flex-col items-center text-center">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">Final results</p>
                    <p class="mt-3 text-6xl font-black" x-text="me.rank === 1 ? '🏆' : (me.rank === 2 ? '🥈' : (me.rank === 3 ? '🥉' : '🎉'))"></p>
                    <p class="mt-3 text-3xl font-black" x-text="me.rank ? `You finished #${me.rank}` : 'Quiz complete!'"></p>
                    <p class="mt-1 text-white/70" x-text="`${(me.points || 0).toLocaleString()} points · ${s?.participant_count || 0} players`"></p>

                    <div class="mt-6 w-full space-y-2">
                        <template x-for="entry in s?.leaderboard || []" :key="entry.rank + entry.name">
                            <div class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-left" :class="entry.is_me ? 'bg-amber-300 text-amber-950' : 'bg-white/10'">
                                <span class="w-7 text-center font-black" x-text="entry.rank <= 3 ? ['🥇','🥈','🥉'][entry.rank - 1] : entry.rank"></span>
                                <span class="flex-1 truncate font-semibold" x-text="entry.name + (entry.is_me ? ' (you)' : '')"></span>
                                <span class="font-bold tabular-nums" x-text="entry.points.toLocaleString()"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            .qp-stage {
                background:
                    radial-gradient(600px 400px at 0% 0%, rgba(124, 58, 237, .55), transparent 60%),
                    radial-gradient(500px 400px at 100% 100%, rgba(2, 132, 199, .45), transparent 60%),
                    #17123a;
            }
            .qp-pop { animation: qp-pop .45s cubic-bezier(.2, 1.4, .4, 1) both; }
            @keyframes qp-pop { from { transform: scale(.85); opacity: 0; } to { transform: scale(1); opacity: 1; } }
            .qp-pulse { animation: qp-pulse 2s ease-in-out infinite; }
            @keyframes qp-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(252, 211, 77, .35); } 50% { box-shadow: 0 0 0 18px rgba(252, 211, 77, 0); } }
            @media (prefers-reduced-motion: reduce) { .qp-pop, .qp-pulse { animation: none; } }
        </style>
    @endpush
</x-tenant-layout>
