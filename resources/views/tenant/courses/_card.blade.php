@php
    $accent = \App\View\CourseAccent::for($course);
    $studentCount = (int) ($studentCount ?? 0);
    $selected = $selected ?? false;
    $archived = $course->status === 'archived';
    $semesters = $course->sections->map(fn ($section) => $section->academicTerm ?? $course->academicTerm)
        ->push($course->sections->isEmpty() ? $course->academicTerm : null)
        ->filter()->unique('id')->sortByDesc('start_date');
@endphp
<a href="{{ route('tenant.courses.show', [app('current_tenant')->slug, $course]) }}"
   @class([
       'group relative flex flex-col h-full bg-white dark:bg-slate-800 rounded-2xl border p-5 pt-6 overflow-hidden hover:shadow-xl hover:shadow-indigo-500/5 hover:-translate-y-0.5 transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
       'border-indigo-300 dark:border-indigo-500/60 ring-1 ring-indigo-300/60 dark:ring-indigo-500/30' => $selected,
       'border-slate-200 dark:border-slate-700 hover:border-indigo-200 dark:hover:border-indigo-500/40' => ! $selected,
       'opacity-75 hover:opacity-100' => $archived,
   ])>
    <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r {{ $accent['gradient'] }}"></div>

    <div class="flex items-start gap-3.5">
        <x-course-avatar :course="$course" size="md" class="shrink-0 group-hover:scale-105 transition" />
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-slate-900 dark:text-white group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition">{{ $course->code }}</h3>
                @if($selected)
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        {{ __('courses.selected') }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2" title="{{ $course->title }}">{{ $course->title }}</p>
        </div>
        @php $badge = $course->statusBadge; @endphp
        @unless($course->status === 'active')
            <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700">{{ $badge['label'] }}</span>
        @endunless
    </div>

    <div class="mt-4 grid grid-cols-3 gap-2">
        <div class="rounded-xl bg-slate-50 dark:bg-slate-700/40 px-3 py-2">
            <p class="text-base font-semibold text-slate-900 dark:text-white leading-tight">{{ $studentCount }}</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ trans_choice('courses.students_label', $studentCount) }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-700/40 px-3 py-2">
            <p class="text-base font-semibold text-slate-900 dark:text-white leading-tight">{{ $course->sections_count }}</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ trans_choice('courses.sections_label', $course->sections_count) }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-700/40 px-3 py-2">
            <p class="text-base font-semibold text-slate-900 dark:text-white leading-tight">{{ $course->num_weeks ?: '—' }}</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ trans_choice('courses.weeks_label', (int) $course->num_weeks) }}</p>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-1.5">
        @foreach($semesters as $semester)
            <span @class([
                'inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium',
                'bg-teal-50 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300' => $semester->isCurrent(),
                'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400' => ! $semester->isCurrent(),
            ])>
                @if($semester->isCurrent())<span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>@endif
                {{ $semester->name }}
            </span>
        @endforeach
        @if($course->teaching_mode)
            <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400">{{ str_replace('_', ' ', ucfirst($course->teaching_mode)) }}</span>
        @endif
    </div>

    <div class="mt-auto pt-4">
        <div class="pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <span class="text-xs font-medium text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition">{{ __('courses.open') }}</span>
            <svg class="w-4 h-4 text-slate-300 dark:text-slate-500 group-hover:text-indigo-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </div>
    </div>
</a>
