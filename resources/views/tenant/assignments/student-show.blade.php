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
        @if($assignment->description || $assignment->instruction_filename)
            <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                @if($assignment->description)
                    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $assignment->description }}</p>
                @endif

                @if($assignment->instruction_filename)
                    <div class="flex items-center gap-3 pt-3 border-t border-slate-100">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-0.5">Assignment Instructions</p>
                            <p class="text-sm font-medium text-slate-900 truncate">{{ $assignment->instruction_filename }}</p>
                        </div>
                        <a href="{{ route('tenant.assignments.instruction', [app('current_tenant')->slug, $assignment]) }}"
                           target="_blank"
                           class="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Open
                        </a>
                    </div>
                @endif
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

        {{-- Group Info (for group assignments) --}}
        @if($assignment->isGroupAssignment() && $myGroup)
            @php
                $usesSet = $assignment->usesStudentGroupSet();
                // Resolve leader display for both paths
                $leaderName = null;
                if ($usesSet) {
                    $leaderName = optional($groupLeader ?? null)->name;
                } else {
                    $leaderMember = $myGroup->members->where('is_leader', true)->first();
                    $leaderName = optional(optional($leaderMember)->user)->name;
                }
            @endphp
            <div class="bg-white rounded-2xl border border-indigo-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-indigo-100 bg-indigo-50/50">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <h3 class="font-semibold text-indigo-900">{{ $myGroup->name }}</h3>
                        @if($isLeader)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 uppercase">Leader</span>
                        @endif
                    </div>
                </div>
                <div class="p-4">
                    {{-- Leader / voting status (StudentGroupSet path only) --}}
                    @if($usesSet)
                        <div class="mb-4 p-3 rounded-xl border @if($leaderName) border-emerald-200 bg-emerald-50 @elseif($activeVoteRound ?? false) border-amber-200 bg-amber-50 @else border-red-200 bg-red-50 @endif">
                            <div class="flex items-start gap-2">
                                <svg class="w-4 h-4 mt-0.5 flex-shrink-0 @if($leaderName) text-emerald-600 @elseif($activeVoteRound ?? false) text-amber-600 @else text-red-600 @endif" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    @if($leaderName)
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/>
                                    @endif
                                </svg>
                                <div class="flex-1 text-xs">
                                    @if($leaderName)
                                        <p class="font-semibold text-emerald-900">Group leader: {{ $leaderName }}</p>
                                        <p class="text-emerald-700 mt-0.5">The leader submits on behalf of the group.</p>
                                    @elseif($activeVoteRound ?? false)
                                        <p class="font-semibold text-amber-900">Leader election in progress</p>
                                        <p class="text-amber-700 mt-0.5">Members are voting. Submission is unlocked once a winner is declared.</p>
                                    @else
                                        <p class="font-semibold text-red-900">No leader elected yet</p>
                                        <p class="text-red-700 mt-0.5">Start a vote in your group workspace to elect a leader before submitting.</p>
                                    @endif
                                    <a href="{{ route('tenant.workspace.show', [app('current_tenant')->slug, $myGroup]) }}" class="inline-block mt-1.5 font-semibold underline hover:no-underline">Go to group workspace →</a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Members</p>
                    <div class="space-y-1.5">
                        @foreach($myGroup->members as $member)
                            @php
                                if ($usesSet) {
                                    $memberIsLeader = ($member->role ?? null) === 'leader';
                                } else {
                                    $memberIsLeader = (bool) ($member->is_leader ?? false);
                                }
                            @endphp
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold {{ $memberIsLeader ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' }}">
                                    {{ strtoupper(substr($member->user->name ?? '?', 0, 1)) }}
                                </div>
                                <span class="text-sm text-slate-700">{{ $member->user->name }}</span>
                                @if($memberIsLeader)
                                    <span class="text-[10px] font-semibold text-amber-600">Leader</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @elseif($assignment->isGroupAssignment() && !$myGroup)
            <div class="bg-white rounded-2xl border border-amber-200 p-6 text-center">
                <svg class="w-8 h-8 text-amber-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                <p class="text-sm font-medium text-amber-800">You are not assigned to any group for this assignment.</p>
                <p class="text-xs text-amber-600 mt-1">Contact your lecturer for group assignment.</p>
            </div>
        @endif

        {{-- Submit --}}
        @php
            $canSubmit = !$assignment->isGroupAssignment() || ($myGroup && $isLeader);
            $hasSubmission = $mySubmission || ($assignment->isGroupAssignment() && $groupSubmission);
            $displaySubmission = $mySubmission ?? $groupSubmission;
        @endphp

        @if(!$hasSubmission && $canSubmit)
            @php $subType = $assignment->submission_type ?? 'file'; @endphp
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">
                        @if($assignment->isGroupAssignment()) Submit for Your Group @else Submit Your Work @endif
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        @if($assignment->isGroupAssignment()) As group leader, your submission applies to all members. @endif
                        @if($subType === 'file') Upload files @elseif($subType === 'text') Type your answer @else Upload files and/or type your answer @endif
                    </p>
                </div>
                <div class="p-6">
                    @if($errors->has('submit'))
                        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">{{ $errors->first('submit') }}</div>
                    @endif
                    <form method="POST" action="{{ route('tenant.assignments.submit', [app('current_tenant')->slug, $assignment]) }}" enctype="multipart/form-data" x-data="{ submitting: false }" @submit="submitting = true" class="space-y-4">
                        @csrf

                        {{-- File Upload --}}
                        @if($subType === 'file' || $subType === 'both')
                            <div class="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center" x-data="{ fileNames: [] }">
                                <svg class="w-8 h-8 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                <p class="text-sm text-slate-600 mb-2">Upload PDF or image files (max 25MB each)</p>
                                <input type="file" name="files[]" multiple {{ $subType === 'file' ? 'required' : '' }} accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                    @change="fileNames = Array.from($event.target.files).map(f => f.name)"
                                    class="text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                                <template x-if="fileNames.length > 0">
                                    <div class="mt-3 space-y-1">
                                        <template x-for="name in fileNames" :key="name">
                                            <div class="inline-flex items-center gap-1.5 bg-indigo-50 text-indigo-700 text-xs font-medium px-2.5 py-1 rounded-lg mr-1.5">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <span x-text="name"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        @endif

                        {{-- Text Input --}}
                        @if($subType === 'text' || $subType === 'both')
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Your Answer {{ $subType === 'text' ? '*' : '' }}</label>
                                <textarea name="text_content" rows="8" {{ $subType === 'text' ? 'required' : '' }}
                                    placeholder="Type your answer here..."
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('text_content') }}</textarea>
                            </div>
                        @endif

                        <div>
                            <label class="text-sm font-medium text-slate-700">Notes (optional)</label>
                            <textarea name="notes" rows="2" placeholder="Any notes for your lecturer..." class="w-full mt-1.5 px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" :disabled="submitting" class="w-full px-6 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold rounded-xl shadow-sm transition flex items-center justify-center gap-2">
                            <svg x-show="submitting" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            @php $submitLabel = $assignment->isGroupAssignment() ? 'Submit for Group' : 'Submit'; @endphp
                            <span x-text="submitting ? 'Submitting...' : @js($submitLabel)"></span>
                        </button>
                    </form>
                </div>
            </div>
        @elseif(!$hasSubmission && $assignment->isGroupAssignment() && $myGroup && !$isLeader)
            {{-- Non-leader group member waiting for leader --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-6 text-center">
                <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm font-medium text-slate-700">Waiting for group leader to submit</p>
                @if($leaderName ?? null)
                    <p class="text-xs text-slate-500 mt-1">{{ $leaderName }} will submit on behalf of your group.</p>
                @endif
            </div>
        @elseif($displaySubmission)
            <div class="bg-white rounded-2xl border border-emerald-200 overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center gap-3">
                        <svg class="w-6 h-6 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <div>
                            <p class="text-sm font-medium text-emerald-900">
                                @if($assignment->isGroupAssignment()) Group submitted @else Submitted @endif
                                {{ $displaySubmission->submitted_at->format('d M Y, H:i') }}
                            </p>
                            <p class="text-xs text-slate-500">
                                @if($displaySubmission->files->count()) {{ $displaySubmission->files->count() }} file(s) @endif
                                @if($displaySubmission->files->count() && $displaySubmission->text_content) &middot; @endif
                                @if($displaySubmission->text_content) Text answer included @endif
                                &middot; Status: {{ ucfirst($displaySubmission->status) }}
                                @if($assignment->isGroupAssignment() && !$isLeader)
                                    &middot; Submitted by {{ $displaySubmission->user->name ?? 'leader' }}
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                @if($displaySubmission->text_content)
                    <div class="px-6 py-4 border-t border-emerald-100">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Answer</p>
                        <div class="bg-emerald-50/50 rounded-xl p-4 text-sm text-slate-700 whitespace-pre-line max-h-48 overflow-y-auto">{{ $displaySubmission->text_content }}</div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-tenant-layout>
