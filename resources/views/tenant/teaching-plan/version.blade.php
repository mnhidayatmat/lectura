<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.teaching-plan.show', [app('current_tenant')->slug, $course]) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Teaching Plan v{{ $plan->version }}</h2>
                <p class="text-sm text-slate-500">{{ $course->code }} &middot; {{ ucfirst($plan->status) }} &middot; {{ $plan->created_at->format('d M Y, H:i') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if($plan->change_note)
            <div class="bg-slate-50 rounded-xl p-4 text-sm text-slate-600">
                <strong>Note:</strong> {{ $plan->change_note }}
            </div>
        @endif

        @foreach($plan->weeks as $week)
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-10 h-10 rounded-xl bg-slate-200 flex items-center justify-center text-sm font-bold text-slate-600">W{{ $week->week_number }}</span>
                    <p class="font-semibold text-slate-900">{{ $week->topic }}</p>
                </div>

                @if($week->lesson_flow)
                    <div class="prose prose-sm max-w-none text-slate-600 mb-4">{!! nl2br(e($week->lesson_flow)) !!}</div>
                @endif

                @if($week->active_learning)
                    <div class="flex flex-wrap gap-2">
                        @foreach($week->active_learning as $a)
                            <span class="text-xs bg-emerald-50 text-emerald-700 px-2 py-1 rounded-lg">{{ $a }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-tenant-layout>
