<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assessments.submissions.index', [$tenant->slug, $course, $assessment]) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Review Submission</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $submission->user->name }} &middot; {{ $assessment->title }}</p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid lg:grid-cols-5 gap-6">
        {{-- Left: Submission files --}}
        <div class="lg:col-span-3 space-y-4">
            {{-- Student info --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-sm font-bold text-indigo-700 dark:text-indigo-400">{{ strtoupper(substr($submission->user->name, 0, 1)) }}</div>
                        <div>
                            <h3 class="font-semibold text-slate-900 dark:text-white">{{ $submission->user->name }}</h3>
                            <p class="text-xs text-slate-400">{{ $submission->user->email }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $submission->submitted_at->format('d M Y, H:i') }}</p>
                        @if($submission->is_late)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400 mt-1">Late</span>
                        @endif
                    </div>
                </div>
                @if($submission->notes)
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Student Notes:</p>
                        <p class="text-sm text-slate-700 dark:text-slate-300">{{ $submission->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Files --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Submitted Files ({{ $submission->files->count() }})</h3>
                <div class="space-y-2">
                    @foreach($submission->files as $file)
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-700/30 border border-slate-100 dark:border-slate-600">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg {{ str_contains($file->file_type, 'pdf') ? 'bg-red-100 dark:bg-red-900/20' : 'bg-blue-100 dark:bg-blue-900/20' }} flex items-center justify-center">
                                    @if(str_contains($file->file_type, 'pdf'))
                                        <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    @else
                                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $file->file_name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ round($file->file_size_bytes / 1024) }} KB</p>
                                </div>
                            </div>
                            <a href="{{ route('tenant.assessments.submissions.download', [$tenant->slug, $course, $assessment, $file]) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Inline PDF preview --}}
            @php $pdfFile = $submission->files->first(fn($f) => str_contains($f->file_type, 'pdf')); @endphp
            @if($pdfFile)
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">PDF Preview</h3>
                        <span class="text-xs text-slate-400">{{ $pdfFile->file_name }}</span>
                    </div>
                    <iframe src="{{ route('tenant.assessments.submissions.download', [$tenant->slug, $course, $assessment, $pdfFile]) }}#toolbar=1" class="w-full h-[600px] bg-slate-100 dark:bg-slate-900"></iframe>
                </div>
            @endif
        </div>

        {{-- Right: Marking form --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sticky top-6">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Mark Submission</h3>

                @if($submission->score && $submission->score->finalized_at)
                    <div class="mb-4 p-3 rounded-xl {{ $submission->score->is_released ? 'bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700' : 'bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium {{ $submission->score->is_released ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                                {{ $submission->score->is_released ? 'Marks Released' : 'Graded — Not Released' }}
                            </span>
                            <span class="text-lg font-bold text-slate-900 dark:text-white">{{ $submission->score->raw_marks }}/{{ $submission->score->max_marks }}</span>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('tenant.assessments.submissions.mark', [$tenant->slug, $course, $assessment, $submission]) }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Marks *</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="raw_marks" value="{{ old('raw_marks', $submission->score?->raw_marks) }}" required min="0" max="{{ $assessment->total_marks }}" step="0.5" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition text-center text-lg font-semibold">
                            <span class="text-slate-500 dark:text-slate-400 text-sm font-medium">/ {{ $assessment->total_marks }}</span>
                        </div>
                        @error('raw_marks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Feedback <span class="text-slate-400">(optional)</span></label>
                        <textarea name="feedback" rows="5" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-indigo-500 transition" placeholder="Write feedback for the student...">{{ old('feedback', $submission->score?->feedback) }}</textarea>
                        @error('feedback') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="w-full px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl shadow-sm transition">
                        Save Marks
                    </button>
                    <p class="text-[11px] text-slate-400 text-center">Marks are NOT visible to student until you release them.</p>
                </form>
            </div>
        </div>
    </div>
</x-tenant-layout>
