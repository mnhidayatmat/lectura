<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $game->title }} · {{ $session->label() }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600,700,800,900&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body { font-family: 'Figtree', sans-serif; } [x-cloak] { display: none !important; }</style>
</head>
{{-- Always dark for the projector; fixed colours so the Dimmed theme cannot remap them. --}}
<body class="antialiased bg-[#14112e] text-[#ffffff] min-h-screen">
    <main x-data="tenderProjector(@js($stateUrl))" x-init="poll()" class="min-h-screen flex flex-col items-center justify-center px-6 py-10 text-center">
        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[#a5b4fc]">{{ $game->title }}</p>
        <h1 class="mt-2 text-4xl sm:text-6xl font-black" x-text="state.label">{{ $session->label() }}</h1>

        <template x-if="state.status !== 'announced'">
            <div class="mt-10">
                <p class="text-xl text-[#cbd5e1]" x-show="state.status === 'setup'">{{ __('tender.proj_setup') }}</p>
                <p class="text-xl text-[#cbd5e1]" x-show="state.status === 'scoring'">
                    {{ __('tender.proj_scoring') }} <span class="font-bold text-[#ffffff]" x-text="state.judges_in + ' / ' + state.judges"></span>
                </p>
                <p class="text-xl text-[#cbd5e1]" x-show="state.status === 'reconcile'">{{ __('tender.proj_reconcile') }}</p>
                <p class="text-xl text-[#cbd5e1]" x-show="state.status === 'compiled'">{{ __('tender.proj_compiled') }}</p>
                <p class="mt-8 max-w-2xl mx-auto text-lg text-[#e2e8f0] whitespace-pre-line" x-show="state.change_order_note" x-text="state.change_order_note"></p>
            </div>
        </template>

        <template x-if="state.status === 'announced'">
            <div class="mt-10 w-full max-w-4xl">
                <p class="text-2xl text-[#cbd5e1]">{{ __('tender.proj_winner') }}</p>
                <p class="mt-2 text-6xl sm:text-8xl font-black text-[#fbbf24]" x-text="state.winner"></p>
                <div class="mt-12 grid grid-cols-2 gap-6">
                    <template x-for="c in state.totals" :key="c.name">
                        <div class="rounded-3xl border p-8" :class="c.won ? 'border-[#fbbf24] bg-[#fbbf24]/10' : 'border-[#475569]'">
                            <p class="text-xl font-semibold" x-text="c.name"></p>
                            <p class="mt-2 text-5xl font-black tabular-nums" x-text="c.mark === null ? '—' : Number(c.mark).toFixed(2)"></p>
                            <p class="text-sm text-[#94a3b8]">{{ __('tender.proj_out_of') }}</p>
                        </div>
                    </template>
                </div>
                <p class="mt-6 text-sm text-[#94a3b8]" x-show="state.tie_break" x-text="tieBreak(state.tie_break)"></p>
            </div>
        </template>
    </main>

    <script>
        function tenderProjector(url) {
            const tieBreaks = @js(collect(['compliance', 'defence', 'change_order', 'technical', 'sudden_death'])->mapWithKeys(fn ($k) => [$k => __('tender.tie_break_'.$k)]));
            return {
                state: { status: @js($session->status), label: @js($session->label()), totals: [] },
                tieBreak(key) { return tieBreaks[key] || ''; },
                async poll() {
                    try {
                        const res = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (res.ok) this.state = await res.json();
                    } catch (e) {}
                    setTimeout(() => this.poll(), 3000);
                },
            };
        }
    </script>
</body>
</html>
