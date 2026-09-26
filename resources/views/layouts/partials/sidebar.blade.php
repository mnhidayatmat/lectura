{{-- Mobile sidebar backdrop --}}
<div x-show="sidebarOpen" x-cloak x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false">
</div>

@php
    $tenant = $currentTenant;
    $prefix = $tenant ? '/' . $tenant->slug : '#';
    $active = fn($pattern, $exclude = null) => request()->is($pattern) && ($exclude === null || !request()->is(...(array) $exclude)) ? true : false;
    $on = fn (...$names) => request()->routeIs(...$names);

    // Course sub-pages that have their own sidebar entry — that entry wins, Courses yields.
    $courseSubPages = ['*/active-learning*', '*/assessments*', '*/whiteboards*', '*/attendance-policy*'];

    $contextCourse = app()->bound('current_course') ? app('current_course') : null;
    $courseRoute = fn (string $name) => route($name, ['tenant' => $tenant->slug, 'course' => $contextCourse->id]);

    $icons = [
        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'dashboard' => 'M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2zm10-2a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1h-4a1 1 0 01-1-1v-5z',
        'info' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'book' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
        'plan' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
        'bolt' => 'M13 10V3L4 14h7v7l9-11h-7z',
        'quiz' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'pen' => 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z',
        'check' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        'wheel' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        'group' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        'people' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        'clipboard' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        'report' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        'chart' => 'M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        'link' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
        'folder' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        'camera' => 'M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z M15 13a3 3 0 11-6 0 3 3 0 016 0z',
        'grid' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'cog' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    ];
@endphp

