<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.active-learning.index', [app('current_tenant')->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-bold text-slate-900">{{ $plan->title }}</h2>
                        @php $badge = $plan->statusBadge; @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700">{{ $badge['label'] }}</span>
                        @if($plan->isAiGenerated())
                            <span class="inline-flex items-center gap-0.5 text-[10px] bg-teal-50 text-teal-700 px-1.5 py-0.5 rounded font-medium">
                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                                AI
                            </span>
                        @endif
                    </div>
                    <p class="mt-0.5 text-sm text-slate-500">{{ $course->code }} — {{ $plan->topic?->title ?? __('active_learning.standalone') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('tenant.active-learning.edit', [app('current_tenant')->slug, $course, $plan]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    {{ __('active_learning.edit') }}
                </a>
                @if($plan->isPublished() && !$plan->activeSession())
                    <form method="POST" action="{{ route('tenant.active-learning.sessions.start', [app('current_tenant')->slug, $course, $plan]) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm hover:shadow-md transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('active_learning.start_live_session') }}
                        </button>
                    </form>
                @elseif($plan->activeSession())
                    <a href="{{ route('tenant.active-learning.sessions.dashboard', [app('current_tenant')->slug, $course, $plan, $plan->activeSession()]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                        <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                        {{ __('active_learning.view_live_session') }}
                    </a>
                @endif
                <form method="POST" action="{{ route('tenant.active-learning.destroy', [app('current_tenant')->slug, $course, $plan]) }}" onsubmit="return confirm('{{ __('active_learning.confirm_delete_plan') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        {{ __('active_learning.delete') }}
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Plan Info Card --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-slate-100">
                <div class="p-5">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('active_learning.duration') }}</p>
                    <p class="text-lg font-bold text-slate-900 mt-1">{{ $plan->duration_minutes }} <span class="text-sm font-normal text-slate-400">{{ __('active_learning.min') }}</span></p>
                </div>
                <div class="p-5">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('active_learning.activities') }}</p>
                    <p class="text-lg font-bold text-slate-900 mt-1">{{ $plan->activities->count() }}</p>
                </div>
                <div class="p-5">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('active_learning.created_by') }}</p>
                    <p class="text-sm font-semibold text-slate-900 mt-1.5">{{ $plan->creator->name }}</p>
                </div>
                <div class="p-5">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">{{ $plan->published_at ? __('active_learning.published_on') : 'Created' }}</p>
                    <p class="text-sm font-semibold text-slate-900 mt-1.5">{{ ($plan->published_at ?? $plan->created_at)->format('d M Y') }}</p>
                </div>
            </div>
            @if($plan->description)
                <div class="px-5 py-4 border-t border-slate-100">
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $plan->description }}</p>
                </div>
            @endif
        </div>

        {{-- Activities Timeline --}}
        @if($plan->activities->isNotEmpty())
            <div>
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">{{ __('active_learning.activities') }}</h3>
                <div class="space-y-3">
                    @php $runningTime = 0; @endphp
                    @foreach($plan->activities as $activity)
                        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden hover:border-slate-300 transition">
                            <div class="px-5 py-4 flex items-start gap-4">
                                {{-- Activity Number + Timeline --}}
                                <div class="flex flex-col items-center flex-shrink-0">
                                    @php $typeBadge = $activity->typeBadge; @endphp
                                    <span class="w-10 h-10 rounded-xl bg-{{ $typeBadge['color'] }}-100 flex items-center justify-center text-sm font-bold text-{{ $typeBadge['color'] }}-700">{{ $loop->iteration }}</span>
                                    @if($activity->duration_minutes)
                                        <span class="text-[10px] text-slate-400 mt-1 font-medium">{{ $runningTime }}m</span>
                                        @php $runningTime += $activity->duration_minutes; @endphp
                                    @endif
                                </div>

                                {{-- Activity Content --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap mb-1">
                                        <h4 class="text-sm font-semibold text-slate-900">{{ $activity->title }}</h4>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $typeBadge['color'] }}-100 text-{{ $typeBadge['color'] }}-700">{{ $typeBadge['label'] }}</span>
                                        @if($activity->duration_minutes)
                                            <span class="inline-flex items-center gap-1 text-[10px] text-slate-400 font-medium">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                {{ $activity->duration_minutes }} {{ __('active_learning.min') }}
                                            </span>
                                        @endif
                                    </div>

                                    @if($activity->description)
                                        <p class="text-sm text-slate-600 mb-3">{{ $activity->description }}</p>
                                    @endif

                                    @if($activity->instructions)
                                        <div class="bg-slate-50 rounded-xl p-4 mb-3 border border-slate-100">
                                            <h5 class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">{{ __('active_learning.instructions') }}</h5>
                                            <div class="text-sm text-slate-600 leading-relaxed">{!! nl2br(e($activity->instructions)) !!}</div>
                                        </div>
                                    @endif

                                    @if($activity->clo_ids)
                                        <div class="flex flex-wrap gap-1.5 mb-3">
                                            @foreach($course->learningOutcomes->whereIn('id', $activity->clo_ids) as $clo)
                                                <span class="text-[10px] bg-blue-50 text-blue-700 px-2 py-1 rounded-md font-medium border border-blue-100" title="{{ $clo->description }}">{{ $clo->code }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Groups --}}
                                    @if($activity->groups->isNotEmpty())
                                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2.5 mt-3">
                                            @foreach($activity->groups as $group)
                                                <div class="border border-slate-200 rounded-xl p-3 bg-slate-50/50">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="text-xs font-semibold text-slate-700">{{ $group->name }}</span>
                                                        <span class="text-[10px] text-slate-400 bg-white px-1.5 py-0.5 rounded">{{ $group->students->count() }}</span>
                                                    </div>
                                                    <div class="space-y-1">
                                                        @foreach($group->students as $student)
                                                            <div class="flex items-center gap-2 text-xs text-slate-600">
                                                                @if($student->pivot->role === 'facilitator')
                                                                    <span class="w-4 h-4 rounded bg-amber-100 flex items-center justify-center text-[8px] font-bold text-amber-700">F</span>
                                                                @else
                                                                    <span class="w-4 h-4 rounded bg-white border border-slate-200 flex items-center justify-center text-[8px] text-slate-400">&bull;</span>
                                                                @endif
                                                                {{ $student->name }}
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Total Duration Footer --}}
                <div class="mt-4 flex items-center justify-center">
                    <span class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Total: {{ $runningTime }} {{ __('active_learning.minutes') }}
                    </span>
                </div>
            </div>
        @endif
    </div>
</x-tenant-layout>
