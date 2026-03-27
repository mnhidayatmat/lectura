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
                        @php $headerBadge = $plan->statusBadge; @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $headerBadge['color'] }}-100 text-{{ $headerBadge['color'] }}-700">{{ $headerBadge['label'] }}</span>
                    </div>
                    <p class="text-sm text-slate-500">{{ $course->code }} — {{ $plan->topic?->title ?? __('active_learning.standalone') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($plan->isDraft())
                    <form method="POST" action="{{ route('tenant.active-learning.publish', [app('current_tenant')->slug, $course, $plan]) }}">
                        @csrf
                        <button class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('active_learning.publish') }}
                        </button>
                    </form>
                @endif

                {{-- AI Generate Button (Pro) --}}
                @if(auth()->user()->isPro() && $tenant->isAiEnabled())
                    <button onclick="document.getElementById('ai-modal').classList.remove('hidden')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm hover:shadow-md transition-all">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                        {{ __('active_learning.generate_ai') }}
                    </button>
                @elseif(auth()->user()->isFree())
                    <span class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-100 text-slate-400 text-sm font-medium rounded-xl cursor-not-allowed" title="{{ __('active_learning.pro_only') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        {{ __('active_learning.generate_ai') }}
                        <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-bold">PRO</span>
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6" x-data="activeLearningBuilder()" x-init="init()">
        {{-- AI Generation Status Banner --}}
        @if($plan->ai_generation_status === 'processing')
            <div class="bg-teal-50 border border-teal-200 rounded-2xl p-4 flex items-center gap-3"
                 x-data="aiPoller('{{ route('tenant.active-learning.generation-status', [app('current_tenant')->slug, $course, $plan]) }}')"
                 x-init="startPolling()">
                <svg class="w-5 h-5 text-teal-600 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span class="text-sm font-medium text-teal-800">{{ __('active_learning.ai_processing') }}</span>
            </div>
        @elseif($plan->ai_generation_status === 'failed')
            <div class="bg-red-50 border border-red-200 rounded-2xl p-4 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm font-medium text-red-800">{{ __('active_learning.ai_failed') }}</span>
            </div>
        @endif

        {{-- Section A: Plan Header --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden" x-data="{ editingHeader: false }">
            <div class="px-6 py-4 flex items-center justify-between cursor-pointer" @click="editingHeader = !editingHeader">
                <div class="flex items-center gap-4">
                    @php $badge = $plan->statusBadge; @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700">{{ $badge['label'] }}</span>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $plan->title }}</p>
                        <p class="text-xs text-slate-500">{{ $plan->duration_minutes }} {{ __('active_learning.minutes') }} &middot; {{ $plan->topic?->title ?? __('active_learning.standalone') }}</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="editingHeader && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div x-show="editingHeader" x-cloak x-transition class="px-6 pb-6 border-t border-slate-100 pt-4">
                <form method="POST" action="{{ route('tenant.active-learning.update', [app('current_tenant')->slug, $course, $plan]) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-medium text-slate-500">{{ __('active_learning.plan_title') }}</label>
                            <input type="text" name="title" value="{{ $plan->title }}" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500" required>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-500">{{ __('active_learning.duration') }}</label>
                            <input type="number" name="duration_minutes" value="{{ $plan->duration_minutes }}" min="5" max="480" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-500">{{ __('active_learning.description') }}</label>
                        <textarea name="description" rows="2" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500">{{ $plan->description }}</textarea>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">{{ __('active_learning.save_changes') }}</button>
                </form>
            </div>
        </div>

        {{-- Section B: Activities --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h3 class="font-semibold text-slate-900">{{ __('active_learning.activities') }}</h3>
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">{{ $plan->activities->count() }}</span>
                </div>
                @php
                    $usedMinutes = $plan->activities->sum('duration_minutes') ?? 0;
                    $totalMinutes = $plan->duration_minutes;
                    $pct = $totalMinutes > 0 ? min(100, round($usedMinutes / $totalMinutes * 100)) : 0;
                @endphp
                <div class="flex items-center gap-3">
                    <div class="hidden sm:block w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ $pct > 100 ? 'bg-red-500' : ($pct > 80 ? 'bg-amber-500' : 'bg-indigo-500') }} transition-all" style="width: {{ min($pct, 100) }}%"></div>
                    </div>
                    <span class="text-xs font-medium {{ $usedMinutes > $totalMinutes ? 'text-red-500' : 'text-slate-400' }}">
                        {{ $usedMinutes }} / {{ $totalMinutes }} {{ __('active_learning.min') }}
                    </span>
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($plan->activities as $activity)
                    <div class="p-6" x-data="{ expanded: false, editing: false }">
                        <div class="flex items-start justify-between cursor-pointer" @click="expanded = !expanded">
                            <div class="flex items-start gap-3">
                                @php $typeBadge = $activity->typeBadge; @endphp
                                <span class="w-8 h-8 rounded-lg bg-{{ $typeBadge['color'] }}-100 flex items-center justify-center text-xs font-bold text-{{ $typeBadge['color'] }}-700 flex-shrink-0 mt-0.5">{{ $loop->iteration }}</span>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="text-sm font-semibold text-slate-900">{{ $activity->title }}</h4>
                                        @php $typeBadge = $activity->typeBadge; @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-{{ $typeBadge['color'] }}-100 text-{{ $typeBadge['color'] }}-700">{{ $typeBadge['label'] }}</span>
                                        @if($activity->duration_minutes)
                                            <span class="text-[10px] text-slate-400">{{ $activity->duration_minutes }} {{ __('active_learning.min') }}</span>
                                        @endif
                                        @if($activity->ai_generated)
                                            <span class="text-[10px] bg-teal-50 text-teal-700 px-1.5 py-0.5 rounded font-medium">AI</span>
                                        @endif
                                    </div>
                                    @if($activity->description)
                                        <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $activity->description }}</p>
                                    @endif
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-slate-400 transition-transform flex-shrink-0 mt-1" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>

                        <div x-show="expanded" x-cloak x-transition class="mt-4 space-y-4">
                            {{-- Activity Details --}}
                            @if($activity->instructions)
                                <div>
                                    <h5 class="text-xs font-semibold text-slate-500 uppercase mb-1">{{ __('active_learning.instructions') }}</h5>
                                    <div class="text-sm text-slate-600 bg-slate-50 rounded-lg p-3">{!! nl2br(e($activity->instructions)) !!}</div>
                                </div>
                            @endif

                            {{-- CLO Tags --}}
                            @if($activity->clo_ids)
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($course->learningOutcomes->whereIn('id', $activity->clo_ids) as $clo)
                                        <span class="text-[10px] bg-blue-50 text-blue-700 px-2 py-1 rounded font-medium">{{ $clo->code }}</span>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Groups Panel (for group/pair activities) --}}
                            @if($activity->isGrouped() && $activity->groups->isNotEmpty())
                                <div>
                                    <h5 class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('active_learning.groups') }}</h5>
                                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        @foreach($activity->groups as $group)
                                            <div class="border border-slate-200 rounded-lg p-3">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-xs font-semibold text-slate-700">{{ $group->name }}</span>
                                                    <span class="text-[10px] text-slate-400">{{ $group->students->count() }} {{ __('active_learning.members') }}</span>
                                                </div>
                                                <div class="space-y-1">
                                                    @foreach($group->students as $student)
                                                        <div class="flex items-center gap-2 text-xs text-slate-600">
                                                            @if($student->pivot->role === 'facilitator')
                                                                <span class="w-4 h-4 rounded bg-amber-100 flex items-center justify-center text-[8px] font-bold text-amber-700">F</span>
                                                            @else
                                                                <span class="w-4 h-4 rounded bg-slate-100 flex items-center justify-center text-[8px] text-slate-400">&bull;</span>
                                                            @endif
                                                            {{ $student->name }}
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Group Arrangement (for group/pair type, when editing) --}}
                            @if($activity->isGrouped() && $plan->isDraft())
                                <div class="border-t border-slate-100 pt-4" x-data="{ showGroupForm: false }">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <button @click="showGroupForm = !showGroupForm" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">
                                            {{ __('active_learning.manage_groups') }}
                                        </button>
                                    </div>
                                    <div x-show="showGroupForm" x-cloak x-transition class="mt-3 space-y-3">
                                        {{-- Auto-arrange from attendance --}}
                                        @if($attendanceSessions->isNotEmpty())
                                            <form method="POST" action="{{ route('tenant.active-learning.groups.arrange-attendance', [app('current_tenant')->slug, $course, $plan, $activity]) }}" class="flex items-end gap-3 flex-wrap">
                                                @csrf
                                                <div>
                                                    <label class="text-xs font-medium text-slate-500">{{ __('active_learning.attendance_session') }}</label>
                                                    <select name="attendance_session_id" class="mt-1 px-3 py-1.5 rounded-lg border border-slate-300 text-xs" required>
                                                        @foreach($attendanceSessions as $session)
                                                            <option value="{{ $session->id }}">{{ $session->section->name ?? 'Section' }} — W{{ $session->week_number }} ({{ $session->started_at->format('d M') }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="text-xs font-medium text-slate-500">{{ __('active_learning.group_size') }}</label>
                                                    <input type="number" name="group_size" value="{{ $activity->max_group_size ?? 4 }}" min="2" max="50" class="mt-1 w-20 px-3 py-1.5 rounded-lg border border-slate-300 text-xs">
                                                </div>
                                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                                                    {{ __('active_learning.auto_arrange') }}
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Manual group creation --}}
                                        <form method="POST" action="{{ route('tenant.active-learning.groups.store', [app('current_tenant')->slug, $course, $plan, $activity]) }}" class="flex items-end gap-3">
                                            @csrf
                                            <div>
                                                <label class="text-xs font-medium text-slate-500">{{ __('active_learning.group_name') }}</label>
                                                <input type="text" name="name" placeholder="{{ __('active_learning.group_number', ['number' => $activity->groups->count() + 1]) }}" class="mt-1 px-3 py-1.5 rounded-lg border border-slate-300 text-xs" required>
                                            </div>
                                            <button type="submit" class="px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition">
                                                {{ __('active_learning.add_group') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endif

                            {{-- Edit / Delete buttons --}}
                            @if($plan->isDraft())
                                <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                                    <button @click="editing = !editing" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">
                                        {{ __('active_learning.edit') }}
                                    </button>
                                    <form method="POST" action="{{ route('tenant.active-learning.activities.destroy', [app('current_tenant')->slug, $course, $plan, $activity]) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" onclick="return confirm('{{ __('active_learning.confirm_delete_activity') }}')" class="text-xs font-medium text-red-600 hover:text-red-700">
                                            {{ __('active_learning.delete') }}
                                        </button>
                                    </form>
                                </div>
                            @endif

                            {{-- Inline Edit Form --}}
                            <div x-show="editing" x-cloak x-transition class="border-t border-slate-100 pt-4">
                                <form method="POST" action="{{ route('tenant.active-learning.activities.update', [app('current_tenant')->slug, $course, $plan, $activity]) }}" class="space-y-3">
                                    @csrf @method('PUT')
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="text-xs font-medium text-slate-500">{{ __('active_learning.activity_title') }}</label>
                                            <input type="text" name="title" value="{{ $activity->title }}" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm" required>
                                        </div>
                                        <div>
                                            <label class="text-xs font-medium text-slate-500">{{ __('active_learning.activity_type') }}</label>
                                            <select name="type" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm" required>
                                                @foreach(\App\Models\ActiveLearningActivity::TYPES as $type)
                                                    <option value="{{ $type }}" {{ $activity->type === $type ? 'selected' : '' }}>{{ __('active_learning.type_' . $type) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-slate-500">{{ __('active_learning.description') }}</label>
                                        <textarea name="description" rows="2" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm">{{ $activity->description }}</textarea>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-slate-500">{{ __('active_learning.instructions') }}</label>
                                        <textarea name="instructions" rows="3" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm">{{ $activity->instructions }}</textarea>
                                    </div>
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="text-xs font-medium text-slate-500">{{ __('active_learning.duration') }}</label>
                                            <input type="number" name="duration_minutes" value="{{ $activity->duration_minutes }}" min="1" max="480" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs font-medium text-slate-500">{{ __('active_learning.max_group_size') }}</label>
                                            <input type="number" name="max_group_size" value="{{ $activity->max_group_size }}" min="2" max="50" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm">
                                        </div>
                                    </div>
                                    {{-- Response Configuration --}}
                                    <div class="border border-slate-200 rounded-lg p-3 space-y-3" x-data="{ editResponseType: '{{ $activity->response_type ?? 'none' }}' }">
                                        <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('active_learning.response_config') }}</h5>
                                        <div class="grid sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="text-xs font-medium text-slate-500">{{ __('active_learning.response_type') }}</label>
                                                <select name="response_type" x-model="editResponseType" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm">
                                                    <option value="none">{{ __('active_learning.rt_none') }}</option>
                                                    <option value="text">{{ __('active_learning.rt_text') }}</option>
                                                    <option value="reflection">{{ __('active_learning.rt_reflection') }}</option>
                                                    <option value="mcq">{{ __('active_learning.rt_mcq') }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="text-xs font-medium text-slate-500">{{ __('active_learning.response_mode') }}</label>
                                                <select name="response_mode" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm">
                                                    <option value="individual" {{ ($activity->response_mode ?? 'individual') === 'individual' ? 'selected' : '' }}>{{ __('active_learning.rm_individual') }}</option>
                                                    <option value="group" {{ ($activity->response_mode ?? '') === 'group' ? 'selected' : '' }}>{{ __('active_learning.rm_group') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <template x-if="editResponseType === 'mcq'">
                                            <div class="space-y-2" x-data="{ options: {{ json_encode($activity->pollOptions->pluck('label')->toArray() ?: ['', '']) }} }">
                                                <label class="text-xs font-medium text-slate-500">{{ __('active_learning.poll_options') }}</label>
                                                <template x-for="(opt, i) in options" :key="i">
                                                    <input type="text" :name="'poll_options[' + i + ']'" x-model="options[i]" :placeholder="'Option ' + (i+1)" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-sm" required />
                                                </template>
                                                <button type="button" @click="if (options.length < 6) options.push('')" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">+ {{ __('active_learning.add_option') }}</button>
                                                <div class="flex items-center gap-4 mt-2">
                                                    <label class="inline-flex items-center gap-1.5 text-xs">
                                                        <input type="checkbox" name="poll_multi_select" value="1" {{ ($activity->poll_config['multi_select'] ?? false) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600">
                                                        {{ __('active_learning.multi_select') }}
                                                    </label>
                                                    <label class="inline-flex items-center gap-1.5 text-xs">
                                                        <input type="checkbox" name="poll_show_results" value="1" {{ ($activity->poll_config['show_results'] ?? true) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600">
                                                        {{ __('active_learning.show_results_to_students') }}
                                                    </label>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    {{-- CLO multi-select --}}
                                    <div>
                                        <label class="text-xs font-medium text-slate-500">{{ __('active_learning.clos') }}</label>
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach($course->learningOutcomes as $clo)
                                                <label class="inline-flex items-center gap-1.5 text-xs">
                                                    <input type="checkbox" name="clo_ids[]" value="{{ $clo->id }}" {{ in_array($clo->id, $activity->clo_ids ?? []) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600">
                                                    {{ $clo->code }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">{{ __('active_learning.save_activity') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center">
                        <p class="text-sm text-slate-500">{{ __('active_learning.no_activities') }}</p>
                    </div>
                @endforelse
            </div>

            {{-- Add Activity Form --}}
            @if($plan->isDraft())
                <div class="border-t border-slate-100 p-6" x-data="{ showForm: false }">
                    <button @click="showForm = !showForm" class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('active_learning.add_activity') }}
                    </button>

                    <div x-show="showForm" x-cloak x-transition class="mt-4">
                        <form method="POST" action="{{ route('tenant.active-learning.activities.store', [app('current_tenant')->slug, $course, $plan]) }}" class="space-y-4 bg-slate-50 rounded-xl p-5">
                            @csrf
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-medium text-slate-500">{{ __('active_learning.activity_title') }} <span class="text-red-500">*</span></label>
                                    <input type="text" name="title" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500" required>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-500">{{ __('active_learning.activity_type') }} <span class="text-red-500">*</span></label>
                                    <select name="type" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500" required>
                                        @foreach(\App\Models\ActiveLearningActivity::TYPES as $type)
                                            <option value="{{ $type }}">{{ __('active_learning.type_' . $type) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-slate-500">{{ __('active_learning.description') }}</label>
                                <textarea name="description" rows="2" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-slate-500">{{ __('active_learning.instructions') }}</label>
                                <textarea name="instructions" rows="3" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500" placeholder="{{ __('active_learning.instructions_placeholder') }}"></textarea>
                            </div>
                            <div class="grid sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="text-xs font-medium text-slate-500">{{ __('active_learning.duration') }}</label>
                                    <input type="number" name="duration_minutes" min="1" max="480" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500" placeholder="15">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-500">{{ __('active_learning.grouping_strategy') }}</label>
                                    <select name="grouping_strategy" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option value="">--</option>
                                        @foreach(\App\Models\ActiveLearningActivity::GROUPING_STRATEGIES as $strategy)
                                            <option value="{{ $strategy }}">{{ __('active_learning.strategy_' . $strategy) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-500">{{ __('active_learning.max_group_size') }}</label>
                                    <input type="number" name="max_group_size" min="2" max="50" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500" placeholder="4">
                                </div>
                            </div>
                            {{-- Response Configuration --}}
                            <div class="border border-slate-200 rounded-lg p-4 space-y-3" x-data="{ responseType: 'none' }">
                                <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('active_learning.response_config') }}</h5>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs font-medium text-slate-500">{{ __('active_learning.response_type') }}</label>
                                        <select name="response_type" x-model="responseType" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500">
                                            <option value="none">{{ __('active_learning.rt_none') }}</option>
                                            <option value="text">{{ __('active_learning.rt_text') }}</option>
                                            <option value="reflection">{{ __('active_learning.rt_reflection') }}</option>
                                            <option value="mcq">{{ __('active_learning.rt_mcq') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-slate-500">{{ __('active_learning.response_mode') }}</label>
                                        <select name="response_mode" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500">
                                            <option value="individual">{{ __('active_learning.rm_individual') }}</option>
                                            <option value="group">{{ __('active_learning.rm_group') }}</option>
                                        </select>
                                    </div>
                                </div>
                                {{-- MCQ Options --}}
                                <template x-if="responseType === 'mcq'">
                                    <div class="space-y-2" x-data="{ options: ['', ''] }">
                                        <label class="text-xs font-medium text-slate-500">{{ __('active_learning.poll_options') }}</label>
                                        <template x-for="(opt, i) in options" :key="i">
                                            <input type="text" :name="'poll_options[' + i + ']'" x-model="options[i]" :placeholder="'Option ' + (i+1)" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-sm" required />
                                        </template>
                                        <button type="button" @click="if (options.length < 6) options.push('')" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">+ {{ __('active_learning.add_option') }}</button>
                                        <div class="flex items-center gap-4 mt-2">
                                            <label class="inline-flex items-center gap-1.5 text-xs">
                                                <input type="checkbox" name="poll_multi_select" value="1" class="rounded border-slate-300 text-indigo-600">
                                                {{ __('active_learning.multi_select') }}
                                            </label>
                                            <label class="inline-flex items-center gap-1.5 text-xs">
                                                <input type="checkbox" name="poll_show_results" value="1" checked class="rounded border-slate-300 text-indigo-600">
                                                {{ __('active_learning.show_results_to_students') }}
                                            </label>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            {{-- CLO checkboxes --}}
                            @if($course->learningOutcomes->isNotEmpty())
                                <div>
                                    <label class="text-xs font-medium text-slate-500">{{ __('active_learning.clos') }}</label>
                                    <div class="flex flex-wrap gap-2 mt-1">
                                        @foreach($course->learningOutcomes as $clo)
                                            <label class="inline-flex items-center gap-1.5 text-xs">
                                                <input type="checkbox" name="clo_ids[]" value="{{ $clo->id }}" class="rounded border-slate-300 text-indigo-600">
                                                {{ $clo->code }}: {{ Str::limit($clo->description, 50) }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">{{ __('active_learning.add_activity') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        {{-- Delete Plan --}}
        @if($plan->isDraft())
            <div class="border border-dashed border-red-200 rounded-2xl p-4 flex items-center justify-between bg-red-50/30">
                <div>
                    <p class="text-sm font-medium text-red-700">{{ __('active_learning.delete_plan') }}</p>
                    <p class="text-xs text-red-400 mt-0.5">This action cannot be undone</p>
                </div>
                <form method="POST" action="{{ route('tenant.active-learning.destroy', [app('current_tenant')->slug, $course, $plan]) }}">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('{{ __('active_learning.confirm_delete_plan') }}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        {{ __('active_learning.delete_plan') }}
                    </button>
                </form>
            </div>
        @endif
    </div>

    {{-- AI Generation Modal --}}
    @if(auth()->user()->isPro())
        <div id="ai-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="document.getElementById('ai-modal').classList.add('hidden')">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-gradient-to-r from-teal-50 to-emerald-50">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-teal-500 to-emerald-500 flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-slate-900">{{ __('active_learning.generate_ai') }}</h3>
                            <p class="text-[10px] text-teal-600 font-medium">Powered by AI</p>
                        </div>
                    </div>
                    <button onclick="document.getElementById('ai-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('tenant.active-learning.generate-ai', [app('current_tenant')->slug, $course, $plan]) }}" enctype="multipart/form-data" class="p-6 space-y-4">
                    @csrf
                    @php
                        $studentCount = \App\Models\SectionStudent::whereHas('section', fn($q) => $q->where('course_id', $course->id))->where('is_active', true)->distinct('user_id')->count('user_id');
                    @endphp
                    <div class="bg-slate-50 rounded-xl p-4 space-y-2 border border-slate-100">
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Context</p>
                        <p class="text-xs text-slate-600"><span class="font-medium text-slate-700">{{ __('active_learning.topic') }}:</span> {{ $plan->topic?->title ?? 'Week ' . $plan->week_number }}</p>
                        <p class="text-xs text-slate-600"><span class="font-medium text-slate-700">{{ __('active_learning.duration') }}:</span> {{ $plan->duration_minutes }} {{ __('active_learning.minutes') }}</p>
                        <p class="text-xs text-slate-600"><span class="font-medium text-slate-700">CLOs:</span> {{ $course->learningOutcomes->pluck('code')->implode(', ') ?: 'None defined' }}</p>
                        <p class="text-xs text-slate-600">
                            <span class="font-medium text-slate-700">{{ __('active_learning.students') }}:</span>
                            <span class="font-semibold text-indigo-600">{{ $studentCount }}</span> {{ __('active_learning.enrolled') }}
                        </p>
                    </div>

                    {{-- PDF Upload --}}
                    <div x-data="{ fileName: null }">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('active_learning.upload_lecture_notes') }}</label>
                        <label class="flex items-center gap-3 px-4 py-3 border-2 border-dashed border-slate-300 rounded-xl cursor-pointer hover:border-teal-400 hover:bg-teal-50/30 transition">
                            <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-700" x-text="fileName || '{{ __('active_learning.choose_pdf') }}'"></p>
                                <p class="text-xs text-slate-400">{{ __('active_learning.pdf_max_size') }}</p>
                            </div>
                            <input type="file" name="lecture_notes_file" accept=".pdf" class="hidden" @change="fileName = $event.target.files[0]?.name" />
                        </label>
                        @error('lecture_notes_file')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Text notes (optional alongside PDF) --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('active_learning.additional_notes') }}</label>
                        <textarea name="lecture_notes" rows="4" class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition"
                            placeholder="{{ __('active_learning.additional_notes_placeholder') }}"></textarea>
                        <p class="mt-1.5 text-xs text-slate-400">{{ __('active_learning.lecture_notes_help') }}</p>
                    </div>

                    <button type="submit" class="w-full px-4 py-3 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm hover:shadow-md transition-all flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                        {{ __('active_learning.generate_plan') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

    <script>
        function activeLearningBuilder() {
            return {
                init() {}
            }
        }

        function aiPoller(url) {
            return {
                startPolling() {
                    const interval = setInterval(async () => {
                        try {
                            const res = await fetch(url);
                            const data = await res.json();
                            if (data.status === 'completed' || data.status === 'failed') {
                                clearInterval(interval);
                                window.location.reload();
                            }
                        } catch (e) {
                            clearInterval(interval);
                        }
                    }, 3000);
                }
            }
        }
    </script>
</x-tenant-layout>
