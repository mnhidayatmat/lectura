{{-- Course switcher: one command-palette modal for the whole lecturer layout.
     Opened by the sidebar course card, the topbar pill, or Cmd/Ctrl+K. --}}
@php
    $switcherCourses = ($accessibleCoursesForSwitcher ?? collect())->reject(fn ($c) => $c->status === 'archived')->values();
    $switcherCurrent = app()->bound('current_course') ? app('current_course') : null;
    $switcherService = app(\App\Services\Course\CourseContextService::class);
@endphp

@if($switcherCourses->isNotEmpty())
<div x-data="courseSwitcher()" x-cloak
     @open-course-switcher.window="show()"
     @keydown.window.meta.k.prevent="toggle()"
     @keydown.window.ctrl.k.prevent="toggle()"
     @keydown.escape.window="open && hide()">
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-[70] bg-slate-900/60 backdrop-blur-sm" @click="hide()"></div>

    <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 -translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-x-4 top-[10vh] z-[71] mx-auto max-w-lg bg-white dark:bg-[#242d3d] rounded-2xl shadow-2xl ring-1 ring-slate-900/10 dark:ring-[#354158] overflow-hidden"
         role="dialog" aria-modal="true" aria-label="{{ __('nav.switch_course') }}">

        <div class="flex items-center gap-3 px-4 border-b border-slate-100 dark:border-[#354158]">
            <svg class="w-5 h-5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input x-ref="search" x-model="q" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="choose()"
                   type="text" placeholder="{{ __('nav.search_courses') }}" autocomplete="off"
                   class="flex-1 py-4 bg-transparent border-0 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:ring-0 focus:outline-none">
            <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded border border-slate-200 dark:border-[#354158] text-[10px] font-medium text-slate-400">ESC</kbd>
        </div>

        <div x-ref="list" class="max-h-[55vh] overflow-y-auto py-2">
            <p class="px-4 pt-1 pb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('nav.switch_course') }}</p>
            @foreach($switcherCourses as $c)
                @php
                    $isCurrent = $switcherCurrent && $switcherCurrent->id === $c->id;
                    $cTerm = $switcherService->termFor($c);
                @endphp
                <form method="POST" action="{{ route('tenant.course-context.select', $currentTenant->slug) }}"
                      data-row data-search="{{ strtolower($c->code.' '.$c->title) }}"
                      x-show="matches($el.dataset.search)">
                    @csrf
                    <input type="hidden" name="course_id" value="{{ $c->id }}">
                    <input type="hidden" name="redirect" value="{{ $switcherService->switchUrl(request(), $currentTenant, $c) }}">
                    <button type="submit" @mouseenter="hover($el)"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-left transition"
                            :class="isActive($el) ? 'bg-indigo-50 dark:bg-indigo-500/10' : ''">
                        <x-course-avatar :course="$c" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $c->code }}</span>
                                @if($cTerm?->isCurrent())
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-teal-50 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300">{{ $cTerm->name }}</span>
                                @elseif($cTerm)
                                    <span class="text-[10px] text-slate-400">{{ $cTerm->name }}</span>
                                @endif
                            </span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 truncate">{{ $c->title }}</span>
                        </span>
                        @if($isCurrent)
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        @endif
                    </button>
                </form>
            @endforeach
            <p x-show="empty" class="px-4 py-6 text-center text-sm text-slate-400">{{ __('nav.no_course_match') }}</p>
        </div>

        <div class="flex items-center justify-between gap-2 px-4 py-3 border-t border-slate-100 dark:border-[#354158] bg-slate-50/60 dark:bg-[#1f2838]">
            <a href="{{ route('tenant.course-context.picker', $currentTenant->slug) }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                {{ __('nav.course_picker') }}
            </a>
            <a href="{{ route('tenant.courses.index', $currentTenant->slug) }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                {{ __('nav.manage_courses') }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</div>

<script>
    function courseSwitcher() {
        return {
            open: false,
            q: '',
            active: 0,
            empty: false,
            show() {
                this.open = true;
                this.q = '';
                this.active = 0;
                this.$nextTick(() => this.$refs.search.focus());
            },
            hide() { this.open = false; },
            toggle() { this.open ? this.hide() : this.show(); },
            matches(text) {
                const q = this.q.trim().toLowerCase();
                return q === '' || q.split(/\s+/).every(part => text.includes(part));
            },
            rows() {
                return [...this.$refs.list.querySelectorAll('[data-row]')].filter(row => this.matches(row.dataset.search));
            },
            isActive(button) {
                return this.rows()[this.active] === button.closest('form');
            },
            hover(button) {
                this.active = this.rows().indexOf(button.closest('form'));
            },
            move(step) {
                const count = this.rows().length;
                if (count) this.active = (this.active + step + count) % count;
            },
            choose() {
                const row = this.rows()[this.active];
                if (row) row.submit();
            },
            init() {
                this.$watch('q', () => {
                    this.active = 0;
                    this.empty = this.rows().length === 0;
                });
            },
        };
    }
</script>
@endif
