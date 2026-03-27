<x-tenant-layout>
    @php
        $assignment = $mark->assignment;
        $course = $assignment->course;
        $submission = $mark->submission;
        $feedback = $submission?->feedback;
    @endphp

    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.marks', $tenant->slug) }}" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $assignment->title }}</h2>
                <p class="text-sm text-slate-500">{{ $course->code }} — {{ $course->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Score Card --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="p-6 text-center">
                <div class="w-24 h-24 rounded-full mx-auto flex items-center justify-center border-4 {{ $mark->percentage >= 70 ? 'border-emerald-200 bg-emerald-50' : ($mark->percentage >= 40 ? 'border-amber-200 bg-amber-50' : 'border-red-200 bg-red-50') }}">
                    <span class="text-2xl font-bold {{ $mark->percentage >= 70 ? 'text-emerald-700' : ($mark->percentage >= 40 ? 'text-amber-700' : 'text-red-600') }}">{{ number_format($mark->percentage, 0) }}%</span>
                </div>
                <p class="mt-3 text-lg font-bold text-slate-900">{{ $mark->total_marks }} / {{ $mark->max_marks }}</p>
                @if($mark->grade)
                    <p class="mt-1 text-sm text-slate-500">Grade: <strong>{{ $mark->grade }}</strong></p>
                @endif
                @if($mark->finalized_at)
                    <p class="mt-2 text-xs text-slate-400">Graded on {{ $mark->finalized_at->format('d M Y, H:i') }}</p>
                @endif
            </div>

            {{-- Info Row --}}
            <div class="grid grid-cols-3 divide-x divide-slate-100 border-t border-slate-100">
                <div class="p-4 text-center">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase">Type</p>
                    <p class="text-sm font-medium text-slate-900 mt-0.5">{{ ucfirst($assignment->type) }}</p>
                </div>
                <div class="p-4 text-center">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase">Submitted</p>
                    <p class="text-sm font-medium text-slate-900 mt-0.5">{{ $submission?->submitted_at?->format('d M Y') ?? '--' }}</p>
                </div>
                <div class="p-4 text-center">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase">Deadline</p>
                    <p class="text-sm font-medium text-slate-900 mt-0.5">{{ $assignment->deadline?->format('d M Y') ?? '--' }}</p>
                </div>
            </div>
        </div>

        {{-- Feedback Section --}}
        @if($feedback && $feedback->is_released)
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-slate-900 text-sm">Feedback</h3>
                        @if($feedback->ai_generated)
                            <span class="inline-flex items-center gap-0.5 text-[10px] bg-teal-50 text-teal-700 px-1.5 py-0.5 rounded font-medium">
                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                                AI-Generated
                            </span>
                        @endif
                    </div>
                    @if($feedback->performance_level)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold
                            {{ $feedback->performance_level === 'advanced' ? 'bg-emerald-100 text-emerald-700' :
                               ($feedback->performance_level === 'average' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                            {{ ucfirst($feedback->performance_level) }}
                        </span>
                    @endif
                </div>

                <div class="p-5 space-y-4">
                    @if($feedback->strengths)
                        <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
                            <h5 class="text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Strengths
                            </h5>
                            <p class="text-sm text-emerald-800 leading-relaxed">{{ $feedback->strengths }}</p>
                        </div>
                    @endif

                    @if($feedback->improvement_tips)
                        <div class="bg-amber-50 rounded-xl p-4 border border-amber-100">
                            <h5 class="text-xs font-semibold text-amber-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                Areas for Improvement
                            </h5>
                            <p class="text-sm text-amber-800 leading-relaxed">{{ $feedback->improvement_tips }}</p>
                        </div>
                    @endif

                    @if($feedback->missing_points)
                        <div class="bg-red-50 rounded-xl p-4 border border-red-100">
                            <h5 class="text-xs font-semibold text-red-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                Missing Points
                            </h5>
                            <p class="text-sm text-red-800 leading-relaxed">{{ $feedback->missing_points }}</p>
                        </div>
                    @endif

                    @if($feedback->misconceptions)
                        <div class="bg-purple-50 rounded-xl p-4 border border-purple-100">
                            <h5 class="text-xs font-semibold text-purple-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                Misconceptions
                            </h5>
                            <p class="text-sm text-purple-800 leading-relaxed">{{ $feedback->misconceptions }}</p>
                        </div>
                    @endif

                    @if($feedback->revision_advice)
                        <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100">
                            <h5 class="text-xs font-semibold text-indigo-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                Revision Advice
                            </h5>
                            <p class="text-sm text-indigo-800 leading-relaxed">{{ $feedback->revision_advice }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Submitted Files --}}
        @if($submission && $submission->files->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900 text-sm">Your Submission</h3>
                </div>
                <div class="divide-y divide-slate-50">
                    @foreach($submission->files as $file)
                        <div class="px-5 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg {{ str_contains($file->file_type, 'pdf') ? 'bg-red-50' : 'bg-slate-100' }} flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 {{ str_contains($file->file_type, 'pdf') ? 'text-red-500' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ $file->file_name }}</p>
                                    <p class="text-xs text-slate-400">{{ number_format($file->file_size_bytes / 1024, 0) }} KB</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-tenant-layout>
