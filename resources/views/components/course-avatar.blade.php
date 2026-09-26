@props(['course', 'size' => 'md'])

@php
    $accent = \App\View\CourseAccent::for($course);
    $sizes = [
        'xs' => ['box' => 'w-7 h-7 rounded-lg', 'code' => 'text-[10px]', 'num' => null],
        'sm' => ['box' => 'w-9 h-9 rounded-xl', 'code' => 'text-[11px]', 'num' => null],
        'md' => ['box' => 'w-11 h-11 rounded-xl', 'code' => 'text-xs', 'num' => null],
        'lg' => ['box' => 'w-16 h-16 rounded-2xl', 'code' => 'text-base', 'num' => 'text-[10px]'],
        'xl' => ['box' => 'w-full aspect-square rounded-2xl', 'code' => 'text-3xl sm:text-4xl', 'num' => 'text-sm'],
    ][$size];
    $number = \App\View\CourseAccent::number($course);
@endphp

<div {{ $attributes->class(["relative flex-shrink-0 overflow-hidden bg-gradient-to-br {$accent['gradient']} {$sizes['box']} flex flex-col items-center justify-center text-white shadow-sm"]) }}>
    @if($size === 'xl')
        <div class="absolute -top-6 -right-6 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-8 -left-4 w-20 h-20 rounded-full bg-black/10"></div>
    @endif
    <span class="relative font-extrabold tracking-wide leading-none {{ $sizes['code'] }}">{{ \App\View\CourseAccent::monogram($course) }}</span>
    @if($sizes['num'] && $number)
        <span class="relative font-semibold opacity-80 leading-none mt-1 {{ $sizes['num'] }}">{{ $number }}</span>
    @endif
</div>
