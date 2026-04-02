{{-- Global Math Formula Modal — listens for 'open-math' event from any TipTap editor --}}
<div x-data="mathModal()" x-on:open-math.window="open($event.detail.callback, '', $event.detail.block)"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="absolute inset-0 bg-black/50" @click="close()"></div>
    <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden" @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><text x="2" y="17" font-size="14" font-style="italic" font-family="serif" fill="currentColor" stroke="none">fx</text></svg>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Math Formula</h3>
            </div>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                    <input type="checkbox" x-model="displayMode" @change="updatePreview()"
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Display mode (centered block)
                </label>
                <button @click="close()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Quick-insert templates --}}
        <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80">
            <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Quick Insert</p>
            <div class="flex flex-wrap gap-1">
                <template x-for="t in templates" :key="t.latex">
                    <button type="button" @click="insertTemplate(t.latex)"
                            :title="t.title"
                            class="px-2 py-1 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:border-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition" x-text="t.label">
                    </button>
                </template>
            </div>
        </div>

        {{-- LaTeX input --}}
        <div class="px-6 py-4 space-y-3 flex-1 overflow-y-auto">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1.5">LaTeX Formula</label>
                <textarea x-ref="latexInput" x-model="latex" @input="updatePreview()" @keydown.ctrl.enter="insert()"
                          rows="3" placeholder="e.g. \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}"
                          class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"></textarea>
            </div>

            {{-- Live preview --}}
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1.5">Preview</label>
                <div class="min-h-[60px] flex items-center justify-center p-4 rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900" x-html="preview"></div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between px-6 py-3 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80">
            <p class="text-[10px] text-slate-400 dark:text-slate-500">Use LaTeX syntax &middot; Ctrl+Enter to insert</p>
            <div class="flex gap-2">
                <button type="button" @click="close()" class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-800 transition">Cancel</button>
                <button type="button" @click="insert()" class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition" :disabled="!latex.trim()">
                    Insert Formula
                </button>
            </div>
        </div>
    </div>
</div>
