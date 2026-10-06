<x-tenant-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Watch</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Animated episodes from your courses</p>
        </div>
    </x-slot>

    @php extract(\App\Support\WatchView::helpers($tenant)); @endphp
    @php $featured = $home['featured']; @endphp

    <div class="rounded-3xl bg-slate-950 text-slate-100 p-4 sm:p-6 space-y-8 shadow-xl">
        @if(collect($home['series'])->isEmpty())
            <div class="py-16 text-center">
                <div class="mx-auto w-14 h-14 rounded-2xl bg-slate-800 flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-[#94a3b8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.55-2.28A1 1 0 0121 8.62v6.76a1 1 0 01-1.45.9L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-white">Nothing to watch yet</h3>
                <p class="text-sm text-[#94a3b8] mt-1">Episodes appear here when your lecturers release them.</p>
            </div>
        @endif

        @if($featured)
            @php $due = $deadline($featured['required_by']); @endphp
            <section class="relative overflow-hidden rounded-2xl bg-slate-900 min-h-[16rem] sm:min-h-[22rem] flex items-end" aria-label="Featured episode">
                @if($featured['poster_url'])
                    <img src="{{ $featured['poster_url'] }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                @else
                    <span class="absolute inset-0 bg-gradient-to-br from-teal-700 via-slate-800 to-slate-950"></span>
                @endif
                <span class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-slate-950/10"></span>
                <span class="absolute inset-0 bg-gradient-to-r from-slate-950/80 via-transparent to-transparent"></span>
                <div class="relative p-5 sm:p-8 max-w-xl">
                    <p class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-amber-400">{{ $featured['series_title'] }}</p>
                    <h3 class="mt-1 text-2xl sm:text-4xl font-extrabold tracking-tight text-white text-balance">{{ $featured['title'] }}</h3>
                    <p class="mt-2 text-sm text-[#cbd5e1]">
                        EP {{ $featured['episode_number'] }}
                        @if($featured['duration_seconds']) · {{ $clock($featured['duration_seconds']) }} @endif
                        · {{ $featured['course']['code'] }}
                        @if($featured['week_number']) · Week {{ $featured['week_number'] }} @endif
                    </p>
                    @if($featured['synopsis'])
                        <p class="mt-2 text-sm text-[#cbd5e1] line-clamp-2">{{ $featured['synopsis'] }}</p>
                    @endif
                    @if($due && ! ($featured['progress']['completed'] ?? false))
                        <p class="mt-3 inline-flex px-2 py-0.5 rounded-md text-xs font-bold {{ $due === 'Overdue' ? 'bg-red-500 text-white' : 'bg-amber-400 text-amber-950' }}">{{ $due }}</p>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ $episodeUrl($featured) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#ffffff] text-slate-950 text-sm font-bold hover:bg-[#e2e8f0] transition">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            {{ $featured['progress'] && ! $featured['progress']['completed'] ? 'Resume' : 'Play' }}
                        </a>
                        <a href="{{ route('tenant.watch.series', [$tenant->slug, $featured['series_id']]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white/15 text-white text-sm font-bold hover:bg-white/25 transition">
                            All episodes
                        </a>
                    </div>
                </div>
            </section>
        @endif

        @include('tenant.watch._row', ['title' => 'Continue watching', 'items' => $home['continue_watching']])

        @php
            $missedWeeks = collect($home['because_you_missed'])->pluck('week_number')->unique();
            $missedTitle = $missedWeeks->count() === 1 ? 'Because you missed Week '.$missedWeeks->first() : 'Because you missed class';
            $missedItems = collect($home['because_you_missed'])->map(fn ($row) => [
                'ep' => $row['episode'],
                'badge' => 'Absent '.($row['missed_on'] ? \Illuminate\Support\Carbon::parse($row['missed_on'])->timezone($tz)->format('j M') : ''),
            ])->all();
        @endphp
        @include('tenant.watch._row', ['title' => $missedTitle, 'items' => $missedItems])

        @include('tenant.watch._row', ['title' => 'New episodes', 'items' => $home['new_episodes']])

        @foreach($home['series'] as $series)
            @include('tenant.watch._row', [
                'title' => $series['title'].' · '.$series['course']['code'],
                'items' => $series['episodes'],
                'link' => route('tenant.watch.series', [$tenant->slug, $series['id']]),
                'linkLabel' => $series['completed_count'].' of '.$series['episodes_count'].' watched',
            ])
        @endforeach
    </div>
</x-tenant-layout>
