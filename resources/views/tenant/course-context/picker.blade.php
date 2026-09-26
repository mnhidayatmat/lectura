<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="darkMode()" x-bind:class="{ 'dark': dark }" @toggle-dark.window="toggle()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('nav.pick_course_title') }} · {{ config('app.name', 'Lectura') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @include('layouts.partials.pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
    <script>
        if (localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
        function darkMode() {
            return {
                dark: localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches),
                toggle() {
                    this.dark = !this.dark;
                    localStorage.setItem('darkMode', this.dark);
                }
            }
        }
    </script>
</head>
@php
    $hour = (int) now()->format('G');
    $greetingKey = $hour < 12 ? 'nav.greeting_morning' : ($hour < 18 ? 'nav.greeting_afternoon' : 'nav.greeting_evening');
    $firstName = explode(' ', trim(auth()->user()->name))[0];
    $showSearch = $courses->count() > 8;
@endphp
<body class="antialiased min-h-screen bg-slate-100 text-slate-700 dark:bg-[#1c2333] dark:text-slate-300">
    <div class="relative min-h-screen flex flex-col overflow-hidden"
         x-data="{
             manage: false,
             q: '',
             items: @js($courses->map(fn ($c) => strtolower($c->code.' '.$c->title))->values()),
             matches(t) { const q = this.q.trim().toLowerCase(); return q === '' || q.split(/\s+/).every(p => t.includes(p)); },
             get none() { return this.q.trim() !== '' && ! this.items.some(t => this.matches(t)); },
         }">

        {{-- Soft backdrop glow --}}
        <div class="pointer-events-none absolute inset-x-0 -top-40 h-[28rem] bg-gradient-to-b from-indigo-200/60 via-indigo-100/20 to-transparent dark:from-indigo-500/15 dark:via-indigo-500/5 blur-2xl"></div>

        {{-- Top bar --}}
        <header class="relative flex items-center justify-between gap-4 px-4 sm:px-8 h-16">
            <div class="flex items-center gap-3 min-w-0">
                <img src="/icons/icon-192x192.png" alt="Lectura" class="w-8 h-8 rounded-lg">
                <div class="min-w-0">
                    <p class="text-base font-bold text-slate-900 dark:text-white leading-tight">Lectura</p>
                    <p class="text-[11px] text-slate-500 truncate leading-tight">{{ $tenant->name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5">
                <button @click="$dispatch('toggle-dark')" class="p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-white/70 dark:hover:bg-white/5 dark:hover:text-slate-200 transition" title="Toggle dark mode">
                    <svg x-show="!dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg x-show="dark" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-xl hover:bg-white/70 dark:hover:bg-white/5 transition">
                        <span class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-xs font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click.away="open = false" x-transition class="absolute right-0 mt-1 w-56 bg-white dark:bg-[#242d3d] rounded-xl shadow-lg border border-slate-200 dark:border-[#354158] z-50 py-1">
                        <div class="px-4 py-3 border-b border-slate-100 dark:border-[#354158]">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
                        </div>
                        <a href="{{ route('tenant.settings', $tenant->slug) }}" class="block px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#2a3548]">{{ __('nav.settings') }}</a>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#2a3548]">{{ __('nav.profile') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#2a3548]">{{ __('nav.logout') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="relative flex-1 flex flex-col items-center px-4 sm:px-8 pt-6 sm:pt-14 pb-16">
            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ __($greetingKey, ['name' => $firstName]) }}</p>
            <h1 class="mt-2 text-3xl sm:text-5xl font-extrabold tracking-tight text-slate-900 dark:text-white text-center">{{ __('nav.pick_course_title') }}</h1>
            <p class="mt-3 text-sm sm:text-base text-slate-500 dark:text-slate-400 text-center max-w-xl">{{ __('nav.pick_course_subtitle') }}</p>

            @if($showSearch)
                <div class="mt-8 w-full max-w-md relative">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input x-model="q" type="text" placeholder="{{ __('nav.search_courses') }}" autofocus
                           class="w-full pl-10 pr-4 py-3 rounded-2xl bg-white dark:bg-[#242d3d] border border-slate-200 dark:border-[#354158] text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-transparent shadow-sm">
                </div>
            @endif

            {{-- Course tiles --}}
            <div class="mt-10 sm:mt-14 w-full max-w-5xl grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-x-5 gap-y-8 sm:gap-x-8 sm:gap-y-10">
                @foreach($courses as $course)
                    @php
                        $slots = $todaySlots[$course->id];
                        $nextSlot = $slots->first();
                        $term = $terms[$course->id];
                        $students = (int) ($studentCounts[$course->id] ?? 0);
                        $isCurrent = $currentCourseId === $course->id;
                    @endphp
                    <div class="group relative" x-show="matches(@js(strtolower($course->code.' '.$course->title)))">
                        <form method="POST" action="{{ route('tenant.course-context.select', $tenant->slug) }}">
                            @csrf
                            <input type="hidden" name="course_id" value="{{ $course->id }}">
                            <input type="hidden" name="redirect" value="{{ route('tenant.dashboard', $tenant->slug, false) }}">
                            <button type="submit" :tabindex="manage ? -1 : 0"
                                    class="block w-full text-left rounded-2xl focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-500 focus-visible:ring-offset-4 focus-visible:ring-offset-slate-100 dark:focus-visible:ring-offset-[#1c2333]">
                                <div class="relative transition duration-200 ease-out group-hover:-translate-y-1 group-hover:scale-[1.03]">
                                    <x-course-avatar :course="$course" size="xl"
                                        class="ring-4 ring-transparent group-hover:ring-white dark:group-hover:ring-slate-200 shadow-lg shadow-slate-900/10 group-hover:shadow-xl transition {{ $isCurrent ? '!ring-indigo-500' : '' }}" />
                                    @if($nextSlot)
                                        <span class="absolute top-2.5 left-2.5 inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-[#fff]/95 text-[10px] font-bold text-[#047857] shadow">
                                            <span class="relative flex w-1.5 h-1.5"><span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span><span class="relative inline-flex rounded-full w-1.5 h-1.5 bg-emerald-500"></span></span>
                                            {{ __('nav.class_today', ['time' => $nextSlot->start_time]) }}
                                        </span>
                                    @endif
                                    @if($isCurrent)
                                        <span class="absolute top-2.5 right-2.5 w-6 h-6 rounded-full bg-[#fff] text-[#4f46e5] flex items-center justify-center shadow">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-3 px-0.5">
                                    <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition">{{ $course->code }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400 leading-snug line-clamp-2">{{ $course->title }}</p>
                                    <p class="mt-1.5 text-[11px] text-slate-400 dark:text-slate-500 truncate">
                                        @if($term)<span class="{{ $term->isCurrent() ? 'text-teal-600 dark:text-teal-400 font-medium' : '' }}">{{ $term->name }}</span> · @endif{{ trans_choice('nav.students_count', $students, ['count' => $students]) }}
                                    </p>
                                </div>
                            </button>
                        </form>

                        {{-- Manage mode: tiles open course settings instead --}}
                        <a x-show="manage" x-cloak href="{{ route('tenant.courses.show', [$tenant->slug, $course]) }}"
                           class="absolute inset-x-0 top-0 aspect-square rounded-2xl bg-slate-900/60 backdrop-blur-[1px] flex items-center justify-center text-white ring-4 ring-white/0 hover:ring-white/80 transition"
                           aria-label="{{ __('nav.manage') }} {{ $course->code }}">
                            <span class="w-12 h-12 rounded-full border-2 border-white/90 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </span>
                        </a>
                    </div>
                @endforeach

                {{-- Add course --}}
                <a href="{{ route('tenant.courses.create', $tenant->slug) }}" x-show="q === ''" class="group block rounded-2xl focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-500">
                    <div class="w-full aspect-square rounded-2xl border-2 border-dashed border-slate-300 dark:border-[#354158] flex items-center justify-center text-slate-400 group-hover:border-indigo-400 group-hover:text-indigo-500 group-hover:bg-white/60 dark:group-hover:bg-white/5 transition">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
                    </div>
                    <p class="mt-3 px-0.5 text-sm font-semibold text-slate-500 dark:text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition">{{ __('nav.add_course') }}</p>
                </a>
            </div>

            <p x-show="none" x-cloak class="mt-6 text-sm text-slate-400">{{ __('nav.no_course_match') }}</p>

            <div class="mt-14 flex flex-col sm:flex-row items-center gap-3">
                <button type="button" @click="manage = !manage"
                        class="px-6 py-2.5 rounded-xl border-2 text-sm font-semibold tracking-wide transition"
                        :class="manage ? 'bg-slate-900 border-slate-900 text-white dark:bg-white dark:border-white dark:text-slate-900' : 'border-slate-400 text-slate-700 hover:border-slate-600 hover:text-slate-900 dark:border-[#64748b] dark:text-[#e2e8f0] dark:hover:border-[#e2e8f0] dark:hover:text-[#fff]'">
                    <span x-show="!manage">{{ __('nav.manage_courses') }}</span>
                    <span x-show="manage" x-cloak>{{ __('nav.done') }}</span>
                </button>
                <a href="{{ route('tenant.courses.index', $tenant->slug) }}" class="px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-300 transition">{{ __('nav.browse_courses') }} →</a>
            </div>
        </main>
    </div>
</body>
</html>
