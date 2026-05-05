@php
    /**
     * @var \App\Models\User $student
     * @var \App\Models\Course $course
     * @var \App\Models\Assessment $assessment
     * @var \Illuminate\Support\Collection $existingAnswerScripts  Keyed by user_id, each ['score_id' => ..., 'name' => ...]
     */
    $existing = $existingAnswerScripts[$student->id] ?? null;
    $existingName = $existing['name'] ?? null;
    $existingScoreId = $existing['score_id'] ?? null;
@endphp

<td class="px-3 py-2.5"
    x-data="{
        existing: @js((bool) $existing),
        existingName: @js($existingName ?? ''),
        removing: false,
        newName: '',
        get hasNew() { return this.newName !== ''; },
        pickFile() { this.$refs.scriptInput.click(); },
        onPicked(e) {
            const f = e.target.files[0];
            if (!f) return;
            this.newName = f.name;
            this.removing = false;
        },
        clearNew() {
            this.$refs.scriptInput.value = '';
            this.newName = '';
        },
    }">
    <input type="file" x-ref="scriptInput"
           name="answer_script_files[{{ $student->id }}]"
           accept="application/pdf,image/jpeg,image/png"
           class="hidden"
           @change="onPicked($event)">
    <input type="hidden" name="remove_answer_scripts[{{ $student->id }}]"
           value="1" :disabled="!removing || hasNew">

    <div class="flex flex-col gap-1 items-stretch min-w-[150px] max-w-[180px]">
        {{-- Existing file (kept) --}}
        <template x-if="existing && !removing && !hasNew">
            <div class="flex items-center gap-1">
                @if($existingScoreId)
                    <a href="{{ route('tenant.assessments.scores.answer-script.view', [$tenant->slug, $course, $assessment, $existingScoreId]) }}"
                       target="_blank"
                       class="flex items-center gap-1 flex-1 min-w-0 px-2 py-1 rounded-md bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/30 transition"
                       title="View answer script">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span class="text-[10px] font-medium truncate" x-text="existingName"></span>
                    </a>
                @endif
                <button type="button" @click="pickFile()"
                        class="p-1 text-slate-400 hover:text-indigo-500 transition flex-shrink-0" title="Replace">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
                <button type="button" @click="removing = true"
                        class="p-1 text-slate-400 hover:text-red-500 transition flex-shrink-0" title="Remove">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>

        {{-- Removal pending --}}
        <template x-if="existing && removing && !hasNew">
            <div class="flex items-center gap-1 px-2 py-1 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700">
                <span class="text-[10px] text-red-700 dark:text-red-400 flex-1">Will remove</span>
                <button type="button" @click="removing = false" class="text-[10px] text-slate-500 hover:text-slate-700">Undo</button>
            </div>
        </template>

        {{-- New file picked --}}
        <template x-if="hasNew">
            <div class="flex items-center gap-1 px-2 py-1 rounded-md bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700">
                <svg class="w-3 h-3 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
                <span class="text-[10px] font-medium text-indigo-700 dark:text-indigo-300 truncate flex-1" x-text="newName"></span>
                <button type="button" @click="clearNew()" class="text-slate-400 hover:text-red-500 transition flex-shrink-0">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>

        {{-- Upload trigger when nothing on file (or after marking for removal) --}}
        <template x-if="!hasNew && (!existing || removing)">
            <button type="button" @click="pickFile()"
                    class="inline-flex items-center justify-center gap-1 px-2.5 py-1 rounded-md border border-dashed border-slate-300 dark:border-slate-600 text-slate-500 dark:text-slate-400 hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition text-[10px] font-medium">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>Upload</span>
            </button>
        </template>
    </div>
</td>