{{-- Sidebar --}}
<div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
     class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-900 -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 flex flex-col">

    {{-- Logo area --}}
    <div class="flex h-16 items-center gap-3 px-6 border-b border-slate-800/80 flex-shrink-0">
        <img src="/icons/icon-192x192.png" alt="Lectura" class="w-8 h-8 rounded-lg">
        <div class="min-w-0">
            <span class="text-lg font-bold text-white">Lectura</span>
            @if($currentTenant)
                <p class="text-[11px] text-slate-500 truncate leading-none mt-0.5">{{ $currentTenant->name }}</p>
            @endif
        </div>
    </div>

    @if($contextCourse)
        {{-- Active course: the whole sidebar belongs to it --}}
        @php
            $sidebarTerm = app(\App\Services\Course\CourseContextService::class)->termFor($contextCourse);
            $canSwitch = ($accessibleCoursesForSwitcher ?? collect())->where('status', '!=', 'archived')->count() > 1;
        @endphp
        <div class="px-4 pt-4 flex-shrink-0">
            <button type="button" @click="$dispatch('open-course-switcher'); sidebarOpen = false"
                    class="w-full flex items-center gap-3 p-2.5 rounded-2xl bg-slate-800/70 hover:bg-slate-800 border border-slate-700/60 hover:border-slate-600 transition group text-left"
                    title="{{ __('nav.switch_course') }}">
                <x-course-avatar :course="$contextCourse" size="md" />
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-white truncate leading-tight">{{ $contextCourse->code }}</span>
                    <span class="block text-[11px] text-slate-400 truncate leading-tight mt-0.5">{{ $contextCourse->title }}</span>
                    <span class="block text-[10px] text-slate-500 truncate leading-tight mt-0.5">{{ $sidebarTerm?->name ?? __('nav.no_semester') }}</span>
                </span>
                <svg class="w-4 h-4 text-slate-500 group-hover:text-slate-300 transition flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
            </button>
            @if($canSwitch)
                <p class="hidden lg:flex items-center justify-center gap-1 mt-1.5 text-[10px] text-slate-600">
                    {{ __('nav.shortcut_hint') }}
                    <kbd class="px-1 rounded border border-slate-700 text-slate-500 font-sans" x-text="/Mac|iPhone|iPad/.test(navigator.platform) ? '⌘K' : 'Ctrl K'">⌘K</kbd>
                </p>
            @endif
        </div>

        <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-1">
            <x-sidebar-link :href="$courseRoute('tenant.courses.show')" :active="$on('tenant.courses.show', 'tenant.courses.edit', 'tenant.courses.sections.*', 'tenant.courses.attendance-policy.*')" :icon="$icons['home']">{{ __('nav.course_overview') }}</x-sidebar-link>

            <p class="pt-4 pb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-600">{{ __('nav.teaching') }}</p>
            <x-sidebar-link :href="$courseRoute('tenant.teaching-plan.show')" :active="$on('tenant.teaching-plan.*')" :icon="$icons['plan']">{{ __('nav.teaching_plan') }}</x-sidebar-link>
            <x-sidebar-link :href="$courseRoute('tenant.materials.manage')" :active="$on('tenant.materials.*')" :icon="$icons['book']">{{ __('nav.materials') }}</x-sidebar-link>
            <x-sidebar-link :href="$courseRoute('tenant.active-learning.index')" :active="$on('tenant.active-learning.*')" :icon="$icons['bolt']">{{ __('nav.active_learning') }}</x-sidebar-link>
            <x-sidebar-link :href="$courseRoute('tenant.quizzes.course')" :active="$on('tenant.quizzes.*')" :icon="$icons['quiz']">{{ __('nav.quizzes') }}</x-sidebar-link>
            <x-sidebar-link :href="$courseRoute('tenant.whiteboards.index')" :active="$on('tenant.whiteboards.*')" :icon="$icons['pen']">{{ __('nav.whiteboards') }}</x-sidebar-link>

            <p class="pt-4 pb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-600">{{ __('nav.classroom') }}</p>
            <x-sidebar-link :href="$courseRoute('tenant.attendance.course')" :active="$on('tenant.attendance.*')" :icon="$icons['check']">{{ __('nav.attendance') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/random-wheel'" :active="$on('tenant.random-wheel*')" :icon="$icons['wheel']">{{ __('nav.random_wheel') }}</x-sidebar-link>
            <x-sidebar-link :href="$courseRoute('tenant.student-groups.index')" :active="$on('tenant.student-groups.*')" :icon="$icons['group']">{{ __('nav.student_groups') }}</x-sidebar-link>

            <p class="pt-4 pb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-600">{{ __('nav.assess') }}</p>
            <x-sidebar-link :href="$courseRoute('tenant.assessments.index')" :active="$on('tenant.assessments.*')" :icon="$icons['clipboard']">{{ __('nav.assessments') }}</x-sidebar-link>
            <x-sidebar-link :href="$courseRoute('tenant.assessment-reports.course')" :active="$on('tenant.assessment-reports.*')" :icon="$icons['report']">{{ __('nav.clo_reports') }}</x-sidebar-link>
            <x-sidebar-link :href="$courseRoute('tenant.performance.course')" :active="$on('tenant.performance.*', 'tenant.analytics.*')" :icon="$icons['chart']">{{ __('performance.title') }}</x-sidebar-link>
            <x-sidebar-link :href="$courseRoute('tenant.clo-plo.edit')" :active="$on('tenant.clo-plo.*')" :icon="$icons['link']">{{ __('nav.clo_plo') }}</x-sidebar-link>

            <p class="pt-4 pb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-600">{{ __('nav.records') }}</p>
            <x-sidebar-link :href="$courseRoute('tenant.files.manage')" :active="$on('tenant.files.*')" :icon="$icons['folder']">{{ __('nav.course_files') }}</x-sidebar-link>
            <x-sidebar-link :href="$courseRoute('tenant.portfolio.course')" :active="$on('tenant.portfolio.*')" :icon="$icons['camera']">{{ __('nav.portfolio') }}</x-sidebar-link>

            <p class="pt-4 pb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-600">{{ __('nav.general') }}</p>
            <x-sidebar-link :href="$prefix.'/courses'" :active="$on('tenant.courses.index', 'tenant.courses.create', 'tenant.course-context.*')" :icon="$icons['grid']">{{ __('nav.all_courses') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/semesters'" :active="$on('tenant.academic-terms.*')" :icon="$icons['calendar']">{{ __('nav.semesters') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/settings'" :active="$on('tenant.settings*')" :icon="$icons['cog']">{{ __('nav.settings') }}</x-sidebar-link>
            @if(in_array($userRole, ['admin', 'coordinator']))
                <x-sidebar-link :href="$prefix.'/admin/settings'" :active="$active('*/admin/settings*')" :icon="$icons['cog']">{{ __('nav.admin') }}</x-sidebar-link>
            @endif
        </nav>
    @else
        {{-- No course selected yet --}}
        <nav class="flex-1 overflow-y-auto px-4 py-5 space-y-1.5">
            <x-sidebar-link :href="$prefix.'/dashboard'" :active="$active('*/dashboard')" :icon="$icons['dashboard']">{{ __('nav.dashboard') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/courses'" :active="$active('*/courses*', $courseSubPages)" :icon="$icons['book']">{{ __('nav.courses') }}</x-sidebar-link>

            <p class="pt-5 pb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-600">{{ __('nav.teaching') }}</p>
            <x-sidebar-link :href="$prefix.'/attendance'" :active="$active('*/attendance*')" :icon="$icons['check']">{{ __('nav.attendance') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/random-wheel'" :active="$active('*/random-wheel*')" :icon="$icons['wheel']">{{ __('nav.random_wheel') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/quizzes'" :active="$active('*/quizzes*')" :icon="$icons['quiz']">{{ __('nav.quizzes') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/assessments'" :active="$active('*/assessments*')" :icon="$icons['clipboard']">{{ __('nav.assessments') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/active-learning'" :active="$active('*/active-learning*')" :icon="$icons['people']">{{ __('nav.active_learning') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/my-groups'" :active="$active('*/my-groups*', '*/groups*')" :icon="$icons['group']">{{ __('nav.student_groups') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/whiteboards'" :active="$active('*/whiteboards*')" :icon="$icons['pen']">{{ __('nav.whiteboards') }}</x-sidebar-link>

            <p class="pt-5 pb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-600">{{ __('nav.management') }}</p>
            <x-sidebar-link :href="$prefix.'/materials'" :active="$active('*/materials*')" :icon="$icons['book']">{{ __('nav.materials') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/files'" :active="$active('*/files*')" :icon="$icons['folder']">{{ __('nav.course_files') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/portfolio'" :active="$active('*/portfolio*')" :icon="$icons['camera']">{{ __('nav.portfolio') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/performance'" :active="$active('*/performance*')" :icon="$icons['chart']">{{ __('performance.title') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/semesters'" :active="$active('*/semesters*')" :icon="$icons['calendar']">{{ __('nav.semesters') }}</x-sidebar-link>
            <x-sidebar-link :href="$prefix.'/settings'" :active="$active('*/settings*', '*/admin/settings*')" :icon="$icons['cog']">{{ __('nav.settings') }}</x-sidebar-link>

            @if(in_array($userRole, ['admin', 'coordinator']))
                <p class="pt-5 pb-1 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-600">{{ __('nav.admin') }}</p>
                <x-sidebar-link :href="$prefix.'/admin/settings'" :active="$active('*/admin/settings*')" :icon="$icons['cog']">{{ __('nav.settings') }}</x-sidebar-link>
            @endif
        </nav>
    @endif

    {{-- Sidebar footer --}}
    <div class="flex-shrink-0 border-t border-slate-800/80 p-4">
        <div class="flex items-center gap-3 px-2">
            <div class="w-9 h-9 rounded-full bg-indigo-600/20 flex items-center justify-center text-sm font-bold text-indigo-400">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-200 truncate">{{ auth()->user()->name }}</p>
                <p class="text-xs text-slate-500 truncate">{{ ucfirst($userRole) }}</p>
            </div>
        </div>
    </div>
</div>
