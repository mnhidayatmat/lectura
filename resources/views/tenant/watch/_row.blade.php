@if(count($items))
    <section aria-label="{{ $title }}">
        <div class="flex items-baseline justify-between mb-3">
            <h3 class="text-base font-bold text-white">{{ $title }}</h3>
            @isset($link)<a href="{{ $link }}" class="text-xs font-semibold text-violet-300 hover:text-violet-200">{{ $linkLabel ?? 'See all' }}</a>@endisset
        </div>
        <div class="flex gap-3 overflow-x-auto snap-x pb-2 -mx-1 px-1 [scrollbar-width:thin]">
            @foreach($items as $item)
                @include('tenant.watch._card', is_array($item) && isset($item['ep']) ? $item : ['ep' => $item])
            @endforeach
        </div>
    </section>
@endif
