@php $badge = $plan->statusBadge; @endphp
<div class="group bg-white rounded-2xl border border-slate-200 hover:border-indigo-200 hover:shadow-sm transition-all overflow-hidden">
    <div class="px-5 py-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
            <div class="w-11 h-11 rounded-xl {{ $plan->status === 'published' ? 'bg-emerald-100' : ($plan->status === 'archived' ? 'bg-slate-50' : 'bg-amber-50') }} flex items-center justify-center flex-shrink-0">
                @if($plan->week_number)
                    <span class="text-sm font-bold {{ $plan->status === 'published' ? 'text-emerald-700' : ($plan->status === 'archived' ? 'text-slate-400' : 'text-amber-700') }}">W{{ $plan->week_number }}</span>
                @else
                    <svg class="w-5 h-5 {{ $plan->status === 'published' ? 'text-emerald-500' : ($plan->status === 'archived' ? 'text-slate-300' : 'text-amber-400') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                @endif
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h4 class="text-sm font-semibold text-slate-900 truncate">{{ $plan->title }}</h4>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-700">{{ $badge['label'] }}</span>
                    @if($plan->source === 'ai_generated')
                        <span class="inline-flex items-center gap-0.5 text-[10px] bg-teal-50 text-teal-700 px-1.5 py-0.5 rounded font-medium">
                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                            AI
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-2 mt-1">
                    @if($plan->topic)
                        <span class="text-xs text-slate-500">{{ Str::limit($plan->topic->title, 40) }}</span>
                        <span class="text-slate-300">&middot;</span>
                    @endif
                    <span class="text-xs text-slate-400">{{ $plan->activities_count }} {{ __('active_learning.activities') }}</span>
                    <span class="text-slate-300">&middot;</span>
                    <span class="text-xs text-slate-400">{{ $plan->duration_minutes }} {{ __('active_learning.minutes') }}</span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 opacity-60 group-hover:opacity-100 transition">
            @if($plan->status !== 'archived')
                <a href="{{ route('tenant.active-learning.edit', [app('current_tenant')->slug, $course, $plan]) }}" class="px-3.5 py-2 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition">
                    {{ $plan->status === 'published' ? __('active_learning.edit') : __('active_learning.build') }}
                </a>
            @endif
            <a href="{{ route('tenant.active-learning.show', [app('current_tenant')->slug, $course, $plan]) }}" class="px-3.5 py-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                {{ __('active_learning.view') }}
            </a>
        </div>
    </div>
</div>
