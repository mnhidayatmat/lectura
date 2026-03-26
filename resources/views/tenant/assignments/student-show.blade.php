<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assignments.index', app('current_tenant')->slug) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $assignment->title }}</h2>
                <p class="text-sm text-slate-500">{{ $assignment->course->code }} &middot; {{ $assignment->total_marks }} marks</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto space-y-6">
        {{-- Assignment Info --}}
        @if($assignment->description)
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <p class="text-sm text-slate-600 whitespace-pre-line">{{ $assignment->description }}</p>
            </div>
        @endif

        {{-- Deadline --}}
        @if($assignment->deadline)
            <div class="bg-white rounded-2xl border p-4 flex items-center gap-3 {{ $assignment->deadline->isPast() ? 'border-red-200 bg-red-50' : 'border-slate-200' }}">
                <svg class="w-5 h-5 {{ $assignment->deadline->isPast() ? 'text-red-500' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="text-sm font-medium {{ $assignment->deadline->isPast() ? 'text-red-700' : 'text-slate-700' }}">Deadline: {{ $assignment->deadline->format('d M Y, H:i') }}</p>
                    <p class="text-xs {{ $assignment->deadline->isPast() ? 'text-red-500' : 'text-slate-400' }}">{{ $assignment->deadline->diffForHumans() }}</p>
                </div>
            </div>
        @endif

        {{-- My Mark --}}
        @if($myMark)
            <div class="bg-white rounded-2xl border-2 border-emerald-200 p-6 text-center">
                <p class="text-xs text-slate-500 uppercase font-medium mb-1">Your Mark</p>
                <p class="text-4xl font-extrabold text-emerald-600">{{ $myMark->total_marks }} <span class="text-lg text-slate-400">/ {{ $myMark->max_marks }}</span></p>
                <p class="text-sm text-slate-500 mt-1">{{ $myMark->percentage }}%</p>
            </div>
        @endif

        {{-- Feedback --}}
        @if($myFeedback)
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Feedback</h3>
                </div>
                <div class="p-6 space-y-4 text-sm">
                    @if($myFeedback->strengths)
                        <div>
                            <p class="font-medium text-emerald-700 mb-1">Strengths</p>
                            <p class="text-slate-600">{{ $myFeedback->strengths }}</p>
                        </div>
                    @endif
                    @if($myFeedback->improvement_tips)
                        <div>
                            <p class="font-medium text-amber-700 mb-1">Areas for Improvement</p>
                            <p class="text-slate-600">{{ $myFeedback->improvement_tips }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Submit --}}
        @if(!$mySubmission)
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Submit Your Work</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('tenant.assignments.submit', [app('current_tenant')->slug, $assignment]) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div class="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center">
                            <svg class="w-8 h-8 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <p class="text-sm text-slate-600 mb-2">Upload PDF or image files (max 25MB each)</p>
                            <input type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Notes (optional)</label>
                            <textarea name="notes" rows="2" placeholder="Any notes for your lecturer..." class="w-full mt-1.5 px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                        <button type="submit" class="w-full px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm transition">Submit</button>
                    </form>
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-emerald-200 p-6">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <div>
                        <p class="text-sm font-medium text-emerald-900">Submitted {{ $mySubmission->submitted_at->format('d M Y, H:i') }}</p>
                        <p class="text-xs text-slate-500">{{ $mySubmission->files->count() }} file(s) &middot; Status: {{ ucfirst($mySubmission->status) }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-tenant-layout>
