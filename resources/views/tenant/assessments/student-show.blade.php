<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.my-assessments', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $assessment->title }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $course->code }} — {{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="max-w-2xl space-y-6">
        {{-- Assessment Info --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center gap-3 mb-4">
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400">{{ ucfirst(str_replace('_', ' ', $assessment->type)) }}</span>
                <span class="text-sm text-slate-500 dark:text-slate-400">{{ $assessment->total_marks }} marks &middot; {{ $assessment->weightage }}%</span>
            </div>
            @if($assessment->description)
                <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line mb-4">{{ $assessment->description }}</p>
            @endif
            @if($assessment->due_date)
                @php $isPastDue = now()->isAfter($assessment->due_date); @endphp
                <div class="flex items-center gap-2 text-sm {{ $isPastDue ? 'text-red-600 dark:text-red-400' : 'text-slate-600 dark:text-slate-300' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Due: {{ $assessment->due_date->format('d M Y, H:i') }} {{ $isPastDue ? '(Past due)' : '' }}</span>
                </div>
            @endif
        </div>

        {{-- Instruction File --}}
        @if($assessment->instruction_file_path)
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-indigo-200 dark:border-indigo-700 p-5">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-indigo-500 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Assignment Instructions</h3>
                </div>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800">
                    @php
                        $ext = strtolower(pathinfo($assessment->instruction_file_name ?? '', PATHINFO_EXTENSION));
                        $isPdf = $ext === 'pdf';
                        $isWord = in_array($ext, ['doc', 'docx']);
                        $isPpt = in_array($ext, ['ppt', 'pptx']);
                        $iconColor = $isPdf ? 'text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/30'
                                   : ($isWord ? 'text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/30'
                                   : ($isPpt ? 'text-orange-600 dark:text-orange-400 bg-orange-100 dark:bg-orange-900/30'
                                   : 'text-indigo-600 dark:text-indigo-400 bg-indigo-100 dark:bg-indigo-900/30'));
                    @endphp
                    <div class="w-10 h-10 rounded-xl {{ $iconColor }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $assessment->instruction_file_name }}</p>
                        <p class="text-[11px] text-indigo-500 dark:text-indigo-400 uppercase tracking-wider">{{ strtoupper($ext) }} file</p>
                    </div>
                    <a href="{{ route('tenant.assessments.instruction.download', [$tenant->slug, $course, $assessment]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition flex-shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download
                    </a>
                </div>
            </div>
        @endif

        {{-- Released Marks --}}
        @if($score)
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-emerald-200 dark:border-emerald-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Your Mark</h3>
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">Released</span>
                </div>
                <div class="flex items-center gap-6">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center border-4 {{ $score->percentage >= 70 ? 'border-emerald-400 text-emerald-600 dark:text-emerald-400' : ($score->percentage >= 40 ? 'border-amber-400 text-amber-600 dark:text-amber-400' : 'border-red-400 text-red-600 dark:text-red-400') }}">
                        <span class="text-xl font-bold">{{ round($score->percentage) }}%</span>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $score->raw_marks }} <span class="text-sm font-normal text-slate-400">/ {{ $score->max_marks }}</span></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Weighted: {{ $score->weighted_marks }} ({{ $assessment->weightage }}%)</p>
                    </div>
                </div>
                @if($score->feedback)
                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Feedback</h4>
                        <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $score->feedback }}</p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Submission Status / Form --}}
        @if($submission)
            <div x-data="{ showReupload: false, showDeleteConfirm: false }">
                {{-- Submission Card --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Your Submission</h3>
                        @php $badge = $submission->status_badge; @endphp
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $badge['color'] }}-100 dark:bg-{{ $badge['color'] }}-900/20 text-{{ $badge['color'] }}-700 dark:text-{{ $badge['color'] }}-400">{{ $badge['label'] }}</span>
                    </div>

                    <div class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                        Submitted {{ $submission->submitted_at->format('d M Y, H:i') }}
                        @if($submission->is_late) <span class="text-red-500 font-medium ml-1">(Late)</span> @endif
                    </div>

                    @if($submission->notes)
                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-3">{{ $submission->notes }}</p>
                    @endif

                    <div class="space-y-2">
                        @foreach($submission->files as $file)
                            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-700/30 border border-slate-100 dark:border-slate-600">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg {{ str_contains($file->file_type, 'pdf') ? 'bg-red-100 dark:bg-red-900/20' : 'bg-blue-100 dark:bg-blue-900/20' }} flex items-center justify-center">
                                        <svg class="w-4 h-4 {{ str_contains($file->file_type, 'pdf') ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $file->file_name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ round($file->file_size_bytes / 1024) }} KB</p>
                                    </div>
                                </div>
                                <a href="{{ route('tenant.assessments.submissions.download', [$tenant->slug, $course, $assessment, $file]) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                            </div>
                        @endforeach
                    </div>

                    @if(!$score)
                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                            <p class="text-xs text-slate-400 text-center">Your submission is being reviewed. Marks will appear here once released.</p>
                        </div>
                    @endif

                    {{-- Action Buttons (only if not graded) --}}
                    @if($submission->status !== 'graded')
                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 flex gap-2">
                            <button @click="showReupload = !showReupload" class="flex-1 px-3 py-2 rounded-lg border border-indigo-300 dark:border-indigo-700 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-sm font-medium transition">
                                Replace Files
                            </button>
                            <button @click="showDeleteConfirm = true" class="flex-1 px-3 py-2 rounded-lg border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-medium transition">
                                Delete Submission
                            </button>
                        </div>
                    @endif
                </div>

            {{-- Re-upload Form Panel --}}
            @if($submission->status !== 'graded')
                <div x-show="showReupload" x-transition class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Replace Files</h3>

                    <form method="POST" action="{{ route('tenant.my-assessments.resubmit', [$tenant->slug, $course, $assessment]) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ files: [], uploading: false }" @submit="uploading = true">
                        @csrf

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Upload Files *</label>
                            <div class="border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-6 text-center hover:border-indigo-400 dark:hover:border-indigo-500 transition cursor-pointer" @click="$refs.fileInput.click()">
                                <svg class="w-8 h-8 mx-auto text-slate-400 dark:text-slate-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                <p class="text-sm text-slate-600 dark:text-slate-400">Click to upload or drag files here</p>
                                <p class="text-xs text-slate-400 mt-1">PDF, Images, Word — max 25MB each</p>
                            </div>
                            <input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" x-ref="fileInput" class="hidden" @change="files = Array.from($event.target.files)">

                            {{-- File names --}}
                            <template x-if="files.length > 0">
                                <div class="mt-3 space-y-1">
                                    <template x-for="(file, i) in files" :key="i">
                                        <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                            <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
                                            <span x-text="file.name"></span>
                                            <span class="text-xs text-slate-400" x-text="'(' + Math.round(file.size / 1024) + ' KB)'"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            @error('files') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('files.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes <span class="text-slate-400">(optional)</span></label>
                            <textarea name="notes" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition" placeholder="Any additional notes...">{{ old('notes') }}</textarea>
                        </div>

                        <div class="flex gap-2">
                            <button type="submit" :disabled="uploading" class="flex-1 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-sm font-medium rounded-xl shadow-sm transition">
                                <span x-show="!uploading">Replace & Resubmit</span>
                                <span x-show="uploading" class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Uploading…
                                </span>
                            </button>
                            <button type="button" @click="showReupload = false" class="px-4 py-2.5 text-slate-700 dark:text-slate-300 text-sm font-medium border border-slate-300 dark:border-slate-600 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Delete Confirmation Modal --}}
                <div x-show="showDeleteConfirm" x-transition class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center" @click.outside="showDeleteConfirm = false">
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 max-w-sm mx-4 shadow-xl">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Delete Submission?</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-6">Are you sure you want to delete your submission? This action cannot be undone. You may resubmit afterward.</p>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('tenant.my-assessments.delete', [$tenant->slug, $course, $assessment]) }}" class="flex-1">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl transition">
                                    Yes, Delete
                                </button>
                            </form>
                            <button @click="showDeleteConfirm = false" class="flex-1 px-4 py-2.5 text-slate-700 dark:text-slate-300 text-sm font-medium border border-slate-300 dark:border-slate-600 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
                {{-- End of wrapper div --}}
            </div>
            @endif
        @elseif($assessment->requires_submission && $assessment->status === 'active')
            {{-- Submission form --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6" x-data="{ files: [], uploading: false }">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Submit Your Work</h3>

                <form method="POST" action="{{ route('tenant.my-assessments.submit', [$tenant->slug, $course, $assessment]) }}" enctype="multipart/form-data" class="space-y-4" @submit="uploading = true">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Upload Files *</label>
                        <div class="border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-6 text-center hover:border-indigo-400 dark:hover:border-indigo-500 transition cursor-pointer" @click="$refs.fileInput.click()">
                            <svg class="w-8 h-8 mx-auto text-slate-400 dark:text-slate-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <p class="text-sm text-slate-600 dark:text-slate-400">Click to upload or drag files here</p>
                            <p class="text-xs text-slate-400 mt-1">PDF, Images, Word — max 25MB each</p>
                        </div>
                        <input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" x-ref="fileInput" class="hidden" @change="files = Array.from($event.target.files)">

                        {{-- File names --}}
                        <template x-if="files.length > 0">
                            <div class="mt-3 space-y-1">
                                <template x-for="(file, i) in files" :key="i">
                                    <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                        <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
                                        <span x-text="file.name"></span>
                                        <span class="text-xs text-slate-400" x-text="'(' + Math.round(file.size / 1024) + ' KB)'"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                        @error('files') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('files.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes <span class="text-slate-400">(optional)</span></label>
                        <textarea name="notes" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition" placeholder="Any additional notes for the lecturer...">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit" :disabled="uploading" class="w-full px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-sm font-medium rounded-xl shadow-sm transition">
                        <span x-show="!uploading">Submit Assignment</span>
                        <span x-show="uploading" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Uploading…
                        </span>
                    </button>

                    @if($assessment->due_date && now()->isAfter($assessment->due_date))
                        <p class="text-xs text-red-500 text-center">The due date has passed. Your submission will be marked as late.</p>
                    @endif
                </form>
            </div>
        @elseif($assessment->status !== 'active')
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 text-center">
                <p class="text-sm text-slate-500 dark:text-slate-400">This assessment is not currently accepting submissions.</p>
            </div>
        @endif
    </div>
</x-tenant-layout>
