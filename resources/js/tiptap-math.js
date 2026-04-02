import { Node, mergeAttributes } from '@tiptap/core'
import katex from 'katex'
import 'katex/dist/katex.min.css'

/**
 * Inline math node — rendered with KaTeX.
 * Stored as <span data-math="LaTeX">rendered</span>.
 */
export const MathInline = Node.create({
    name: 'mathInline',
    group: 'inline',
    inline: true,
    atom: true,

    addAttributes() {
        return {
            latex: { default: '' },
        }
    },

    parseHTML() {
        return [{ tag: 'span[data-math-inline]' }]
    },

    renderHTML({ node, HTMLAttributes }) {
        let rendered = ''
        try {
            rendered = katex.renderToString(node.attrs.latex, { throwOnError: false, displayMode: false })
        } catch { rendered = node.attrs.latex }

        return ['span', mergeAttributes(HTMLAttributes, {
            'data-math-inline': '',
            'data-latex': node.attrs.latex,
            class: 'math-inline',
            contenteditable: 'false',
        }), ['span', { innerHTML: rendered }]]
    },

    addNodeView() {
        return ({ node }) => {
            const dom = document.createElement('span')
            dom.classList.add('math-inline')
            dom.setAttribute('data-math-inline', '')
            dom.setAttribute('data-latex', node.attrs.latex)
            dom.contentEditable = 'false'
            try {
                dom.innerHTML = katex.renderToString(node.attrs.latex, { throwOnError: false, displayMode: false })
            } catch { dom.textContent = node.attrs.latex }
            return { dom }
        }
    },
})

/**
 * Block math node — displayed equation.
 * Stored as <div data-math-block>rendered</div>.
 */
export const MathBlock = Node.create({
    name: 'mathBlock',
    group: 'block',
    atom: true,

    addAttributes() {
        return {
            latex: { default: '' },
        }
    },

    parseHTML() {
        return [{ tag: 'div[data-math-block]' }]
    },

    renderHTML({ node, HTMLAttributes }) {
        let rendered = ''
        try {
            rendered = katex.renderToString(node.attrs.latex, { throwOnError: false, displayMode: true })
        } catch { rendered = node.attrs.latex }

        return ['div', mergeAttributes(HTMLAttributes, {
            'data-math-block': '',
            'data-latex': node.attrs.latex,
            class: 'math-block',
            contenteditable: 'false',
        }), ['div', { innerHTML: rendered }]]
    },

    addNodeView() {
        return ({ node }) => {
            const dom = document.createElement('div')
            dom.classList.add('math-block')
            dom.setAttribute('data-math-block', '')
            dom.setAttribute('data-latex', node.attrs.latex)
            dom.contentEditable = 'false'
            try {
                dom.innerHTML = katex.renderToString(node.attrs.latex, { throwOnError: false, displayMode: true })
            } catch { dom.textContent = node.attrs.latex }
            return { dom }
        }
    },
})

/**
 * Alpine component for the math formula modal.
 */
