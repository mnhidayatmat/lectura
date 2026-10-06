@props(['heading' => 'Courses', 'count' => 0])

<div>
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $heading }}</h3>
        <span class="text-xs text-slate-400">{{ $count }} {{ Str::plural('course', $count) }}</span>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {{ $slot }}
    </div>
</div>
