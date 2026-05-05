@php
    /**
     * @var \App\Models\User $student
     * @var \App\Models\Course $course
     * @var \App\Models\Assessment $assessment
     * @var \Illuminate\Support\Collection $existingAnswerScripts  Keyed by user_id, each ['score_id' => ..., 'name' => ...]
     * @var string|null $wrapAs                                    'td' (default) for table rows, 'div' for mobile cards
     */
    $existing = $existingAnswerScripts[$student->id] ?? null;
    $existingName = $existing['name'] ?? null;
    $existingScoreId = $existing['score_id'] ?? null;
    $wrapAs = $wrapAs ?? 'td';
    $wrapClass = $wrapAs === 'td' ? 'px-3 py-2.5' : '';
@endphp

<{{ $wrapAs }} class="{{ $wrapClass }}"
    x-data="{
        existing: @js((bool) $existing),
        existingName: @js($existingName ?? ''),
        studentLabel: @js($student->name),
        studentId: @js((int) $student->id),
        removing: false,
        newName: '',
        isMobile: false,
        scanning: false,
        scannedPages: [],
        scanBuilding: false,
        get hasNew() { return this.newName !== ''; },
        init() {
            this.isMobile = window.matchMedia('(hover: none) and (pointer: coarse)').matches;
        },
        pickFile(mode = 'file') {
            if (mode === 'scan') { this.startScan(); return; }
            const input = this.$refs.scriptInput;
            input.setAttribute('accept', 'application/pdf,image/jpeg,image/png');
            input.removeAttribute('capture');
            input.click();
        },
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
        startScan() {
            this.scannedPages = [];
            this.scanning = true;
            this.$nextTick(() => this.captureNext());
        },
        captureNext() {
            this.$refs.scanInput.value = '';
            this.$refs.scanInput.click();
        },
        async onScanned(e) {
            const f = e.target.files[0];
            if (!f) return;
            try {
                const page = await window.LecturaScan.imageFromFile(f);
                this.scannedPages.push(page);
            } catch (err) {
                console.error(err);
                alert('Could not read the captured image.');
            }
        },
        removeScanPage(idx) {
            this.scannedPages.splice(idx, 1);
        },
        cancelScan() {
            if (this.scanBuilding) return;
            this.scanning = false;
            this.scannedPages = [];
        },
        async finishScan() {
            if (this.scannedPages.length === 0 || this.scanBuilding) return;
            this.scanBuilding = true;
            try {
                const blob = await window.LecturaScan.buildPdf(this.scannedPages);
                const fileName = 'scan-' + this.studentId + '-' + this.scannedPages.length + 'p.pdf';
                const file = new File([blob], fileName, { type: 'application/pdf' });
                const dt = new DataTransfer();
                dt.items.add(file);
                const input = this.$refs.scriptInput;
                input.setAttribute('accept', 'application/pdf,image/jpeg,image/png');
                input.removeAttribute('capture');
                input.files = dt.files;
                this.newName = fileName;
                this.removing = false;
                this.scanning = false;
                this.scannedPages = [];
            } catch (err) {
                console.error(err);
                alert('Could not build the PDF. Please try again.');
            } finally {
                this.scanBuilding = false;
            }
        },
    }">
    <input type="file" x-ref="scriptInput"
           name="answer_script_files[{{ $student->id }}]"
           accept="application/pdf,image/jpeg,image/png"
           class="hidden"
           @change="onPicked($event)">
    <input type="file" x-ref="scanInput"
           accept="image/*"
           capture="environment"
           class="hidden"
           @change="onScanned($event)">
    <input type="hidden" name="remove_answer_scripts[{{ $student->id }}]"
           value="1" :disabled="!removing || hasNew">

    <div class="flex flex-col gap-1 items-stretch {{ $wrapAs === 'td' ? 'min-w-[150px] max-w-[180px]' : '' }}">
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
                <button type="button" x-show="isMobile" @click="pickFile('scan')"
                        class="p-1 text-slate-400 hover:text-indigo-500 transition flex-shrink-0" title="Scan to PDF">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                </button>
                <button type="button" @click="pickFile('file')"
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

        {{-- Upload / Scan triggers when nothing on file (or after marking for removal) --}}
        <template x-if="!hasNew && (!existing || removing)">
            <div class="flex items-center gap-1">
                <button type="button" @click="pickFile('file')"
                        class="flex-1 inline-flex items-center justify-center gap-1 px-2.5 py-1 rounded-md border border-dashed border-slate-300 dark:border-slate-600 text-slate-500 dark:text-slate-400 hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition text-[10px] font-medium">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Upload</span>
                </button>
                <button type="button" x-show="isMobile" @click="pickFile('scan')"
                        class="inline-flex items-center justify-center gap-1 px-2.5 py-1 rounded-md border border-dashed border-indigo-300 dark:border-indigo-600 text-indigo-600 dark:text-indigo-400 hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition text-[10px] font-medium" title="Scan to PDF">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                    <span>Scan</span>
                </button>
            </div>
        </template>
    </div>

    {{-- Scan-to-PDF modal (teleported to body so it floats above tables) --}}
    <template x-teleport="body">
        <div x-show="scanning" x-cloak
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
             @keydown.escape.window="cancelScan()">
            <div class="absolute inset-0 bg-slate-900/70" @click="cancelScan()"></div>
            <div class="relative bg-white dark:bg-slate-800 w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl shadow-2xl flex flex-col max-h-[92vh]"
                 @click.stop>
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Scan answer script</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate" x-text="studentLabel"></p>
                    </div>
                    <button type="button" @click="cancelScan()" :disabled="scanBuilding"
                            class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 disabled:opacity-50 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="px-5 py-4 overflow-y-auto flex-1">
                    <template x-if="scannedPages.length === 0">
                        <div class="text-center py-8">
                            <div class="w-16 h-16 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center mb-3">
                                <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Open the camera to capture the first page.</p>
                        </div>
                    </template>

                    <template x-if="scannedPages.length > 0">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                                <span x-text="scannedPages.length"></span> page<span x-show="scannedPages.length !== 1">s</span> captured
                            </p>
                            <div class="grid grid-cols-3 gap-2">
                                <template x-for="(p, idx) in scannedPages" :key="idx">
                                    <div class="relative group rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900">
                                        <img :src="p.dataUrl" class="w-full h-24 object-cover">
                                        <span class="absolute top-1 left-1 px-1.5 py-0.5 rounded bg-slate-900/75 text-white text-[10px] font-bold" x-text="idx + 1"></span>
                                        <button type="button" @click="removeScanPage(idx)" :disabled="scanBuilding"
                                                class="absolute top-1 right-1 w-5 h-5 rounded-full bg-red-500 text-white flex items-center justify-center disabled:opacity-50 hover:bg-red-600 transition">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row gap-2">
                    <button type="button" @click="captureNext()" :disabled="scanBuilding"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl border border-indigo-300 dark:border-indigo-600 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 disabled:opacity-50 text-sm font-semibold transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span x-text="scannedPages.length === 0 ? 'Capture page' : 'Add page'"></span>
                    </button>
                    <button type="button" @click="finishScan()" :disabled="scannedPages.length === 0 || scanBuilding"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition">
                        <svg x-show="!scanBuilding" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <svg x-show="scanBuilding" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="4" stroke-opacity="0.25"/><path stroke-linecap="round" stroke-width="4" d="M12 2a10 10 0 0110 10"/></svg>
                        <span x-text="scanBuilding ? 'Building PDF…' : 'Save as PDF'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</{{ $wrapAs }}>
