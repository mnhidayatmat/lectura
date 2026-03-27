<x-tenant-layout>
    <div x-data="sessionDashboard()" x-init="init()" class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wide">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                        Live
                    </span>
                    <span class="text-sm text-slate-400">{{ $course->code }}</span>
                </div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $plan->title }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">
                    {{ __('active_learning.join_code') }}:
                    <code class="text-lg font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $session->join_code }}</code>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-center px-4 py-2 bg-white border border-slate-200 rounded-xl">
                    <p class="text-2xl font-bold text-indigo-600" x-text="state.participant_count">0</p>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wide">{{ __('active_learning.joined') }}</p>
                </div>
                <form method="POST" action="{{ route('tenant.active-learning.sessions.end', [app('current_tenant')->slug, $course, $plan, $session]) }}" onsubmit="return confirm('{{ __('active_learning.confirm_end_session') }}')">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl transition">
                        {{ __('active_learning.end_session') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Activity Progress Bar --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('active_learning.progress') }}</span>
                <span class="text-xs text-slate-400" x-text="state.current_index + ' / ' + state.total_activities"></span>
            </div>
            <div class="flex gap-1.5">
                @foreach($activities as $i => $activity)
                    <div class="flex-1 h-2 rounded-full transition-colors duration-300"
                         :class="{
                             'bg-indigo-600': {{ $activity->id }} === state.current_activity?.id,
                             'bg-emerald-400': {{ $i }} < (state.current_index - 1),
                             'bg-slate-200': {{ $i }} >= state.current_index
                         }"></div>
                @endforeach
            </div>
        </div>

        {{-- Current Activity Panel --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden" x-show="state.current_activity">
            <div class="px-6 py-5 border-b border-slate-100">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="text-lg font-bold text-slate-900" x-text="state.current_activity?.title"></h3>
                            <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-[10px] font-bold uppercase" x-text="state.current_activity?.type"></span>
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full text-[10px] font-bold uppercase" x-text="state.current_activity?.response_type"></span>
                        </div>
                        <p class="text-sm text-slate-500" x-text="state.current_activity?.duration_minutes + ' min'"></p>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center gap-1">
                            <span class="text-2xl font-bold text-indigo-600" x-text="state.current_activity?.response_count || 0"></span>
                            <span class="text-slate-400">/</span>
                            <span class="text-lg text-slate-500" x-text="state.participant_count"></span>
                        </div>
                        <p class="text-[10px] text-slate-400 uppercase tracking-wide">{{ __('active_learning.responses') }}</p>
                    </div>
                </div>
            </div>

            {{-- Instructions --}}
            <div class="px-6 py-4 bg-slate-50/50">
                <p class="text-sm text-slate-700 whitespace-pre-line" x-text="state.current_activity?.instructions || state.current_activity?.description"></p>
            </div>

            {{-- Poll Results (live bar chart for MCQ) --}}
            <template x-if="state.current_activity?.response_type === 'mcq' && state.current_activity?.poll_distribution">
                <div class="px-6 py-4 border-t border-slate-100">
                    <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('active_learning.live_results') }}</h4>
                    <div class="space-y-2">
                        <template x-for="option in state.current_activity.poll_distribution" :key="option.option_id">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-slate-700" x-text="option.label"></span>
                                    <span class="text-sm font-semibold text-slate-900" x-text="option.count"></span>
                                </div>
                                <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500 rounded-full transition-all duration-500"
                                         :style="'width: ' + (state.current_activity.response_count > 0 ? (option.count / state.current_activity.response_count * 100) : 0) + '%'"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Text Responses (recent list) --}}
            <template x-if="['text', 'reflection'].includes(state.current_activity?.response_type) && state.current_activity?.recent_responses?.length">
                <div class="px-6 py-4 border-t border-slate-100 max-h-64 overflow-y-auto">
                    <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ __('active_learning.recent_responses') }}</h4>
                    <div class="space-y-2">
                        <template x-for="resp in state.current_activity.recent_responses" :key="resp.submitted_at">
                            <div class="flex items-start gap-3 p-3 bg-slate-50 rounded-lg">
                                <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-[10px] font-bold text-indigo-700 shrink-0" x-text="resp.user_name.charAt(0).toUpperCase()"></div>
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-slate-500" x-text="resp.user_name"></p>
                                    <p class="text-sm text-slate-700 mt-0.5" x-text="resp.text"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Advance Button --}}
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end">
                <form method="POST" action="{{ route('tenant.active-learning.sessions.advance', [app('current_tenant')->slug, $course, $plan, $session]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                        {{ __('active_learning.next_activity') }}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </form>
            </div>
        </div>

        {{-- Session ended state --}}
        <div x-show="state.status === 'completed'" x-cloak class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <div class="w-16 h-16 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">{{ __('active_learning.session_completed') }}</h3>
            <a href="{{ route('tenant.active-learning.sessions.summary', [app('current_tenant')->slug, $course, $plan, $session]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition mt-4">
                {{ __('active_learning.view_summary') }}
            </a>
        </div>
    </div>

    @push('scripts')
    <script>
    function sessionDashboard() {
        return {
            state: {
                status: '{{ $session->status }}',
                participant_count: 0,
                current_activity: null,
                total_activities: {{ $activities->count() }},
                current_index: 0,
            },
            pollInterval: null,

            init() {
                this.fetchState();
                // Poll every 5 seconds as WebSocket fallback
                this.pollInterval = setInterval(() => this.fetchState(), 5000);

                // Listen via Echo if available
                if (window.Echo) {
                    const channel = window.Echo.join('active-learning-session.{{ $session->id }}');

                    channel.listen('.App\\Events\\ActiveLearning\\ActivityAdvanced', (e) => {
                        this.fetchState();
                    });

                    channel.listen('.App\\Events\\ActiveLearning\\ResponseSubmitted', (e) => {
                        if (this.state.current_activity && e.activity_id === this.state.current_activity.id) {
                            this.state.current_activity.response_count = e.response_count;
                        }
                        this.state.participant_count = e.participant_count;
                    });

                    channel.listen('.App\\Events\\ActiveLearning\\SessionEnded', (e) => {
                        this.state.status = 'completed';
                        clearInterval(this.pollInterval);
                    });
                }
            },

            async fetchState() {
                try {
                    const res = await fetch('{{ route("tenant.active-learning.sessions.state", [app("current_tenant")->slug, $course, $plan, $session]) }}');
                    if (res.ok) {
                        this.state = await res.json();
                    }
                } catch (e) {}
            },
        }
    }
    </script>
    @endpush
</x-tenant-layout>
