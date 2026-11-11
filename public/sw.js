const CACHE_NAME = 'hsm-freelance-v2';
const OFFLINE_URL = '/offline';
const PRECACHE = [OFFLINE_URL, '/manifest.webmanifest', '/icons/icon-192.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Cache-First for static assets
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(req).then((cached) =>
                cached ||
                fetch(req).then((res) => {
                    const copy = res.clone();
                    caches.open(CACHE_NAME).then((c) => c.put(req, copy));
                    return res;
                })
            )
        );
        return;
    }

    // Network-First with offline fallback for HTML navigation
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(() => caches.match(OFFLINE_URL))
        );
    }
});

// Push Notification Event Listener
self.addEventListener('push', (event) => {
    let payload = {
        title: 'Tugas Baru — D&P PT. HSM',
        body: 'Anda menerima penugasan operasional baru.',
        url: '/pwa/workspace',
    };

    if (event.data) {
        try {
            payload = Object.assign(payload, event.data.json());
        } catch (e) {
            payload.body = event.data.text();
        }
    }

    const options = {
        body: payload.body,
        icon: '/icons/icon-192.svg',
        badge: '/icons/icon-192.svg',
        vibrate: [200, 100, 200],
        tag: 'hsm-task-' + Date.now(),
        renotify: true,
        data: { url: payload.url || '/pwa/workspace' },
    };

    event.waitUntil(self.registration.showNotification(payload.title, options));
});

// Notification Click Handler -> Buka Halaman Detail Tugas
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification.data?.url || '/pwa/workspace';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url.includes(targetUrl) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
