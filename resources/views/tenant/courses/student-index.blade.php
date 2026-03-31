<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">My Courses</h2>
                <p class="mt-1 text-sm text-slate-500">Your enrolled courses and sections</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">

        {{-- Join Course Card --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5" x-data="{ showJoin: false }">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Join a Course</p>
                        <p class="text-xs text-slate-400">Enter the invite code from your lecturer</p>
                    </div>
                </div>
                <button @click="showJoin = !showJoin" class="px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition">
                    <span x-text="showJoin ? 'Cancel' : 'Join'"></span>
                </button>
            </div>
            <div x-show="showJoin" x-cloak x-transition class="mt-4">
                <form method="POST" action="{{ route('tenant.my-courses.enroll', $tenant->slug) }}" class="flex gap-2">
                    @csrf
                    <input type="text" name="invite_code" placeholder="Enter invite code" required class="flex-1 px-4 py-2.5 border border-slate-300 rounded-xl text-sm text-center uppercase tracking-widest placeholder:tracking-normal placeholder:normal-case focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" maxlength="20" />
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Enroll</button>
                </form>
                @error('invite_code')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Courses List --}}
        @if($courses->isEmpty())
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-16 text-center">
                <div class="w-20 h-20 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 mb-2">No courses yet</h3>
                <p class="text-sm text-slate-500 max-w-sm mx-auto">Ask your lecturer for a section invite code to enroll in your first course.</p>
            </div>
        @else
            <div class="grid gap-4">
                @foreach($courses as $item)
                    @php $course = $item->course; @endphp
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 hover:border-indigo-200 dark:hover:border-indigo-600 hover:shadow-md transition-all overflow-hidden">
                        <a href="{{ route('tenant.my-courses.show', [$tenant->slug, $course]) }}" class="group p-5 flex items-center gap-4 block">
                            {{-- Course Icon --}}
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center flex-shrink-0 shadow-sm group-hover:shadow-md transition">
                                <span class="text-lg font-bold text-white">{{ strtoupper(substr($course->code, 0, 2)) }}</span>
                            </div>

                            {{-- Course Info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <h3 class="text-base font-bold text-slate-900 dark:text-white truncate group-hover:text-indigo-700 dark:group-hover:text-indigo-400 transition">{{ $course->code }}</h3>
                                    @php $badge = $course->statusBadge; @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700 dark:bg-{{ $badge['color'] }}-900/40 dark:text-{{ $badge['color'] }}-300">{{ $badge['label'] }}</span>
                                </div>
                                <p class="text-sm text-slate-600 dark:text-slate-400 truncate">{{ $course->title }}</p>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs text-slate-400 dark:text-slate-500">
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        {{ $course->lecturer?->name ?? 'Unknown' }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        {{ $item->sections->pluck('name')->implode(', ') }}
                                    </span>
                                    @if($course->credit_hours)
                                        <span>{{ $course->credit_hours }} credit hrs</span>
                                    @endif
                                    <span>{{ $course->num_weeks }} weeks</span>
                                </div>
                            </div>

                            {{-- Arrow --}}
                            <svg class="w-5 h-5 text-slate-300 dark:text-slate-600 group-hover:text-indigo-400 transition flex-shrink-0 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        {{-- Quick Actions --}}
                        <div class="px-5 pb-4 flex items-center gap-2">
                            <a href="{{ route('tenant.materials.student-course', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-100 dark:border-indigo-800 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition" title="Course Materials">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                Materials
                            </a>
                            <a href="{{ route('tenant.my-attendance.course', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-100 dark:border-emerald-800 rounded-lg hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition" title="Attendance">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                Attendance
                            </a>
                            <a href="{{ route('tenant.assignments.index', $tenant->slug) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 border border-amber-100 dark:border-amber-800 rounded-lg hover:bg-amber-100 dark:hover:bg-amber-900/50 transition" title="Assignments">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Assignments
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-tenant-layout>
