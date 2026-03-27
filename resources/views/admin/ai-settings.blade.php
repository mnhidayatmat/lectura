<x-admin-layout>
    <div x-data="aiSettings()" class="space-y-6">

        {{-- Page header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">AI Provider Settings</h2>
                <p class="mt-1 text-sm text-slate-500">Configure global AI providers for the platform</p>
            </div>
            <button
                @click="openAddModal()"
                type="button"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Provider
            </button>
        </div>

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="flex items-center gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-800" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition>
                <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-800" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition>
                <svg class="w-5 h-5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                {{ session('error') }}
            </div>
        @endif

        {{-- Stats row --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-slate-900">{{ $providers->count() }}</p>
                <p class="text-sm text-slate-500">Total Providers</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-slate-900">{{ $providers->where('is_active', true)->count() }}</p>
                <p class="text-sm text-slate-500">Active</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-slate-900">{{ $providers->filter(fn($p) => $p->hasApiKey())->count() }}</p>
                <p class="text-sm text-slate-500">Keys Configured</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-slate-900">{{ $providers->where('is_default', true)->first()?->name ?? 'None' }}</p>
                <p class="text-sm text-slate-500">Default Provider</p>
            </div>
        </div>

        {{-- Providers list --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Configured Providers</h3>
                <span class="text-xs text-slate-400">{{ $providers->count() }} provider(s)</span>
            </div>

            @if($providers->isEmpty())
                <div class="p-16 flex flex-col items-center text-center">
                    <div class="w-20 h-20 bg-violet-50 rounded-2xl flex items-center justify-center mb-6">
                        <svg class="w-10 h-10 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">No AI providers configured</h3>
                    <p class="text-sm text-slate-500 max-w-sm mb-6">Add your first AI provider to enable AI-powered features across the platform.</p>
                    <button @click="openAddModal()" type="button" class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Your First Provider
                    </button>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($providers as $provider)
                        <div class="px-6 py-5 hover:bg-slate-50/50 transition">
                            <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                                {{-- Provider info --}}
                                <div class="flex items-center gap-4 flex-1 min-w-0">
                                    <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0
                                        {{ match($provider->provider_type) {
                                            'anthropic' => 'bg-orange-100',
                                            'openai' => 'bg-emerald-100',
                                            'google' => 'bg-blue-100',
                                            default => 'bg-slate-100',
                                        } }}">
                                        @if($provider->provider_type === 'anthropic')
                                            <svg class="w-6 h-6 text-orange-700" viewBox="0 0 24 24" fill="currentColor"><path d="M13.827 3.52l3.603 10.106-3.36 2.302L8.254 3.52h5.573zm-5.78 0l5.862 16.96H19.5L13.638 3.52H8.047zM4.5 3.52l5.862 16.96h-.002L4.5 3.52z"/></svg>
                                        @elseif($provider->provider_type === 'openai')
                                            <svg class="w-6 h-6 text-emerald-700" viewBox="0 0 24 24" fill="currentColor"><path d="M22.282 9.821a5.985 5.985 0 00-.516-4.91 6.046 6.046 0 00-6.51-2.9A6.065 6.065 0 0011.035.41a6.045 6.045 0 00-5.769 4.21 5.987 5.987 0 00-3.998 2.9 6.05 6.05 0 00.74 7.097 5.98 5.98 0 00.516 4.911 6.05 6.05 0 006.51 2.9A6.065 6.065 0 0013.455 24a6.043 6.043 0 005.77-4.211 5.987 5.987 0 003.997-2.9 6.052 6.052 0 00-.94-7.068z"/></svg>
                                        @elseif($provider->provider_type === 'google')
                                            <svg class="w-6 h-6 text-blue-700" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                                        @else
                                            <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="font-semibold text-slate-900 truncate">{{ $provider->name }}</h4>
                                            @if($provider->is_default)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-[10px] font-bold uppercase tracking-wide">Default</span>
                                            @endif
                                            @if($provider->is_active)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-[10px] font-bold uppercase tracking-wide">
                                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>Active
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full text-[10px] font-bold uppercase tracking-wide">Inactive</span>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                                {{ $providerTypes[$provider->provider_type] ?? $provider->provider_type }}
                                            </span>
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                <code class="bg-slate-100 px-1.5 py-0.5 rounded text-[11px]">{{ $provider->model }}</code>
                                            </span>
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                                @if($provider->hasApiKey())
                                                    <span class="text-emerald-600 font-medium">{{ $provider->getMaskedApiKey() }}</span>
                                                @else
                                                    <span class="text-amber-600 font-medium">No key set</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Config summary --}}
                                <div class="flex flex-wrap items-center gap-2 lg:gap-3 text-xs shrink-0">
                                    <div class="px-2.5 py-1.5 bg-slate-50 rounded-lg text-center">
                                        <p class="font-semibold text-slate-700">{{ number_format($provider->max_tokens) }}</p>
                                        <p class="text-[10px] text-slate-400">Max Tokens</p>
                                    </div>
                                    <div class="px-2.5 py-1.5 bg-slate-50 rounded-lg text-center">
                                        <p class="font-semibold text-slate-700">{{ $provider->temperature }}</p>
                                        <p class="text-[10px] text-slate-400">Temp</p>
                                    </div>
                                    <div class="px-2.5 py-1.5 bg-slate-50 rounded-lg text-center">
                                        <p class="font-semibold text-slate-700">{{ $provider->top_p }}</p>
                                        <p class="text-[10px] text-slate-400">Top P</p>
                                    </div>
                                    <div class="px-2.5 py-1.5 bg-slate-50 rounded-lg text-center">
                                        <p class="font-semibold text-slate-700">{{ $provider->timeout_seconds }}s</p>
                                        <p class="text-[10px] text-slate-400">Timeout</p>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <form method="POST" action="{{ route('admin.ai-settings.test', $provider) }}">
                                        @csrf
                                        <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-teal-600 hover:bg-teal-50 transition" title="Test Connection">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        </button>
                                    </form>

                                    <button
                                        @click="editProvider({{ $provider->id }}, {{ Js::from($provider->only(['id','name','provider_type','model','api_base_url','max_tokens','temperature','top_p','timeout_seconds','is_active','is_default'])) }})"
                                        type="button"
                                        class="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition"
                                        title="Edit"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>

                                    @unless($provider->is_default)
                                        <form method="POST" action="{{ route('admin.ai-settings.destroy', $provider) }}" onsubmit="return confirm('Delete provider {{ addslashes($provider->name) }}? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition" title="Delete">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Add Provider Modal --}}
        <div x-show="modalMode === 'add'" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-full items-start justify-center p-4 pt-20">
                <div x-show="modalMode === 'add'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="modalMode = null"></div>

                <div x-show="modalMode === 'add'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-4 scale-95" class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl">

                    <form method="POST" action="{{ route('admin.ai-settings.store') }}">
                        @csrf
                        <div class="px-6 py-5 border-b border-slate-100">
                            <h3 class="text-lg font-bold text-slate-900">Add AI Provider</h3>
                            <p class="text-sm text-slate-500 mt-1">Configure a new AI provider for the platform</p>
                        </div>

                        <div class="px-6 py-5 space-y-5 max-h-[60vh] overflow-y-auto">
                            {{-- Name + Provider Type --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Display Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" placeholder="e.g. Claude Production" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition placeholder:text-slate-400" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Provider Type <span class="text-red-500">*</span></label>
                                    <select name="provider_type" x-model="form.provider_type" @change="onProviderTypeChange()" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition bg-white">
                                        @foreach($providerTypes as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- API Key --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">API Key</label>
                                <input type="password" name="api_key" placeholder="sk-ant-api03-..." class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition placeholder:text-slate-400 font-mono" />
                                <p class="mt-1.5 text-xs text-slate-400" x-text="providerHint"></p>
                            </div>

                            {{-- Model + Base URL --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Model <span class="text-red-500">*</span></label>
                                    <template x-if="form.provider_type !== 'custom'">
                                        <select name="model" x-model="form.model" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition bg-white">
                                            <template x-for="(label, key) in getModelsFor(form.provider_type)" :key="key">
                                                <option :value="key" x-text="label"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="form.provider_type === 'custom'">
                                        <input type="text" name="model" x-model="form.model" placeholder="e.g. my-model-v1" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition placeholder:text-slate-400" />
                                    </template>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">API Base URL <span class="text-slate-400 font-normal">(optional)</span></label>
                                    <input type="url" name="api_base_url" placeholder="https://api.example.com/v1" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition placeholder:text-slate-400" />
                                    <p class="mt-1.5 text-xs text-slate-400">Override default endpoint. Required for custom providers.</p>
                                </div>
                            </div>

                            {{-- Generation Parameters --}}
                            <div class="border border-slate-200 rounded-xl p-4">
                                <h4 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                    Generation Parameters
                                </h4>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Max Tokens</label>
                                        <input type="number" name="max_tokens" value="4096" min="1" max="200000" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Temperature</label>
                                        <input type="number" name="temperature" value="0.7" min="0" max="2" step="0.05" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition" />
                                        <p class="mt-1 text-[10px] text-slate-400">0 = focused, 2 = creative</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Top P</label>
                                        <input type="number" name="top_p" value="1.0" min="0" max="1" step="0.05" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition" />
                                        <p class="mt-1 text-[10px] text-slate-400">Nucleus sampling</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Timeout (s)</label>
                                        <input type="number" name="timeout_seconds" value="120" min="10" max="600" step="5" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition" />
                                    </div>
                                </div>
                            </div>

                            {{-- Toggles --}}
                            <div class="flex flex-wrap items-center gap-6">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="hidden" name="is_active" value="0" />
                                    <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded border-slate-300 text-red-600 focus:ring-red-500/20" />
                                    <span class="text-sm font-medium text-slate-700">Active</span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="hidden" name="is_default" value="0" />
                                    <input type="checkbox" name="is_default" value="1" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/20" />
                                    <span class="text-sm font-medium text-slate-700">Set as Default</span>
                                </label>
                            </div>
                        </div>

                        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50 rounded-b-2xl">
                            <button type="button" @click="modalMode = null" class="px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200 rounded-xl transition">Cancel</button>
                            <button type="submit" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Add Provider</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Edit Provider Modal --}}
        <div x-show="modalMode === 'edit'" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-full items-start justify-center p-4 pt-20">
                <div x-show="modalMode === 'edit'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="modalMode = null"></div>

                <div x-show="modalMode === 'edit'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-4 scale-95" class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl">

                    <form :action="editAction" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="px-6 py-5 border-b border-slate-100">
                            <h3 class="text-lg font-bold text-slate-900">Edit AI Provider</h3>
                            <p class="text-sm text-slate-500 mt-1">Update provider configuration</p>
                        </div>

                        <div class="px-6 py-5 space-y-5 max-h-[60vh] overflow-y-auto">
                            {{-- Name + Provider Type --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Display Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" x-model="form.name" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition placeholder:text-slate-400" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Provider Type <span class="text-red-500">*</span></label>
                                    <select name="provider_type" x-model="form.provider_type" @change="onProviderTypeChange()" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition bg-white">
                                        @foreach($providerTypes as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- API Key --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">API Key <span class="text-slate-400 font-normal">(leave empty to keep current)</span></label>
                                <input type="password" name="api_key" placeholder="Enter new key to replace current one" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition placeholder:text-slate-400 font-mono" />
                                <p class="mt-1.5 text-xs text-slate-400" x-text="providerHint"></p>
                            </div>

                            {{-- Model + Base URL --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Model <span class="text-red-500">*</span></label>
                                    <template x-if="form.provider_type !== 'custom'">
                                        <select name="model" x-model="form.model" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition bg-white">
                                            <template x-for="(label, key) in getModelsFor(form.provider_type)" :key="key">
                                                <option :value="key" x-text="label"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="form.provider_type === 'custom'">
                                        <input type="text" name="model" x-model="form.model" placeholder="e.g. my-model-v1" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition placeholder:text-slate-400" />
                                    </template>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">API Base URL <span class="text-slate-400 font-normal">(optional)</span></label>
                                    <input type="url" name="api_base_url" x-model="form.api_base_url" placeholder="https://api.example.com/v1" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition placeholder:text-slate-400" />
                                </div>
                            </div>

                            {{-- Generation Parameters --}}
                            <div class="border border-slate-200 rounded-xl p-4">
                                <h4 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                    Generation Parameters
                                </h4>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Max Tokens</label>
                                        <input type="number" name="max_tokens" x-model="form.max_tokens" min="1" max="200000" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Temperature</label>
                                        <input type="number" name="temperature" x-model="form.temperature" min="0" max="2" step="0.05" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition" />
                                        <p class="mt-1 text-[10px] text-slate-400">0 = focused, 2 = creative</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Top P</label>
                                        <input type="number" name="top_p" x-model="form.top_p" min="0" max="1" step="0.05" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition" />
                                        <p class="mt-1 text-[10px] text-slate-400">Nucleus sampling</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Timeout (s)</label>
                                        <input type="number" name="timeout_seconds" x-model="form.timeout_seconds" min="10" max="600" step="5" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition" />
                                    </div>
                                </div>
                            </div>

                            {{-- Toggles --}}
                            <div class="flex flex-wrap items-center gap-6">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="hidden" name="is_active" value="0" />
                                    <input type="checkbox" name="is_active" value="1" :checked="form.is_active" class="w-4 h-4 rounded border-slate-300 text-red-600 focus:ring-red-500/20" />
                                    <span class="text-sm font-medium text-slate-700">Active</span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="hidden" name="is_default" value="0" />
                                    <input type="checkbox" name="is_default" value="1" :checked="form.is_default" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/20" />
                                    <span class="text-sm font-medium text-slate-700">Set as Default</span>
                                </label>
                            </div>
                        </div>

                        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50 rounded-b-2xl">
                            <button type="button" @click="modalMode = null" class="px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200 rounded-xl transition">Cancel</button>
                            <button type="submit" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    @push('scripts')
    <script>
        function aiSettings() {
            return {
                modalMode: null, // null, 'add', 'edit'
                editAction: '',
                defaultModels: @json($defaultModels),

                form: {
                    name: '',
                    provider_type: 'anthropic',
                    model: 'claude-sonnet-4-6',
                    api_base_url: '',
                    max_tokens: 4096,
                    temperature: 0.7,
                    top_p: 1.0,
                    timeout_seconds: 120,
                    is_active: true,
                    is_default: false,
                },

                get providerHint() {
                    const hints = {
                        anthropic: 'Get your API key from console.anthropic.com',
                        openai: 'Get your API key from platform.openai.com',
                        google: 'Get your API key from aistudio.google.com',
                        custom: 'Enter the API key for your custom provider',
                    };
                    return hints[this.form.provider_type] || '';
                },

                getModelsFor(type) {
                    return this.defaultModels[type] || {};
                },

                onProviderTypeChange() {
                    const models = this.getModelsFor(this.form.provider_type);
                    const keys = Object.keys(models);
                    this.form.model = keys.length > 0 ? keys[0] : '';
                },

                openAddModal() {
                    this.form = {
                        name: '',
                        provider_type: 'anthropic',
                        model: 'claude-sonnet-4-6',
                        api_base_url: '',
                        max_tokens: 4096,
                        temperature: 0.7,
                        top_p: 1.0,
                        timeout_seconds: 120,
                        is_active: true,
                        is_default: false,
                    };
                    this.modalMode = 'add';
                },

                editProvider(id, provider) {
                    this.editAction = '{{ url("admin/ai-settings") }}/' + id;
                    this.form = {
                        name: provider.name,
                        provider_type: provider.provider_type,
                        model: provider.model,
                        api_base_url: provider.api_base_url || '',
                        max_tokens: provider.max_tokens,
                        temperature: provider.temperature,
                        top_p: provider.top_p,
                        timeout_seconds: provider.timeout_seconds,
                        is_active: provider.is_active,
                        is_default: provider.is_default,
                    };
                    this.modalMode = 'edit';
                },
            }
        }
    </script>
    @endpush
</x-admin-layout>
