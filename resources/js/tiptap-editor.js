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
            // Defer all setup to the next tick so the browser has rendered the
            // element before ProseMirror attaches.  This is critical when the
            // editor lives inside x-if — Alpine calls init() synchronously
            // before the element has layout, so new Editor() fails silently
            // inside _ensureEditor's try/catch, leaving this.editor = null and
            // making every toolbar button a no-op via optional chaining.
            this.$nextTick(() => {
                // Attach paste/drop listeners on the WRAPPER div (capture phase)
                // BEFORE the editor is created, so we intercept images before
                // ProseMirror ever sees the event.
                const wrapper = this.$refs.editorContent
                if (wrapper) {
                    wrapper.addEventListener('paste', (e) => {
                        const items = e.clipboardData?.items
                        if (!items) return
                        for (const item of items) {
                            if (item.type.startsWith('image/')) {
                                e.preventDefault()
                                e.stopImmediatePropagation()
                                const file = item.getAsFile()
                                if (file) this._uploadAndInsert(file)
                                return
                            }
                        }
                    }, true) // capture phase = runs before ProseMirror

                    wrapper.addEventListener('drop', (e) => {
                        const files = e.dataTransfer?.files
                        if (!files) return
                        for (const file of files) {
                            if (file.type.startsWith('image/')) {
                                e.preventDefault()
                                e.stopImmediatePropagation()
                                this._uploadAndInsert(file)
                                return
                            }
                        }
                    }, true)
                }

                this._ensureEditor()
            })

            // Form submit listener can be attached immediately — this.$el is
            // in the DOM regardless of x-if timing.
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

                return true
            } catch (e) {
                console.warn('Tiptap editor init failed:', e)
                return false
            }
        },

        async _uploadAndInsert(file) {
            if (this.uploading) return
            this.uploading = true

            // ── DO NOT call chain()/dispatch() before OR after the await ──
            // ProseMirror validates transaction lineage: any dispatch after an
            // async gap (or after focus-triggered state updates) throws
            // "mismatched transaction". The only safe path is:
            //   1. Read current HTML (no state mutation)
            //   2. Await the upload
            //   3. Destroy + recreate the editor with the new HTML
            const htmlBefore = this.editor ? this.editor.getHTML() : this.content

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

            if (src) {
                const newHtml = htmlBefore + `<img src="${src}">`

                // Tear down the current editor (no dispatch, just destroy).
                if (this.editor) {
                    this.editor.destroy()
                    this.editor = null
                }

                this.content = newHtml
                if (this.$refs.hiddenInput) {
                    this.$refs.hiddenInput.value = newHtml
                }

                // $nextTick ensures the DOM has settled before ProseMirror
                // attaches to the element again.
                this.$nextTick(() => this._ensureEditor())
            }

            this.uploading = false
        },

        insertImageFromFile() {
            this._ensureEditor()
            const input = document.createElement('input')
            input.type = 'file'
            input.accept = 'image/*'
            input.onchange = () => {
                const file = input.files?.[0]
                if (file) this._uploadAndInsert(file)
            }
            input.click()
        },

        destroy() {
            this.editor?.destroy()
            this.editor = null
        },

        toggleBold()        { this._ensureEditor(); this.editor?.chain().focus().toggleBold().run() },
        toggleItalic()      { this._ensureEditor(); this.editor?.chain().focus().toggleItalic().run() },
        toggleUnderline()   { this._ensureEditor(); this.editor?.chain().focus().toggleUnderline().run() },
        toggleStrike()      { this._ensureEditor(); this.editor?.chain().focus().toggleStrike().run() },
        toggleHeading(level){ this._ensureEditor(); this.editor?.chain().focus().toggleHeading({ level }).run() },
        toggleBulletList()  { this._ensureEditor(); this.editor?.chain().focus().toggleBulletList().run() },
        toggleOrderedList() { this._ensureEditor(); this.editor?.chain().focus().toggleOrderedList().run() },
        setTextAlign(align) { this._ensureEditor(); this.editor?.chain().focus().setTextAlign(align).run() },
        toggleBlockquote()  { this._ensureEditor(); this.editor?.chain().focus().toggleBlockquote().run() },
        setHorizontalRule() { this._ensureEditor(); this.editor?.chain().focus().setHorizontalRule().run() },

        insertTable() {
            this._ensureEditor()
            this.editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
        },
        addColumnAfter() { this._ensureEditor(); this.editor?.chain().focus().addColumnAfter().run() },
        addRowAfter()    { this._ensureEditor(); this.editor?.chain().focus().addRowAfter().run() },
        deleteColumn()   { this._ensureEditor(); this.editor?.chain().focus().deleteColumn().run() },
        deleteRow()      { this._ensureEditor(); this.editor?.chain().focus().deleteRow().run() },
        deleteTable()    { this._ensureEditor(); this.editor?.chain().focus().deleteTable().run() },

        isActive(name, attrs = {}) {
            return this.editor?.isActive(name, attrs) ?? false
        },
    }
}
