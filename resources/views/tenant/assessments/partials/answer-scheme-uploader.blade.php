@php
    /** @var \App\Models\Assessment|null $assessment */
    $existingPath = $assessment->answer_scheme_path ?? null;
    $existingName = $assessment->answer_scheme_filename ?? null;
@endphp

<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6"
     x-data="answerSchemeUploader({
         hasExisting: {{ $existingPath ? 'true' : 'false' }},
         existingName: @js($existingName ?? ''),
     })"
     x-init="init()">

    <div class="flex items-start justify-between mb-4">
        <div class="min-w-0">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Marking Answer Scheme
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 uppercase tracking-wide">Lecturer only</span>
            </h3>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                Upload the marking key as a PDF, or scan handwritten pages with your phone camera — multiple shots will be merged into one PDF automatically.
            </p>
        </div>
    </div>

    {{-- Existing file row --}}
    <div x-show="hasExisting && !removing && !hasNewFile"
         class="flex items-center gap-3 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 mb-3">
        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        </div>
        <span class="flex-1 text-sm font-medium text-emerald-800 dark:text-emerald-200 truncate" x-text="existingName"></span>
        @if($existingPath && isset($assessment))
            <a href="{{ route('tenant.assessments.answer-scheme.view', [$tenant->slug, $course, $assessment]) }}"
               target="_blank"
               class="p-1.5 text-emerald-500 hover:text-emerald-700 transition flex-shrink-0" title="View">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </a>
            <a href="{{ route('tenant.assessments.answer-scheme.download', [$tenant->slug, $course, $assessment]) }}"
               class="p-1.5 text-emerald-500 hover:text-emerald-700 transition flex-shrink-0" title="Download">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            </a>
        @endif
        <button type="button" @click="removing = true"
                class="p-1.5 text-slate-400 hover:text-red-500 transition flex-shrink-0" title="Remove">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
    </div>

    {{-- Removal warning --}}
    <div x-show="removing && !hasNewFile"
         class="flex items-center gap-3 p-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 mb-3">
        <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="flex-1 text-xs text-red-700 dark:text-red-400">Answer scheme will be removed when you save.</span>
        <button type="button" @click="removing = false" class="text-xs text-slate-500 hover:text-slate-700 transition">Undo</button>
        <input type="hidden" name="remove_answer_scheme" value="1">
    </div>

    {{-- Action buttons (visible when no existing-and-kept, no new file picked) --}}
    <div x-show="(!hasExisting || removing) && !hasNewFile && !scanning && !merging">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {{-- Upload PDF --}}
            <button type="button"
                    @click="$refs.pdfFile.click()"
                    class="group flex items-center gap-3 p-4 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-emerald-400 dark:hover:border-emerald-500 hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10 transition text-left">
                <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-700 group-hover:bg-emerald-100 dark:group-hover:bg-emerald-900/30 flex items-center justify-center flex-shrink-0 transition">
                    <svg class="w-5 h-5 text-slate-500 group-hover:text-emerald-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 group-hover:text-emerald-700 dark:group-hover:text-emerald-300 transition">Upload PDF</p>
                    <p class="text-[11px] text-slate-400">Up to 25 MB</p>
                </div>
            </button>

            {{-- Scan with camera --}}
            <button type="button"
                    @click="$refs.cameraInput.click()"
                    class="group flex items-center gap-3 p-4 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-emerald-400 dark:hover:border-emerald-500 hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10 transition text-left">
                <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-700 group-hover:bg-emerald-100 dark:group-hover:bg-emerald-900/30 flex items-center justify-center flex-shrink-0 transition">
                    <svg class="w-5 h-5 text-slate-500 group-hover:text-emerald-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 group-hover:text-emerald-700 dark:group-hover:text-emerald-300 transition">Scan with camera</p>
                    <p class="text-[11px] text-slate-400">Mobile-friendly · merges to PDF</p>
                </div>
            </button>
        </div>
        <p class="mt-3 text-[11px] text-slate-400 dark:text-slate-500">PDF only — scanned images are auto-merged into a single PDF in your browser before upload.</p>
    </div>

    {{-- Scanning tray (after first capture, before merge/save) --}}
    <div x-show="scanning && !merging" class="space-y-3">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                <span x-text="pages.length"></span> page<span x-show="pages.length !== 1">s</span> captured
            </p>
            <button type="button" @click="cancelScan()" class="text-xs text-slate-400 hover:text-red-500 transition">Cancel</button>
        </div>

        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 max-h-72 overflow-y-auto">
            <template x-for="(page, idx) in pages" :key="page.id">
                <div class="relative group rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                    <img :src="page.dataUrl" class="w-full h-28 object-cover" alt="">
                    <div class="absolute top-1 left-1 px-1.5 py-0.5 rounded-md bg-black/60 text-white text-[10px] font-bold" x-text="idx + 1"></div>
                    <button type="button" @click="removePage(page.id)"
                            class="absolute top-1 right-1 w-6 h-6 rounded-md bg-red-500/90 text-white opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="$refs.cameraInput.click()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add page
            </button>
            <button type="button" @click="finishScan()" :disabled="pages.length === 0"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-semibold rounded-lg transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Merge to PDF
            </button>
        </div>
    </div>

    {{-- Merging spinner --}}
    <div x-show="merging" class="flex items-center justify-center gap-3 p-6 rounded-xl bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-600">
        <svg class="animate-spin h-5 w-5 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <p class="text-xs text-slate-600 dark:text-slate-300">Merging <span x-text="pages.length"></span> page<span x-show="pages.length !== 1">s</span> into a single PDF…</p>
    </div>

    {{-- New file selected preview --}}
    <div x-show="hasNewFile" class="flex items-center gap-3 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700">
        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200 truncate" x-text="newFileName"></p>
            <p class="text-[10px] text-emerald-600 dark:text-emerald-400" x-text="hasExisting ? 'Will replace existing answer scheme on save' : 'Will be uploaded on save'"></p>
        </div>
        <button type="button" @click="resetNewFile()"
                class="text-slate-400 hover:text-red-500 transition flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    {{-- Hidden inputs --}}
    <input type="file" name="answer_scheme_file" x-ref="answerSchemeFile" accept="application/pdf" class="hidden">
    <input type="file" x-ref="pdfFile" accept="application/pdf" class="hidden"
           @change="onPdfPicked($event)">
    <input type="file" x-ref="cameraInput" accept="image/*" capture="environment" multiple class="hidden"
           @change="onImagesPicked($event)">

    @error('answer_scheme_file')
        <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

