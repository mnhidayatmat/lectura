<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Programme Learning Outcomes</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $programme->code }} — {{ $programme->name }}</p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- Existing PLOs --}}
        @if($plos->isNotEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($plos as $plo)
                        <div x-data="{ editing: false }" class="p-4">
                            <div x-show="!editing" class="flex items-start justify-between">
                                <div class="flex items-start gap-3">
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 text-xs font-bold flex-shrink-0">{{ $plo->code }}</span>
                                    <div>
                                        <p class="text-sm text-slate-800 dark:text-slate-200">{{ $plo->description }}</p>
                                        @if($plo->domain)
                                            <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wider">{{ str_replace('_', ' ', $plo->domain) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                                    <button @click="editing = true" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Edit</button>
                                    <form method="POST" action="{{ route('tenant.plos.destroy', [$tenant->slug, $programme, $plo]) }}" onsubmit="return confirm('Delete this PLO?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
                                    </form>
                                </div>
                            </div>

                            {{-- Inline edit form --}}
                            <form x-show="editing" x-cloak method="POST" action="{{ route('tenant.plos.update', [$tenant->slug, $programme, $plo]) }}" class="space-y-3">
                                @csrf @method('PUT')
                                <div class="grid sm:grid-cols-4 gap-3">
                                    <input type="text" name="code" value="{{ $plo->code }}" required class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm" placeholder="Code">
                                    <div class="sm:col-span-2">
                                        <input type="text" name="description" value="{{ $plo->description }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm" placeholder="Description">
                                    </div>
                                    <select name="domain" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm">
                                        <option value="">Domain</option>
                                        @foreach(\App\Models\ProgrammeLearningOutcome::DOMAINS as $d)
                                            <option value="{{ $d }}" {{ $plo->domain === $d ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $d)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">Save</button>
                                    <button type="button" @click="editing = false" class="px-3 py-1.5 text-slate-500 text-xs rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition">Cancel</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Add PLO form --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Add PLO</h3>
            <form method="POST" action="{{ route('tenant.plos.store', [$tenant->slug, $programme]) }}" class="space-y-3">
                @csrf
                <div class="grid sm:grid-cols-4 gap-3">
                    <div>
                        <input type="text" name="code" required placeholder="e.g. PLO1" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500">
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <input type="text" name="description" required placeholder="Description of the programme learning outcome" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500">
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <select name="domain" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">MQF Domain</option>
                            @foreach(\App\Models\ProgrammeLearningOutcome::DOMAINS as $d)
                                <option value="{{ $d }}">{{ ucfirst(str_replace('_', ' ', $d)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Add PLO</button>
            </form>
        </div>
    </div>
</x-tenant-layout>
