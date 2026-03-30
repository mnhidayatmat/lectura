<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.student-groups.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">New Group Set</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} — {{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST" action="{{ route('tenant.student-groups.store', [$tenant->slug, $course]) }}" class="space-y-6" x-data="{ method: 'random' }">
            @csrf

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Group Set Details</h3>

                {{-- Type --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Class Type</label>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach(['lecture' => 'Lecture', 'lab' => 'Lab', 'tutorial' => 'Tutorial'] as $key => $label)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="type" value="{{ $key }}" {{ $key === 'lab' ? 'checked' : '' }} class="peer sr-only">
                                <div class="flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 border-slate-200 dark:border-slate-600 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-900/20 transition">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $label }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Lab Groups Semester 1" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description <span class="text-slate-400">(optional)</span></label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition" placeholder="Purpose of these groups...">{{ old('description') }}</textarea>
                </div>

                {{-- Creation method --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Arrangement Method</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative cursor-pointer">
                            <input type="radio" name="creation_method" value="random" x-model="method" class="peer sr-only">
                            <div class="flex flex-col items-center gap-1.5 p-4 rounded-xl border-2 border-slate-200 dark:border-slate-600 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-900/20 transition">
                                <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Random</span>
                                <span class="text-[10px] text-slate-400">Auto-assign students</span>
                            </div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" name="creation_method" value="manual" x-model="method" class="peer sr-only">
                            <div class="flex flex-col items-center gap-1.5 p-4 rounded-xl border-2 border-slate-200 dark:border-slate-600 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-900/20 transition">
                                <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Manual</span>
                                <span class="text-[10px] text-slate-400">Create groups yourself</span>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Group size (for random) --}}
                <div x-show="method === 'random'" x-cloak x-transition>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Group Size</label>
                    <input type="number" name="group_size" value="{{ old('group_size', 4) }}" min="2" max="20" class="w-32 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                    <p class="text-xs text-slate-400 mt-1">{{ $studentCount }} students enrolled — will create ~{{ $studentCount > 0 ? (int) ceil($studentCount / max(old('group_size', 4), 1)) : 0 }} groups</p>
                    @error('group_size') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Group Set
                </button>
                <a href="{{ route('tenant.student-groups.index', [$tenant->slug, $course]) }}" class="px-4 py-2.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 transition">Cancel</a>
            </div>
        </form>
    </div>
</x-tenant-layout>
