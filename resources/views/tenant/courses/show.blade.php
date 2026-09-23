<x-tenant-layout>
    @php
        $tenant = app('current_tenant');
        $totalStudents = $course->totalStudents();
        $assessmentWeight = (float) $course->assessments->sum('weightage');
        $mappedClos = $course->learningOutcomes->filter(fn ($clo) => $clo->programmeLearningOutcomes->isNotEmpty())->count();
        $numWeeks = max((int) $course->num_weeks, 1);
        $topicsByWeek = $course->topics->groupBy('week_number');
    @endphp

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                <a href="{{ route('tenant.courses.index', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition flex-shrink-0">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-2xl font-bold text-slate-900 break-all">{{ $course->code }}</h2>
                        @php $badge = $course->statusBadge; @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700 flex-shrink-0">{{ $badge['label'] }}</span>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-500 break-words">{{ $course->title }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="{{ route('tenant.teaching-plan.show', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Teaching Plan
                </a>
                <a href="{{ route('tenant.courses.edit', [$tenant->slug, $course]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Overview --}}
        <div class="grid lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 overflow-hidden">
                <div class="flex items-center gap-x-5 gap-y-2 flex-wrap text-sm text-slate-600">
                    @if($course->faculty)
                        <span class="inline-flex items-center gap-1.5 min-w-0">
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span class="break-words">{{ $course->faculty->name }}</span>
                        </span>
                    @endif
                    @if($course->programme)
                        <span class="inline-flex items-center gap-1.5 min-w-0">
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                            <span class="break-words">{{ $course->programme->name }}</span>
                        </span>
                    @endif
                    @if($semesters->isNotEmpty())
                        <span class="inline-flex items-center gap-1.5 min-w-0">
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="break-words">{{ $semesters->pluck('name')->implode(', ') }}</span>
                        </span>
                    @endif
                    @if($course->teaching_mode)
                        <span class="inline-flex items-center gap-1.5 min-w-0">
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span class="break-words">{{ str_replace('_', ' ', ucfirst($course->teaching_mode)) }}</span>
                        </span>
                    @endif
                    @if($course->format)
                        <span class="inline-flex items-center gap-1.5 min-w-0">
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            <span class="break-words">{{ collect($course->format)->filter()->keys()->map(fn($f) => ucfirst($f))->implode(', ') }}</span>
                        </span>
                    @endif
                </div>

                @if($course->description)
                    <p class="mt-3 text-sm text-slate-600 leading-relaxed break-words whitespace-pre-line line-clamp-3" x-data="{ open: false }" :class="open && 'line-clamp-none'" @click="open = !open">{{ $course->description }}</p>
                @endif

                <div class="mt-4 grid grid-cols-3 sm:grid-cols-6 gap-3">
                    @foreach([
                        ['Students', $totalStudents, 'text-indigo-700'],
                        ['Sections', $course->sections->count(), 'text-slate-900'],
                        ['CLOs', $course->learningOutcomes->count(), 'text-slate-900'],
                        ['Topics', $course->topics->count(), 'text-slate-900'],
                        ['Credits', $course->credit_hours ?? '--', 'text-slate-900'],
                        ['Weeks', $course->num_weeks, 'text-slate-900'],
                    ] as [$label, $value, $color])
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5 overflow-hidden">
                            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider truncate">{{ $label }}</p>
                            <p class="text-xl font-bold {{ $color }} mt-0.5 truncate">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col gap-4">
                {{-- Semester progress --}}
                <div class="bg-white rounded-2xl border border-slate-200 p-5 overflow-hidden" title="Estimated from the semester start date">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Semester Progress</p>
                        @if($currentWeek)
                            <span class="text-xs font-semibold text-teal-700 bg-teal-50 px-2 py-0.5 rounded-full">Week {{ $currentWeek }}</span>
                        @endif
                    </div>
                    @if($currentWeek)
                        <p class="mt-2 text-sm text-slate-600">Week <span class="font-bold text-slate-900">{{ $currentWeek }}</span> of {{ $numWeeks }}<span class="text-slate-400"> · {{ $currentTerm->name }}</span></p>
                        <div class="mt-2 flex gap-0.5">
                            @for($w = 1; $w <= $numWeeks; $w++)
                                <div class="h-2 flex-1 rounded-sm {{ $w < $currentWeek ? 'bg-teal-400' : ($w === $currentWeek ? 'bg-teal-600' : 'bg-slate-100') }}"></div>
                            @endfor
                        </div>
                        @if($topicsByWeek->has($currentWeek))
                            <p class="mt-3 text-xs text-slate-500 truncate">This week: <span class="font-medium text-slate-700">{{ $topicsByWeek[$currentWeek]->pluck('title')->implode(', ') }}</span></p>
                        @endif
                    @else
                        <p class="mt-2 text-sm text-slate-400">
                            {{ $upcomingTerm ? $upcomingTerm->name.' starts '.$upcomingTerm->start_date->format('j M Y') : 'Not in an active semester.' }}
                        </p>
                    @endif
                </div>

                @if($course->invite_code)
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 overflow-hidden" x-data="{ copied: false }">
                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Lecturer Join Code</p>
                        <div class="mt-1 flex items-center justify-between gap-2">
                            <p class="text-lg font-bold text-indigo-700 font-mono tracking-wider break-all">{{ $course->invite_code }}</p>
                            <button type="button" @click="navigator.clipboard.writeText('{{ $course->invite_code }}'); copied = true; setTimeout(() => copied = false, 1500)" class="px-2.5 py-1 text-xs font-medium rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition flex-shrink-0">
                                <span x-show="!copied">Copy</span><span x-show="copied" x-cloak class="text-emerald-600">Copied</span>
                            </button>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1 leading-tight">For co-lecturers only. Students use the section code.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Course Tools --}}
        @php
            $toolGroups = [
                'Teach' => [
                    ['Materials', 'Upload & manage', route('tenant.materials.manage', [$tenant->slug, $course]), 'indigo', 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
                    ['Active Learning', $course->activeLearningPlans->count().' '.Str::plural('plan', $course->activeLearningPlans->count()), route('tenant.active-learning.index', [$tenant->slug, $course]), 'teal', 'M13 10V3L4 14h7v7l9-11h-7z'],
                    ['Quizzes', 'Live quizzes', route('tenant.quizzes.course', [$tenant->slug, $course]), 'amber', 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['Whiteboards', 'Collaborative', route('tenant.whiteboards.index', [$tenant->slug, $course]), 'fuchsia', 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z'],
                ],
                'Assess' => [
                    ['Assessments', $course->assessments->count().' planned', route('tenant.assessments.index', [$tenant->slug, $course]), 'rose', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                    ['Reports', 'CLO attainment', route('tenant.assessment-reports.course', [$tenant->slug, $course]), 'sky', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                    ['Performance', 'Student insights', route('tenant.performance.course', [$tenant->slug, $course]), 'violet', 'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z'],
                    ['CLO–PLO Map', $mappedClos.'/'.$course->learningOutcomes->count().' mapped', route('tenant.clo-plo.edit', [$tenant->slug, $course]), 'indigo', 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'],
                ],
                'Class' => [
                    ['Attendance', 'QR check-in', route('tenant.attendance.course', [$tenant->slug, $course]), 'emerald', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                    ['Att. Report', 'PDF & Excel', route('tenant.attendance.report', [$tenant->slug, $course]), 'rose', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ['Att. Policy', 'Warning rules', route('tenant.courses.attendance-policy.edit', [$tenant->slug, $course]), 'cyan', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
                    ['Groups', $course->studentGroupSets->count().' '.Str::plural('set', $course->studentGroupSets->count()), route('tenant.student-groups.index', [$tenant->slug, $course]), 'violet', 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
                ],
                'Records' => [
                    ['Course Files', 'Compliance folders', route('tenant.files.manage', [$tenant->slug, $course]), 'slate', 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z'],
                    ['Portfolio', 'Teaching evidence', route('tenant.portfolio.course', [$tenant->slug, $course]), 'amber', 'M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z M15 13a3 3 0 11-6 0 3 3 0 016 0z'],
                ],
            ];
        @endphp
        @php
            $toolPalette = [
                'indigo' => ['bg-indigo-100 group-hover:bg-indigo-200', 'text-indigo-600'],
                'teal' => ['bg-teal-100 group-hover:bg-teal-200', 'text-teal-600'],
                'amber' => ['bg-amber-100 group-hover:bg-amber-200', 'text-amber-600'],
                'fuchsia' => ['bg-fuchsia-100 group-hover:bg-fuchsia-200', 'text-fuchsia-600'],
                'rose' => ['bg-rose-100 group-hover:bg-rose-200', 'text-rose-600'],
                'sky' => ['bg-sky-100 group-hover:bg-sky-200', 'text-sky-600'],
                'violet' => ['bg-violet-100 group-hover:bg-violet-200', 'text-violet-600'],
                'emerald' => ['bg-emerald-100 group-hover:bg-emerald-200', 'text-emerald-600'],
                'cyan' => ['bg-cyan-100 group-hover:bg-cyan-200', 'text-cyan-600'],
                'slate' => ['bg-slate-100 group-hover:bg-slate-200', 'text-slate-600'],
            ];
        @endphp
        <div class="bg-white rounded-2xl border border-slate-200 p-5 overflow-hidden">
            <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-5">
                @foreach($toolGroups as $groupName => $tools)
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">{{ $groupName }}</p>
                        <div class="space-y-1">
                            @foreach($tools as [$label, $sub, $url, $color, $icon])
                                <a href="{{ $url }}" class="group flex items-center gap-3 px-2 py-2 -mx-2 rounded-xl hover:bg-slate-50 transition">
                                    <div class="w-9 h-9 rounded-lg {{ $toolPalette[$color][0] }} flex items-center justify-center flex-shrink-0 transition">
                                        <svg class="w-[18px] h-[18px] {{ $toolPalette[$color][1] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-900 truncate">{{ $label }}</p>
                                        <p class="text-[11px] text-slate-400 truncate">{{ $sub }}</p>
                                    </div>
                                    <svg class="w-4 h-4 text-slate-300 opacity-0 group-hover:opacity-100 group-hover:text-indigo-400 transition flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Two Column: Sections + Assessment Plan --}}
        <div class="grid lg:grid-cols-2 gap-6">
            {{-- Sections --}}
            <div class="bg-white rounded-2xl border border-slate-200">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-slate-900 text-sm">Sections</h3>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold">{{ $course->sections->count() }}</span>
                    </div>
                    @if($isOwner)
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button @click="open = !open" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add
                        </button>
                        <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 z-20">
                            <form method="POST" action="{{ route('tenant.courses.sections.store', [$tenant->slug, $course]) }}" class="bg-white rounded-xl border border-slate-200 shadow-lg p-4 space-y-3 w-80">
                                @csrf
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" name="name" placeholder="Section 01" required class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                                    <input type="text" name="code" placeholder="SEC01" required class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="number" name="capacity" placeholder="Capacity" min="1" class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                                    <select name="academic_term_id" class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500 bg-white">
                                        <option value="">Semester</option>
                                        @foreach($terms as $term)
                                            <option value="{{ $term->id }}" {{ $term->is_default ? 'selected' : '' }}>{{ $term->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <label class="block text-[10px] font-medium text-slate-500 mb-1">Assign lecturers (optional)</label>
                                <div class="space-y-1 max-h-32 overflow-y-auto border border-slate-300 rounded-lg p-2 bg-white">
                                    @foreach($lecturers as $lecturer)
                                        <label class="flex items-center gap-2 px-1 py-0.5 rounded hover:bg-slate-50 cursor-pointer">
                                            <input type="checkbox" name="lecturer_ids[]" value="{{ $lecturer->id }}" {{ $lecturer->id === $course->lecturer_id ? 'checked' : '' }}
                                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-3.5 h-3.5" />
                                            <span class="text-xs text-slate-700">{{ $lecturer->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <button type="submit" class="w-full px-2.5 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg">Create Section</button>
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="max-h-[32rem] overflow-y-auto rounded-b-2xl">
                    @forelse($semesterGroups as $group)
                        <div class="sticky top-0 z-10 px-5 py-2 bg-slate-50/95 backdrop-blur border-b border-slate-100 flex items-center gap-2">
                            <span class="text-xs font-semibold text-slate-700">{{ $group['term']?->name ?? 'No semester' }}</span>
                            @if($group['term']?->isCurrent())
                                <span class="text-[10px] font-semibold text-teal-700 bg-teal-50 px-1.5 py-0.5 rounded">Current</span>
                            @endif
                            @if($group['term']?->isClosed())
                                <span class="text-[10px] font-semibold text-slate-500 bg-slate-200 px-1.5 py-0.5 rounded">Closed</span>
                            @endif
                            <span class="text-[11px] text-slate-400 ml-auto">{{ $group['sections']->count() }} {{ Str::plural('section', $group['sections']->count()) }} · {{ $group['sections']->sum(fn ($s) => $s->activeStudents->count()) }} students</span>
                        </div>
                        <div class="divide-y divide-slate-50">
                    @foreach($group['sections'] as $section)
                        @php
                            $enrolled = $section->activeStudents->count();
                            $fill = $section->capacity ? min(100, (int) round($enrolled / $section->capacity * 100)) : null;
                        @endphp
                        <div class="relative flex items-start justify-between gap-3 px-5 py-3 hover:bg-slate-50/50 transition group {{ !$section->is_active ? 'opacity-50' : '' }}">
                            <a href="{{ route('tenant.courses.sections.show', [$tenant->slug, $course, $section]) }}" class="absolute inset-0" aria-label="Open {{ $section->name }}"></a>
                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                <div class="w-9 h-9 rounded-xl {{ $section->is_active ? 'bg-emerald-100' : 'bg-slate-100' }} flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-bold {{ $section->is_active ? 'text-emerald-700' : 'text-slate-400' }}">{{ substr($section->name, 0, 2) }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="text-sm font-medium text-slate-900 break-words">{{ $section->name }}</p>
                                        <span class="text-[11px] text-slate-400 break-all">{{ $section->code }}</span>
                                        @unless($section->is_active)
                                            <span class="bg-red-50 text-red-600 px-1 py-0.5 rounded text-[10px] font-medium flex-shrink-0">Inactive</span>
                                        @endunless
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[11px] text-slate-400">
                                        <button type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText('{{ $section->invite_code }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                                class="relative z-10 inline-flex items-center gap-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded text-[10px] font-medium transition" title="Click to copy. Share this code with students to let them enroll in this section.">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                            <span x-show="!copied">Student code: <code class="font-mono font-bold break-all">{{ $section->invite_code }}</code></span>
                                            <span x-show="copied" x-cloak>Copied!</span>
                                        </button>
                                        @if($section->lecturers->isNotEmpty())
                                            @foreach($section->lecturers as $sectionLecturer)
                                                <span class="bg-indigo-50 text-indigo-600 px-1 py-0.5 rounded text-[10px] font-medium break-words">{{ $sectionLecturer->name }}</span>
                                            @endforeach
                                        @elseif($isOwner)
                                            <span class="bg-slate-50 text-slate-400 px-1 py-0.5 rounded text-[10px] font-medium italic">Unassigned</span>
                                        @endif
                                    </div>
                                    @if($section->schedule)
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach($section->schedule as $slot)
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium break-words
                                                    {{ $slot['type'] === 'lecture' ? 'bg-blue-50 text-blue-600' : ($slot['type'] === 'tutorial' ? 'bg-amber-50 text-amber-600' : ($slot['type'] === 'lab' ? 'bg-purple-50 text-purple-600' : 'bg-slate-100 text-slate-500')) }}">
                                                    {{ ucfirst(substr($slot['day'], 0, 3)) }}
                                                    {{ $slot['start_time'] }}-{{ $slot['end_time'] }}
                                                    @if(!empty($slot['location']))
                                                        &middot; {{ $slot['location'] }}
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0 pt-1">
                                <div class="text-right">
                                    <p class="text-sm font-bold text-slate-900 leading-tight">{{ $enrolled }}@if($section->capacity)<span class="text-slate-400 font-medium">/{{ $section->capacity }}</span>@endif</p>
                                    @if($fill !== null)
                                        <div class="mt-1 w-14 h-1 rounded-full bg-slate-100 overflow-hidden">
                                            <div class="h-full rounded-full {{ $fill >= 100 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ $fill }}%"></div>
                                        </div>
                                    @else
                                        <p class="text-[10px] text-slate-400 leading-tight">students</p>
                                    @endif
                                </div>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </div>
                    @endforeach
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-400">No sections yet. Add a section for the semester you are teaching to start enrolling students.</div>
                    @endforelse
                </div>
            </div>

            {{-- Assessment Plan --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-slate-900 text-sm">Assessment Plan</h3>
                        <span class="inline-flex items-center px-2 h-5 rounded-full text-[10px] font-bold {{ abs($assessmentWeight - 100) < 0.01 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}" title="Total weightage of top-level assessments">
                            {{ rtrim(rtrim(number_format($assessmentWeight, 2), '0'), '.') }}%
                        </span>
                    </div>
                    <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Manage</a>
                </div>
                @if($course->assessments->isNotEmpty())
                    <div class="px-5 pt-4">
                        <div class="flex h-2 rounded-full overflow-hidden bg-slate-100">
                            @php $barColors = ['bg-indigo-500', 'bg-teal-500', 'bg-amber-500', 'bg-rose-500', 'bg-violet-500', 'bg-sky-500', 'bg-emerald-500', 'bg-fuchsia-500']; @endphp
                            @foreach($course->assessments as $i => $assessment)
                                <div class="{{ $barColors[$i % count($barColors)] }} h-full" style="width: {{ min(100, (float) $assessment->weightage) }}%" title="{{ $assessment->title }} — {{ (float) $assessment->weightage }}%"></div>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="divide-y divide-slate-50 max-h-96 overflow-y-auto">
                    @forelse($course->assessments as $i => $assessment)
                        <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="px-5 py-3 flex items-center justify-between gap-3 hover:bg-slate-50/50 transition">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-2.5 h-2.5 rounded-full {{ $barColors[$i % count($barColors)] }} flex-shrink-0"></span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900 truncate">{{ $assessment->title }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">
                                        {{ ucwords(str_replace('_', ' ', $assessment->type)) }}
                                        @if($assessment->due_date)
                                            &middot; Due {{ $assessment->due_date->format('j M') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @php $statusClass = ['active' => 'bg-emerald-50 text-emerald-600', 'completed' => 'bg-indigo-50 text-indigo-600'][$assessment->status] ?? 'bg-slate-100 text-slate-500'; @endphp
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium {{ $statusClass }}">{{ ucfirst($assessment->status) }}</span>
                                <span class="text-sm font-bold text-slate-900 w-12 text-right">{{ (float) $assessment->weightage }}%</span>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-400">
                            No assessments planned yet.
                            <a href="{{ route('tenant.assessments.create', [$tenant->slug, $course]) }}" class="block mt-2 text-indigo-600 hover:text-indigo-700 font-medium">Create the first assessment</a>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- CLOs --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2">
                    <h3 class="font-semibold text-slate-900 text-sm">Learning Outcomes</h3>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-bold">{{ $course->learningOutcomes->count() }}</span>
                    @if($course->learningOutcomes->isNotEmpty())
                        <a href="{{ route('tenant.clo-plo.edit', [$tenant->slug, $course]) }}" class="text-[11px] text-slate-400 hover:text-indigo-600 transition">{{ $mappedClos }}/{{ $course->learningOutcomes->count() }} mapped to PLOs</a>
                    @endif
                </div>
                <form method="POST" action="{{ route('tenant.courses.clos.store', [$tenant->slug, $course]) }}" class="flex items-center gap-2" x-data="{ show: false }">
                    @csrf
                    <template x-if="show">
                        <div class="flex items-center gap-2">
                            <input type="text" name="code" placeholder="CLO#" required class="w-16 px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <input type="text" name="description" placeholder="Description..." required class="w-48 sm:w-72 px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <button type="submit" class="px-2.5 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg">Add</button>
                            <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                    </template>
                    <button type="button" x-show="!show" @click="show = true" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add
                    </button>
                </form>
            </div>
            <div class="divide-y divide-slate-50">
                @forelse($course->learningOutcomes as $clo)
                    <div class="px-5 py-3 flex items-start justify-between gap-3 group hover:bg-slate-50/50 transition">
                        <div class="flex items-start gap-3 min-w-0">
                            <span class="inline-flex items-center justify-center px-2.5 py-1 bg-indigo-50 text-indigo-700 text-[10px] font-bold rounded-md flex-shrink-0 mt-0.5">{{ $clo->code }}</span>
                            <div class="min-w-0">
                                <p class="text-sm text-slate-700 break-words">{{ $clo->description }}</p>
                                @if($clo->programmeLearningOutcomes->isNotEmpty())
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach($clo->programmeLearningOutcomes as $plo)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-violet-50 text-violet-600" title="{{ $plo->description }}">{{ $plo->code }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('tenant.courses.clos.destroy', [$tenant->slug, $course, $clo]) }}" class="opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-1 text-slate-400 hover:text-red-500" onclick="return confirm('Remove this CLO?')">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-slate-400">No CLOs defined yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Weekly Topics --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h3 class="font-semibold text-slate-900 text-sm">Weekly Topics</h3>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-teal-100 text-teal-700 text-[10px] font-bold">{{ $course->topics->count() }}</span>
                </div>
                <form method="POST" action="{{ route('tenant.courses.topics.store', [$tenant->slug, $course]) }}" class="flex items-center gap-2" x-data="{ show: false }">
                    @csrf
                    <template x-if="show">
                        <div class="flex items-center gap-2">
                            <input type="number" name="week_number" placeholder="Wk" min="1" required class="w-14 px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <input type="text" name="title" placeholder="Topic title..." required class="w-48 sm:w-64 px-2 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500" />
                            <button type="submit" class="px-2.5 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg">Add</button>
                            <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                    </template>
                    <button type="button" x-show="!show" @click="show = true" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add
                    </button>
                </form>
            </div>
            @if($course->topics->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-slate-400">No weekly topics defined yet.</div>
            @else
                <div class="grid sm:grid-cols-2 gap-px bg-slate-100">
                    @php $closById = $course->learningOutcomes->keyBy('id'); @endphp
                    @foreach($course->topics as $topic)
                        @php
                            $isCurrent = $currentWeek && (int) $topic->week_number === $currentWeek;
                            $isPast = $currentWeek && (int) $topic->week_number < $currentWeek;
                            $topicCloIds = collect($topic->clo_ids ?? [])->map(fn ($id) => (int) $id);
                            $topicClos = $topicCloIds->map(fn ($id) => $closById->get($id))->filter();
                        @endphp
                        <div class="px-5 py-3 flex items-start justify-between gap-2 group transition {{ $isCurrent ? 'bg-teal-50' : 'bg-white hover:bg-slate-50/50' }}" x-data="{ editClos: false }">
                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                <span class="inline-flex items-center justify-center w-9 h-7 text-[10px] font-bold rounded-md flex-shrink-0 {{ $isCurrent ? 'bg-teal-600 text-white' : 'bg-teal-50 text-teal-700' }}">W{{ $topic->week_number }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="text-sm truncate {{ $isPast ? 'text-slate-400' : 'text-slate-700' }} {{ $isCurrent ? 'font-semibold' : '' }}" @if($topic->description) title="{{ $topic->description }}" @endif>{{ $topic->title }}</span>
                                        @if($isCurrent)
                                            <span class="text-[10px] font-semibold text-teal-700 uppercase tracking-wider flex-shrink-0">Now</span>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1" x-show="!editClos">
                                        @forelse($topicClos as $clo)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700" title="{{ $clo->description }}">{{ $clo->code }}</span>
                                        @empty
                                            @if($course->learningOutcomes->isNotEmpty())
                                                <span class="text-[10px] text-amber-600">No CLO linked</span>
                                            @endif
                                        @endforelse
                                        @if($course->learningOutcomes->isNotEmpty())
                                            <button type="button" @click="editClos = true" class="text-[10px] text-slate-400 hover:text-indigo-600 font-medium opacity-0 group-hover:opacity-100 transition">Edit CLOs</button>
                                        @endif
                                    </div>
                                    @if($course->learningOutcomes->isNotEmpty())
                                        <form x-show="editClos" x-cloak method="POST" action="{{ route('tenant.courses.topics.update', [$tenant->slug, $course, $topic]) }}" class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            @csrf @method('PUT')
                                            @foreach($course->learningOutcomes as $clo)
                                                <label class="cursor-pointer" title="{{ $clo->description }}">
                                                    <input type="checkbox" name="clo_ids[]" value="{{ $clo->id }}" class="peer sr-only" {{ $topicCloIds->contains($clo->id) ? 'checked' : '' }}>
                                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold border border-slate-300 text-slate-500 peer-checked:bg-indigo-600 peer-checked:border-indigo-600 peer-checked:text-white transition">{{ $clo->code }}</span>
                                                </label>
                                            @endforeach
                                            <button type="submit" class="px-2 py-0.5 bg-indigo-600 text-white text-[10px] font-medium rounded">Save</button>
                                            <button type="button" @click="editClos = false" class="text-[10px] text-slate-400 hover:text-slate-600">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('tenant.courses.topics.destroy', [$tenant->slug, $course, $topic]) }}" class="opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1 text-slate-400 hover:text-red-500" onclick="return confirm('Remove this topic?')">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-tenant-layout>