@once
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js" defer></script>
        <script>
            window.answerSchemeUploader = function (opts) {
                return {
                    hasExisting: opts.hasExisting,
                    existingName: opts.existingName,
                    removing: false,
                    hasNewFile: false,
                    newFileName: '',
                    scanning: false,
                    merging: false,
                    pages: [], // { id, dataUrl, blob }

                    init() {},

                    onPdfPicked(e) {
                        const f = e.target.files[0];
                        if (!f) return;
                        this.assignFileToInput(f);
                        this.newFileName = f.name;
                        this.hasNewFile = true;
                        this.removing = false;
                        e.target.value = '';
                    },

                    async onImagesPicked(e) {
                        const files = Array.from(e.target.files || []);
                        if (files.length === 0) return;
                        this.scanning = true;
                        for (const f of files) {
                            if (!f.type.startsWith('image/')) continue;
                            const dataUrl = await this.readAsDataUrl(f);
                            this.pages.push({
                                id: crypto.randomUUID ? crypto.randomUUID() : String(Date.now() + Math.random()),
                                dataUrl,
                            });
                        }
                        e.target.value = '';
                    },

                    removePage(id) {
                        this.pages = this.pages.filter(p => p.id !== id);
                        if (this.pages.length === 0) this.scanning = false;
                    },

                    cancelScan() {
                        this.pages = [];
                        this.scanning = false;
                    },

                    async finishScan() {
                        if (this.pages.length === 0) return;
                        if (typeof window.jspdf === 'undefined') {
                            alert('PDF library is still loading. Please try again in a moment.');
                            return;
                        }
                        this.merging = true;
                        try {
                            const { jsPDF } = window.jspdf;
                            // A4 portrait at 72 dpi-ish (jsPDF default unit is mm)
                            const pdf = new jsPDF({ unit: 'mm', format: 'a4' });
                            const pageW = pdf.internal.pageSize.getWidth();
                            const pageH = pdf.internal.pageSize.getHeight();

                            for (let i = 0; i < this.pages.length; i++) {
                                const dataUrl = this.pages[i].dataUrl;
                                const img = await this.loadImage(dataUrl);
                                // Fit image into the page, preserving aspect ratio
                                const ratio = Math.min(pageW / img.width, pageH / img.height);
                                const w = img.width * ratio;
                                const h = img.height * ratio;
                                const x = (pageW - w) / 2;
                                const y = (pageH - h) / 2;
                                if (i > 0) pdf.addPage();
                                // jsPDF supports JPEG/PNG via data URL
                                const fmt = dataUrl.startsWith('data:image/png') ? 'PNG' : 'JPEG';
                                pdf.addImage(dataUrl, fmt, x, y, w, h, undefined, 'FAST');
                            }

                            const blob = pdf.output('blob');
                            const filename = 'answer-scheme-' + new Date().toISOString().slice(0,10) + '.pdf';
                            const file = new File([blob], filename, { type: 'application/pdf' });
                            this.assignFileToInput(file);
                            this.newFileName = filename;
                            this.hasNewFile = true;
                            this.removing = false;
                        } catch (err) {
                            console.error('PDF merge failed', err);
                            alert('Failed to merge images into a PDF: ' + (err?.message || err));
                        } finally {
                            this.merging = false;
                            this.scanning = false;
                            this.pages = [];
                        }
                    },

                    resetNewFile() {
                        this.hasNewFile = false;
                        this.newFileName = '';
                        this.$refs.answerSchemeFile.value = '';
                    },

                    assignFileToInput(file) {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        this.$refs.answerSchemeFile.files = dt.files;
                    },

                    readAsDataUrl(file) {
                        return new Promise((resolve, reject) => {
                            const r = new FileReader();
                            r.onload = () => resolve(r.result);
                            r.onerror = reject;
                            r.readAsDataURL(file);
                        });
                    },

                    loadImage(src) {
                        return new Promise((resolve, reject) => {
                            const img = new Image();
                            img.onload = () => resolve(img);
                            img.onerror = reject;
                            img.src = src;
                        });
                    },
                };
            };
        </script>
    @endpush
@endonce
