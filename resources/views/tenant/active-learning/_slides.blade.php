@php $slides = $activity->slides; @endphp
@if(! empty($slides))
    <div x-data="{ slides: @js($slides), open: null,
                   show(i) { this.open = i },
                   prev() { this.open = (this.open + this.slides.length - 1) % this.slides.length },
                   next() { this.open = (this.open + 1) % this.slides.length } }"
         class="{{ $class ?? '' }}">
        <h5 class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">{{ __('active_learning.slides_to_teach') }}</h5>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5">
            @foreach($slides as $i => $slide)
                <button type="button" @click.stop="show({{ $i }})"
                        class="group text-left rounded-lg border border-slate-200 bg-[#fff] overflow-hidden hover:border-indigo-400 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    <img src="{{ $slide['url'] }}" alt="{{ __('active_learning.slide_n', ['n' => $slide['number']]) }}" loading="lazy" class="w-full aspect-video object-cover">
                    <div class="px-2 py-1.5 flex items-baseline gap-1.5">
                        <span class="text-[10px] font-bold text-indigo-600 flex-shrink-0">{{ $slide['number'] }}</span>
                        <span class="text-[11px] text-slate-600 truncate group-hover:text-slate-900">{{ $slide['title'] }}</span>
                    </div>
                </button>
            @endforeach
        </div>

        <template x-teleport="body">
            <div x-show="open !== null" x-cloak x-transition.opacity
                 class="fixed inset-0 z-[100] bg-slate-900/90 flex flex-col items-center justify-center p-4"
                 @click.self="open = null"
                 @keydown.escape.window="open = null"
                 @keydown.arrow-left.window="open !== null && prev()"
                 @keydown.arrow-right.window="open !== null && next()">
                <template x-if="open !== null">
                    <div class="w-full max-w-6xl">
                        <div class="flex items-center justify-between text-white mb-3 gap-3">
                            <p class="text-sm font-medium truncate">
                                <span x-text="'{{ __('active_learning.slide') }} ' + slides[open].number"></span>
                                <span class="text-white/60">·</span>
                                <span x-text="slides[open].title"></span>
                            </p>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <a :href="slides[open].url" target="_blank" rel="noopener" class="text-xs px-2.5 py-1.5 rounded-md bg-white/10 hover:bg-white/20">{{ __('active_learning.open_full_size') }}</a>
                                <button type="button" @click="open = null" class="p-1.5 rounded-md bg-white/10 hover:bg-white/20" aria-label="{{ __('active_learning.close') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="relative">
                            <img :src="slides[open].url" :alt="slides[open].title" class="w-full max-h-[80vh] object-contain rounded-lg bg-[#fff]">
                            <button type="button" x-show="slides.length > 1" @click="prev()" class="absolute left-2 top-1/2 -translate-y-1/2 p-2 rounded-full bg-slate-900/60 text-white hover:bg-slate-900/80" aria-label="{{ __('active_learning.previous') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button type="button" x-show="slides.length > 1" @click="next()" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 rounded-full bg-slate-900/60 text-white hover:bg-slate-900/80" aria-label="{{ __('active_learning.next') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                        <p class="text-center text-xs text-white/50 mt-2" x-text="(open + 1) + ' / ' + slides.length"></p>
                    </div>
                </template>
            </div>
        </template>
    </div>
@endif
