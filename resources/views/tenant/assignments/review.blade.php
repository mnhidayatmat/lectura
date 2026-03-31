<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assignments.show', [app('current_tenant')->slug, $assignment]) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Review: {{ $submission->user->name }}</h2>
                <p class="text-sm text-slate-500">{{ $assignment->title }} &middot; {{ $assignment->course->code }}</p>
            </div>
        </div>
    </x-slot>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Left: Submission --}}
        <div class="space-y-6">
            {{-- Submission Content --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Submission</h3>
                </div>

                {{-- Files --}}
                @if($submission->files->isNotEmpty())
                    <div class="p-4 space-y-2">
                        @foreach($submission->files as $file)
                            <div class="flex items-center gap-3 bg-slate-50 rounded-xl p-3">
                                <div class="w-10 h-10 rounded-xl {{ $file->drive_file_id ? 'bg-blue-100' : 'bg-red-100' }} flex items-center justify-center flex-shrink-0">
                                    @if($file->drive_file_id)
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                                    @else
                                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-900 truncate">{{ $file->file_name }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ number_format($file->file_size_bytes / 1024, 1) }} KB &middot; {{ $file->file_type }}
                                        @if($file->drive_file_id) &middot; <span class="text-blue-600">Synced to Drive</span> @endif
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Text Content --}}
                @if($submission->text_content)
                    <div class="px-6 py-4 {{ $submission->files->isNotEmpty() ? 'border-t border-slate-100' : '' }}">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Written Answer</p>
                        <div class="bg-slate-50 rounded-xl p-4 text-sm text-slate-700 whitespace-pre-line max-h-96 overflow-y-auto">{{ $submission->text_content }}</div>
                    </div>
                @endif

                @if($submission->notes)
                    <div class="px-6 py-3 border-t border-slate-100">
                        <p class="text-xs text-slate-500"><strong>Student notes:</strong> {{ $submission->notes }}</p>
                    </div>
                @endif
                <div class="px-6 py-3 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs text-slate-400">Submitted: {{ $submission->submitted_at->format('d M Y, H:i') }} {{ $submission->is_late ? '(LATE)' : '' }}</span>

                    @if($assignment->marking_mode === 'ai_assisted' && $submission->status !== 'ai_completed')
                        <form method="POST" action="{{ route('tenant.assignments.ai-mark', [app('current_tenant')->slug, $assignment, $submission]) }}">
                            @csrf
                            <button class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white text-xs font-medium rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                AI Mark
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- AI Suggestions --}}
            @if($submission->markingSuggestions->isNotEmpty())
                <div class="bg-white rounded-2xl border-2 border-teal-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-teal-100 bg-teal-50/50 flex items-center gap-2">
                        <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        <h3 class="font-semibold text-teal-900">AI Marking Suggestions</h3>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach($submission->markingSuggestions as $sug)
                            <div class="px-6 py-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-slate-900">{{ $sug->criteria?->title ?? $sug->question_ref ?? 'Overall' }}</span>
                                    <span class="text-xs bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full">Confidence: {{ round(($sug->confidence ?? 0) * 100) }}%</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-lg font-bold text-teal-700">{{ $sug->suggested_marks }}</span>
                                    <span class="text-sm text-slate-400">/ {{ $sug->max_marks }}</span>
                                    <div class="flex-1 bg-slate-100 rounded-full h-2">
                                        <div class="bg-teal-500 h-2 rounded-full" style="width: {{ $sug->max_marks > 0 ? ($sug->suggested_marks / $sug->max_marks * 100) : 0 }}%"></div>
                                    </div>
                                </div>
                                @if($sug->explanation)
                                    <p class="text-xs text-slate-500 mt-2">{{ $sug->explanation }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Right: Marking Form --}}
        <div>
            <form method="POST" action="{{ route('tenant.assignments.finalize', [app('current_tenant')->slug, $assignment, $submission]) }}" class="space-y-6">
                @csrf

                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="font-semibold text-slate-900">Mark Submission</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        @if($assignment->rubric && $assignment->rubric->criteria->isNotEmpty())
                            @foreach($assignment->rubric->criteria as $criteria)
                                @php
                                    $suggestion = $submission->markingSuggestions->where('rubric_criteria_id', $criteria->id)->first();
                                    $defaultMark = $suggestion?->suggested_marks ?? '';
                                @endphp
                                <div class="bg-slate-50 rounded-xl p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="text-sm font-medium text-slate-700">{{ $criteria->title }}</label>
                                        <span class="text-xs text-slate-400">Max: {{ $criteria->max_marks }}</span>
                                    </div>
                                    <input type="number" name="marks[{{ $criteria->id }}]" value="{{ $existingMark ? '' : $defaultMark }}" required min="0" max="{{ $criteria->max_marks }}" step="0.5" placeholder="Enter marks" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                    @if($criteria->levels->isNotEmpty())
                                        <div class="flex gap-1 mt-2">
                                            @foreach($criteria->levels as $level)
                                                <span class="text-[10px] bg-slate-200 text-slate-500 px-1.5 py-0.5 rounded">{{ $level->label }}: {{ $level->marks }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <div>
                                <label class="text-sm font-medium text-slate-700">Total Marks</label>
                                <input type="number" name="marks[total]" required min="0" max="{{ $assignment->total_marks }}" step="0.5" placeholder="Enter marks" class="w-full mt-1.5 px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Feedback --}}
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="font-semibold text-slate-900">Feedback</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Strengths</label>
                            <textarea name="feedback_strengths" rows="2" placeholder="What did the student do well?" class="w-full mt-1.5 px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Areas for Improvement</label>
                            <textarea name="feedback_improvements" rows="2" placeholder="What could be improved?" class="w-full mt-1.5 px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm transition">
                    Finalize Marks & Release Feedback
                </button>
            </form>
        </div>
    </div>
</x-tenant-layout>
