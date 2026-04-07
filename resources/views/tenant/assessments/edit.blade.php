<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assessments.index', [$tenant->slug, $course]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Edit Assessment</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} — {{ $assessment->title }}</p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6" x-data="{ requiresSubmission: {{ old('requires_submission', $assessment->requires_submission) ? 'true' : 'false' }} }">
        {{-- Left: Assessment form --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('tenant.assessments.update', [$tenant->slug, $course, $assessment]) }}" class="space-y-6">
                @csrf @method('PUT')

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-5">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Assessment Details</h3>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title *</label>
                        <input type="text" name="title" value="{{ old('title', $assessment->title) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Type *</label>
                            <select name="type" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                                @foreach(\App\Models\Assessment::TYPES as $type)
                                    <option value="{{ $type }}" {{ old('type', $assessment->type) === $type ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Method</label>
                            <select name="method" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                                <option value="">— Select —</option>
                                @foreach(\App\Models\Assessment::METHODS as $method)
                                    <option value="{{ $method }}" {{ old('method', $assessment->method) === $method ? 'selected' : '' }}>{{ ucfirst($method) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Weightage (%) *</label>
                            <input type="number" name="weightage" value="{{ old('weightage', $assessment->weightage) }}" required min="0" max="100" step="0.5" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Total Marks *</label>
                            <input type="number" name="total_marks" value="{{ old('total_marks', $assessment->total_marks) }}" required min="1" step="0.5" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Status</label>
                            <select name="status" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                                @foreach(\App\Models\Assessment::STATUSES as $s)
                                    <option value="{{ $s }}" {{ old('status', $assessment->status) === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Bloom's Taxonomy Level</label>
                        <select name="bloom_level" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                            <option value="">— Select —</option>
                            @foreach(\App\Models\Assessment::BLOOM_LEVELS as $level)
                                <option value="{{ $level }}" {{ old('bloom_level', $assessment->bloom_level) === $level ? 'selected' : '' }}>{{ ucfirst($level) }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($course->learningOutcomes->isNotEmpty())
                        @php $selectedClos = old('clo_ids', $assessment->clos->pluck('id')->toArray()); @endphp
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Map to CLOs</label>
                            <div class="space-y-2">
                                @foreach($course->learningOutcomes as $clo)
                                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition cursor-pointer">
                                        <input type="checkbox" name="clo_ids[]" value="{{ $clo->id }}" {{ in_array($clo->id, $selectedClos) ? 'checked' : '' }} class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <div>
                                            <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $clo->code }}</span>
                                            <p class="text-xs text-slate-600 dark:text-slate-300">{{ $clo->description }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                        <textarea name="description" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">{{ old('description', $assessment->description) }}</textarea>
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
                            <input type="datetime-local" name="due_date" value="{{ old('due_date', $assessment->due_date?->format('Y-m-d\TH:i')) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                            <p class="mt-1 text-xs text-slate-400">Students can still submit after due date, but it will be marked as late.</p>
                            @error('due_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">Save Changes</button>
            </form>
        </div>

        {{-- Right: Linked items & Child Assessments --}}
        <div class="space-y-4">
            {{-- Child Assessments Section --}}
            @if(!$assessment->parent_id)
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Child Assessments</h3>
                    <a href="{{ route('tenant.assessments.child.create', [$tenant->slug, $course, $assessment]) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Child
                    </a>
                </div>

                @forelse($assessment->children as $child)
                    <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-slate-100 dark:border-slate-700' : '' }}">
                        <div class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <div>
                                <p class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $child->title }}</p>
                                <p class="text-[10px] text-slate-400">{{ $child->weightage }}% &middot; {{ $child->total_marks }} marks</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('tenant.assessments.edit', [$tenant->slug, $course, $child]) }}" class="text-slate-400 hover:text-indigo-500">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 text-center py-3">No child assessments yet.</p>
                @endforelse
            </div>
            @endif

            {{-- Linked Items --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Linked Items</h3>

                @forelse($assessment->items as $item)
                    <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-slate-100 dark:border-slate-700' : '' }}">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-md {{ $item->assessable_type === 'App\\Models\\Assignment' ? 'bg-blue-100 text-blue-600' : 'bg-amber-100 text-amber-600' }} flex items-center justify-center">
                                @if($item->assessable_type === 'App\\Models\\Assignment')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                @else
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                            </span>
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $item->assessable?->title ?? 'Deleted' }}</span>
                        </div>
                        <form method="POST" action="{{ route('tenant.assessments.items.destroy', [$tenant->slug, $course, $assessment, $item]) }}">
                            @csrf @method('DELETE')
                            <button class="text-slate-400 hover:text-red-500 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 text-center py-3">No linked items yet.</p>
                @endforelse

                {{-- Link new item --}}
                <form method="POST" action="{{ route('tenant.assessments.items.store', [$tenant->slug, $course, $assessment]) }}" class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 space-y-2">
                    @csrf
                    <select name="assessable" required class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs">
                        <option value="">— Link a quiz or assignment —</option>
                        @if($assignments->isNotEmpty())
                            <optgroup label="Assignments">
                                @foreach($assignments as $a)
                                    <option value="assignment:{{ $a->id }}">{{ $a->title }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($quizzes->isNotEmpty())
                            <optgroup label="Quizzes">
                                @foreach($quizzes as $q)
                                    <option value="quiz:{{ $q->id }}">{{ $q->title }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                    <button type="submit" class="w-full px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">Link Item</button>
                </form>
            </div>
        </div>
    </div>
</x-tenant-layout>
