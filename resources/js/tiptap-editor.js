import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import { Underline } from '@tiptap/extension-underline'
import { TextAlign } from '@tiptap/extension-text-align'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table-row'
import { TableCell } from '@tiptap/extension-table-cell'
import { TableHeader } from '@tiptap/extension-table-header'

export default function tiptapEditor(initialContent = '') {
    return {
        editor: null,
        content: initialContent,
        _initialized: false,

        init() {
            // Try immediately in case element is already visible
            this._tryInit()

            // Watch for visibility changes — observe style mutations on all ancestors
            // so x-show toggling on any parent is detected
            const observer = new MutationObserver(() => this._tryInit())
            let el = this.$refs.editorContent
            while (el && el !== document.body) {
                observer.observe(el, { attributes: true, attributeFilter: ['style', 'class'] })
                el = el.parentElement
            }
            this._observer = observer

            // Also poll briefly for cases MutationObserver misses (e.g. Alpine x-show transitions)
            this._pollCount = 0
            this._pollTimer = setInterval(() => {
                this._tryInit()
                this._pollCount++
                if (this._initialized || this._pollCount > 50) {
                    clearInterval(this._pollTimer)
                }
            }, 200)
        },

        _tryInit() {
            if (this._initialized) return
            // Only init when the editor element is visible (offsetParent !== null)
            if (!this.$refs.editorContent || this.$refs.editorContent.offsetParent === null) return

            this._initialized = true
            this._observer?.disconnect()

            this.editor = new Editor({
                element: this.$refs.editorContent,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [3, 4] },
                    }),
                    Underline,
                    TextAlign.configure({
                        types: ['heading', 'paragraph'],
                    }),
                    Table.configure({ resizable: false }),
                    TableRow,
                    TableCell,
                    TableHeader,
                ],
                content: this.content,
                editorProps: {
                    attributes: {
                        class: 'prose prose-sm max-w-none focus:outline-none min-h-[120px] px-3 py-2',
                    },
                },
                onUpdate: ({ editor }) => {
                    this.content = editor.getHTML()
                },
            })
        },

        destroy() {
            clearInterval(this._pollTimer)
            this._observer?.disconnect()
            this.editor?.destroy()
        },

        // Toolbar actions (each ensures editor is initialized first)
        toggleBold() { this._tryInit(); this.editor?.chain().focus().toggleBold().run() },
        toggleItalic() { this._tryInit(); this.editor?.chain().focus().toggleItalic().run() },
        toggleUnderline() { this._tryInit(); this.editor?.chain().focus().toggleUnderline().run() },
        toggleStrike() { this._tryInit(); this.editor?.chain().focus().toggleStrike().run() },
        toggleHeading(level) { this.editor.chain().focus().toggleHeading({ level }).run() },
        toggleBulletList() { this.editor.chain().focus().toggleBulletList().run() },
        toggleOrderedList() { this.editor.chain().focus().toggleOrderedList().run() },
        setTextAlign(align) { this.editor.chain().focus().setTextAlign(align).run() },
        toggleBlockquote() { this.editor.chain().focus().toggleBlockquote().run() },
        setHorizontalRule() { this.editor.chain().focus().setHorizontalRule().run() },

        insertTable() {
            this.editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
        },
        addColumnAfter() { this.editor.chain().focus().addColumnAfter().run() },
        addRowAfter() { this.editor.chain().focus().addRowAfter().run() },
        deleteColumn() { this.editor.chain().focus().deleteColumn().run() },
        deleteRow() { this.editor.chain().focus().deleteRow().run() },
        deleteTable() { this.editor.chain().focus().deleteTable().run() },

        // Active state checks
        isActive(name, attrs = {}) {
            return this.editor?.isActive(name, attrs) ?? false
        },
    }
}
