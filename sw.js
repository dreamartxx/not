// Public offline resources only. Never store authenticated HTML, API responses or media.
const CACHE = 'not-public-pwa-v3';
const BASE = new URL('./', self.location.href);
const PUBLIC_FILES = ['offline.html', 'assets/offline.css', 'assets/offline.js', 'assets/icon-3d-192.png'];
const publicURLs = new Set(PUBLIC_FILES.map(path => new URL(path, BASE).href));
self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll([...publicURLs])));
});
self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) {
      if (key.startsWith('not-public-pwa-') && key !== CACHE) await caches.delete(key);
    }
    await self.clients.claim();
  })());
});
self.addEventListener('message', event => {
  if (event.data?.type === 'ACTIVATE_UPDATE') event.waitUntil(self.skipWaiting());
});
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== BASE.origin) return;
  // This allowlist deliberately excludes api.php (including file navigations).
  const isAppNavigation = request.mode === 'navigate' &&
    [BASE.pathname, new URL('index.php', BASE).pathname].includes(url.pathname);
  if (isAppNavigation) {
    event.respondWith(fetch(request).catch(async () =>
      (await caches.open(CACHE)).match(new URL('offline.html', BASE).href)));
  } else if (publicURLs.has(url.href)) {
    event.respondWith((async () => {
      const cached = await (await caches.open(CACHE)).match(request);
      return cached || fetch(request);
    })());
  }
});
