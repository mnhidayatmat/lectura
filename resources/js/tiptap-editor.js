import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import { Underline } from '@tiptap/extension-underline'
import { TextAlign } from '@tiptap/extension-text-align'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table-row'
import { TableCell } from '@tiptap/extension-table-cell'
import { TableHeader } from '@tiptap/extension-table-header'
import { Image } from '@tiptap/extension-image'

export default function tiptapEditor(initialContent = '') {
    return {
        editor: null,
        content: initialContent,
        uploading: false,

        init() {
            this._ensureEditor()

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
                        Image.configure({
                            inline: false,
                            allowBase64: true,
                        }),
                    ],
                    content: this.content,
                    editorProps: {
                        attributes: {
                            class: 'focus:outline-none min-h-[120px] px-3 py-2',
                        },
                    },
                    onUpdate: ({ editor }) => {
                        this.content = editor.getHTML()
                        if (this.$refs.hiddenInput) {
                            this.$refs.hiddenInput.value = this.content
                        }
                    },
                })

                // Use DOM events on the editor element for image paste/drop
                const editorDom = el.querySelector('.ProseMirror') || el
                editorDom.addEventListener('paste', (e) => this._handleImagePaste(e), true)
                editorDom.addEventListener('drop', (e) => this._handleImageDrop(e), true)

                return true
            } catch (e) {
                console.warn('Tiptap editor init failed, will retry on interaction:', e)
                return false
            }
        },

        _handleImagePaste(e) {
            const items = e.clipboardData?.items
            if (!items) return

            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    e.preventDefault()
                    e.stopImmediatePropagation()
                    const file = item.getAsFile()
                    if (file) this._uploadImage(file)
                    return
                }
            }
        },

        _handleImageDrop(e) {
            const files = e.dataTransfer?.files
            if (!files) return

            for (const file of files) {
                if (file.type.startsWith('image/')) {
                    e.preventDefault()
                    e.stopImmediatePropagation()
                    this._uploadImage(file)
                    return
                }
            }
        },

        async _uploadImage(file) {
            if (this.uploading) return
            this.uploading = true

            let src = null

            try {
                const formData = new FormData()
                formData.append('image', file)
                const token = document.querySelector('meta[name="csrf-token"]')?.content
                const response = await fetch('/editor/upload-image', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: formData,
                })

                if (!response.ok) throw new Error('Upload failed: ' + response.status)

                const data = await response.json()
                src = data.url
            } catch (e) {
                console.warn('Server upload failed, using base64:', e.message)
                src = await new Promise((resolve) => {
                    const reader = new FileReader()
                    reader.onload = () => resolve(reader.result)
                    reader.readAsDataURL(file)
                })
            }

            if (src && this.editor) {
                // Use setTimeout to ensure ProseMirror has fully finished any pending
                // event processing before we touch the editor state
                setTimeout(() => {
                    try {
                        this.editor.chain().focus().setImage({ src }).run()
                    } catch (e1) {
                        // If chain fails, fall back to setContent
                        try {
                            const html = this.editor.getHTML()
                            this.editor.commands.setContent(html + `<img src="${src}">`)
                        } catch (e2) {
                            // Last resort: inject into DOM directly
                            const proseMirror = this.$refs.editorContent?.querySelector('.ProseMirror')
                            if (proseMirror) {
                                const img = document.createElement('img')
                                img.src = src
                                proseMirror.appendChild(img)
                                // Sync content
                                this.content = this.editor.getHTML()
                                if (this.$refs.hiddenInput) {
                                    this.$refs.hiddenInput.value = this.content
                                }
                            }
                        }
                    }
                    this.uploading = false
                }, 50)
            } else {
                this.uploading = false
            }
        },

        insertImageFromFile() {
            this._ensureEditor()
            const input = document.createElement('input')
            input.type = 'file'
            input.accept = 'image/*'
            input.onchange = () => {
                const file = input.files?.[0]
                if (file) this._uploadImage(file)
            }
            input.click()
        },

        destroy() {
            this.editor?.destroy()
            this.editor = null
        },

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
