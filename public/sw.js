/*
 * Service worker AstoOne.
 * - Aset build (/build/*), ikon, gambar, dan font: cache dulu (cepat & hemat kuota).
 * - Halaman: selalu dari jaringan (data pribadi tidak disimpan di HP);
 *   bila sinyal hilang tampilkan halaman /offline.
 * Naikkan VERSION setiap mengubah file ini.
 */
const VERSION = 'astoone-v1';
const STATIC = `${VERSION}-static`;
const PRECACHE = ['/offline', '/manifest.webmanifest', '/icons/icon-192.png', '/images/plant-asto.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== location.origin) return;
    if (url.pathname.startsWith('/admin') || url.pathname.startsWith('/buka/') || url.pathname.startsWith('/kampanye/')) return;

    const isAsset = /^\/(build|icons|images|storage)\//.test(url.pathname) || /\.(woff2?|png|webp|jpg|svg|ico)$/.test(url.pathname);
    if (isAsset) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(STATIC).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline')));
    }
});
