{{-- Portrait clip cards; each opens the clip feed on that clip, with New and watched marks. Needs $clips and $tenant. --}}
@if(count($clips))
    <section aria-label="{{ __('watch.clips') }}">
        <div class="flex items-baseline justify-between mb-3">
            <h3 class="text-base font-bold text-white">{{ __('watch.clips') }}</h3>
            <a href="{{ route('tenant.watch.clips', $tenant->slug) }}" class="text-xs font-semibold text-violet-300 hover:text-violet-200">{{ __('watch.see_all') }}</a>
        </div>
        <div class="flex gap-2.5 overflow-x-auto snap-x pb-2 -mx-1 px-1 [scrollbar-width:thin]">
            @foreach($clips as $clip)
                <a href="{{ route('tenant.watch.clips', [$tenant->slug, 'clip' => $clip['id']]) }}"
                   class="group snap-start shrink-0 w-28 sm:w-32 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 rounded-xl">
                    <div class="relative aspect-[9/16] rounded-xl overflow-hidden bg-slate-800 ring-1 ring-white/5">
                        @if($clip['poster_url'])
                            <img src="{{ $clip['poster_url'] }}" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover transition duration-300 group-hover:scale-105">
                        @else
                            <span class="absolute inset-0 bg-gradient-to-br from-violet-800 via-slate-800 to-slate-950"></span>
                        @endif
                        <span class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-transparent to-transparent"></span>
                        <svg class="absolute top-2 left-2 w-5 h-5 text-[#ffffff] drop-shadow" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        @if($clip['is_new'])
                            <span class="absolute top-2 right-2 px-1.5 py-0.5 rounded-full bg-[#0f766e] text-[10px] font-bold text-[#ffffff]">{{ __('watch.new') }}</span>
                        @elseif($clip['watched'])
                            <svg class="absolute top-2 right-2 w-5 h-5 text-[#ffffff] drop-shadow" fill="currentColor" viewBox="0 0 24 24" role="img" aria-label="{{ __('watch.watched') }}"><path fill-rule="evenodd" d="M12 2a10 10 0 100 20 10 10 0 000-20zm4.7 7.7a1 1 0 00-1.4-1.4L11 12.6l-2.3-2.3a1 1 0 00-1.4 1.4l3 3a1 1 0 001.4 0l5-5z" clip-rule="evenodd"/></svg>
                        @endif
                        <span class="absolute left-2 right-2 bottom-2">
                            <span class="block text-[10px] font-bold text-[#cbd5e1]">EP {{ $clip['episode']['episode_number'] }}</span>
                            <span class="block text-xs font-bold leading-tight text-[#ffffff] line-clamp-3">{{ $clip['title'] }}</span>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
