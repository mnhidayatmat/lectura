import React from 'react';
import { createRoot } from 'react-dom/client';
import Whiteboard from './Whiteboard.jsx';
import '@excalidraw/excalidraw/index.css';

const mount = document.getElementById('whiteboard-root');

if (mount) {
    const config = {
        boardId: Number(mount.dataset.boardId),
        title: mount.dataset.title || 'Untitled',
        initialScene: mount.dataset.scene ? JSON.parse(mount.dataset.scene) : null,
        userId: Number(mount.dataset.userId),
        userName: mount.dataset.userName || 'Anonymous',
        sceneUrl: mount.dataset.sceneUrl,
        csrfToken: mount.dataset.csrf,
        channel: mount.dataset.channel,
    };

    createRoot(mount).render(<Whiteboard {...config} />);
}
