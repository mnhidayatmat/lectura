<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.dashboard', $tenant->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('active_learning.session_review') }}</h2>
                <p class="text-sm text-slate-500">{{ $session->plan->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto space-y-4">
        @foreach($activities as $activity)
            @php $response = $userResponses->get($activity->id); @endphp
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">{{ $loop->iteration }}</span>
                        <h4 class="font-semibold text-slate-900">{{ $activity->title }}</h4>
                        <span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full text-[10px] font-bold uppercase">{{ $activity->type }}</span>
                    </div>
                </div>

                <div class="px-5 py-4">
                    @if($activity->instructions)
                        <p class="text-sm text-slate-600 mb-3">{{ $activity->instructions }}</p>
                    @endif

                    @if($response)
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                            <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-1">{{ __('active_learning.your_response') }}</p>
                            @if(in_array($response->response_type, ['text', 'reflection']))
                                <p class="text-sm text-slate-700">{{ $response->getTextContent() }}</p>
                            @elseif($response->response_type === 'mcq')
                                @foreach($activity->pollOptions as $option)
                                    <div class="flex items-center gap-2 text-sm {{ in_array($option->id, $response->getSelectedOptions()) ? 'text-indigo-700 font-medium' : 'text-slate-400' }}">
                                        @if(in_array($option->id, $response->getSelectedOptions()))
                                            <svg class="w-4 h-4 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        @else
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                                        @endif
                                        {{ $option->label }}
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-slate-400 italic">{{ __('active_learning.no_response_submitted') }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-tenant-layout>
