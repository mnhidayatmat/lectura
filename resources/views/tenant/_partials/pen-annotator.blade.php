{{-- Reusable pen-tool annotator overlay.
     Triggered by any element with [data-annotate-open] on the page,
     where the trigger carries:
       data-file-id, data-file-name, data-file-ext,
       data-file-url   (URL to original PDF/image),
       data-save-url   (POST endpoint accepting {strokes, image}),
       data-strokes    (JSON string of saved strokes; '[]' if none).
     Include this partial once per page that uses pen marking. --}}
<div id="annotator" class="fixed inset-0 z-50 hidden bg-slate-900/95 flex flex-col" data-csrf="{{ csrf_token() }}">
    <div class="flex items-center gap-2 px-4 py-2 bg-slate-800 text-white border-b border-slate-700 flex-wrap">
        <span class="text-sm font-medium mr-2 max-w-xs truncate" data-annot-filename>file</span>

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

        <div class="flex items-center gap-1 ml-1">
            <button type="button" data-annot-color="#dc2626" class="w-7 h-7 rounded-full ring-2 ring-white" style="background:#dc2626" title="Red"></button>
            <button type="button" data-annot-color="#1d4ed8" class="w-7 h-7 rounded-full ring-2 ring-transparent hover:ring-slate-400" style="background:#1d4ed8" title="Blue"></button>
            <button type="button" data-annot-color="#16a34a" class="w-7 h-7 rounded-full ring-2 ring-transparent hover:ring-slate-400" style="background:#16a34a" title="Green"></button>
            <button type="button" data-annot-color="#000000" class="w-7 h-7 rounded-full ring-2 ring-transparent hover:ring-slate-400" style="background:#000000" title="Black"></button>
            <button type="button" data-annot-color="#facc15" class="w-7 h-7 rounded-full ring-2 ring-transparent hover:ring-slate-400" style="background:#facc15" title="Yellow"></button>
        </div>

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
        <div data-annot-loading class="text-slate-300 text-sm">Loading…</div>
    </div>
</div>

@once
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" integrity="sha512-q+4liFwdPC/bNdhUpZx6aXDx/h77yEQtn4I1slHydcbZK34nLaR3cAeYSJshoxIOq3mjEf7xJE8YWIUHMn+oCQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
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
            tool: 'pen', color: '#dc2626', width: 2,
            strokes: [], undoStack: [], pages: [],
            fileUrl: null, saveUrl: null, fileExt: null,
            drawing: false, currentStroke: null, currentPageIdx: null,
        };

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
            try { state.strokes = JSON.parse(data.strokes || '[]') || []; } catch (e) { state.strokes = []; }

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
            // Round to 4 decimals (≈0.1px precision at 1100px wide) so a long
            // session of strokes doesn't balloon the JSON payload past
            // post_max_size when serialized.
            const getPos = (e) => {
                const rect = c.getBoundingClientRect();
                return [
                    Math.round(((e.clientX - rect.left) / rect.width) * 10000) / 10000,
                    Math.round(((e.clientY - rect.top) / rect.height) * 10000) / 10000,
                ];
            };
            c.addEventListener('pointerdown', (e) => {
                e.preventDefault();
                c.setPointerCapture(e.pointerId);
                state.drawing = true;
                state.currentPageIdx = pageIdx;
                state.currentStroke = {
                    page: pageIdx, tool: state.tool, color: state.color, width: state.width,
                    points: [getPos(e)],
                };
            });
            c.addEventListener('pointermove', (e) => {
                if (!state.drawing || state.currentPageIdx !== pageIdx) return;
                e.preventDefault();
                state.currentStroke.points.push(getPos(e));
                drawStrokeIncrement(page, state.currentStroke);
            });
            const finish = () => {
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
            state.strokes.filter(s => s.page === pageIdx).forEach(s => drawWholeStroke(page, s));
        }

        function applyEraser(eraserStroke) {
            const page = state.pages[eraserStroke.page];
            if (!page) return;
            const radius = (eraserStroke.width * 3) / page.overlay.width;
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
                // Stay well under the typical 8 MB post_max_size — once that
                // ceiling is breached PHP rejects the body before Laravel runs
                // and the user just sees a 500 with no log entry. The image
                // is a preview only; if it won't fit even after compression
                // we drop it and persist strokes alone so the lecturer's
                // marks aren't lost.
                const MAX_BODY_BYTES = 5 * 1024 * 1024;
                const strokesPayload = JSON.stringify(state.strokes);
                const headroom = MAX_BODY_BYTES - strokesPayload.length - 256;

                const flattened = buildFlattenedImage(headroom);
                const body = JSON.stringify({ strokes: state.strokes, image: flattened });

                const res = await fetch(state.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body,
                });
                if (!res.ok) throw new Error('Server returned ' + res.status);
                saveLabel.textContent = flattened ? 'Saved ✓' : 'Saved (no preview)';
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

        // Build a JPEG data URL that fits under `maxBytes`. We try the natural
        // canvas size at quality 0.8 first, then progressively scale down and
        // drop quality. If we can't fit, return null and the caller saves
        // strokes only.
        function buildFlattenedImage(maxBytes) {
            if (state.pages.length === 0) return null;
            // Strokes alone already filled the request budget — sending any
            // image would push us past post_max_size.
            if (typeof maxBytes !== 'number' || maxBytes <= 0) return null;

            const gap = 16;
            let totalH = 0, maxW = 0;
            state.pages.forEach(p => {
                totalH += p.base.height;
                maxW = Math.max(maxW, p.base.width);
            });
            totalH += gap * (state.pages.length - 1);

            const attempts = [
                { scale: 1.0, quality: 0.8 },
                { scale: 0.85, quality: 0.75 },
                { scale: 0.7, quality: 0.7 },
                { scale: 0.55, quality: 0.65 },
                { scale: 0.4, quality: 0.6 },
                { scale: 0.3, quality: 0.55 },
            ];

            for (const { scale, quality } of attempts) {
                const out = document.createElement('canvas');
                out.width = Math.max(1, Math.round(maxW * scale));
                out.height = Math.max(1, Math.round(totalH * scale));
                const ctx = out.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, out.width, out.height);

                let y = 0;
                state.pages.forEach(p => {
                    const pw = Math.round(p.base.width * scale);
                    const ph = Math.round(p.base.height * scale);
                    ctx.drawImage(p.base, 0, y, pw, ph);
                    ctx.drawImage(p.overlay, 0, y, pw, ph);
                    y += ph + Math.round(gap * scale);
                });

                const dataUrl = out.toDataURL('image/jpeg', quality);
                if (dataUrl.length <= maxBytes) {
                    return dataUrl;
                }
            }
            return null;
        }
    })();
</script>
@endpush
@endonce
