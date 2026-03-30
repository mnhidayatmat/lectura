<x-tenant-layout>
    <div x-data="studentSession()" x-init="init()" class="max-w-lg mx-auto space-y-4">

        {{-- Session Header --}}
        <div class="text-center pt-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wide">
                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                Live
            </span>
            <h2 class="text-lg font-bold text-slate-900 mt-2">{{ $session->plan->title }}</h2>
            <p class="text-xs text-slate-400" x-text="'Activity ' + state.current_index + ' of ' + state.total_activities"></p>
        </div>

        {{-- Prerequisites --}}
        @if($session->plan->prerequisites)
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    <div>
                        <h4 class="text-xs font-semibold text-amber-800 uppercase tracking-wider mb-1">{{ __('active_learning.prerequisites') }}</h4>
                        <div class="text-sm text-amber-900 leading-relaxed whitespace-pre-line">{{ $session->plan->prerequisites }}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Waiting State --}}
        <div x-show="!state.current_activity && state.status === 'active'" class="bg-white rounded-2xl border border-slate-200 p-10 text-center">
            <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center mx-auto mb-4 animate-pulse">
                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm text-slate-500">{{ __('active_learning.waiting_for_activity') }}</p>
        </div>

        {{-- Current Activity --}}
        <div x-show="state.current_activity" x-cloak class="bg-white rounded-2xl border border-slate-200 overflow-hidden">

            {{-- Activity Info --}}
            <div class="px-5 py-4 border-b border-slate-100">
                <div class="flex items-center gap-2 mb-1.5">
                    <h3 class="text-base font-bold text-slate-900" x-text="state.current_activity?.title"></h3>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-[10px] font-bold uppercase" x-text="state.current_activity?.type"></span>
                    <span class="text-xs text-slate-400" x-text="state.current_activity?.duration_minutes + ' min'"></span>
                </div>
            </div>

            {{-- Instructions --}}
            <div class="px-5 py-4 bg-indigo-50/50">
                <div class="text-sm text-slate-700 rich-instructions" x-html="state.current_activity?.instructions || state.current_activity?.description || ''"></div>
            </div>

            {{-- Response Area --}}
            <div class="px-5 py-5">
                {{-- Already submitted --}}
                <div x-show="submitted" x-cloak class="text-center py-4">
                    <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </div>
                    <p class="text-sm font-medium text-emerald-700">{{ __('active_learning.response_submitted') }}</p>
                    <button @click="submitted = false; submitting = false" class="mt-2 text-xs text-indigo-600 hover:text-indigo-700 font-medium">{{ __('active_learning.edit_response') }}</button>
                </div>

                {{-- Text response --}}
                <template x-if="!submitted && ['text', 'reflection'].includes(state.current_activity?.response_type)">
                    <div>
                        <textarea
                            x-model="responseText"
                            :maxlength="state.current_activity?.response_type === 'reflection' ? 500 : 2000"
                            rows="4"
                            :placeholder="state.current_activity?.response_type === 'reflection' ? '{{ __('active_learning.write_reflection') }}' : '{{ __('active_learning.type_response') }}'"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition resize-none placeholder:text-slate-400"
                        ></textarea>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xs text-slate-400" x-text="responseText.length + '/' + (state.current_activity?.response_type === 'reflection' ? 500 : 2000)"></span>
                            <button @click="submitText()" :disabled="submitting || !responseText.trim()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 text-white text-sm font-medium rounded-xl transition">
                                <span x-show="!submitting">{{ __('active_learning.submit') }}</span>
                                <span x-show="submitting" class="flex items-center gap-2">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    {{ __('active_learning.submitting') }}
                                </span>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- MCQ response --}}
                <template x-if="!submitted && state.current_activity?.response_type === 'mcq'">
                    <div>
                        <div class="space-y-2">
                            <template x-for="option in state.current_activity?.poll_options || []" :key="option.id">
                                <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition"
                                       :class="selectedOptions.includes(option.id) ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-slate-300'">
                                    <input type="checkbox" :value="option.id" x-model.number="selectedOptions" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20" />
                                    <span class="text-sm text-slate-700" x-text="option.label"></span>
                                </label>
                            </template>
                        </div>
                        <button @click="submitMcq()" :disabled="submitting || selectedOptions.length === 0" class="w-full mt-4 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 text-white text-sm font-medium rounded-xl transition">
                            <span x-show="!submitting">{{ __('active_learning.submit') }}</span>
                            <span x-show="submitting">{{ __('active_learning.submitting') }}</span>
                        </button>
                    </div>
                </template>

                {{-- No response needed --}}
                <template x-if="state.current_activity?.response_type === 'none'">
                    <div class="text-center py-4">
                        <p class="text-sm text-slate-500">{{ __('active_learning.no_response_needed') }}</p>
                    </div>
                </template>
            </div>
        </div>

        {{-- Session Ended --}}
        <div x-show="state.status === 'completed'" x-cloak class="bg-white rounded-2xl border border-slate-200 p-10 text-center">
            <div class="w-14 h-14 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">{{ __('active_learning.session_ended') }}</h3>
            <a href="{{ route('tenant.session.review', [app('current_tenant')->slug, $session]) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition mt-2">
                {{ __('active_learning.review_responses') }}
            </a>
        </div>
    </div>

    @push('scripts')
    <script>
    function studentSession() {
        return {
            state: {!! json_encode(app(App\Services\ActiveLearning\SessionService::class)->getSessionState($session)) !!},
            responseText: {!! json_encode($existingResponse?->getTextContent() ?? '') !!},
            selectedOptions: {!! json_encode($existingResponse?->getSelectedOptions() ?? []) !!},
            submitted: {{ $existingResponse ? 'true' : 'false' }},
            submitting: false,
            pollInterval: null,

            init() {
                this.pollInterval = setInterval(() => this.fetchState(), 5000);

                if (window.Echo) {
                    const channel = window.Echo.join('active-learning-session.{{ $session->id }}');

                    channel.listen('.App\\Events\\ActiveLearning\\ActivityAdvanced', () => {
                        this.submitted = false;
                        this.responseText = '';
                        this.selectedOptions = [];
                        this.fetchState();
                    });

                    channel.listen('.App\\Events\\ActiveLearning\\SessionEnded', () => {
                        this.state.status = 'completed';
                        clearInterval(this.pollInterval);
                    });
                }
            },

            async fetchState() {
                try {
                    const res = await fetch('{{ route("tenant.session.student-state", [app("current_tenant")->slug, $session]) }}');
                    if (res.ok) {
                        const data = await res.json();
                        const prevActivityId = this.state.current_activity?.id;
                        this.state = data;

                        // Reset form if activity changed
                        if (data.current_activity?.id !== prevActivityId) {
                            this.submitted = data.current_activity?.user_responded || false;
                            this.responseText = data.current_activity?.user_response?.response_data?.text || '';
                            this.selectedOptions = data.current_activity?.user_response?.response_data?.selected_options || [];
                        }
                    }
                } catch (e) {}
            },

            async submitText() {
                this.submitting = true;
                await this.sendResponse({ text: this.responseText });
            },

            async submitMcq() {
                this.submitting = true;
                await this.sendResponse({ selected_options: this.selectedOptions });
            },

            async sendResponse(responseData) {
                try {
                    const res = await fetch('{{ route("tenant.session.respond", [app("current_tenant")->slug, $session]) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            activity_id: this.state.current_activity.id,
                            response_data: responseData,
                        }),
                    });

                    if (res.ok) {
                        this.submitted = true;
                    } else {
                        const err = await res.json();
                        alert(err.message || 'Failed to submit');
                    }
                } catch (e) {
                    alert('Network error. Please try again.');
                } finally {
                    this.submitting = false;
                }
            },
        }
    }
    </script>
    @endpush
</x-tenant-layout>
