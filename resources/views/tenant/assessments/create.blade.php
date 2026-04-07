<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            @if($parent)
                <a href="{{ route('tenant.assessments.edit', [$tenant->slug, $course, $parent]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @else
                <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @endif
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $parent ? 'Add Child Assessment' : 'Add Assessment' }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">@if($parent) As a part of: {{ $parent->title }} @else {{ $course->code }} — {{ $course->title }} @endif</p>
            </div>
        </div>
    </x-slot>

    @if($parent)
        <div class="mb-6 px-4 py-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-indigo-900 dark:text-indigo-100">Creating child assessment for <strong>{{ $parent->title }}</strong></p>
                <p class="text-xs text-indigo-600 dark:text-indigo-300">This will be a part that contributes to the parent assessment's total weightage and marks.</p>
            </div>
        </div>
    @endif

    <div class="max-w-2xl" x-data="{ selectedType: '{{ old('type', 'quiz') }}', requiresSubmission: {{ old('requires_submission') ? 'true' : 'false' } }">
        <form method="POST" action="{{ route('tenant.assessments.store', [$tenant->slug, $course]) }}" class="space-y-6">
            @csrf
            @if($parent)
                <input type="hidden" name="parent_id" value="{{ $parent->id }}" />
            @endif

            {{-- Assessment Type Cards --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Assessment Type</h3>
                <input type="hidden" name="type" :value="selectedType">
                @php
                    $typeCards = [
                        'quiz' => ['label' => 'Quiz', 'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'amber'],
                        'assignment' => ['label' => 'Assignment', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'color' => 'blue'],
                        'test' => ['label' => 'Test', 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'color' => 'indigo'],
                        'project' => ['label' => 'Project', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', 'color' => 'purple'],
                        'presentation' => ['label' => 'Presentation', 'icon' => 'M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z', 'color' => 'rose'],
                        'lab' => ['label' => 'Lab', 'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z', 'color' => 'teal'],
                        'final_exam' => ['label' => 'Final Exam', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'red'],
                        'other' => ['label' => 'Other', 'icon' => 'M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z', 'color' => 'slate'],
                    ];
                @endphp
                <div class="grid grid-cols-4 gap-2">
                    @foreach($typeCards as $key => $card)
                        <button type="button" @click="selectedType = '{{ $key }}'"
                            :class="selectedType === '{{ $key }}' ? 'border-{{ $card['color'] }}-500 bg-{{ $card['color'] }}-50 dark:bg-{{ $card['color'] }}-900/20 ring-2 ring-{{ $card['color'] }}-200' : 'border-slate-200 dark:border-slate-600 hover:border-{{ $card['color'] }}-300'"
                            class="flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 transition text-center">
                            <svg class="w-5 h-5 text-{{ $card['color'] }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/></svg>
                            <span class="text-[11px] font-medium text-slate-700 dark:text-slate-300">{{ $card['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                @error('type') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Details --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Details</h3>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required placeholder="e.g. Quiz 1, Assignment 2, Final Exam" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
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

                {{-- Bloom's Taxonomy visual selector --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Bloom's Taxonomy Level</label>
                    @php
                        $bloomData = [
                            'remember' => ['label' => 'Remember', 'desc' => 'Recall facts', 'color' => 'slate'],
                            'understand' => ['label' => 'Understand', 'desc' => 'Explain ideas', 'color' => 'sky'],
                            'apply' => ['label' => 'Apply', 'desc' => 'Use in new situations', 'color' => 'emerald'],
                            'analyze' => ['label' => 'Analyze', 'desc' => 'Draw connections', 'color' => 'amber'],
                            'evaluate' => ['label' => 'Evaluate', 'desc' => 'Justify decisions', 'color' => 'orange'],
                            'create' => ['label' => 'Create', 'desc' => 'Produce new work', 'color' => 'rose'],
                        ];
                    @endphp
                    <div class="grid grid-cols-6 gap-1.5" x-data="{ bloom: '{{ old('bloom_level', '') }}' }">
                        <input type="hidden" name="bloom_level" :value="bloom">
                        @foreach($bloomData as $key => $b)
                            <button type="button" @click="bloom = bloom === '{{ $key }}' ? '' : '{{ $key }}'"
                                :class="bloom === '{{ $key }}' ? 'border-{{ $b['color'] }}-500 bg-{{ $b['color'] }}-50 dark:bg-{{ $b['color'] }}-900/20 ring-1 ring-{{ $b['color'] }}-300' : 'border-slate-200 dark:border-slate-600'"
                                class="flex flex-col items-center p-2 rounded-xl border-2 transition text-center">
                                <span class="text-[11px] font-semibold text-{{ $b['color'] }}-600 dark:text-{{ $b['color'] }}-400">{{ $b['label'] }}</span>
                                <span class="text-[8px] text-slate-400 leading-tight mt-0.5">{{ $b['desc'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description <span class="text-slate-400">(optional)</span></label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition" placeholder="Additional notes about this assessment...">{{ old('description') }}</textarea>
                </div>
            </div>

            {{-- Student Submission --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Student Submission</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Require students to submit files for this assessment</p>
                    </div>
                    <button type="button" @click="requiresSubmission = !requiresSubmission" :class="requiresSubmission ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600'" class="relative w-11 h-6 rounded-full transition-colors">
                        <span :class="requiresSubmission ? 'translate-x-5' : 'translate-x-0.5'" class="inline-block w-5 h-5 bg-white rounded-full shadow transform transition-transform"></span>
                    </button>
                    <input type="hidden" name="requires_submission" :value="requiresSubmission ? 1 : 0">
                </div>

                <div x-show="requiresSubmission" x-collapse>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Due Date <span class="text-slate-400">(optional)</span></label>
                        <input type="datetime-local" name="due_date" value="{{ old('due_date') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                        <p class="mt-1 text-xs text-slate-400">Students can still submit after due date, but it will be marked as late.</p>
                        @error('due_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- CLO Mapping --}}
            @if($course->learningOutcomes->isNotEmpty())
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Map to CLOs</h3>
                    <p class="text-xs text-slate-400 mb-3">Select which Course Learning Outcomes this assessment measures.</p>
                    <div class="space-y-2">
                        @foreach($course->learningOutcomes as $clo)
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 hover:border-indigo-200 dark:hover:border-indigo-800 transition cursor-pointer has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/20 has-[:checked]:border-indigo-300 dark:has-[:checked]:border-indigo-700">
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

            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ $parent ? 'Add Child Assessment' : 'Add Assessment' }}
                </button>
                @if($parent)
                    <a href="{{ route('tenant.assessments.edit', [$tenant->slug, $course, $parent]) }}" class="px-4 py-2.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 transition">Cancel</a>
                @else
                    <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="px-4 py-2.5 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 transition">Cancel</a>
                @endif
            </div>
        </form>
    </div>
</x-tenant-layout>
