@php
    $joinOpen = $errors->has('invite_code') || session('info');
@endphp
<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('nav.all_courses') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('courses.subtitle') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="$dispatch('toggle-join-course')" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-teal-700 dark:text-teal-300 bg-teal-50 dark:bg-teal-500/10 border border-teal-200 dark:border-teal-500/30 rounded-xl hover:bg-teal-100 dark:hover:bg-teal-500/20 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    {{ __('courses.join') }}
                </button>
                @if($currentCourses->isNotEmpty())
                    <a href="{{ route('tenant.course-context.picker', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white border border-slate-300 dark:border-slate-600 rounded-xl hover:bg-slate-50 shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        {{ __('nav.course_picker') }}
                    </a>
                @endif
                <a href="{{ route('tenant.courses.create', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm shadow-indigo-500/20 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('courses.create') }}
                </a>
            </div>
        </div>
    </x-slot>

    {{-- Join a course: opened from the header, and kept open when the code was rejected --}}
    <div x-data="{ open: {{ $joinOpen ? 'true' : 'false' }} }" @toggle-join-course.window="open = !open; if (open) $nextTick(() => $refs.code.focus())"
         x-show="open" x-cloak x-transition class="mb-6 bg-white rounded-2xl border border-teal-200 dark:border-teal-500/30 p-5">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-teal-100 dark:bg-teal-500/15 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-teal-600 dark:text-teal-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('courses.join') }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('courses.join_hint') }}</p>
                <form method="POST" action="{{ route('tenant.courses.join', app('current_tenant')->slug) }}" class="mt-3 flex flex-col sm:flex-row gap-2">
                    @csrf
                    <input x-ref="code" type="text" name="invite_code" value="{{ old('invite_code') }}" placeholder="{{ __('courses.join_placeholder') }}" required maxlength="20"
                           class="flex-1 px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm uppercase tracking-widest placeholder:tracking-normal placeholder:normal-case focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500" />
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 sm:flex-none px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-xl transition">{{ __('courses.join_submit') }}</button>
                        <button type="button" @click="open = false" class="px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition">{{ __('courses.cancel') }}</button>
                    </div>
                </form>
                @error('invite_code')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
                @if(session('info'))
                    <p class="mt-2 text-xs text-amber-600">{{ session('info') }}</p>
                @endif
            </div>
        </div>
    </div>

    @if($courses->isEmpty())
        {{-- Empty state --}}
        <div class="bg-white rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-12 flex flex-col items-center justify-center text-center">
                <div class="w-20 h-20 bg-indigo-50 dark:bg-indigo-500/15 rounded-2xl flex items-center justify-center mb-6">
                    <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">{{ __('courses.empty_title') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm mb-6">{{ __('courses.empty_body') }}</p>
                <a href="{{ route('tenant.courses.create', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('courses.empty_cta') }}
                </a>

                <div class="mt-10 grid sm:grid-cols-3 gap-4 w-full max-w-2xl">
                    @foreach([1 => 'bg-indigo-100 text-indigo-600', 2 => 'bg-teal-100 text-teal-600', 3 => 'bg-amber-100 text-amber-600'] as $step => $tone)
                        <div class="text-left bg-slate-50 dark:bg-slate-700/40 rounded-xl p-4 border border-slate-100 dark:border-slate-700">
                            <div class="w-8 h-8 rounded-lg {{ $tone }} flex items-center justify-center mb-3"><span class="text-sm font-bold">{{ $step }}</span></div>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __("courses.step{$step}_title") }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ __("courses.step{$step}_body") }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        @if($currentCourses->isNotEmpty())
            {{-- At-a-glance totals for the active courses --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                @foreach([
                    ['key' => 'courses', 'tone' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
                    ['key' => 'sections', 'tone' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
                    ['key' => 'students', 'tone' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                    ['key' => 'running', 'tone' => 'bg-teal-50 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ] as $tile)
                    <div class="bg-white rounded-2xl border border-slate-200 dark:border-slate-700 p-4 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl {{ $tile['tone'] }} flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tile['icon'] }}"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xl font-bold text-slate-900 dark:text-white leading-tight">{{ number_format($stats[$tile['key']]) }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ __('courses.stat_'.$tile['key']) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- One card per course; its semesters are listed on the card and inside the course --}}
            @php
                $items = $currentCourses->values()->map(fn ($c) => [
                    'id' => $c->id,
                    'text' => strtolower($c->code.' '.$c->title),
                    'running' => $runningNow->contains($c->id),
                ]);
            @endphp
            <div x-data="{
                    q: '',
                    filter: 'all',
                    items: {{ Illuminate\Support\Js::from($items) }},
                    matches(item) {
                        const needle = this.q.trim().toLowerCase();
                        if (needle !== '' && !item.text.includes(needle)) return false;
                        if (this.filter === 'running') return item.running;
                        if (this.filter === 'earlier') return !item.running;
                        return true;
                    },
                    show(id) { return this.matches(this.items.find(i => i.id === id)); },
                    get shown() { return this.items.filter(i => this.matches(i)).length; },
                    get filtered() { return this.q.trim() !== '' || this.filter !== 'all'; },
                    reset() { this.q = ''; this.filter = 'all'; },
                }"
                @keydown.window="if ($event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) && !document.activeElement.isContentEditable) { $event.preventDefault(); $refs.search.focus(); }">
                <div class="flex flex-col md:flex-row md:items-center gap-3 mb-5">
                    <div class="relative flex-1 md:max-w-md">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-ref="search" type="search" x-model.debounce.150ms="q" @keydown.escape="q = ''; $el.blur()" placeholder="{{ __('courses.search_placeholder') }}"
                               class="w-full pl-10 pr-10 py-2.5 bg-white border border-slate-300 dark:border-slate-600 rounded-xl text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" />
                        <kbd x-show="q === ''" class="hidden sm:inline-flex absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 text-[10px] font-medium text-slate-400 border border-slate-200 dark:border-slate-600 rounded pointer-events-none">/</kbd>
                    </div>
                    <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-700/50 rounded-xl self-start" role="tablist">
                        @foreach(['all', 'running', 'earlier'] as $key)
                            <button type="button" role="tab" @click="filter = '{{ $key }}'" :aria-selected="filter === '{{ $key }}'"
                                    :class="filter === '{{ $key }}' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition">
                                {{ __('courses.filter_'.$key) }}
                                <span class="ml-1 text-[10px] opacity-60">{{ match ($key) { 'all' => $stats['courses'], 'running' => $stats['running'], 'earlier' => $stats['courses'] - $stats['running'] } }}</span>
                            </button>
                        @endforeach
                    </div>
                    <p x-show="filtered" x-cloak class="md:ml-auto text-xs text-slate-500 dark:text-slate-400"
                       x-text="{{ Illuminate\Support\Js::from(__('courses.showing')) }}.replace(':shown', shown).replace(':total', items.length)"></p>
                </div>

                <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
                    @foreach($currentCourses as $course)
                        <div x-show="show({{ $course->id }})" x-transition.opacity.duration.150ms>
                            @include('tenant.courses._card', [
                                'course' => $course,
                                'studentCount' => $studentCounts[$course->id] ?? 0,
                                'selected' => $currentCourseId === $course->id,
                            ])
                        </div>
                    @endforeach
                </div>

                <div x-cloak x-show="shown === 0" class="mt-2 py-10 text-center bg-white rounded-2xl border border-dashed border-slate-300 dark:border-slate-600">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('courses.no_match') }}</p>
                    <button type="button" @click="reset()" class="mt-2 text-sm font-medium text-indigo-600 dark:text-indigo-300 hover:underline">{{ __('courses.clear_filters') }}</button>
                </div>
            </div>
        @endif

        @if($archivedCourses->isNotEmpty())
            {{-- Archived courses stay reachable, just folded away --}}
            <div x-data="{ showArchived: {{ $currentCourses->isEmpty() ? 'true' : 'false' }} }" @class(['mt-10 pt-6 border-t border-slate-200 dark:border-slate-700' => $currentCourses->isNotEmpty()])>
                <button @click="showArchived = !showArchived" class="flex items-center gap-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition">
                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': showArchived }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    {{ __('courses.archived', ['count' => $archivedCourses->count()]) }}
                </button>
                <div x-show="showArchived" x-cloak x-transition class="mt-4 grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
                    @foreach($archivedCourses as $course)
                        @include('tenant.courses._card', [
                            'course' => $course,
                            'studentCount' => $studentCounts[$course->id] ?? 0,
                            'selected' => $currentCourseId === $course->id,
                        ])
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</x-tenant-layout>
