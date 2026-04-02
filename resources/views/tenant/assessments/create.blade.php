<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Add Assessment</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} — {{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('tenant.assessments.store', [$tenant->slug, $course]) }}" class="space-y-6">
            @csrf

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Assessment Details</h3>

                {{-- Title --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required placeholder="e.g. Quiz 1, Assignment 2, Final Exam" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Type & Method --}}
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Type *</label>
                        <select name="type" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                            @foreach(\App\Models\Assessment::TYPES as $type)
                                <option value="{{ $type }}" {{ old('type') === $type ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Method</label>
                        <select name="method" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                            <option value="">— Select —</option>
                            @foreach(\App\Models\Assessment::METHODS as $method)
                                <option value="{{ $method }}" {{ old('method') === $method ? 'selected' : '' }}>{{ ucfirst($method) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Weightage & Marks --}}
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Weightage (%) *</label>
                        <input type="number" name="weightage" value="{{ old('weightage', 10) }}" required min="0" max="100" step="0.5" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                        @error('weightage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Total Marks *</label>
                        <input type="number" name="total_marks" value="{{ old('total_marks', 100) }}" required min="1" step="0.5" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                        @error('total_marks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Bloom Level --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Bloom's Taxonomy Level</label>
                    <select name="bloom_level" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                        <option value="">— Select —</option>
                        @foreach(\App\Models\Assessment::BLOOM_LEVELS as $level)
                            <option value="{{ $level }}" {{ old('bloom_level') === $level ? 'selected' : '' }}>{{ ucfirst($level) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- CLO Mapping --}}
                @if($course->learningOutcomes->isNotEmpty())
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Map to CLOs</label>
                        <div class="space-y-2">
                            @foreach($course->learningOutcomes as $clo)
                                <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition cursor-pointer">
                                    <input type="checkbox" name="clo_ids[]" value="{{ $clo->id }}" {{ in_array($clo->id, old('clo_ids', [])) ? 'checked' : '' }} class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    <div>
                                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $clo->code }}</span>
                                        <p class="text-xs text-slate-600 dark:text-slate-300">{{ $clo->description }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Description --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description <span class="text-slate-400">(optional)</span></label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition" placeholder="Additional notes about this assessment...">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Assessment
                </button>
                <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="px-4 py-2.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 transition">Cancel</a>
            </div>
        </form>
    </div>
</x-tenant-layout>
