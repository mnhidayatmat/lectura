import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import QRCode from 'qrcode';
import tiptapEditor from './tiptap-editor';
import groupChat from './group-chat';

window.QRCode = QRCode;

// Register Alpine components on THE SAME Alpine instance that Livewire uses.
// This ensures x-data="componentName(...)" resolves correctly.
Alpine.data('tiptapEditor', tiptapEditor);
Alpine.data('groupChat', groupChat);

// Also expose on window for any inline x-data expressions
window.tiptapEditor = tiptapEditor;
window.groupChat = groupChat;

Livewire.start();
