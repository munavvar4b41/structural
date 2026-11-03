/* Structural PWA service worker — enables installability. */
const CACHE_VERSION = 'structural-pwa-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(caches.open(CACHE_VERSION));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();

            await Promise.all(
                keys
                    .filter((key) => key !== CACHE_VERSION)
                    .map((key) => caches.delete(key)),
            );

            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Never cache Inertia/XHR document navigations or API calls.
    if (request.headers.get('X-Inertia') || url.pathname.startsWith('/api/')) {
        return;
    }

    event.respondWith(
        (async () => {
            try {
                return await fetch(request);
            } catch {
                const cached = await caches.match(request);

                if (cached) {
                    return cached;
                }

                throw new Error('Network unavailable');
            }
        })(),
    );
});
