@props(['href', 'active' => false, 'icon'])

<a href="{{ $href }}" @if($active) aria-current="page" @endif
   {{ $attributes->class([
       'group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-all duration-150',
       'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' => $active,
       'text-slate-400 hover:text-white hover:bg-slate-800' => ! $active,
   ]) }}>
    <svg class="w-5 h-5 flex-shrink-0 {{ $active ? 'text-white' : 'text-slate-500 group-hover:text-slate-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
    <span class="truncate">{{ $slot }}</span>
</a>
