import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Excalidraw } from '@excalidraw/excalidraw';

/**
 * Generate a stable per-tab source id so we can ignore broadcasts we caused
 * ourselves and avoid feedback loops.
 */
function makeSourceId() {
    return Math.random().toString(36).slice(2) + Date.now().toString(36);
}

/**
 * Strip volatile fields from appState before persisting/broadcasting — they
 * are local-only (cursor, current tool, viewport zoom, etc.) and would create
 * pointless churn if synced.
 */
function sanitizeAppState(appState) {
    if (!appState) return null;
    const {
        collaborators, // local-only
        cursorButton,
        editingElement,
        draggingElement,
        resizingElement,
        selectedElementIds,
        selectionElement,
        ...rest
    } = appState;
    return rest;
}

export default function Whiteboard({
    boardId,
    title,
    initialScene,
    userName,
    sceneUrl,
    csrfToken,
    channel,
}) {
    const [api, setApi] = useState(null);
    const sourceIdRef = useRef(makeSourceId());
    const saveTimerRef = useRef(null);
    const lastSentRef = useRef(0);
    const remoteApplyingRef = useRef(false);
    const [status, setStatus] = useState('idle'); // idle | saving | saved | error

    const initialData = useMemo(() => {
        if (!initialScene) return undefined;
        return {
            elements: initialScene.elements || [],
            appState: {
                ...(initialScene.appState || {}),
                collaborators: new Map(),
                viewBackgroundColor: initialScene?.appState?.viewBackgroundColor || '#ffffff',
            },
            scrollToContent: true,
        };
    }, [initialScene]);

    // -------------------------------------------------------------- save

    const persistScene = useCallback(
        async (elements, appState) => {
            try {
                setStatus('saving');
                const res = await fetch(sceneUrl, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        elements,
                        appState: sanitizeAppState(appState),
                        sourceId: sourceIdRef.current,
                    }),
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                lastSentRef.current = data.version;
                setStatus('saved');
            } catch (err) {
                console.error('[whiteboard] save failed', err);
                setStatus('error');
            }
        },
        [csrfToken, sceneUrl]
    );

    const handleChange = useCallback(
        (elements, appState) => {
            // Suppress the change handler when we're applying a remote update,
            // otherwise we'd echo it back and create an infinite loop.
            if (remoteApplyingRef.current) return;

            if (saveTimerRef.current) clearTimeout(saveTimerRef.current);
            saveTimerRef.current = setTimeout(() => {
                persistScene(elements, appState);
            }, 600);
        },
        [persistScene]
    );

    // -------------------------------------------------------------- realtime

    useEffect(() => {
        if (!api || !window.Echo) return;

        const ch = window.Echo.private(channel);

        ch.listen('.scene.updated', (payload) => {
            if (!payload || payload.sourceId === sourceIdRef.current) return;
            if (payload.version <= lastSentRef.current) return;

            remoteApplyingRef.current = true;
            try {
                api.updateScene({
                    elements: payload.elements,
                    // Avoid clobbering the local user's tool/zoom; only sync
                    // canvas-affecting bits.
                    appState: payload.appState
                        ? {
                              viewBackgroundColor: payload.appState.viewBackgroundColor,
                          }
                        : undefined,
                });
                lastSentRef.current = payload.version;
            } finally {
                // Defer re-enabling so the resulting onChange doesn't re-trigger save
                setTimeout(() => {
                    remoteApplyingRef.current = false;
                }, 0);
            }
        });

        return () => {
            try {
                window.Echo.leave(channel);
            } catch (e) {
                /* ignore */
            }
        };
    }, [api, channel]);

    // -------------------------------------------------------------- render

    const statusLabel =
        status === 'saving'
            ? 'Saving…'
            : status === 'saved'
              ? 'All changes saved'
              : status === 'error'
                ? 'Save failed — retrying on next change'
                : 'Ready';

    return (
        <div className="flex h-screen flex-col bg-slate-50">
            <header className="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-2">
                <div className="flex items-center gap-3">
                    <a
                        href={document.referrer || '/'}
                        className="text-slate-500 hover:text-slate-900"
                        title="Back"
                    >
                        ←
                    </a>
                    <h1 className="text-sm font-semibold text-slate-900">{title}</h1>
                    <span
                        className={
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs ' +
                            (status === 'error'
                                ? 'bg-red-50 text-red-700'
                                : status === 'saving'
                                  ? 'bg-amber-50 text-amber-700'
                                  : 'bg-emerald-50 text-emerald-700')
                        }
                    >
                        ● {statusLabel}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-xs text-slate-500">You: {userName}</span>
                </div>
            </header>
            <div className="flex-1">
                <Excalidraw
                    excalidrawAPI={(instance) => setApi(instance)}
                    initialData={initialData}
                    onChange={handleChange}
                    UIOptions={{
                        canvasActions: {
                            saveToActiveFile: false,
                            loadScene: false,
                        },
                    }}
                />
            </div>
        </div>
    );
}
