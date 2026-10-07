<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.watch.index', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition" aria-label="Back to Watch">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $series['title'] }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $series['course']['code'] }} {{ $series['course']['title'] }}</p>
            </div>
        </div>
    </x-slot>

    @php extract(\App\Support\WatchView::helpers($tenant)); @endphp
    @php
        $upNext = $series['up_next'];
        $watchedPct = $series['episodes_count'] ? round($series['completed_count'] / $series['episodes_count'] * 100) : 0;
    @endphp

    <div class="rounded-3xl bg-slate-950 text-slate-100 overflow-hidden shadow-xl">
        <section class="relative min-h-[14rem] sm:min-h-[18rem] flex items-end">
            @if($series['cover_url'])
                <img src="{{ $series['cover_url'] }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            @elseif($upNext && $upNext['poster_url'])
                <img src="{{ $upNext['poster_url'] }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            @else
                <span class="absolute inset-0 bg-gradient-to-br from-teal-700 via-slate-800 to-slate-950"></span>
            @endif
            <span class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-slate-950/10"></span>
            <div class="relative p-5 sm:p-8 max-w-2xl">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-amber-400">Series · {{ $series['episodes_count'] }} {{ Str::plural('episode', $series['episodes_count']) }}</p>
                <h3 class="mt-1 text-2xl sm:text-4xl font-extrabold tracking-tight text-white text-balance">{{ $series['title'] }}</h3>
                @if($series['tagline'])<p class="mt-1 text-sm text-[#cbd5e1]">{{ $series['tagline'] }}</p>@endif
                <p class="mt-1 text-xs text-[#94a3b8]">{{ $series['course']['code'] }} · {{ $series['lecturer_name'] }} @if($series['current_week']) · Week {{ $series['current_week'] }} now @endif</p>
            </div>
        </section>

        <div class="px-5 sm:px-8 pb-6 space-y-6">
            <div class="flex flex-wrap items-center gap-4">
                @if($upNext)
                    <a href="{{ route('tenant.watch.episode', [$tenant->slug, $upNext['id']]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-violet-300 text-slate-950 text-sm font-bold hover:bg-violet-200 transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        @if($upNext['progress'] && ! $upNext['progress']['completed'])
                            Resume EP {{ $upNext['episode_number'] }} @if($upNext['duration_seconds']) · {{ $clock(max(0, $upNext['duration_seconds'] - $upNext['progress']['position_seconds'])) }} left @endif
                        @else
                            Play EP {{ $upNext['episode_number'] }}
                        @endif
                    </a>
                @endif
                <div class="flex items-center gap-3 flex-1 min-w-[12rem] text-xs text-[#cbd5e1]">
                    <span class="tabular-nums">{{ $series['completed_count'] }} of {{ $series['episodes_count'] }} watched</span>
                    <span class="flex-1 h-1.5 rounded-full bg-slate-800 overflow-hidden"><span class="block h-full rounded-full bg-violet-400" style="width: {{ $watchedPct }}%"></span></span>
                </div>
            </div>

            <ol class="divide-y divide-white/5">
                @foreach($series['episodes'] as $ep)
                    @php
                        $progress = $ep['progress'];
                        $due = $deadline($ep['required_by']);
                        $checks = $ep['quick_checks'];
                    @endphp
                    <li>
                        <a href="{{ $episodeUrl($ep) }}" class="group grid grid-cols-[8rem_1fr] sm:grid-cols-[11rem_1fr] gap-4 py-4 {{ $ep['is_available'] ? '' : 'pointer-events-none' }}" @unless($ep['is_available']) aria-disabled="true" @endunless>
                            <div class="relative aspect-video rounded-lg overflow-hidden bg-slate-800">
                                @if($ep['poster_url'])
                                    <img src="{{ $ep['poster_url'] }}" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover {{ $ep['is_available'] ? '' : 'opacity-40' }}">
                                @else
                                    <span class="absolute inset-0 bg-gradient-to-br from-teal-700 to-slate-900"></span>
                                    <span class="absolute left-2 bottom-0.5 text-3xl font-extrabold text-white/90">{{ $ep['episode_number'] }}</span>
                                @endif
                                @if($ep['duration_seconds'])<span class="absolute right-1.5 bottom-1.5 px-1 rounded bg-slate-950/80 text-[10px] font-semibold text-white tabular-nums">{{ $clock($ep['duration_seconds']) }}</span>@endif
                                @if($progress)<span class="absolute left-0 right-0 bottom-0 h-1 bg-white/20"><span class="block h-full bg-violet-400" style="width: {{ $progress['watched_percent'] }}%"></span></span>@endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold {{ $ep['is_available'] ? 'text-white group-hover:text-violet-200' : 'text-[#64748b]' }}">{{ $ep['episode_number'] }}. {{ $ep['title'] }}</p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-[#94a3b8]">
                                    @if($ep['week_number'])<span>Week {{ $ep['week_number'] }}</span>@endif
                                    @if(! $ep['is_available'])
                                        <span class="px-1.5 py-0.5 rounded bg-slate-800 text-[#cbd5e1] font-semibold">{{ $ep['available_at'] ? 'Unlocks '.\Illuminate\Support\Carbon::parse($ep['available_at'])->timezone($tz)->format('D j M, g:i A') : 'Coming soon' }}</span>
                                    @elseif($progress && $progress['completed'])
                                        <span class="text-emerald-300 font-semibold">Watched</span>
                                    @elseif($progress)
                                        <span>{{ $progress['watched_percent'] }}%</span>
                                    @elseif($ep['is_new'])
                                        <span class="px-1.5 py-0.5 rounded bg-teal-400 text-teal-950 font-bold">New</span>
                                    @endif
                                    @if($due && ! ($progress['completed'] ?? false))
                                        <span class="px-1.5 py-0.5 rounded font-bold {{ $due === 'Overdue' ? 'bg-red-500 text-white' : 'bg-amber-400 text-amber-950' }}">{{ $due }}</span>
                                    @endif
                                    @if($checks['answered'] > $checks['correct'])
                                        <span class="text-amber-300 font-semibold">Check to redo</span>
                                    @elseif($checks['total'] && $checks['correct'] === $checks['total'])
                                        <span class="text-emerald-300">Checks {{ $checks['correct'] }}/{{ $checks['total'] }}</span>
                                    @endif
                                </p>
                                @if($ep['synopsis'])<p class="mt-1 text-sm text-[#cbd5e1] line-clamp-2">{{ $ep['synopsis'] }}</p>@endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ol>

            @if(count($series['learning_outcomes']))
                <section class="rounded-2xl bg-slate-900 p-5">
                    <h3 class="text-sm font-bold text-white mb-3">What you'll learn</h3>
                    <ul class="space-y-2 text-sm text-[#cbd5e1]">
                        @foreach($series['learning_outcomes'] as $clo)
                            <li class="flex gap-3"><span class="shrink-0 font-bold text-violet-300">{{ $clo['code'] }}</span><span>{{ $clo['description'] }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-tenant-layout>
