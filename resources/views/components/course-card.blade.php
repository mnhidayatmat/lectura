@props([
    'href',
    'course',
    'accent' => 'indigo',
    'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    'meta' => null,
    'live' => 0,
    'metricLabel' => null,
    'metricValue' => null,
    'metricPercent' => null,
    'metricTone' => null,
    'footerLeft' => null,
    'footerRight' => null,
])

@php
    $accents = [
        'emerald' => ['icon' => 'from-emerald-500 to-teal-600', 'code' => 'text-emerald-600 dark:text-emerald-400', 'hover' => 'hover:border-emerald-200 dark:hover:border-emerald-700', 'title' => 'group-hover:text-emerald-700 dark:group-hover:text-emerald-400', 'chevron' => 'group-hover:text-emerald-400'],
        'amber' => ['icon' => 'from-amber-400 to-orange-500', 'code' => 'text-amber-600 dark:text-amber-400', 'hover' => 'hover:border-amber-200 dark:hover:border-amber-700', 'title' => 'group-hover:text-amber-700 dark:group-hover:text-amber-400', 'chevron' => 'group-hover:text-amber-400'],
        'rose' => ['icon' => 'from-rose-500 to-pink-600', 'code' => 'text-rose-600 dark:text-rose-400', 'hover' => 'hover:border-rose-200 dark:hover:border-rose-700', 'title' => 'group-hover:text-rose-700 dark:group-hover:text-rose-400', 'chevron' => 'group-hover:text-rose-400'],
        'teal' => ['icon' => 'from-teal-500 to-cyan-600', 'code' => 'text-teal-600 dark:text-teal-400', 'hover' => 'hover:border-teal-200 dark:hover:border-teal-700', 'title' => 'group-hover:text-teal-700 dark:group-hover:text-teal-400', 'chevron' => 'group-hover:text-teal-400'],
        'violet' => ['icon' => 'from-violet-500 to-purple-600', 'code' => 'text-violet-600 dark:text-violet-400', 'hover' => 'hover:border-violet-200 dark:hover:border-violet-700', 'title' => 'group-hover:text-violet-700 dark:group-hover:text-violet-400', 'chevron' => 'group-hover:text-violet-400'],
        'fuchsia' => ['icon' => 'from-fuchsia-500 to-pink-600', 'code' => 'text-fuchsia-600 dark:text-fuchsia-400', 'hover' => 'hover:border-fuchsia-200 dark:hover:border-fuchsia-700', 'title' => 'group-hover:text-fuchsia-700 dark:group-hover:text-fuchsia-400', 'chevron' => 'group-hover:text-fuchsia-400'],
        'indigo' => ['icon' => 'from-indigo-500 to-indigo-600', 'code' => 'text-indigo-600 dark:text-indigo-400', 'hover' => 'hover:border-indigo-200 dark:hover:border-indigo-700', 'title' => 'group-hover:text-indigo-700 dark:group-hover:text-indigo-400', 'chevron' => 'group-hover:text-indigo-400'],
    ];
    $a = $accents[$accent] ?? $accents['indigo'];

    $tones = [
        'good' => ['bar' => 'bg-emerald-500', 'text' => 'text-emerald-600 dark:text-emerald-400'],
        'warn' => ['bar' => 'bg-amber-500', 'text' => 'text-amber-600 dark:text-amber-400'],
        'bad' => ['bar' => 'bg-red-500', 'text' => 'text-red-600 dark:text-red-400'],
        'neutral' => ['bar' => 'bg-indigo-500', 'text' => 'text-slate-900 dark:text-white'],
        'none' => ['bar' => 'bg-slate-200 dark:bg-slate-600', 'text' => 'text-slate-400'],
    ];
    $tone = $tones[$metricTone ?? ($metricPercent === null ? 'none' : ($metricPercent >= 80 ? 'good' : ($metricPercent >= 60 ? 'warn' : 'bad')))];
    $archived = $course->status === 'archived';
@endphp

<a href="{{ $href }}"
   {{ $attributes->class(['group flex flex-col bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 hover:shadow-md p-5 transition-all', $a['hover'], 'opacity-75 hover:opacity-100' => $archived]) }}>
    <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $a['icon'] }} flex items-center justify-center flex-shrink-0 shadow-sm">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <p class="text-xs font-bold {{ $a['code'] }}">{{ $course->code }}</p>
                @if($live > 0)
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 rounded-full text-[10px] font-semibold">
                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                        Live
                    </span>
                @endif
            </div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white truncate {{ $a['title'] }} transition">{{ $course->title }}</h4>
            @if($meta)
                <p class="text-xs text-slate-400 mt-0.5 truncate">{{ $meta }}</p>
            @endif
        </div>
        <svg class="w-5 h-5 text-slate-300 dark:text-slate-600 {{ $a['chevron'] }} transition flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </div>

    <div class="mt-auto pt-4">
        <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
            @if($metricLabel)
                <div class="flex items-baseline justify-between mb-1.5">
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $metricLabel }}</span>
                    <span class="text-sm font-bold {{ $tone['text'] }}">{{ $metricValue ?? '—' }}</span>
                </div>
                <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden mb-3">
                    <div class="h-full rounded-full {{ $tone['bar'] }}" style="width: {{ min(max($metricPercent ?? 0, 0), 100) }}%"></div>
                </div>
            @endif
            <div class="flex items-center justify-between gap-3 text-xs text-slate-400">
                <span class="truncate">{{ $footerLeft }}</span>
                <span class="truncate text-right">{{ $footerRight }}</span>
            </div>
        </div>
    </div>
</a>
