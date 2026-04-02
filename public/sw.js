const CACHE_NAME = 'lectura-v1';
const OFFLINE_URL = '/offline.html';

// Assets to pre-cache on install
const PRE_CACHE = [
    '/',
    OFFLINE_URL,
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRE_CACHE))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Skip non-GET and cross-origin requests
    if (request.method !== 'GET' || !request.url.startsWith(self.location.origin)) {
        return;
    }

    // Network-first for navigation (HTML pages)
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    // Cache-first for static assets (icons, CSS, JS, fonts)
    if (request.destination === 'image' || request.destination === 'style' ||
        request.destination === 'script' || request.destination === 'font') {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Network-first for everything else
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});
