/*
 * Service worker for the staff Sell screen – lets the page open with no internet.
 * Pages: network first, fall back to the cached copy. Built assets: cache first.
 * Sales themselves are queued by staff-pos.js, not here.
 */
const CACHE = 'nuttyland-staff-v1';
const OFFLINE_PAGES = ['/staff/sell'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll(['/js/staff-pos.js?v=1', '/favicon.svg'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== location.origin) return;

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/js/') || url.pathname === '/favicon.svg') {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy));
            return res;
        })));
        return;
    }

    if (req.mode === 'navigate' && OFFLINE_PAGES.includes(url.pathname)) {
        event.respondWith(fetch(req).then((res) => {
            if (res.ok && !res.redirected) {
                const copy = res.clone();
                caches.open(CACHE).then((c) => c.put(url.pathname, copy));
            }
            return res;
        }).catch(() => caches.match(url.pathname)));
    }
});
