<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.active-learning.show', [app('current_tenant')->slug, $course, $plan]) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('active_learning.session_summary') }}</h2>
                <p class="text-sm text-slate-500">{{ $plan->title }} — {{ $course->code }}</p>
            </div>
        </div>
    </x-slot>

    @php $summary = $session->summary_data ?? []; @endphp

    <div class="space-y-6">
        {{-- Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <p class="text-2xl font-bold text-slate-900">{{ $summary['participant_count'] ?? 0 }}</p>
                <p class="text-sm text-slate-500">{{ __('active_learning.students_joined') }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <p class="text-2xl font-bold text-slate-900">{{ $summary['total_responses'] ?? 0 }}</p>
                <p class="text-sm text-slate-500">{{ __('active_learning.total_responses') }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <p class="text-2xl font-bold text-indigo-600">{{ $summary['overall_response_rate'] ?? 0 }}%</p>
                <p class="text-sm text-slate-500">{{ __('active_learning.response_rate') }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <p class="text-2xl font-bold text-slate-900">{{ $summary['duration_minutes'] ?? 0 }} min</p>
                <p class="text-sm text-slate-500">{{ __('active_learning.duration') }}</p>
            </div>
        </div>

        {{-- Per-Activity Breakdown --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('active_learning.activity_breakdown') }}</h3>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($activities as $activity)
                    @php
                        $activitySummary = collect($summary['per_activity'] ?? [])->firstWhere('activity_id', $activity->id);
                        $activityResponses = $session->responses->where('activity_id', $activity->id);
                    @endphp
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <h4 class="font-medium text-slate-900">{{ $activity->title }}</h4>
                                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-[10px] font-bold uppercase">{{ $activity->type }}</span>
                            </div>
                            <span class="text-sm font-semibold text-slate-700">
                                {{ $activitySummary['response_count'] ?? $activityResponses->count() }} / {{ $summary['participant_count'] ?? 0 }}
                                <span class="text-slate-400 font-normal">({{ $activitySummary['response_rate'] ?? 0 }}%)</span>
                            </span>
                        </div>

                        {{-- Response bar --}}
                        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-500 rounded-full" style="width: {{ $activitySummary['response_rate'] ?? 0 }}%"></div>
                        </div>

                        {{-- MCQ results --}}
                        @if($activity->response_type === 'mcq')
                            <div class="mt-3 space-y-1.5">
                                @foreach($activity->pollOptions as $option)
                                    @php $optCount = $activityResponses->filter(fn($r) => in_array($option->id, $r->getSelectedOptions()))->count(); @endphp
                                    <div class="flex items-center gap-3 text-sm">
                                        <span class="w-32 truncate text-slate-600">{{ $option->label }}</span>
                                        <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-violet-400 rounded-full" style="width: {{ $activityResponses->count() > 0 ? round($optCount / $activityResponses->count() * 100) : 0 }}%"></div>
                                        </div>
                                        <span class="text-xs text-slate-500 w-8 text-right">{{ $optCount }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Text responses (expandable) --}}
                        @if(in_array($activity->response_type, ['text', 'reflection']) && $activityResponses->isNotEmpty())
                            <div x-data="{ open: false }" class="mt-3">
                                <button @click="open = !open" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                    <span x-text="open ? '{{ __('active_learning.hide_responses') }}' : '{{ __('active_learning.show_responses') }} ({{ $activityResponses->count() }})'"></span>
                                </button>
                                <div x-show="open" x-cloak x-transition class="mt-2 space-y-2 max-h-48 overflow-y-auto">
                                    @foreach($activityResponses as $resp)
                                        <div class="p-2.5 bg-slate-50 rounded-lg text-sm">
                                            <span class="font-medium text-slate-600">{{ $resp->user->name }}:</span>
                                            <span class="text-slate-700">{{ $resp->getTextContent() }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Participants List --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('active_learning.participants') }} ({{ $session->participants->count() }})</h3>
            </div>
            <div class="px-6 py-4">
                <div class="flex flex-wrap gap-2">
                    @foreach($session->participants as $participant)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg text-sm">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 flex items-center justify-center text-[9px] font-bold text-indigo-700">{{ strtoupper(substr($participant->user->name, 0, 1)) }}</span>
                            {{ $participant->user->name }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-tenant-layout>
