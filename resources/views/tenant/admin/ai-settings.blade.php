<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.dashboard', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ __('active_learning.ai_settings_title') }}</h2>
                <p class="text-sm text-slate-500">{{ __('active_learning.ai_settings_desc') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-2xl space-y-6">
        {{-- Provider & Key Management --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('active_learning.ai_provider') }}</h3>
            </div>
            <form method="POST" action="{{ route('tenant.admin.ai-settings.update', app('current_tenant')->slug) }}" class="p-6 space-y-5">
                @csrf @method('PUT')

                {{-- Provider Selection --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">{{ __('active_learning.default_provider') }}</label>
                    <div class="flex gap-3">
                        @foreach($providers as $provider)
                            <label class="flex items-center gap-2 px-4 py-3 rounded-xl border cursor-pointer transition {{ $currentProvider === $provider ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 hover:border-slate-300' }}">
                                <input type="radio" name="provider" value="{{ $provider }}" {{ $currentProvider === $provider ? 'checked' : '' }} class="text-indigo-600">
                                <span class="text-sm font-medium text-slate-700 capitalize">{{ $provider }}</span>
                                @if($keyStatus[$provider])
                                    <span class="text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-medium">{{ __('active_learning.key_set') }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- API Key Input --}}
                <div>
                    <label for="api_key" class="block text-sm font-medium text-slate-700">{{ __('active_learning.api_key') }}</label>
                    <p class="text-xs text-slate-400 mb-1">{{ __('active_learning.api_key_help') }}</p>
                    <input type="password" name="api_key" id="api_key" placeholder="sk-..."
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                        {{ __('active_learning.save_settings') }}
                    </button>
                </div>
            </form>

            {{-- Key removal --}}
            <div class="px-6 pb-6 space-y-2">
                @foreach($providers as $provider)
                    @if($keyStatus[$provider])
                        <form method="POST" action="{{ route('tenant.admin.ai-settings.update', app('current_tenant')->slug) }}" class="inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="provider" value="{{ $currentProvider }}">
                            <input type="hidden" name="remove_key" value="{{ $provider }}">
                            <button type="submit" onclick="return confirm('{{ __('active_learning.confirm_remove_key', ['provider' => $provider]) }}')"
                                class="text-xs text-red-500 hover:text-red-700 font-medium">
                                {{ __('active_learning.remove_key', ['provider' => ucfirst($provider)]) }}
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Test Connection --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('active_learning.test_connection') }}</h3>
            </div>
            <div class="p-6">
                <p class="text-sm text-slate-600 mb-4">{{ __('active_learning.test_connection_desc') }}</p>
                <form method="POST" action="{{ route('tenant.admin.ai-settings.test', app('current_tenant')->slug) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-teal-700 bg-teal-50 border border-teal-200 rounded-xl hover:bg-teal-100 transition">
                        {{ __('active_learning.test_now') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Usage Stats --}}
        @if($usageStats->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">{{ __('active_learning.ai_usage') }}</h3>
                </div>
                <div class="p-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-slate-500 uppercase">
                                <th class="pb-2">{{ __('active_learning.module') }}</th>
                                <th class="pb-2">{{ __('active_learning.calls') }}</th>
                                <th class="pb-2">{{ __('active_learning.tokens_in') }}</th>
                                <th class="pb-2">{{ __('active_learning.tokens_out') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($usageStats as $stat)
                                <tr>
                                    <td class="py-2 font-medium text-slate-700 capitalize">{{ str_replace('_', ' ', $stat->module) }}</td>
                                    <td class="py-2 text-slate-600">{{ number_format($stat->total_calls) }}</td>
                                    <td class="py-2 text-slate-600">{{ number_format($stat->total_input_tokens) }}</td>
                                    <td class="py-2 text-slate-600">{{ number_format($stat->total_output_tokens) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-tenant-layout>
