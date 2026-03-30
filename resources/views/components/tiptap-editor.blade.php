@props(['name', 'content' => ''])

<div x-data="tiptapEditor(@js($content))" x-init="init(); $nextTick(() => _tryInit())" x-on:destroy.window="destroy()" x-effect="$el.offsetParent !== null && $nextTick(() => _tryInit())" class="tiptap-content rounded-lg border border-slate-300 dark:border-slate-600 overflow-hidden bg-white dark:bg-slate-800">
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-0.5 px-2 py-1.5 border-b border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50">
        {{-- Text formatting --}}
        <button type="button" @click="toggleBold()" :class="isActive('bold') && 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700'" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition" title="Bold">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"/><path d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"/></svg>
        </button>
        <button type="button" @click="toggleItalic()" :class="isActive('italic') && 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700'" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition" title="Italic">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
        </button>
        <button type="button" @click="toggleUnderline()" :class="isActive('underline') && 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700'" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition" title="Underline">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
        </button>
        <button type="button" @click="toggleStrike()" :class="isActive('strike') && 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700'" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition" title="Strikethrough">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="4" y1="12" x2="20" y2="12"/><path d="M17.5 7.5A4.5 4.5 0 009 7.5M6.5 16.5A4.5 4.5 0 0015 16.5"/></svg>
        </button>

        <span class="w-px h-5 bg-slate-300 dark:bg-slate-500 mx-1"></span>

        {{-- Headings --}}
        <button type="button" @click="toggleHeading(3)" :class="isActive('heading', {level: 3}) && 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700'" class="px-1.5 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition text-[10px] font-bold" title="Heading 3">H3</button>
        <button type="button" @click="toggleHeading(4)" :class="isActive('heading', {level: 4}) && 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700'" class="px-1.5 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition text-[10px] font-bold" title="Heading 4">H4</button>

        <span class="w-px h-5 bg-slate-300 dark:bg-slate-500 mx-1"></span>

        {{-- Lists --}}
        <button type="button" @click="toggleBulletList()" :class="isActive('bulletList') && 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700'" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition" title="Bullet List">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="5" cy="6" r="1" fill="currentColor"/><circle cx="5" cy="12" r="1" fill="currentColor"/><circle cx="5" cy="18" r="1" fill="currentColor"/></svg>
        </button>
        <button type="button" @click="toggleOrderedList()" :class="isActive('orderedList') && 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700'" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition" title="Numbered List">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="10" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="10" y1="18" x2="20" y2="18"/><text x="3" y="8" fill="currentColor" font-size="7" font-weight="bold">1</text><text x="3" y="14" fill="currentColor" font-size="7" font-weight="bold">2</text><text x="3" y="20" fill="currentColor" font-size="7" font-weight="bold">3</text></svg>
        </button>

        <span class="w-px h-5 bg-slate-300 dark:bg-slate-500 mx-1"></span>

        {{-- Alignment --}}
        <button type="button" @click="setTextAlign('left')" :class="isActive({textAlign: 'left'}) && 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700'" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition" title="Align Left">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>
        </button>
        <button type="button" @click="setTextAlign('center')" :class="isActive({textAlign: 'center'}) && 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700'" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition" title="Align Center">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
        </button>

        <span class="w-px h-5 bg-slate-300 dark:bg-slate-500 mx-1"></span>

        {{-- Block elements --}}
        <button type="button" @click="toggleBlockquote()" :class="isActive('blockquote') && 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700'" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition" title="Blockquote">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg>
        </button>
        <button type="button" @click="setHorizontalRule()" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition" title="Horizontal Rule">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/></svg>
        </button>

        <span class="w-px h-5 bg-slate-300 dark:bg-slate-500 mx-1"></span>

        {{-- Table --}}
        <div class="relative" x-data="{ tableMenu: false }">
            <button type="button" @click="tableMenu = !tableMenu" class="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition" title="Table">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
            </button>
            <div x-show="tableMenu" x-cloak @click.away="tableMenu = false" class="absolute left-0 mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-lg shadow-lg z-50 py-1 w-40">
                <button type="button" @click="insertTable(); tableMenu = false" class="w-full text-left px-3 py-1.5 text-xs hover:bg-slate-100 dark:hover:bg-slate-700">Insert Table</button>
                <button type="button" @click="addColumnAfter(); tableMenu = false" class="w-full text-left px-3 py-1.5 text-xs hover:bg-slate-100 dark:hover:bg-slate-700">Add Column</button>
                <button type="button" @click="addRowAfter(); tableMenu = false" class="w-full text-left px-3 py-1.5 text-xs hover:bg-slate-100 dark:hover:bg-slate-700">Add Row</button>
                <button type="button" @click="deleteColumn(); tableMenu = false" class="w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Delete Column</button>
                <button type="button" @click="deleteRow(); tableMenu = false" class="w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Delete Row</button>
                <button type="button" @click="deleteTable(); tableMenu = false" class="w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Delete Table</button>
            </div>
        </div>
    </div>

    {{-- Editor content area --}}
    <div x-ref="editorContent" class="text-sm text-slate-900 dark:text-white"></div>

    {{-- Hidden input to submit HTML --}}
    <input type="hidden" name="{{ $name }}" :value="content">
</div>
