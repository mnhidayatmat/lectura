<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('nav.all_courses') }}</h2>
                <p class="mt-1 text-sm text-slate-500">Manage your courses, sections, and students</p>
            </div>
            <div class="flex items-center gap-2">
            @if($currentCourses->isNotEmpty())
                <a href="{{ route('tenant.course-context.picker', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    {{ __('nav.course_picker') }}
                </a>
            @endif
            <a href="{{ route('tenant.courses.create', app('current_tenant')->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm shadow-indigo-500/20 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Course
            </a>
            </div>
        </div>
    </x-slot>

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
        <div x-data="{ q: '', courses: {{ Illuminate\Support\Js::from($currentCourses->map(fn ($c) => strtolower($c->code.' '.$c->title))) }},
            visible() { const needle = this.q.trim().toLowerCase(); return needle === '' ? this.courses.length : this.courses.filter(c => c.includes(needle)).length; } }">
            <div class="relative mb-5 max-w-md">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" x-model.debounce.150ms="q" placeholder="Search by code or title..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-300 rounded-xl text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" />
            </div>
            <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
                @foreach($currentCourses as $course)
                    <div x-show="q.trim() === '' || {{ Illuminate\Support\Js::from(strtolower($course->code.' '.$course->title)) }}.includes(q.trim().toLowerCase())" x-transition.duration.150ms>
                        @include('tenant.courses._card', ['course' => $course])
                    </div>
                @endforeach
            </div>
            <p x-cloak x-show="q.trim() !== '' && visible() === 0" class="mt-6 text-center text-sm text-slate-400">No courses match your search.</p>
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
