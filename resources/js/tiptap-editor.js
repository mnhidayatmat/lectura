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

        init() {
            this._ensureEditor()

            // Sync hidden input value before form submission
            const form = this.$el.closest('form')
            if (form) {
                form.addEventListener('submit', () => {
                    this._syncHiddenInput()
                })
            }
        },

        _syncHiddenInput() {
            if (this.editor) {
                this.content = this.editor.getHTML()
            }
            if (this.$refs.hiddenInput) {
                this.$refs.hiddenInput.value = this.content
            }
        },

        _ensureEditor() {
            if (this.editor) return true

            const el = this.$refs.editorContent
            if (!el) return false

            try {
                this.editor = new Editor({
                    element: el,
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
                            class: 'focus:outline-none min-h-[120px] px-3 py-2',
                        },
                    },
                    onUpdate: ({ editor }) => {
                        this.content = editor.getHTML()
                        // Directly set hidden input value as well
                        if (this.$refs.hiddenInput) {
                            this.$refs.hiddenInput.value = this.content
                        }
                    },
                })
                return true
            } catch (e) {
                console.warn('Tiptap editor init failed, will retry on interaction:', e)
                return false
            }
        },

        destroy() {
            this.editor?.destroy()
            this.editor = null
        },

        // Every toolbar action ensures the editor exists first (handles x-show lazy init)
        toggleBold() { this._ensureEditor(); this.editor?.chain().focus().toggleBold().run() },
        toggleItalic() { this._ensureEditor(); this.editor?.chain().focus().toggleItalic().run() },
        toggleUnderline() { this._ensureEditor(); this.editor?.chain().focus().toggleUnderline().run() },
        toggleStrike() { this._ensureEditor(); this.editor?.chain().focus().toggleStrike().run() },
        toggleHeading(level) { this._ensureEditor(); this.editor?.chain().focus().toggleHeading({ level }).run() },
        toggleBulletList() { this._ensureEditor(); this.editor?.chain().focus().toggleBulletList().run() },
        toggleOrderedList() { this._ensureEditor(); this.editor?.chain().focus().toggleOrderedList().run() },
        setTextAlign(align) { this._ensureEditor(); this.editor?.chain().focus().setTextAlign(align).run() },
        toggleBlockquote() { this._ensureEditor(); this.editor?.chain().focus().toggleBlockquote().run() },
        setHorizontalRule() { this._ensureEditor(); this.editor?.chain().focus().setHorizontalRule().run() },

        insertTable() {
            this._ensureEditor()
            this.editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
        },
        addColumnAfter() { this._ensureEditor(); this.editor?.chain().focus().addColumnAfter().run() },
        addRowAfter() { this._ensureEditor(); this.editor?.chain().focus().addRowAfter().run() },
        deleteColumn() { this._ensureEditor(); this.editor?.chain().focus().deleteColumn().run() },
        deleteRow() { this._ensureEditor(); this.editor?.chain().focus().deleteRow().run() },
        deleteTable() { this._ensureEditor(); this.editor?.chain().focus().deleteTable().run() },

        isActive(name, attrs = {}) {
            return this.editor?.isActive(name, attrs) ?? false
        },
    }
}