export function mathModal() {
    return {
        show: false,
        latex: '',
        displayMode: false,
        preview: '',
        callback: null,

        open(cb, initial = '', block = false) {
            this.callback = cb
            this.latex = initial
            this.displayMode = block
            this.updatePreview()
            this.show = true
            this.$nextTick(() => this.$refs.latexInput?.focus())
        },

        close() {
            this.show = false
            this.latex = ''
            this.preview = ''
            this.callback = null
        },

        updatePreview() {
            if (!this.latex.trim()) {
                this.preview = '<span class="text-slate-400 text-sm italic">Type a formula above...</span>'
                return
            }
            try {
                this.preview = katex.renderToString(this.latex, {
                    throwOnError: false,
                    displayMode: this.displayMode,
                })
            } catch (e) {
                this.preview = `<span class="text-red-500 text-sm">${e.message}</span>`
            }
        },

        insert() {
            if (this.latex.trim() && this.callback) {
                this.callback(this.latex.trim(), this.displayMode)
            }
            this.close()
        },

        insertTemplate(tmpl) {
            const input = this.$refs.latexInput
            if (!input) { this.latex = tmpl; this.updatePreview(); return }

            const start = input.selectionStart
            const end = input.selectionEnd
            const before = this.latex.substring(0, start)
            const after = this.latex.substring(end)
            this.latex = before + tmpl + after
            this.updatePreview()

            this.$nextTick(() => {
                const cursorPos = start + tmpl.length
                input.focus()
                input.setSelectionRange(cursorPos, cursorPos)
            })
        },

        templates: [
            { label: 'a/b', latex: '\\frac{a}{b}', title: 'Fraction' },
            { label: 'x²', latex: 'x^{2}', title: 'Superscript' },
            { label: 'xₙ', latex: 'x_{n}', title: 'Subscript' },
            { label: '√', latex: '\\sqrt{x}', title: 'Square root' },
            { label: '∛', latex: '\\sqrt[3]{x}', title: 'Cube root' },
            { label: '±', latex: '\\pm', title: 'Plus-minus' },
            { label: '×', latex: '\\times', title: 'Times' },
            { label: '÷', latex: '\\div', title: 'Division' },
            { label: '≠', latex: '\\neq', title: 'Not equal' },
            { label: '≤', latex: '\\leq', title: 'Less or equal' },
            { label: '≥', latex: '\\geq', title: 'Greater or equal' },
            { label: '≈', latex: '\\approx', title: 'Approximately' },
            { label: '∞', latex: '\\infty', title: 'Infinity' },
            { label: 'Σ', latex: '\\sum_{i=1}^{n}', title: 'Summation' },
            { label: '∏', latex: '\\prod_{i=1}^{n}', title: 'Product' },
            { label: '∫', latex: '\\int_{a}^{b}', title: 'Integral' },
            { label: 'lim', latex: '\\lim_{x \\to \\infty}', title: 'Limit' },
            { label: 'dx/dy', latex: '\\frac{dx}{dy}', title: 'Derivative' },
            { label: '∂', latex: '\\frac{\\partial f}{\\partial x}', title: 'Partial derivative' },
            { label: 'sin', latex: '\\sin(\\theta)', title: 'Sine' },
            { label: 'cos', latex: '\\cos(\\theta)', title: 'Cosine' },
            { label: 'tan', latex: '\\tan(\\theta)', title: 'Tangent' },
            { label: 'log', latex: '\\log_{b}(x)', title: 'Logarithm' },
            { label: 'ln', latex: '\\ln(x)', title: 'Natural log' },
            { label: 'eˣ', latex: 'e^{x}', title: 'Exponential' },
            { label: '|x|', latex: '\\left| x \\right|', title: 'Absolute value' },
            { label: '()', latex: '\\left( \\right)', title: 'Parentheses' },
            { label: '[]', latex: '\\left[ \\right]', title: 'Brackets' },
            { label: 'π', latex: '\\pi', title: 'Pi' },
            { label: 'α', latex: '\\alpha', title: 'Alpha' },
            { label: 'β', latex: '\\beta', title: 'Beta' },
            { label: 'θ', latex: '\\theta', title: 'Theta' },
            { label: 'Δ', latex: '\\Delta', title: 'Delta' },
            { label: 'λ', latex: '\\lambda', title: 'Lambda' },
            { label: 'μ', latex: '\\mu', title: 'Mu' },
            { label: 'σ', latex: '\\sigma', title: 'Sigma' },
            { label: 'ω', latex: '\\omega', title: 'Omega' },
            { label: '→', latex: '\\rightarrow', title: 'Right arrow' },
            { label: '⇒', latex: '\\Rightarrow', title: 'Implies' },
            { label: 'mat', latex: '\\begin{bmatrix} a & b \\\\ c & d \\end{bmatrix}', title: 'Matrix' },
            { label: 'vec', latex: '\\vec{v}', title: 'Vector' },
            { label: '∈', latex: '\\in', title: 'Element of' },
            { label: '∉', latex: '\\notin', title: 'Not element of' },
            { label: '∪', latex: '\\cup', title: 'Union' },
            { label: '∩', latex: '\\cap', title: 'Intersection' },
        ],
    }
}
