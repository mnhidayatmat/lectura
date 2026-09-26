<x-tenant-layout>
    @php
        $monogram = fn (string $code) => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $code) ?: 'C', 0, 2));
        $palette = [
            'bg-indigo-500', 'bg-violet-500', 'bg-teal-500', 'bg-rose-500',
            'bg-amber-500', 'bg-sky-500', 'bg-emerald-500', 'bg-fuchsia-500',
        ];
        $courseColor = fn ($course) => $palette[crc32((string) $course->id) % count($palette)];
    @endphp

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('nav.pick_course_title') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('nav.pick_course_subtitle') }}</p>
            </div>
            <a href="{{ route('tenant.courses.create', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm shadow-indigo-500/20 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Course
            </a>
        </div>
    </x-slot>

    {{-- Netflix-style course picker --}}
    @if($currentCourses->isNotEmpty())
        <div class="mb-10">
            <div class="flex flex-wrap justify-center gap-6 sm:gap-10">
                @foreach($currentCourses as $course)
                    <form method="POST" action="{{ route('tenant.course-context.select', app('current_tenant')->slug) }}" class="group flex flex-col items-center gap-3 w-36">
                        @csrf
                        <input type="hidden" name="course_id" value="{{ $course->id }}">
                        <input type="hidden" name="redirect" value="{{ route('tenant.courses.show', ['tenant' => app('current_tenant')->slug, 'course' => $course->id]) }}">
                        <button type="submit"
                                class="w-32 h-32 sm:w-36 sm:h-36 rounded-3xl {{ $courseColor($course) }} flex items-center justify-center text-white text-4xl font-extrabold tracking-wide ring-4 ring-transparent group-hover:ring-white dark:group-hover:ring-slate-600 group-hover:scale-105 transition-all duration-200 shadow-lg">
                            {{ $monogram($course->code) }}
                        </button>
                        <div class="text-center">
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">{{ $course->title }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ $course->sections->map(fn ($s) => $s->term()?->name ?? $course->academicTerm?->name)->filter()->unique()->first() ?? __('nav.no_semester') }}
                            </p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{{ trans_choice('nav.sections_count', $course->sections_count, ['count' => $course->sections_count]) }}</p>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>

        {{-- Course management --}}
        <div class="pt-6 border-t border-slate-200 dark:border-[#354158]">
            <h3 class="text-sm font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-5">{{ __('nav.manage_courses') }}</h3>
        </div>
    @endif

    {{-- Join a Course --}}
    <div class="mb-6 bg-white rounded-2xl border border-slate-200 p-5" x-data="{ showJoin: false }">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-900">Join a Course</p>
                    <p class="text-xs text-slate-400">Enter the invite code to take over an existing course</p>
                </div>
            </div>
            <button @click="showJoin = !showJoin" class="px-4 py-2 text-sm font-medium text-teal-700 bg-teal-50 border border-teal-200 rounded-xl hover:bg-teal-100 transition">
                <span x-text="showJoin ? 'Cancel' : 'Join'"></span>
            </button>
        </div>
        <div x-show="showJoin" x-cloak x-transition class="mt-4">
            <form method="POST" action="{{ route('tenant.courses.join', app('current_tenant')->slug) }}" class="flex gap-2">
                @csrf
                <input type="text" name="invite_code" placeholder="Enter course invite code" required class="flex-1 px-4 py-2.5 border border-slate-300 rounded-xl text-sm text-center uppercase tracking-widest placeholder:tracking-normal placeholder:normal-case focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500" maxlength="20" />
                <button type="submit" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-xl transition">Join</button>
            </form>
            @error('invite_code')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @if(session('info'))
                <p class="mt-2 text-xs text-amber-600">{{ session('info') }}</p>
            @endif
        </div>
    </div>

    @if($courses->isEmpty())
        {{-- Empty state --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="p-12 flex flex-col items-center justify-center text-center">
                <div class="w-20 h-20 bg-indigo-50 rounded-2xl flex items-center justify-center mb-6">
                    <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 mb-2">No courses yet</h3>
                <p class="text-sm text-slate-500 max-w-sm mb-6">Create your first course to start managing sections, students, teaching plans, and assessments.</p>
                <a href="{{ route('tenant.courses.create', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Your First Course
                </a>

                <div class="mt-10 grid sm:grid-cols-3 gap-4 w-full max-w-2xl">
                    <div class="text-left bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center mb-3"><span class="text-sm font-bold text-indigo-600">1</span></div>
                        <p class="text-sm font-medium text-slate-700">Create a course</p>
                        <p class="text-xs text-slate-400 mt-1">Add code, title, CLOs, and weekly topics</p>
                    </div>
                    <div class="text-left bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <div class="w-8 h-8 rounded-lg bg-teal-100 flex items-center justify-center mb-3"><span class="text-sm font-bold text-teal-600">2</span></div>
                        <p class="text-sm font-medium text-slate-700">Add sections & students</p>
                        <p class="text-xs text-slate-400 mt-1">Import via CSV or share invite code</p>
                    </div>
                    <div class="text-left bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center mb-3"><span class="text-sm font-bold text-amber-600">3</span></div>
                        <p class="text-sm font-medium text-slate-700">Start teaching</p>
                        <p class="text-xs text-slate-400 mt-1">Generate AI plans, take attendance, run quizzes</p>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- One card per course; its semesters are listed on the card and inside the course --}}
        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($currentCourses as $course)
                @include('tenant.courses._card', ['course' => $course])
            @endforeach
        </div>

        @if($archivedCourses->isNotEmpty())
            {{-- Archived courses stay reachable, just folded away --}}
            <div x-data="{ showArchived: false }" class="mt-10 pt-6 border-t border-slate-200">
                <button @click="showArchived = !showArchived" class="flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-700 transition">
                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': showArchived }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    Archived courses ({{ $archivedCourses->count() }})
                </button>
                <div x-show="showArchived" x-cloak x-transition class="mt-4 grid md:grid-cols-2 xl:grid-cols-3 gap-5">
                    @foreach($archivedCourses as $course)
                        @include('tenant.courses._card', ['course' => $course])
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</x-tenant-layout>
