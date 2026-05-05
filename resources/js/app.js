import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import QRCode from 'qrcode';
import tiptapEditor from './tiptap-editor';
import { mathModal } from './tiptap-math';
import groupChat from './group-chat';

window.QRCode = QRCode;

// Lazy proxy for the scan-to-PDF helpers — jspdf is heavy (~150 kB) and
// only used on Manual Score Entry, so we keep it out of the main bundle
// and let Vite emit a separate chunk fetched the first time a lecturer
// taps "Scan".
let scanModulePromise = null;
const loadScan = () => (scanModulePromise ??= import('./scan-to-pdf'));
window.LecturaScan = {
    imageFromFile: async (f) => (await loadScan()).imageFromFile(f),
    buildPdf: async (pages) => (await loadScan()).buildPdf(pages),
};

// Register Alpine components on THE SAME Alpine instance that Livewire uses.
// This ensures x-data="componentName(...)" resolves correctly.
Alpine.data('tiptapEditor', tiptapEditor);
Alpine.data('mathModal', mathModal);
Alpine.data('groupChat', groupChat);

// Also expose on window for any inline x-data expressions
window.tiptapEditor = tiptapEditor;
window.mathModal = mathModal;
window.groupChat = groupChat;

Livewire.start();

// Register Service Worker for PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
