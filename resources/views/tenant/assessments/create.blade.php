<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            @if($parent)
                <a href="{{ route('tenant.assessments.edit', [$tenant->slug, $course, $parent]) }}"
                   class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition flex-shrink-0">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @else
                <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}"
                   class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition flex-shrink-0">
                    <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @endif
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ $parent ? 'Add Child Assessment' : 'Add Assessment' }}
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    @if($parent)
                        Part of: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $parent->title }}</span>
                    @else
                        {{ $course->code }} — {{ $course->title }}
                    @endif
                </p>
            </div>
        </div>
    </x-slot>

    @php
        $existingChildWeightage = $parent ? $parent->children->sum('weightage') : 0;
        $remainingPct = 100 - $existingChildWeightage;
    @endphp

    <div class="max-w-5xl"
         x-data="{
             selectedType: '{{ old('type', 'quiz') }}',
             requiresSubmission: {{ old('requires_submission') ? 'true' : 'false' }},
             bloom: '{{ old('bloom_level', '') }}',
             hasFile: false,
             fileName: '',
             childWeightage: {{ (float) old('weightage', $parent ? 50 : 10) }},
             childMarks: {{ (float) old('total_marks', 100) }},
             get allocatedMarks() {
                 return Math.round(this.childWeightage / 100 * {{ (float) ($parent?->total_marks ?? 0) }} * 10) / 10;
             },
             get courseEquivalent() {
                 return Math.round(this.childWeightage / 100 * {{ (float) ($parent?->weightage ?? 0) }} * 10) / 10;
             },
         }">

        <div class="lg:grid lg:grid-cols-3 lg:gap-8 space-y-6 lg:space-y-0">

            {{-- ==================== MAIN FORM (2 cols) ==================== --}}
            <div class="lg:col-span-2 space-y-5">
                <form method="POST"
                      action="{{ route('tenant.assessments.store', [$tenant->slug, $course]) }}"
                      enctype="multipart/form-data"
                      class="space-y-6">
                    @csrf
                    @if($parent)
                        <input type="hidden" name="parent_id" value="{{ $parent->id }}">
                    @endif
                    <input type="hidden" name="type" :value="selectedType">
                    <input type="hidden" name="bloom_level" :value="bloom">
                    <input type="hidden" name="requires_submission" :value="requiresSubmission ? 1 : 0">

                    {{-- ── Assessment Type ── --}}
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Assessment Type</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">Choose the format that best describes this assessment.</p>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">

                            {{-- Quiz --}}
                            <button type="button" @click="selectedType = 'quiz'"
                                :class="selectedType === 'quiz'
                                    ? 'border-amber-500 bg-amber-50 dark:bg-amber-900/20 ring-2 ring-amber-200 dark:ring-amber-800'
                                    : 'border-slate-200 dark:border-slate-600 hover:border-amber-300 dark:hover:border-amber-700'"
                                class="flex flex-col items-center gap-2 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Quiz</p>
                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">Question-based timed test</p>
                                </div>
                            </button>

                            {{-- Assignment --}}
                            <button type="button" @click="selectedType = 'assignment'"
                                :class="selectedType === 'assignment'
                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 ring-2 ring-blue-200 dark:ring-blue-800'
                                    : 'border-slate-200 dark:border-slate-600 hover:border-blue-300 dark:hover:border-blue-700'"
                                class="flex flex-col items-center gap-2 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Assignment</p>
                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">Student file submission</p>
                                </div>
                            </button>

                            {{-- Test --}}
                            <button type="button" @click="selectedType = 'test'"
                                :class="selectedType === 'test'
                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 ring-2 ring-indigo-200 dark:ring-indigo-800'
                                    : 'border-slate-200 dark:border-slate-600 hover:border-indigo-300 dark:hover:border-indigo-700'"
                                class="flex flex-col items-center gap-2 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                <svg class="w-7 h-7 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Test</p>
                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">Written or practical exam</p>
                                </div>
                            </button>

                            {{-- Project --}}
                            <button type="button" @click="selectedType = 'project'"
                                :class="selectedType === 'project'
                                    ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20 ring-2 ring-purple-200 dark:ring-purple-800'
                                    : 'border-slate-200 dark:border-slate-600 hover:border-purple-300 dark:hover:border-purple-700'"
                                class="flex flex-col items-center gap-2 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                <svg class="w-7 h-7 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                <div>
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Project</p>
                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">Extended research work</p>
                                </div>
                            </button>

                            {{-- Presentation --}}
                            <button type="button" @click="selectedType = 'presentation'"
                                :class="selectedType === 'presentation'
                                    ? 'border-rose-500 bg-rose-50 dark:bg-rose-900/20 ring-2 ring-rose-200 dark:ring-rose-800'
                                    : 'border-slate-200 dark:border-slate-600 hover:border-rose-300 dark:hover:border-rose-700'"
                                class="flex flex-col items-center gap-2 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                <svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Presentation</p>
                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">Oral or visual delivery</p>
                                </div>
                            </button>

                            {{-- Lab --}}
                            <button type="button" @click="selectedType = 'lab'"
                                :class="selectedType === 'lab'
                                    ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20 ring-2 ring-teal-200 dark:ring-teal-800'
                                    : 'border-slate-200 dark:border-slate-600 hover:border-teal-300 dark:hover:border-teal-700'"
                                class="flex flex-col items-center gap-2 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                <svg class="w-7 h-7 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Lab</p>
                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">Practical laboratory work</p>
                                </div>
                            </button>

                            {{-- Final Exam --}}
                            <button type="button" @click="selectedType = 'final_exam'"
                                :class="selectedType === 'final_exam'
                                    ? 'border-red-500 bg-red-50 dark:bg-red-900/20 ring-2 ring-red-200 dark:ring-red-800'
                                    : 'border-slate-200 dark:border-slate-600 hover:border-red-300 dark:hover:border-red-700'"
                                class="flex flex-col items-center gap-2 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Final Exam</p>
                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">Summative end-of-term</p>
                                </div>
                            </button>

                            {{-- Other --}}
                            <button type="button" @click="selectedType = 'other'"
                                :class="selectedType === 'other'
                                    ? 'border-slate-500 bg-slate-100 dark:bg-slate-700/60 ring-2 ring-slate-300 dark:ring-slate-600'
                                    : 'border-slate-200 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500'"
                                class="flex flex-col items-center gap-2 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                <svg class="w-7 h-7 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Other</p>
                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">Custom assessment</p>
                                </div>
                            </button>

                        </div>
                        @error('type')
                            <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ── Details ── --}}
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-6">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Details</h3>

                        {{-- Title --}}
                        <div>
                            <label for="title" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                Title <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="title" name="title"
                                   value="{{ old('title') }}" required
                                   placeholder="{{ $parent ? 'e.g. Part A — Short Answer' : 'e.g. Quiz 1, Midterm Test, Final Exam' }}"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition placeholder:text-slate-400">
                            @error('title')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Weightage / Marks / Method row --}}
                        <div class="grid sm:grid-cols-3 gap-4">

                            {{-- Weightage --}}
                            <div>
                                @if($parent)
                                    <label for="weightage" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                        Portion of Parent (%) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" id="weightage" name="weightage"
                                           :value="childWeightage"
                                           @input="childWeightage = parseFloat($event.target.value) || 0"
                                           required min="0" max="100" step="0.5"
                                           class="w-full px-4 py-2.5 rounded-xl border border-indigo-300 dark:border-indigo-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                    <p class="mt-1 text-[10px] text-indigo-500 dark:text-indigo-400">
                                        Within parent's <strong>{{ number_format($parent->weightage, 0) }}%</strong> course weight
                                    </p>
                                @else
                                    <label for="weightage" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                        Course Weightage (%) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" id="weightage" name="weightage"
                                           value="{{ old('weightage', 10) }}"
                                           required min="0" max="100" step="0.5"
                                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                    <p class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">% of the overall course grade</p>
                                @endif
                                @error('weightage')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Total Marks --}}
                            <div>
                                <label for="total_marks" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                    Total Marks <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="total_marks" name="total_marks"
                                       value="{{ old('total_marks', 100) }}"
                                       x-model.number="childMarks"
                                       required min="1" step="0.5"
                                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                @if($parent)
                                    <p class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">
                                        Parent has <strong>{{ number_format($parent->total_marks, 0) }}</strong> marks
                                    </p>
                                @endif
                                @error('total_marks')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Method --}}
                            <div>
                                <label for="method" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Method</label>
                                <select id="method" name="method"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                    <option value="">— Not specified —</option>
                                    @foreach(\App\Models\Assessment::METHODS as $method)
                                        <option value="{{ $method }}" {{ old('method') === $method ? 'selected' : '' }}>
                                            {{ ucfirst($method) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('method')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        @if($parent)
                            {{-- Live mark hint for child assessments --}}
                            <div class="flex items-center gap-2 px-3.5 py-2.5 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-xl">
                                <svg class="w-4 h-4 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-xs text-indigo-700 dark:text-indigo-300">
                                    At <strong x-text="childWeightage + '%'"></strong>,
                                    this part equals
                                    <strong x-text="allocatedMarks + ' marks'"></strong>
                                    of the parent's {{ number_format($parent->total_marks, 0) }} marks
                                    (~<strong x-text="courseEquivalent + '%'"></strong> of course grade).
                                </p>
                            </div>
                        @endif

                        {{-- Bloom's Taxonomy --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Bloom's Taxonomy Level
                                <span class="ml-1 text-xs font-normal text-slate-400">(optional — click to select, click again to deselect)</span>
                            </label>
                            <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">

                                {{-- Remember --}}
                                <button type="button" @click="bloom = bloom === 'remember' ? '' : 'remember'"
                                    :class="bloom === 'remember'
                                        ? 'border-slate-500 bg-slate-100 dark:bg-slate-700 ring-1 ring-slate-400'
                                        : 'border-slate-200 dark:border-slate-600 hover:border-slate-400'"
                                    class="flex flex-col items-center gap-1.5 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                    <span class="text-base">🔵</span>
                                    <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-300">Remember</span>
                                    <span class="text-[9px] text-slate-400 leading-tight">Recall facts</span>
                                </button>

                                {{-- Understand --}}
                                <button type="button" @click="bloom = bloom === 'understand' ? '' : 'understand'"
                                    :class="bloom === 'understand'
                                        ? 'border-sky-500 bg-sky-50 dark:bg-sky-900/20 ring-1 ring-sky-300 dark:ring-sky-700'
                                        : 'border-slate-200 dark:border-slate-600 hover:border-sky-300'"
                                    class="flex flex-col items-center gap-1.5 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                    <span class="text-base">🟦</span>
                                    <span class="text-[11px] font-semibold text-sky-700 dark:text-sky-400">Understand</span>
                                    <span class="text-[9px] text-slate-400 leading-tight">Explain ideas</span>
                                </button>

                                {{-- Apply --}}
                                <button type="button" @click="bloom = bloom === 'apply' ? '' : 'apply'"
                                    :class="bloom === 'apply'
                                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 ring-1 ring-emerald-300 dark:ring-emerald-700'
                                        : 'border-slate-200 dark:border-slate-600 hover:border-emerald-300'"
                                    class="flex flex-col items-center gap-1.5 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                    <span class="text-base">🟩</span>
                                    <span class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">Apply</span>
                                    <span class="text-[9px] text-slate-400 leading-tight">Use in new ways</span>
                                </button>

                                {{-- Analyze --}}
                                <button type="button" @click="bloom = bloom === 'analyze' ? '' : 'analyze'"
                                    :class="bloom === 'analyze'
                                        ? 'border-amber-500 bg-amber-50 dark:bg-amber-900/20 ring-1 ring-amber-300 dark:ring-amber-700'
                                        : 'border-slate-200 dark:border-slate-600 hover:border-amber-300'"
                                    class="flex flex-col items-center gap-1.5 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                    <span class="text-base">🟨</span>
                                    <span class="text-[11px] font-semibold text-amber-700 dark:text-amber-400">Analyze</span>
                                    <span class="text-[9px] text-slate-400 leading-tight">Draw connections</span>
                                </button>

                                {{-- Evaluate --}}
                                <button type="button" @click="bloom = bloom === 'evaluate' ? '' : 'evaluate'"
                                    :class="bloom === 'evaluate'
                                        ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20 ring-1 ring-orange-300 dark:ring-orange-700'
                                        : 'border-slate-200 dark:border-slate-600 hover:border-orange-300'"
                                    class="flex flex-col items-center gap-1.5 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                    <span class="text-base">🟧</span>
                                    <span class="text-[11px] font-semibold text-orange-700 dark:text-orange-400">Evaluate</span>
                                    <span class="text-[9px] text-slate-400 leading-tight">Justify decisions</span>
                                </button>

                                {{-- Create --}}
                                <button type="button" @click="bloom = bloom === 'create' ? '' : 'create'"
                                    :class="bloom === 'create'
                                        ? 'border-rose-500 bg-rose-50 dark:bg-rose-900/20 ring-1 ring-rose-300 dark:ring-rose-700'
                                        : 'border-slate-200 dark:border-slate-600 hover:border-rose-300'"
                                    class="flex flex-col items-center gap-1.5 p-3.5 rounded-xl border-2 transition text-center cursor-pointer">
                                    <span class="text-base">🟥</span>
                                    <span class="text-[11px] font-semibold text-rose-700 dark:text-rose-400">Create</span>
                                    <span class="text-[9px] text-slate-400 leading-tight">Produce new work</span>
                                </button>

                            </div>
                        </div>

                        {{-- Description --}}
                        <div>
                            <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                Description
                                <span class="ml-1 text-xs font-normal text-slate-400">(optional)</span>
                            </label>
                            <textarea id="description" name="description" rows="3"
                                      placeholder="Additional notes or instructions for this assessment..."
                                      class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition placeholder:text-slate-400 resize-none">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- ── Student Submission ── --}}
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        {{-- Toggle header --}}
                        <button type="button" @click="requiresSubmission = !requiresSubmission"
                                class="w-full flex items-center justify-between p-6 text-left group">
                            <div class="flex items-center gap-3.5">
                                <div :class="requiresSubmission
                                        ? 'bg-indigo-100 dark:bg-indigo-900/40'
                                        : 'bg-slate-100 dark:bg-slate-700/60'"
                                     class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 transition">
                                    <svg :class="requiresSubmission ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400'"
                                         class="w-5 h-5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                </div>
                                <div class="text-left">
                                    <p class="text-sm font-bold text-slate-900 dark:text-white">Student Submission</p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Require students to upload files for this assessment</p>
                                </div>
                            </div>
                            {{-- Toggle pill --}}
                            <div :class="requiresSubmission ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600'"
                                 class="relative w-12 h-6 rounded-full transition-colors flex-shrink-0">
                                <span :class="requiresSubmission ? 'translate-x-6' : 'translate-x-1'"
                                      class="absolute top-0.5 inline-block w-5 h-5 bg-white rounded-full shadow-sm transform transition-transform"></span>
                            </div>
                        </button>

                        {{-- Collapsible body --}}
                        <div x-show="requiresSubmission" x-collapse>
                            <div class="px-6 pb-6 pt-5 border-t border-slate-100 dark:border-slate-700 space-y-4">
                                <div>
                                    <label for="due_date" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                        Due Date
                                        <span class="ml-1 text-xs font-normal text-slate-400">(optional)</span>
                                    </label>
                                    <input type="datetime-local" id="due_date" name="due_date"
                                           value="{{ old('due_date') }}"
                                           class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                    @error('due_date')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="flex items-start gap-2.5 px-3.5 py-3 bg-amber-50 dark:bg-amber-900/15 border border-amber-200 dark:border-amber-800 rounded-xl">
                                    <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <p class="text-xs text-amber-800 dark:text-amber-300">
                                        Students can still submit after the due date, but their submission will be marked as <strong>late</strong>. You can lock submissions manually from the assessment page.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ── Instruction File ── --}}
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Instruction / Task File</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">Upload a document students will see when viewing this assessment — e.g. question paper, task brief, rubric.</p>

                        {{-- Drop zone (hidden once file chosen) --}}
                        <div x-show="!hasFile"
                             @click="$refs.instructionFile.click()"
                             @dragover.prevent
                             @drop.prevent="
                                 const f = $event.dataTransfer.files[0];
                                 if (f) {
                                     hasFile = true;
                                     fileName = f.name;
                                     const dt = new DataTransfer();
                                     dt.items.add(f);
                                     $refs.instructionFile.files = dt.files;
                                 }
                             "
                             class="group border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-8 text-center hover:border-indigo-400 dark:hover:border-indigo-500 transition cursor-pointer">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/20 transition">
                                <svg class="w-6 h-6 text-slate-400 group-hover:text-indigo-500 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-slate-600 dark:text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">
                                Click to upload or drag and drop
                            </p>
                            <p class="text-xs text-slate-400 mt-1">PDF, Word, PowerPoint, Excel, TXT, ZIP — max 25 MB</p>
                        </div>

                        {{-- File preview pill --}}
                        <div x-show="hasFile"
                             class="flex items-center gap-3 px-4 py-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700">
                            <div class="w-9 h-9 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </div>
                            <span class="flex-1 text-sm font-medium text-indigo-800 dark:text-indigo-200 truncate" x-text="fileName"></span>
                            <button type="button"
                                    @click="hasFile = false; fileName = ''; $refs.instructionFile.value = ''"
                                    class="flex-shrink-0 w-7 h-7 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 flex items-center justify-center text-slate-400 hover:text-red-500 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <input type="file" name="instruction_file" x-ref="instructionFile"
                               accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip"
                               class="hidden"
                               @change="
                                   hasFile = $event.target.files.length > 0;
                                   fileName = $event.target.files[0]?.name ?? '';
                               ">
                        @error('instruction_file')
                            <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ── CLO Mapping ── --}}
                    @if($course->learningOutcomes->isNotEmpty())
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Map to Course Learning Outcomes</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">Select which CLOs this assessment is designed to measure.</p>
                            <div class="space-y-2">
                                @foreach($course->learningOutcomes as $clo)
                                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-slate-600
                                                  hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 hover:border-indigo-200 dark:hover:border-indigo-800
                                                  has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/20
                                                  has-[:checked]:border-indigo-300 dark:has-[:checked]:border-indigo-700
                                                  transition cursor-pointer">
                                        <input type="checkbox" name="clo_ids[]" value="{{ $clo->id }}"
                                               {{ in_array($clo->id, old('clo_ids', [])) ? 'checked' : '' }}
                                               class="mt-0.5 w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 flex-shrink-0">
                                        <div>
                                            <span class="inline-block text-[11px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wide mb-0.5">{{ $clo->code }}</span>
                                            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ $clo->description }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- ── Submit Footer ── --}}
                    <div class="flex items-center gap-3 pt-2 pb-4">
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            {{ $parent ? 'Add Child Assessment' : 'Add Assessment' }}
                        </button>
                        @if($parent)
                            <a href="{{ route('tenant.assessments.edit', [$tenant->slug, $course, $parent]) }}"
                               class="px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition">
                                Cancel
                            </a>
                        @else
                            <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}"
                               class="px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition">
                                Cancel
                            </a>
                        @endif
                    </div>

                </form>
            </div>

            {{-- ==================== SIDEBAR (1 col) ==================== --}}
            <div class="lg:col-span-1">
                <div class="lg:sticky lg:top-6 space-y-4">

                    @if($parent)
                        {{-- ── CHILD: Parent Assessment card ── --}}
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                            <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-4">Parent Assessment</h4>

                            {{-- Parent info --}}
                            <div class="flex items-start gap-3 mb-4">
                                <div class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-900 dark:text-white leading-tight">{{ $parent->title }}</p>
                                    <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 uppercase tracking-wide">
                                            {{ str_replace('_', ' ', $parent->type) }}
                                        </span>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ number_format($parent->weightage, 0) }}% of course</span>
                                        <span class="text-[11px] text-slate-400">·</span>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ number_format($parent->total_marks, 0) }} marks</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Allocation bar --}}
                            @php
                                $siblings = $parent->children;
                                $siblingColors = ['bg-indigo-400', 'bg-violet-400', 'bg-sky-400', 'bg-teal-400', 'bg-amber-400', 'bg-rose-400'];
                            @endphp
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[11px] font-medium text-slate-600 dark:text-slate-400">Portion allocation</span>
                                    <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300">
                                        {{ number_format($existingChildWeightage, 0) }}% used
                                    </span>
                                </div>
                                <div class="h-5 bg-slate-100 dark:bg-slate-700/60 rounded-full overflow-hidden flex">
                                    {{-- Existing siblings --}}
                                    @foreach($siblings as $i => $sibling)
                                        <div class="{{ $siblingColors[$i % count($siblingColors)] }} h-full flex items-center justify-center overflow-hidden transition-all"
                                             style="width: {{ min($sibling->weightage, 100) }}%"
                                             title="{{ $sibling->title }}: {{ number_format($sibling->weightage, 0) }}%">
                                            @if($sibling->weightage >= 8)
                                                <span class="text-[8px] text-white font-bold truncate px-1">{{ number_format($sibling->weightage, 0) }}%</span>
                                            @endif
                                        </div>
                                    @endforeach
                                    {{-- New child (Alpine-reactive dashed segment) --}}
                                    <div class="h-full border-2 border-dashed border-indigo-500 bg-indigo-100/60 dark:bg-indigo-500/20 flex items-center justify-center transition-all overflow-hidden"
                                         :style="'width:' + Math.max(0, Math.min(childWeightage, {{ $remainingPct }})) + '%'"
                                         title="New child">
                                        <span x-show="childWeightage >= 8" class="text-[8px] text-indigo-700 dark:text-indigo-300 font-bold px-1" x-text="childWeightage + '%'"></span>
                                    </div>
                                    {{-- Remaining --}}
                                    <div class="flex-1 h-full"></div>
                                </div>
                                {{-- Legend --}}
                                <div class="flex flex-wrap gap-2 mt-2">
                                    @foreach($siblings as $i => $sibling)
                                        <span class="inline-flex items-center gap-1 text-[10px] text-slate-500 dark:text-slate-400">
                                            <span class="w-2 h-2 rounded-full {{ $siblingColors[$i % count($siblingColors)] }} inline-block"></span>
                                            {{ Str::limit($sibling->title, 14) }}
                                        </span>
                                    @endforeach
                                    <span class="inline-flex items-center gap-1 text-[10px] text-indigo-600 dark:text-indigo-400">
                                        <span class="w-2 h-2 rounded-full border border-dashed border-indigo-500 inline-block"></span>
                                        New (this)
                                    </span>
                                </div>
                            </div>

                            {{-- Live calculation summary --}}
                            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-slate-500 dark:text-slate-400">This part equals</span>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200" x-text="allocatedMarks + ' / {{ number_format($parent->total_marks, 0) }} marks'"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Course grade impact</span>
                                    <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400" x-text="'~' + courseEquivalent + '%'"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Remaining available</span>
                                    <span class="text-xs font-bold"
                                          :class="{{ $remainingPct }} - childWeightage < 0
                                              ? 'text-red-600 dark:text-red-400'
                                              : 'text-slate-600 dark:text-slate-300'"
                                          x-text="Math.max(0, {{ $remainingPct }} - childWeightage).toFixed(1) + '%'">
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Existing siblings list --}}
                        @if($siblings->isNotEmpty())
                            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                                <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-4">
                                    Existing Parts ({{ $siblings->count() }})
                                </h4>
                                <div class="space-y-3">
                                    @foreach($siblings as $i => $sibling)
                                        <div class="flex items-center gap-3 py-0.5">
                                            <span class="w-2.5 h-2.5 rounded-full {{ $siblingColors[$i % count($siblingColors)] }} flex-shrink-0 mt-0.5"></span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 truncate">{{ $sibling->title }}</p>
                                                <p class="text-[10px] text-slate-400 mt-0.5">{{ number_format($sibling->weightage, 0) }}% · {{ number_format($sibling->total_marks, 0) }} marks</p>
                                            </div>
                                            @php $badge = $sibling->status_badge; @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $badge['color'] }}-100 dark:bg-{{ $badge['color'] }}-900/30 text-{{ $badge['color'] }}-700 dark:text-{{ $badge['color'] }}-400 flex-shrink-0">
                                                {{ $badge['label'] }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Over-allocation warning --}}
                        <div x-show="childWeightage > {{ $remainingPct }}"
                             class="flex items-start gap-2.5 px-5 py-4 bg-red-50 dark:bg-red-900/15 border border-red-200 dark:border-red-800 rounded-xl">
                            <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <p class="text-xs text-red-700 dark:text-red-300">
                                Weightage exceeds the remaining <strong>{{ number_format($remainingPct, 0) }}%</strong> available in this parent. Total would exceed 100%.
                            </p>
                        </div>

                    @else
                        {{-- ── TOP-LEVEL: Course Plan Status card ── --}}
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                            <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-4">Course</h4>
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white leading-tight">{{ $course->title }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $course->code }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Tip card --}}
                        <div class="bg-indigo-50 dark:bg-indigo-900/15 rounded-2xl border border-indigo-200 dark:border-indigo-800 p-6">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-indigo-800 dark:text-indigo-200 mb-1">Plan your weightage</p>
                                    <p class="text-xs text-indigo-700 dark:text-indigo-300 leading-relaxed">
                                        Total course weightage across all assessments should add up to <strong>100%</strong>. Use the
                                        <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="underline font-semibold hover:text-indigo-900 dark:hover:text-indigo-100">Assessment Plan</a>
                                        page to monitor the overall allocation.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Top-level type guidance --}}
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                            <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-4">Quick Guide</h4>
                            <div class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300">
                                <div class="flex items-start gap-2">
                                    <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Set <strong>Weightage</strong> to reflect this assessment's share of the final grade.</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>You can add <strong>child components</strong> (e.g. Part A, Part B) after creation.</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Map to <strong>CLOs</strong> to enable outcome-based analytics and reports.</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Enable <strong>Student Submission</strong> to collect and manage uploaded work.</span>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

        </div>
    </div>
</x-tenant-layout>
