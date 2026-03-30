import './bootstrap';

import QRCode from 'qrcode';
import tiptapEditor from './tiptap-editor';

window.QRCode = QRCode;
window.tiptapEditor = tiptapEditor;

// Register tiptapEditor on Livewire's Alpine instance (Livewire v4 bundles Alpine).
// Do NOT import Alpine separately — that creates a duplicate instance.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('tiptapEditor', tiptapEditor);
});
