<x-tenant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Semesters</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage academic terms for your courses</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6" x-data="{ showCreate: false, editingId: null }">

        @if(session('success'))
            <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-700 dark:text-red-300">
                {{ session('error') }}
            </div>
        @endif

        {{-- Create Button --}}
        <div class="flex justify-end">
            <button @click="showCreate = !showCreate" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span x-text="showCreate ? 'Cancel' : 'Add Semester'"></span>
            </button>
        </div>

        {{-- Create Form --}}
        <div x-show="showCreate" x-cloak x-transition class="bg-white dark:bg-slate-800 rounded-2xl border border-indigo-200 dark:border-indigo-700 p-6">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-4">New Semester</h3>
            <form method="POST" action="{{ route('tenant.academic-terms.store', app('current_tenant')->slug) }}">
                @csrf
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Semester 1, 2026/2027" value="{{ old('name') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Code *</label>
                        <input type="text" name="code" required placeholder="e.g. SEM1-2627" value="{{ old('code') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Start Date *</label>
                        <input type="date" name="start_date" required value="{{ old('start_date') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">End Date *</label>
                        <input type="date" name="end_date" required value="{{ old('end_date') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                </div>
                <div class="flex items-center justify-between mt-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_default" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Set as default semester</span>
                    </label>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">
                        Create Semester
                    </button>
                </div>
            </form>
            @if($errors->any())
                <div class="mt-3 text-sm text-red-600 dark:text-red-400">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Semesters List --}}
        @if($terms->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-12 text-center">
                <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">No semesters created yet.</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Add a semester to organise your courses by academic term.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($terms as $term)
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        {{-- View Mode --}}
                        <div x-show="editingId !== {{ $term->id }}" class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-4 flex-1 min-w-0">
                                <div class="w-12 h-12 rounded-xl {{ $term->is_default ? 'bg-indigo-100 dark:bg-indigo-900/40' : 'bg-slate-100 dark:bg-slate-700' }} flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6 {{ $term->is_default ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $term->name }}</p>
                                        @if($term->is_default)
                                            <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 rounded-full">Default</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        <span class="font-medium">{{ $term->code }}</span>
                                        &middot; {{ $term->start_date->format('d M Y') }} — {{ $term->end_date->format('d M Y') }}
                                        &middot; {{ $term->courses_count }} {{ Str::plural('course', $term->courses_count) }}, {{ $term->sections_count }} {{ Str::plural('section', $term->sections_count) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button @click="editingId = {{ $term->id }}" class="p-2 text-slate-400 hover:text-indigo-600 transition" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('tenant.academic-terms.destroy', [app('current_tenant')->slug, $term]) }}" onsubmit="return confirm('Delete this semester? Only possible if no courses/sections are assigned.')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-400 hover:text-red-500 transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Edit Mode --}}
                        <div x-show="editingId === {{ $term->id }}" x-cloak class="p-5 bg-slate-50 dark:bg-slate-900/30">
                            <form method="POST" action="{{ route('tenant.academic-terms.update', [app('current_tenant')->slug, $term]) }}">
                                @csrf
                                @method('PUT')
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name *</label>
                                        <input type="text" name="name" required value="{{ $term->name }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Code *</label>
                                        <input type="text" name="code" required value="{{ $term->code }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Start Date *</label>
                                        <input type="date" name="start_date" required value="{{ $term->start_date->format('Y-m-d') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">End Date *</label>
                                        <input type="date" name="end_date" required value="{{ $term->end_date->format('Y-m-d') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mt-4">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="is_default" value="1" @checked($term->is_default) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="text-sm text-slate-600 dark:text-slate-400">Set as default</span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="editingId = null" class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl transition">Cancel</button>
                                        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition">Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-tenant-layout>
