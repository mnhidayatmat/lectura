import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import QRCode from 'qrcode';
import tiptapEditor from './tiptap-editor';

window.QRCode = QRCode;

// Register tiptapEditor on THE SAME Alpine instance that Livewire uses.
// This ensures x-data="tiptapEditor(...)" resolves correctly.
Alpine.data('tiptapEditor', tiptapEditor);

// Also expose on window for any inline x-data="tiptapEditor(...)" expressions
window.tiptapEditor = tiptapEditor;

Livewire.start();
