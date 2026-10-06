{{-- One episode tile in a horizontal row. Needs $ep (episode fragment) and the _helpers closures. --}}
@php
    $progress = $ep['progress'];
    $due = $deadline($ep['required_by']);
    $checks = $ep['quick_checks'] ?? ['total' => 0, 'answered' => 0, 'correct' => 0];
@endphp
<a href="{{ $episodeUrl($ep) }}" class="group snap-start shrink-0 w-56 sm:w-64 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 rounded-xl">
    <div class="relative aspect-video rounded-xl overflow-hidden bg-slate-800 ring-1 ring-white/5">
        @if($ep['poster_url'])
            <img src="{{ $ep['poster_url'] }}" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover transition duration-300 group-hover:scale-105 {{ $ep['is_available'] ? '' : 'opacity-40' }}">
        @else
            <span class="absolute inset-0 bg-gradient-to-br from-teal-700 via-slate-800 to-slate-900"></span>
            <span class="absolute left-3 bottom-1 text-5xl font-extrabold text-white/90 tracking-tight">{{ $ep['episode_number'] }}</span>
        @endif
        <span class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-transparent"></span>
        <span class="absolute top-2 left-2 flex flex-wrap gap-1">
            @if(isset($badge))
                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-red-500/20 text-red-300">{{ $badge }}</span>
            @endif
            @if(! $ep['is_available'])
                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-slate-900/80 text-[#cbd5e1]">Locked</span>
            @elseif($ep['is_new'])
                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-teal-400 text-teal-950">New</span>
            @endif
            @if($due && ! ($progress['completed'] ?? false))
                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide {{ $due === 'Overdue' ? 'bg-red-500/90 text-white' : 'bg-amber-400/90 text-amber-950' }}">{{ $due }}</span>
            @endif
        </span>
        @if($ep['duration_seconds'])
            <span class="absolute right-2 bottom-2 px-1.5 py-0.5 rounded bg-slate-950/80 text-[11px] font-semibold text-white tabular-nums">
                {{ $progress && ! $progress['completed'] && $ep['duration_seconds'] ? $clock(max(0, $ep['duration_seconds'] - $progress['position_seconds'])).' left' : $clock($ep['duration_seconds']) }}
            </span>
        @endif
        @if($ep['is_available'])
            <span class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                <span class="w-11 h-11 rounded-full bg-[#fffffff2] flex items-center justify-center shadow-lg"><svg class="w-5 h-5 text-slate-900 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
            </span>
        @endif
        @if($progress)
            <span class="absolute left-0 right-0 bottom-0 h-1 bg-white/20"><span class="block h-full bg-violet-400" style="width: {{ $progress['watched_percent'] }}%"></span></span>
        @endif
    </div>
    <p class="mt-2 text-sm font-semibold text-slate-100 leading-snug line-clamp-1">EP {{ $ep['episode_number'] }} · {{ $ep['title'] }}</p>
    <p class="text-xs text-[#94a3b8] line-clamp-1">
        {{ $subtitle ?? trim(($ep['course']['code'] ?? '').($ep['week_number'] ? ' · Week '.$ep['week_number'] : '')) }}
        @if($checks['answered'] > $checks['correct']) · <span class="text-amber-300">Check to redo</span>
        @elseif($checks['total'] && $checks['correct'] === $checks['total']) · <span class="text-emerald-300">Checks {{ $checks['correct'] }}/{{ $checks['total'] }}</span>
        @endif
    </p>
</a>
