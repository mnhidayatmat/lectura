<x-tenant-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.assignments.show', [app('current_tenant')->slug, $assignment]) }}" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                @php
                    $reviewTitleGroup = $submission->studentGroup ?? $submission->assignmentGroup;
                @endphp
                <h2 class="text-2xl font-bold text-slate-900">
                    Review: {{ $assignment->isGroupAssignment() && $reviewTitleGroup ? $reviewTitleGroup->name : $submission->user->name }}
                </h2>
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
                            @php $annotatable = $file->isAnnotatable(); @endphp
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
                                        @if($file->annotated_at) &middot; <span class="text-rose-600 font-medium">Annotated {{ $file->annotated_at->diffForHumans() }}</span> @endif
                                    </p>
                                </div>
                                @if($annotatable)
                                    <button type="button"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium rounded-lg transition flex-shrink-0"
                                        data-annotate-open
                                        data-file-id="{{ $file->id }}"
                                        data-file-name="{{ $file->file_name }}"
                                        data-file-ext="{{ strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION)) }}"
                                        data-file-url="{{ route('tenant.assignments.files.raw', [app('current_tenant')->slug, $assignment, $submission, $file]) }}"
                                        data-save-url="{{ route('tenant.assignments.files.annotations.store', [app('current_tenant')->slug, $assignment, $submission, $file]) }}"
                                        data-strokes='@json($file->annotations ?? [])'>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        {{ $file->annotated_at ? 'Edit Marks' : 'Mark with Pen' }}
                                    </button>
                                @endif
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
            {{-- Group members info --}}
            @if($assignment->isGroupAssignment() && $groupMembers && $groupMembers->isNotEmpty())
                <div class="bg-indigo-50 rounded-2xl border border-indigo-200 p-5 mb-6">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <h3 class="font-semibold text-indigo-900">Group Members ({{ $groupMembers->count() }})</h3>
                    </div>
                    <p class="text-xs text-indigo-700 mb-3">Marks and feedback will be applied to all group members below.</p>
                    <div class="space-y-1.5">
                        @foreach($groupMembers as $member)
                            @php
                                $isMemberLeader = ($member->role ?? null) === 'leader' || (bool) ($member->is_leader ?? false);
                            @endphp
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-bold {{ $isMemberLeader ? 'bg-amber-100 text-amber-700' : 'bg-white text-indigo-700' }}">
                                    {{ strtoupper(substr($member->user->name ?? '?', 0, 1)) }}
                                </div>
                                <span class="text-sm text-indigo-900">{{ $member->user->name }}</span>
                                @if($isMemberLeader)
                                    <span class="text-[9px] font-bold text-amber-600 bg-amber-100 px-1.5 py-0.5 rounded-full">Leader</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

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
                    @if($assignment->isGroupAssignment() && $groupMembers && $groupMembers->count() > 1)
                        Finalize Marks for All {{ $groupMembers->count() }} Members
                    @else
                        Finalize Marks & Release Feedback
                    @endif
                </button>
            </form>
        </div>
    </div>

    {{-- Pen-tool annotator overlay --}}
    <div id="annotator" class="fixed inset-0 z-50 hidden bg-slate-900/95 flex flex-col" data-csrf="{{ csrf_token() }}">
        <div class="flex items-center gap-2 px-4 py-2 bg-slate-800 text-white border-b border-slate-700 flex-wrap">
            <span class="text-sm font-medium mr-2 max-w-xs truncate" data-annot-filename>file</span>

            {{-- Tools --}}
            <div class="flex items-center gap-1 bg-slate-700/50 rounded-lg p-1">
                <button type="button" data-annot-tool="pen" class="px-3 py-1.5 text-xs font-medium rounded-md bg-rose-600 text-white" title="Pen">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <button type="button" data-annot-tool="highlighter" class="px-3 py-1.5 text-xs font-medium rounded-md text-slate-300 hover:bg-slate-600" title="Highlighter">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.5 11.5l-2-2L4 19v2h2l9.5-9.5zm0 0L19 8l-3-3-3.5 3.5"/></svg>
                </button>
                <button type="button" data-annot-tool="eraser" class="px-3 py-1.5 text-xs font-medium rounded-md text-slate-300 hover:bg-slate-600" title="Eraser">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 13l-6 6m0 0l-6-6m6 6V3"/></svg>
                </button>
            </div>

            {{-- Colors --}}
            <div class="flex items-center gap-1 ml-1">
                <button type="button" data-annot-color="#dc2626" class="w-7 h-7 rounded-full ring-2 ring-white" style="background:#dc2626" title="Red"></button>
                <button type="button" data-annot-color="#1d4ed8" class="w-7 h-7 rounded-full ring-2 ring-transparent hover:ring-slate-400" style="background:#1d4ed8" title="Blue"></button>
                <button type="button" data-annot-color="#16a34a" class="w-7 h-7 rounded-full ring-2 ring-transparent hover:ring-slate-400" style="background:#16a34a" title="Green"></button>
                <button type="button" data-annot-color="#000000" class="w-7 h-7 rounded-full ring-2 ring-transparent hover:ring-slate-400" style="background:#000000" title="Black"></button>
                <button type="button" data-annot-color="#facc15" class="w-7 h-7 rounded-full ring-2 ring-transparent hover:ring-slate-400" style="background:#facc15" title="Yellow"></button>
            </div>

            {{-- Width --}}
            <label class="flex items-center gap-2 text-xs text-slate-300 ml-2">
                <span>Width</span>
                <input type="range" min="1" max="12" step="1" value="2" data-annot-width class="w-24">
                <span data-annot-width-label>2</span>
            </label>

            <div class="flex-1"></div>

            <button type="button" data-annot-undo class="px-3 py-1.5 text-xs font-medium rounded-md bg-slate-700 hover:bg-slate-600 text-white" title="Undo last stroke">
                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                Undo
            </button>
            <button type="button" data-annot-clear class="px-3 py-1.5 text-xs font-medium rounded-md bg-slate-700 hover:bg-slate-600 text-white" title="Clear all">
                Clear
            </button>
            <button type="button" data-annot-save class="px-4 py-1.5 text-xs font-semibold rounded-md bg-emerald-600 hover:bg-emerald-700 text-white">
                <span data-annot-save-label>Save</span>
            </button>
            <button type="button" data-annot-close class="px-3 py-1.5 text-xs font-medium rounded-md bg-slate-700 hover:bg-slate-600 text-white" title="Close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div data-annot-stage class="flex-1 overflow-auto bg-slate-700 p-6 flex flex-col items-center gap-4">
            {{-- pages injected here --}}
            <div data-annot-loading class="text-slate-300 text-sm">Loading…</div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" integrity="sha512-g/feAizyeUWv3wHM5kqIO5tzLJBO+5rTHnPbhqQlZysrHvSkpd9GnJoSWvnvFLbE8ChijW7TqFKyOmkO6UE+jw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        (function () {
            if (window.pdfjsLib && window.pdfjsLib.GlobalWorkerOptions) {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            }

            const overlay = document.getElementById('annotator');
            if (!overlay) return;
            const csrf = overlay.dataset.csrf;
            const stage = overlay.querySelector('[data-annot-stage]');
            const loading = overlay.querySelector('[data-annot-loading]');
            const filenameEl = overlay.querySelector('[data-annot-filename]');
            const widthInput = overlay.querySelector('[data-annot-width]');
            const widthLabel = overlay.querySelector('[data-annot-width-label]');
            const saveBtn = overlay.querySelector('[data-annot-save]');
            const saveLabel = overlay.querySelector('[data-annot-save-label]');

            const state = {
                tool: 'pen',
                color: '#dc2626',
                width: 2,
                strokes: [],          // [{page, tool, color, width, points: [[nx,ny],...]}]
                undoStack: [],
                pages: [],            // [{wrapper, base, overlay, ctx, w, h}]
                fileUrl: null,
                saveUrl: null,
                fileExt: null,
                drawing: false,
                currentStroke: null,
                currentPageIdx: null,
            };

            // Toolbar bindings
            overlay.querySelectorAll('[data-annot-tool]').forEach(btn => {
                btn.addEventListener('click', () => {
                    state.tool = btn.dataset.annotTool;
                    overlay.querySelectorAll('[data-annot-tool]').forEach(b => {
                        if (b === btn) {
                            b.classList.add('bg-rose-600', 'text-white');
                            b.classList.remove('text-slate-300', 'hover:bg-slate-600');
                        } else {
                            b.classList.remove('bg-rose-600', 'text-white');
                            b.classList.add('text-slate-300', 'hover:bg-slate-600');
                        }
                    });
                });
            });
            overlay.querySelectorAll('[data-annot-color]').forEach(btn => {
                btn.addEventListener('click', () => {
                    state.color = btn.dataset.annotColor;
                    overlay.querySelectorAll('[data-annot-color]').forEach(b => {
                        b.classList.toggle('ring-white', b === btn);
                        b.classList.toggle('ring-transparent', b !== btn);
                    });
                });
            });
            widthInput.addEventListener('input', () => {
                state.width = parseInt(widthInput.value, 10);
                widthLabel.textContent = state.width;
            });
            overlay.querySelector('[data-annot-undo]').addEventListener('click', () => {
                if (state.strokes.length === 0) return;
                const last = state.strokes.pop();
                state.undoStack.push(last);
                redrawPage(last.page);
            });
            overlay.querySelector('[data-annot-clear]').addEventListener('click', () => {
                if (!confirm('Clear all annotations on this file?')) return;
                state.strokes = [];
                state.pages.forEach((_, i) => redrawPage(i));
            });
            overlay.querySelector('[data-annot-close]').addEventListener('click', closeAnnotator);
            saveBtn.addEventListener('click', saveAnnotations);

            // Open trigger
            document.querySelectorAll('[data-annotate-open]').forEach(btn => {
                btn.addEventListener('click', () => openAnnotator(btn.dataset));
            });

            async function openAnnotator(data) {
                state.fileUrl = data.fileUrl;
                state.saveUrl = data.saveUrl;
                state.fileExt = (data.fileExt || '').toLowerCase();
                state.strokes = [];
                state.undoStack = [];
                state.pages = [];
                try {
                    state.strokes = JSON.parse(data.strokes || '[]') || [];
                } catch (e) { state.strokes = []; }

                filenameEl.textContent = data.fileName || 'file';
                stage.querySelectorAll('[data-annot-page]').forEach(n => n.remove());
                loading.style.display = '';
                overlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';

                try {
                    if (state.fileExt === 'pdf') {
                        await renderPdf(state.fileUrl);
                    } else {
                        await renderImage(state.fileUrl);
                    }
                    // Replay existing strokes onto each page
                    state.pages.forEach((_, i) => redrawPage(i));
                } catch (err) {
                    console.error(err);
                    loading.textContent = 'Failed to load file: ' + (err.message || err);
                    return;
                }
                loading.style.display = 'none';
            }

            function closeAnnotator() {
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }

            async function renderPdf(url) {
                if (!window.pdfjsLib) throw new Error('PDF library not loaded');
                const pdf = await window.pdfjsLib.getDocument({ url, withCredentials: true }).promise;
                const maxWidth = Math.min(stage.clientWidth - 48, 1100);

                for (let p = 1; p <= pdf.numPages; p++) {
                    const page = await pdf.getPage(p);
                    const viewport1 = page.getViewport({ scale: 1 });
                    const scale = maxWidth / viewport1.width;
                    const viewport = page.getViewport({ scale });
                    const dpr = window.devicePixelRatio || 1;

                    const wrapper = makePageWrapper(viewport.width, viewport.height);
                    const base = wrapper.querySelector('[data-base]');
                    const overlayCanvas = wrapper.querySelector('[data-overlay]');

                    base.width = Math.round(viewport.width * dpr);
                    base.height = Math.round(viewport.height * dpr);
                    overlayCanvas.width = base.width;
                    overlayCanvas.height = base.height;

                    const baseCtx = base.getContext('2d');
                    baseCtx.scale(dpr, dpr);
                    await page.render({ canvasContext: baseCtx, viewport }).promise;

                    state.pages.push({
                        wrapper, base, overlay: overlayCanvas,
                        ctx: overlayCanvas.getContext('2d'),
                        w: viewport.width, h: viewport.height, dpr,
                    });
                    bindPointer(state.pages.length - 1);
                }
            }

            async function renderImage(url) {
                const img = new Image();
                await new Promise((resolve, reject) => {
                    img.onload = resolve;
                    img.onerror = () => reject(new Error('Image failed to load'));
                    img.src = url;
                });
                const maxWidth = Math.min(stage.clientWidth - 48, 1100);
                const scale = Math.min(1, maxWidth / img.naturalWidth);
                const w = img.naturalWidth * scale;
                const h = img.naturalHeight * scale;
                const dpr = window.devicePixelRatio || 1;

                const wrapper = makePageWrapper(w, h);
                const base = wrapper.querySelector('[data-base]');
                const overlayCanvas = wrapper.querySelector('[data-overlay]');

                base.width = Math.round(w * dpr);
                base.height = Math.round(h * dpr);
                overlayCanvas.width = base.width;
                overlayCanvas.height = base.height;

                const baseCtx = base.getContext('2d');
                baseCtx.drawImage(img, 0, 0, base.width, base.height);

                state.pages.push({
                    wrapper, base, overlay: overlayCanvas,
                    ctx: overlayCanvas.getContext('2d'),
                    w, h, dpr,
                });
                bindPointer(0);
            }

            function makePageWrapper(cssW, cssH) {
                const wrapper = document.createElement('div');
                wrapper.dataset.annotPage = '';
                wrapper.className = 'relative shadow-lg bg-white';
                wrapper.style.width = cssW + 'px';
                wrapper.style.height = cssH + 'px';
                wrapper.innerHTML = `
                    <canvas data-base style="width:${cssW}px;height:${cssH}px;display:block;"></canvas>
                    <canvas data-overlay style="width:${cssW}px;height:${cssH}px;position:absolute;left:0;top:0;cursor:crosshair;touch-action:none;"></canvas>
                `;
                stage.appendChild(wrapper);
                return wrapper;
            }

            function bindPointer(pageIdx) {
                const page = state.pages[pageIdx];
                const c = page.overlay;

                const getPos = (e) => {
                    const rect = c.getBoundingClientRect();
                    return [
                        (e.clientX - rect.left) / rect.width,
                        (e.clientY - rect.top) / rect.height,
                    ];
                };

                c.addEventListener('pointerdown', (e) => {
                    e.preventDefault();
                    c.setPointerCapture(e.pointerId);
                    state.drawing = true;
                    state.currentPageIdx = pageIdx;
                    state.currentStroke = {
                        page: pageIdx,
                        tool: state.tool,
                        color: state.color,
                        width: state.width,
                        points: [getPos(e)],
                    };
                });
                c.addEventListener('pointermove', (e) => {
                    if (!state.drawing || state.currentPageIdx !== pageIdx) return;
                    e.preventDefault();
                    const p = getPos(e);
                    state.currentStroke.points.push(p);
                    drawStrokeIncrement(page, state.currentStroke);
                });
                const finish = (e) => {
                    if (!state.drawing || state.currentPageIdx !== pageIdx) return;
                    state.drawing = false;
                    if (state.currentStroke && state.currentStroke.points.length > 1) {
                        if (state.currentStroke.tool === 'eraser') {
                            applyEraser(state.currentStroke);
                            redrawPage(pageIdx);
                        } else {
                            state.strokes.push(state.currentStroke);
                        }
                    }
                    state.currentStroke = null;
                };
                c.addEventListener('pointerup', finish);
                c.addEventListener('pointercancel', finish);
                c.addEventListener('pointerleave', finish);
            }

            function strokeStyleFor(stroke, ctx) {
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                if (stroke.tool === 'highlighter') {
                    ctx.globalAlpha = 0.35;
                    ctx.lineWidth = stroke.width * 4;
                    ctx.strokeStyle = stroke.color;
                    ctx.globalCompositeOperation = 'source-over';
                } else if (stroke.tool === 'eraser') {
                    // visual marker only — actual erase happens by removing strokes
                    ctx.globalAlpha = 1;
                    ctx.lineWidth = stroke.width * 3;
                    ctx.strokeStyle = 'rgba(0,0,0,0.05)';
                    ctx.globalCompositeOperation = 'source-over';
                } else {
                    ctx.globalAlpha = 1;
                    ctx.lineWidth = stroke.width;
                    ctx.strokeStyle = stroke.color;
                    ctx.globalCompositeOperation = 'source-over';
                }
            }

            function drawStrokeIncrement(page, stroke) {
                const ctx = page.ctx;
                const pts = stroke.points;
                if (pts.length < 2) return;
                ctx.save();
                strokeStyleFor(stroke, ctx);
                const a = pts[pts.length - 2];
                const b = pts[pts.length - 1];
                ctx.beginPath();
                ctx.moveTo(a[0] * page.overlay.width, a[1] * page.overlay.height);
                ctx.lineTo(b[0] * page.overlay.width, b[1] * page.overlay.height);
                ctx.stroke();
                ctx.restore();
            }

            function drawWholeStroke(page, stroke) {
                const ctx = page.ctx;
                const pts = stroke.points;
                if (pts.length < 2) return;
                ctx.save();
                strokeStyleFor(stroke, ctx);
                ctx.beginPath();
                ctx.moveTo(pts[0][0] * page.overlay.width, pts[0][1] * page.overlay.height);
                for (let i = 1; i < pts.length; i++) {
                    ctx.lineTo(pts[i][0] * page.overlay.width, pts[i][1] * page.overlay.height);
                }
                ctx.stroke();
                ctx.restore();
            }

            function redrawPage(pageIdx) {
                const page = state.pages[pageIdx];
                if (!page) return;
                page.ctx.clearRect(0, 0, page.overlay.width, page.overlay.height);
                state.strokes
                    .filter(s => s.page === pageIdx)
                    .forEach(s => drawWholeStroke(page, s));
            }

            // Eraser: drop any saved stroke whose points come within ~12px of the eraser path.
            function applyEraser(eraserStroke) {
                const page = state.pages[eraserStroke.page];
                if (!page) return;
                const radius = (eraserStroke.width * 3) / page.overlay.width; // normalized
                const pts = eraserStroke.points;
                state.strokes = state.strokes.filter(s => {
                    if (s.page !== eraserStroke.page) return true;
                    return !s.points.some(p => pts.some(ep => Math.hypot(p[0] - ep[0], p[1] - ep[1]) < radius));
                });
            }

            async function saveAnnotations() {
                saveBtn.disabled = true;
                saveLabel.textContent = 'Saving…';
                try {
                    const flattened = await buildFlattenedImage();
                    const res = await fetch(state.saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ strokes: state.strokes, image: flattened }),
                    });
                    if (!res.ok) throw new Error('Server returned ' + res.status);
                    saveLabel.textContent = 'Saved ✓';
                    setTimeout(() => {
                        saveLabel.textContent = 'Save';
                        saveBtn.disabled = false;
                        location.reload();
                    }, 600);
                } catch (err) {
                    console.error(err);
                    alert('Could not save annotations: ' + (err.message || err));
                    saveLabel.textContent = 'Save';
                    saveBtn.disabled = false;
                }
            }

            function buildFlattenedImage() {
                if (state.pages.length === 0) return null;
                // Stack all pages vertically into one PNG.
                const gap = 16;
                let totalH = 0;
                let maxW = 0;
                state.pages.forEach(p => {
                    totalH += p.base.height;
                    maxW = Math.max(maxW, p.base.width);
                });
                totalH += gap * (state.pages.length - 1);

                const out = document.createElement('canvas');
                out.width = maxW;
                out.height = totalH;
                const ctx = out.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, out.width, out.height);

                let y = 0;
                state.pages.forEach(p => {
                    ctx.drawImage(p.base, 0, y);
                    ctx.drawImage(p.overlay, 0, y);
                    y += p.base.height + gap;
                });

                return out.toDataURL('image/png');
            }
        })();
    </script>
    @endpush
</x-tenant-layout>
