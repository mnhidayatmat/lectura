<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('tenant.courses.show', [app('current_tenant')->slug, $course]) }}" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Teaching Plan</h2>
                    <p class="text-sm text-slate-500">{{ $course->code }} — {{ $course->title }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($currentPlan && $currentPlan->status === 'draft')
                    <form method="POST" action="{{ route('tenant.teaching-plan.publish', [app('current_tenant')->slug, $course, $currentPlan]) }}">
                        @csrf
                        <button class="px-4 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition">Publish</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('tenant.teaching-plan.generate', [app('current_tenant')->slug, $course]) }}">
                    @csrf
                    <button onclick="return confirm('Generate a new AI teaching plan? This will archive the current version.')" class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        {{ $currentPlan ? 'Regenerate with AI' : 'Generate with AI' }}
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(!$currentPlan)
            {{-- No plan yet --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                <div class="w-20 h-20 bg-teal-50 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 mb-2">No teaching plan yet</h3>
                <p class="text-sm text-slate-500 max-w-md mx-auto mb-6">Generate an AI-powered teaching plan based on your course topics and CLOs. You can edit every detail after generation.</p>
                <p class="text-xs text-slate-400">Make sure you've added weekly topics to your course first.</p>
            </div>
        @else
            {{-- Version info --}}
            <div class="flex items-center justify-between bg-white rounded-2xl border border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold {{ $currentPlan->status === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                        v{{ $currentPlan->version }}
                    </span>
                    <span class="text-sm text-slate-500">{{ ucfirst($currentPlan->status) }} &middot; {{ $currentPlan->created_at->format('d M Y, H:i') }}</span>
                </div>
                @if($versions->count() > 1)
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">Version History ({{ $versions->count() }})</button>
                        <div x-show="open" x-cloak @click.away="open = false" class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-lg border z-50 py-1">
                            @foreach($versions as $v)
                                <a href="{{ route('tenant.teaching-plan.version', [app('current_tenant')->slug, $course, $v]) }}" class="flex items-center justify-between px-4 py-2 text-sm hover:bg-slate-50 {{ $v->id === $currentPlan->id ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600' }}">
                                    <span>v{{ $v->version }} — {{ ucfirst($v->status) }}</span>
                                    <span class="text-xs text-slate-400">{{ $v->created_at->format('d M') }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Weeks --}}
            <div class="space-y-4">
                @foreach($currentPlan->weeks as $week)
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden" x-data="{ editing: false }">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between cursor-pointer" @click="editing = !editing">
                            <div class="flex items-center gap-4">
                                <span class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-700">W{{ $week->week_number }}</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $week->topic }}</p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        @if($week->ai_generated)
                                            <span class="text-[10px] bg-teal-50 text-teal-700 px-1.5 py-0.5 rounded font-medium">AI Generated</span>
                                        @else
                                            <span class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-medium">Edited</span>
                                        @endif
                                        @if($week->duration_minutes)
                                            <span class="text-[10px] text-slate-400">{{ $week->duration_minutes }} min</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transition-transform" :class="editing && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>

                        <div x-show="editing" x-cloak x-transition class="p-6 space-y-5">
                            {{-- Lesson Flow --}}
                            @if($week->lesson_flow)
                                <div>
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">Lesson Flow</h4>
                                    <div class="prose prose-sm max-w-none text-slate-600">{!! nl2br(e($week->lesson_flow)) !!}</div>
                                </div>
                            @endif

                            {{-- Time Allocation --}}
                            @if($week->time_allocation)
                                <div>
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">Time Allocation</h4>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($week->time_allocation as $phase => $mins)
                                            <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-medium">{{ $phase }}: {{ $mins }} min</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Active Learning --}}
                            @if($week->active_learning)
                                <div>
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">Active Learning Activities</h4>
                                    <ul class="space-y-1.5">
                                        @foreach($week->active_learning as $activity)
                                            <li class="flex items-start gap-2 text-sm text-slate-600">
                                                <svg class="w-4 h-4 text-emerald-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                {{ $activity }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- Formative Checks --}}
                            @if($week->formative_checks)
                                <div>
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">Formative Checks</h4>
                                    <ul class="space-y-1.5">
                                        @foreach($week->formative_checks as $check)
                                            <li class="flex items-start gap-2 text-sm text-slate-600">
                                                <svg class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                                {{ $check }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- Edit form --}}
                            <div class="pt-4 border-t border-slate-200">
                                <form method="POST" action="{{ route('tenant.teaching-plan.update-week', [app('current_tenant')->slug, $course, $week]) }}" class="space-y-3">
                                    @csrf @method('PUT')
                                    <div>
                                        <label class="text-xs font-medium text-slate-500">Topic</label>
                                        <input type="text" name="topic" value="{{ $week->topic }}" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-slate-500">Lesson Flow</label>
                                        <textarea name="lesson_flow" rows="4" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500">{{ $week->lesson_flow }}</textarea>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-slate-500">Assessment Notes</label>
                                        <textarea name="assessment_notes" rows="2" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500">{{ $week->assessment_notes }}</textarea>
                                    </div>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">Save Week</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-tenant-layout>
