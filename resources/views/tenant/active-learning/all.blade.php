<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('nav.active_learning') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('active_learning.all_plans_desc') }}</p>
            </div>
            @if($courses->isNotEmpty())
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm hover:shadow-md transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('active_learning.new_plan') }}
                        <svg class="w-3 h-3 ml-0.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click.away="open = false" x-transition class="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-slate-200 z-50 py-1 overflow-hidden">
                        <p class="px-4 py-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Select course</p>
                        @foreach($courses as $c)
                            <a href="{{ route('tenant.active-learning.create', [$tenant->slug, $c]) }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 transition">
                                <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    <span class="text-[10px] font-bold text-indigo-700">{{ strtoupper(substr($c->code, 0, 2)) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900 truncate">{{ $c->code }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">{{ $c->title }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-slot>

    @if($plans->isNotEmpty())
        {{-- Stats + Filter Bar --}}
        <div x-data="{ filter: 'all' }" class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                {{-- Filter Tabs --}}
                <div class="flex items-center gap-1 bg-slate-100 rounded-xl p-1">
                    <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition">
                        All <span class="ml-1 text-slate-400">{{ $plans->count() }}</span>
                    </button>
                    <button @click="filter = 'draft'" :class="filter === 'draft' ? 'bg-white shadow-sm text-amber-700' : 'text-slate-500 hover:text-slate-700'" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition">
                        Drafts <span class="ml-1 text-slate-400">{{ $plans->where('status', 'draft')->count() }}</span>
                    </button>
                    <button @click="filter = 'published'" :class="filter === 'published' ? 'bg-white shadow-sm text-emerald-700' : 'text-slate-500 hover:text-slate-700'" class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition">
                        Published <span class="ml-1 text-slate-400">{{ $plans->where('status', 'published')->count() }}</span>
                    </button>
                </div>

                {{-- Summary --}}
                <div class="flex items-center gap-4 text-xs text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        {{ $plans->sum('activities_count') }} activities
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $plans->sum('duration_minutes') }} min total
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        {{ $courses->count() }} {{ Str::plural('course', $courses->count()) }}
                    </span>
                </div>
            </div>

            {{-- Course Groups --}}
            <div class="space-y-8">
                @foreach($courses as $course)
                    @php $coursePlans = $plans->where('course_id', $course->id); @endphp
                    @if($coursePlans->isNotEmpty())
                        <div x-show="filter === 'all' || {{ json_encode($coursePlans->pluck('status')->unique()->toArray()) }}.includes(filter)">
                            {{-- Course Header --}}
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center shadow-sm flex-shrink-0">
                                        <span class="text-[10px] font-bold text-white">{{ strtoupper(substr($course->code, 0, 2)) }}</span>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-900">{{ $course->code }} — {{ $course->title }}</h3>
                                        <p class="text-[11px] text-slate-400">{{ $coursePlans->count() }} {{ Str::plural('plan', $coursePlans->count()) }} &middot; {{ $coursePlans->sum('activities_count') }} activities</p>
                                    </div>
                                </div>
                                <a href="{{ route('tenant.active-learning.index', [$tenant->slug, $course]) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-700 flex items-center gap-1 transition">
                                    {{ __('active_learning.view_all') }}
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </div>

                            {{-- Plan Cards --}}
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($coursePlans as $plan)
                                    @php $badge = $plan->statusBadge; @endphp
                                    <div x-show="filter === 'all' || filter === '{{ $plan->status }}'"
                                         class="group bg-white rounded-2xl border border-slate-200 hover:border-indigo-200 hover:shadow-md transition-all overflow-hidden flex flex-col">
                                        {{-- Card Header --}}
                                        <div class="p-4 flex-1">
                                            <div class="flex items-start justify-between gap-2 mb-3">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700">{{ $badge['label'] }}</span>
                                                    @if($plan->source === 'ai_generated')
                                                        <span class="inline-flex items-center gap-0.5 text-[10px] bg-teal-50 text-teal-700 px-1.5 py-0.5 rounded font-medium">
                                                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                                                            AI
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($plan->week_number)
                                                    <span class="w-8 h-8 rounded-lg {{ $plan->status === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }} flex items-center justify-center text-[10px] font-bold flex-shrink-0">W{{ $plan->week_number }}</span>
                                                @endif
                                            </div>

                                            <h4 class="text-sm font-semibold text-slate-900 mb-1 line-clamp-2">{{ $plan->title }}</h4>

                                            @if($plan->topic)
                                                <p class="text-xs text-slate-500 mb-2 truncate">{{ $plan->topic->title }}</p>
                                            @endif

                                            <div class="flex items-center gap-3 text-[11px] text-slate-400">
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                                    {{ $plan->activities_count }} activities
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    {{ $plan->duration_minutes }} min
                                                </span>
                                            </div>
                                        </div>

                                        {{-- Card Footer --}}
                                        <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between">
                                            <div class="flex items-center gap-1.5">
                                                @if($plan->status === 'draft')
                                                    <a href="{{ route('tenant.active-learning.edit', [$tenant->slug, $course, $plan]) }}" class="px-3 py-1.5 text-[11px] font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition">
                                                        {{ __('active_learning.build') }}
                                                    </a>
                                                @endif
                                                <a href="{{ route('tenant.active-learning.show', [$tenant->slug, $course, $plan]) }}" class="px-3 py-1.5 text-[11px] font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                                    {{ __('active_learning.view') }}
                                                </a>
                                            </div>
                                            <form method="POST" action="{{ route('tenant.active-learning.destroy', [$tenant->slug, $course, $plan]) }}" class="opacity-0 group-hover:opacity-100 transition" x-data x-on:submit.prevent="if(confirm('Delete this plan?')) $el.submit()">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Delete">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @else
        {{-- Empty State --}}
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-16 text-center">
            <div class="w-20 h-20 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('active_learning.no_plans') }}</h3>
            <p class="text-sm text-slate-500 max-w-md mx-auto mb-6">{{ __('active_learning.no_plans_all_desc') }}</p>
            @if($courses->isNotEmpty())
                <a href="{{ route('tenant.active-learning.create', [$tenant->slug, $courses->first()]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm hover:shadow-md transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('active_learning.new_plan') }}
                </a>
            @endif
        </div>
    @endif
</x-tenant-layout>
