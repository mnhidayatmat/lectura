<x-tenant-layout>
    @php
        $slug = $tenant->slug;
        $accent = \App\View\CourseAccent::for($course);
        $hour = (int) now()->format('G');
        $greetingKey = $hour < 12 ? 'nav.greeting_morning' : ($hour < 18 ? 'nav.greeting_afternoon' : 'nav.greeting_evening');
        $firstName = explode(' ', trim(auth()->user()->name))[0];
        $readyDone = $readiness->where('done', true)->count();
        $now = now()->format('H:i');

        $launch = [
            [__('nav.start_attendance'), route('tenant.attendance.course', [$slug, $course]), 'bg-emerald-500', 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z'],
            [__('nav.launch_quiz'), route('tenant.quizzes.course', [$slug, $course]), 'bg-amber-500', 'M13 10V3L4 14h7v7l9-11h-7z'],
            [__('nav.random_wheel'), route('tenant.random-wheel', $slug), 'bg-rose-500', 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
            [__('nav.active_learning'), route('tenant.active-learning.index', [$slug, $course]), 'bg-teal-500', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
            [__('nav.materials'), route('tenant.materials.manage', [$slug, $course]), 'bg-indigo-500', 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12'],
            [__('nav.whiteboards'), route('tenant.whiteboards.index', [$slug, $course]), 'bg-fuchsia-500', 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z'],
        ];
        $typeColors = [
            'lecture' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
            'tutorial' => 'bg-teal-50 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300',
            'lab' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        ];
    @endphp

    <div class="space-y-6">
        {{-- Hero --}}
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br {{ $accent['hero'] }} text-white shadow-xl shadow-slate-900/10">
            <div class="pointer-events-none absolute -top-24 -right-16 w-80 h-80 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-40 w-72 h-72 rounded-full bg-black/10"></div>

            <div class="relative p-6 sm:p-8">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                    <div class="flex items-start gap-4 sm:gap-5 min-w-0">
                        <x-course-avatar :course="$course" size="lg" class="ring-4 ring-white/25 hidden sm:flex" />
                        <div class="min-w-0">
                            <p class="text-sm text-white/70">{{ __($greetingKey, ['name' => $firstName]) }} · {{ now()->translatedFormat('l, j F') }}</p>
                            <h1 class="mt-1 text-3xl sm:text-4xl font-extrabold tracking-tight">{{ $course->code }}</h1>
                            <p class="mt-1 text-base sm:text-lg text-white/85 leading-snug">{{ $course->title }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                @if($term)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 font-medium">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        {{ $term->name }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 font-medium">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ trans_choice('nav.students_count', $studentCount, ['count' => $studentCount]) }}
                                </span>
                                @if($course->credit_hours)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-white/15 font-medium">{{ __('nav.credits', ['count' => $course->credit_hours]) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($otherCourses->isNotEmpty())
                            <button type="button" @click="$dispatch('open-course-switcher')" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#fff] text-[#0f172a] text-sm font-semibold shadow-sm hover:bg-[#fff]/90 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                {{ __('nav.switch_course') }}
                            </button>
                        @endif
                        <a href="{{ route('tenant.courses.show', [$slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/15 hover:bg-white/25 text-sm font-semibold transition">
                            {{ __('nav.course_details') }}
                        </a>
                    </div>
                </div>

                {{-- Semester progress --}}
                <div class="mt-7">
                    <div class="flex items-end justify-between gap-4 text-sm">
                        <p class="font-semibold">
                            @if($currentWeek)
                                {{ __('nav.week_of', ['week' => $currentWeek, 'total' => $numWeeks]) }}
                            @else
                                <span class="text-white/70 font-medium">{{ __('nav.not_in_semester') }}</span>
                            @endif
                        </p>
                        @if($weekTopics->isNotEmpty())
                            <p class="text-white/80 truncate text-right"><span class="text-white/60">{{ __('nav.this_week') }}:</span> {{ $weekTopics->pluck('title')->implode(', ') }}</p>
                        @endif
                    </div>
                    <div class="mt-2.5 flex gap-1">
                        @for($w = 1; $w <= $numWeeks; $w++)
                            <div class="h-1.5 flex-1 rounded-full {{ $currentWeek && $w < $currentWeek ? 'bg-[#fff]/60' : ($currentWeek === $w ? 'bg-[#fff] shadow-[0_0_8px_rgba(255,255,255,0.8)]' : 'bg-[#fff]/20') }}" title="Week {{ $w }}"></div>
                        @endfor
                    </div>
                </div>
            </div>
        </section>

        {{-- Live attendance --}}
        @if($liveSession)
            <a href="{{ route('tenant.attendance.qr', [$slug, $liveSession]) }}" class="flex items-center gap-4 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 hover:border-emerald-300 transition group">
                <span class="relative flex w-3 h-3 flex-shrink-0"><span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span><span class="relative inline-flex rounded-full w-3 h-3 bg-emerald-500"></span></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">{{ __('nav.attendance_live') }}</p>
                    <p class="text-xs text-emerald-700/80 dark:text-emerald-400/80 truncate">{{ $liveSession->section?->name }} · {{ $liveSession->started_at?->format('H:i') }}</p>
                </div>
                <span class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold group-hover:bg-emerald-700 transition">{{ __('nav.open_qr') }}</span>
            </a>
        @endif

        {{-- Quick launch --}}
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
            @foreach($launch as [$label, $url, $color, $icon])
                <a href="{{ $url }}" class="group flex flex-col items-center gap-2.5 p-4 rounded-2xl bg-white dark:bg-[#242d3d] border border-slate-200 dark:border-[#354158] hover:border-slate-300 dark:hover:border-slate-500 hover:-translate-y-0.5 hover:shadow-md transition">
                    <span class="w-11 h-11 rounded-xl {{ $color }} flex items-center justify-center text-white shadow-sm group-hover:scale-110 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
                    </span>
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 text-center leading-tight">{{ $label }}</span>
                </a>
            @endforeach
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                {{-- Today's classes --}}
                <section class="bg-white dark:bg-[#242d3d] rounded-2xl border border-slate-200 dark:border-[#354158] overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-[#354158] flex items-center justify-between">
                        <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('nav.today_classes') }}</h2>
                        <span class="text-xs text-slate-400">{{ now()->translatedFormat('l') }}</span>
                    </div>
                    @forelse($todaySlots as $slot)
                        @php
                            $isNow = $now >= $slot->start_time && $now < $slot->end_time;
                            $isPast = $slot->end_time !== '' && $now >= $slot->end_time;
                        @endphp
                        <div class="flex items-center gap-4 px-5 py-4 border-b last:border-b-0 border-slate-100 dark:border-[#354158] {{ $isPast ? 'opacity-50' : '' }} {{ $isNow ? 'bg-emerald-50/60 dark:bg-emerald-500/5' : '' }}">
                            <div class="w-14 flex-shrink-0 text-center">
                                <p class="text-sm font-bold {{ $isNow ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-slate-100' }}">{{ $slot->start_time }}</p>
                                <p class="text-[11px] text-slate-400">{{ $slot->end_time }}</p>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $slot->section_name }}</p>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $typeColors[$slot->type] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">{{ ucfirst($slot->type) }}</span>
                                    @if($isNow)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500 text-white">{{ __('nav.now') }}</span>
                                    @endif
                                </div>
                                @if($slot->location)
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400 truncate">{{ $slot->location }}</p>
                                @endif
                            </div>
                            @unless($isPast)
                                <a href="{{ route('tenant.attendance.course', [$slug, $course]) }}" class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition {{ $isNow ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-[#2a3548] dark:hover:bg-[#354158] dark:text-slate-200' }}">
                                    {{ __('nav.start_attendance') }}
                                </a>
                            @endunless
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center">
                            <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 dark:bg-[#2a3548] flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('nav.no_class_today') }}</p>
                        </div>
                    @endforelse
                </section>

                {{-- This week + upcoming assessments --}}
                <div class="grid sm:grid-cols-2 gap-6">
                    <section class="bg-white dark:bg-[#242d3d] rounded-2xl border border-slate-200 dark:border-[#354158] p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('nav.this_week') }}</h2>
                            @if($currentWeek)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $accent['soft'] }}">W{{ $currentWeek }}</span>
                            @endif
                        </div>
                        @forelse($weekTopics as $topic)
                            <div class="mt-3">
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $topic->title }}</p>
                                @if($topic->description)
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 line-clamp-2">{{ $topic->description }}</p>
                                @endif
                                @php $topicClos = $course->learningOutcomes->whereIn('id', collect($topic->clo_ids ?? [])->map(fn ($id) => (int) $id)); @endphp
                                @if($topicClos->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach($topicClos as $clo)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300" title="{{ $clo->description }}">{{ $clo->code }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="mt-3 text-sm text-slate-400">{{ __('nav.no_topic_this_week') }}</p>
                        @endforelse
                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-[#354158] flex items-center gap-4 text-xs font-semibold">
                            <a href="{{ route('tenant.teaching-plan.show', [$slug, $course]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('nav.teaching_plan') }}</a>
                            <a href="{{ route('tenant.materials.manage', [$slug, $course]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('nav.materials') }}</a>
                        </div>
                    </section>

                    <section class="bg-white dark:bg-[#242d3d] rounded-2xl border border-slate-200 dark:border-[#354158] p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('nav.upcoming_assessments') }}</h2>
                            <a href="{{ route('tenant.assessments.index', [$slug, $course]) }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('nav.view_all') }}</a>
                        </div>
                        <div class="mt-3 space-y-3">
                            @forelse($upcomingAssessments as $assessment)
                                <div class="flex items-center gap-3">
                                    <div class="w-11 flex-shrink-0 rounded-lg bg-slate-50 dark:bg-[#2a3548] text-center py-1">
                                        <p class="text-[10px] font-semibold uppercase text-slate-400 leading-tight">{{ $assessment->due_date->translatedFormat('M') }}</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-slate-100 leading-tight">{{ $assessment->due_date->format('j') }}</p>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ $assessment->title }}</p>
                                        <p class="text-[11px] text-slate-400 truncate">{{ ucwords(str_replace('_', ' ', $assessment->type)) }} · {{ $assessment->due_date->diffForHumans() }}</p>
                                    </div>
                                    @if((float) $assessment->weightage > 0)
                                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ (float) $assessment->weightage }}%</span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-slate-400">{{ __('nav.nothing_due') }}</p>
                            @endforelse
                        </div>
                    </section>
                </div>
            </div>

            <div class="space-y-6">
                {{-- Stats --}}
                <section class="grid grid-cols-2 gap-3">
                    @foreach([
                        [__('nav.students'), $studentCount, null],
                        [__('nav.sections'), $sectionCount, null],
                        [__('nav.avg_attendance'), $avgAttendance !== null ? $avgAttendance.'%' : '—', trans_choice('nav.sessions_held', $sessionsHeld, ['count' => $sessionsHeld])],
                        [__('nav.assessment_plan'), rtrim(rtrim(number_format($assessmentWeight, 2), '0'), '.').'%', null],
                    ] as [$label, $value, $sub])
                        <div class="bg-white dark:bg-[#242d3d] rounded-2xl border border-slate-200 dark:border-[#354158] p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</p>
                            <p class="mt-1 text-2xl font-extrabold text-slate-900 dark:text-white">{{ $value }}</p>
                            @if($sub)
                                <p class="text-[11px] text-slate-400 mt-0.5">{{ $sub }}</p>
                            @endif
                        </div>
                    @endforeach
                </section>

                {{-- Course setup checklist --}}
                <section class="bg-white dark:bg-[#242d3d] rounded-2xl border border-slate-200 dark:border-[#354158] p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('nav.course_setup') }}</h2>
                        <span class="text-xs font-bold {{ $readyDone === $readiness->count() ? 'text-emerald-600' : 'text-slate-400' }}">{{ $readyDone }}/{{ $readiness->count() }}</span>
                    </div>
                    <div class="mt-3 h-1.5 rounded-full bg-slate-100 dark:bg-[#2a3548] overflow-hidden">
                        <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $readiness->count() ? round($readyDone / $readiness->count() * 100) : 0 }}%"></div>
                    </div>
                    <ul class="mt-4 space-y-1">
                        @foreach($readiness as $item)
                            <li>
                                <a href="{{ $item['url'] }}" class="flex items-center gap-2.5 px-2 py-1.5 -mx-2 rounded-lg hover:bg-slate-50 dark:hover:bg-[#2a3548] transition group">
                                    @if($item['done'])
                                        <span class="w-5 h-5 rounded-full bg-emerald-500 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        </span>
                                    @else
                                        <span class="w-5 h-5 rounded-full border-2 border-slate-300 dark:border-slate-600 flex-shrink-0"></span>
                                    @endif
                                    <span class="text-sm flex-1 {{ $item['done'] ? 'text-slate-400 line-through decoration-slate-300' : 'text-slate-700 dark:text-slate-200' }}">{{ $item['label'] }}</span>
                                    @unless($item['done'])
                                        <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-500 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    @endunless
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        </div>

        {{-- Other courses row --}}
        @if($otherCourses->isNotEmpty())
            <section>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('nav.other_courses') }}</h2>
                    <a href="{{ route('tenant.course-context.picker', $slug) }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('nav.course_picker') }}</a>
                </div>
                <div class="flex gap-4 overflow-x-auto pb-2 -mx-1 px-1 snap-x">
                    @foreach($otherCourses as $other)
                        @php $otherTerm = $termFor($other); @endphp
                        <form method="POST" action="{{ route('tenant.course-context.select', $slug) }}" class="snap-start flex-shrink-0 w-40">
                            @csrf
                            <input type="hidden" name="course_id" value="{{ $other->id }}">
                            <input type="hidden" name="redirect" value="{{ route('tenant.dashboard', $slug, false) }}">
                            <button type="submit" class="group block w-full text-left">
                                <x-course-avatar :course="$other" size="xl" class="!aspect-[4/3] group-hover:scale-[1.03] transition" />
                                <p class="mt-2 text-sm font-bold text-slate-900 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition">{{ $other->code }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $other->title }}</p>
                                @if($otherTerm)
                                    <p class="text-[11px] text-slate-400 truncate">{{ $otherTerm->name }}</p>
                                @endif
                            </button>
                        </form>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-tenant-layout>
