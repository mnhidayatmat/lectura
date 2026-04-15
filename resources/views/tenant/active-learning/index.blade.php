<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.courses.show', [app('current_tenant')->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">{{ __('active_learning.title') }}</h2>
                    <p class="text-sm text-slate-500">{{ $course->code }} — {{ $course->title }}</p>
                </div>
            </div>
            <a href="{{ route('tenant.active-learning.create', [app('current_tenant')->slug, $course]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm hover:shadow-md transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('active_learning.new_plan') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-8">
        @if($plans->isNotEmpty())
            <form method="GET" class="flex items-center justify-end gap-2">
                <label for="sort" class="text-xs font-medium text-slate-500 dark:text-slate-400">Sort by</label>
                <select name="sort" id="sort" onchange="this.form.submit()" class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:ring-1 focus:ring-indigo-500">
                    <option value="latest" {{ ($sort ?? 'latest') === 'latest' ? 'selected' : '' }}>Newest first</option>
                    <option value="oldest" {{ ($sort ?? '') === 'oldest' ? 'selected' : '' }}>Oldest first</option>
                    <option value="title_asc" {{ ($sort ?? '') === 'title_asc' ? 'selected' : '' }}>Title (A–Z)</option>
                    <option value="title_desc" {{ ($sort ?? '') === 'title_desc' ? 'selected' : '' }}>Title (Z–A)</option>
                    <option value="week" {{ ($sort ?? '') === 'week' ? 'selected' : '' }}>Week number</option>
                    <option value="duration" {{ ($sort ?? '') === 'duration' ? 'selected' : '' }}>Duration (longest)</option>
                </select>
            </form>
        @endif

        @if($plans->isEmpty())
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-16 text-center">
                <div class="w-20 h-20 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('active_learning.no_plans') }}</h3>
                <p class="text-sm text-slate-500 max-w-md mx-auto mb-6">{{ __('active_learning.no_plans_desc') }}</p>
                <a href="{{ route('tenant.active-learning.create', [app('current_tenant')->slug, $course]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('active_learning.new_plan') }}
                </a>
            </div>
        @else
            {{-- Draft Plans --}}
            @php $draftPlans = $plans->where('status', 'draft'); @endphp
            @if($draftPlans->isNotEmpty())
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-2 h-2 bg-amber-400 rounded-full"></span>
                        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">{{ __('active_learning.drafts') }}</h3>
                        <span class="text-xs text-slate-400">({{ $draftPlans->count() }})</span>
                    </div>
                    <div class="space-y-3">
                        @foreach($draftPlans as $plan)
                            @include('tenant.active-learning._plan-card', ['plan' => $plan])
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Published Plans --}}
            @php $publishedPlans = $plans->where('status', 'published'); @endphp
            @if($publishedPlans->isNotEmpty())
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-2 h-2 bg-emerald-400 rounded-full"></span>
                        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">{{ __('active_learning.published') }}</h3>
                        <span class="text-xs text-slate-400">({{ $publishedPlans->count() }})</span>
                    </div>
                    <div class="space-y-3">
                        @foreach($publishedPlans as $plan)
                            @include('tenant.active-learning._plan-card', ['plan' => $plan])
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Archived Plans --}}
            @php $archivedPlans = $plans->where('status', 'archived'); @endphp
            @if($archivedPlans->isNotEmpty())
                <div x-data="{ showArchived: false }">
                    <button @click="showArchived = !showArchived" class="flex items-center gap-2 mb-3 group">
                        <span class="w-2 h-2 bg-slate-300 rounded-full"></span>
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider group-hover:text-slate-600 transition">{{ __('active_learning.archived') }}</h3>
                        <span class="text-xs text-slate-400">({{ $archivedPlans->count() }})</span>
                        <svg class="w-4 h-4 text-slate-400 transition-transform" :class="showArchived && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="showArchived" x-cloak x-transition class="space-y-3">
                        @foreach($archivedPlans as $plan)
                            @include('tenant.active-learning._plan-card', ['plan' => $plan])
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-tenant-layout>
