import './bootstrap';

import Alpine from 'alpinejs';
import QRCode from 'qrcode';
import tiptapEditor from './tiptap-editor';

window.Alpine = Alpine;
window.QRCode = QRCode;
window.tiptapEditor = tiptapEditor;

Alpine.start();
