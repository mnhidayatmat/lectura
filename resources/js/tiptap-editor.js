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
            // Wait for Alpine to finish processing refs, then create editor.
            // ProseMirror attaches to DOM elements even if parent is display:none.
            // The min-h CSS ensures correct sizing once the element becomes visible.
            const createEditor = () => {
                const el = this.$refs.editorContent
                if (!el || this.editor) return

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
                    },
                })
            }

            // Use setTimeout(0) instead of requestAnimationFrame.
            // rAF never fires for elements inside display:none parents.
            // setTimeout(0) is guaranteed to fire regardless of visibility.
            this.$nextTick(() => {
                setTimeout(createEditor, 0)
            })
        },

        destroy() {
            this.editor?.destroy()
            this.editor = null
        },

        toggleBold() { this.editor?.chain().focus().toggleBold().run() },
        toggleItalic() { this.editor?.chain().focus().toggleItalic().run() },
        toggleUnderline() { this.editor?.chain().focus().toggleUnderline().run() },
        toggleStrike() { this.editor?.chain().focus().toggleStrike().run() },
        toggleHeading(level) { this.editor?.chain().focus().toggleHeading({ level }).run() },
        toggleBulletList() { this.editor?.chain().focus().toggleBulletList().run() },
        toggleOrderedList() { this.editor?.chain().focus().toggleOrderedList().run() },
        setTextAlign(align) { this.editor?.chain().focus().setTextAlign(align).run() },
        toggleBlockquote() { this.editor?.chain().focus().toggleBlockquote().run() },
        setHorizontalRule() { this.editor?.chain().focus().setHorizontalRule().run() },

        insertTable() {
            this.editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
        },
        addColumnAfter() { this.editor?.chain().focus().addColumnAfter().run() },
        addRowAfter() { this.editor?.chain().focus().addRowAfter().run() },
        deleteColumn() { this.editor?.chain().focus().deleteColumn().run() },
        deleteRow() { this.editor?.chain().focus().deleteRow().run() },
        deleteTable() { this.editor?.chain().focus().deleteTable().run() },

        isActive(name, attrs = {}) {
            return this.editor?.isActive(name, attrs) ?? false
        },
    }
}
