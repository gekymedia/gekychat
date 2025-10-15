// Service Worker for GekyChat PWA (safe + resilient)
const CACHE_VERSION = 'gekychat-v4';
const STATIC_ASSETS = [
  '/',                  // your home page
  '/offline',           // make sure this route/view exists, or remove it
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
];

// Utility guards
const isHTML = (req) =>
  req.method === 'GET' &&
  (req.mode === 'navigate' ||
    (req.headers.get('accept') || '').includes('text/html'));

const sameOrigin = (url) => new URL(url).origin === self.location.origin;

// Install: pre-cache minimal, but never fail on missing file
self.addEventListener('install', (event) => {
  console.log('[SW] installing…');
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_VERSION);
    for (const url of STATIC_ASSETS) {
      try {
        // cache: 'reload' avoids cached 404s during updates
        await cache.add(new Request(url, { cache: 'reload' }));
      } catch (e) {
        // Don’t break install if one URL fails
        console.warn('[SW] skip precache', url, e);
      }
    }
    await self.skipWaiting();
  })());
});

// Activate: clean old caches, take control
self.addEventListener('activate', (event) => {
  console.log('[SW] activating…');
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(
      keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))
    );
    await self.clients.claim();
  })());
});

// Fetch strategies:
// - HTML (pages): network-first with offline fallback
// - Static (icons/build/css/js): stale-while-revalidate
// - Bypass auth/sockets & non-GET
self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Only handle GET
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Skip auth endpoints and anything that shouldn't be cached
  if (
    url.pathname.startsWith('/broadcasting') ||
    url.pathname.startsWith('/reverb') ||
    url.pathname.startsWith('/sanctum') ||
    url.protocol === 'ws:' || url.protocol === 'wss:'
  ) {
    return; // let the network handle it
  }

  // Pages: network-first (stay live)
  if (isHTML(req)) {
    event.respondWith((async () => {
      try {
        const fresh = await fetch(req, { cache: 'no-store' });
        // Optionally cache a copy for offline
        const cache = await caches.open(CACHE_VERSION);
        cache.put(req, fresh.clone());
        return fresh;
      } catch {
        const cache = await caches.open(CACHE_VERSION);
        return (await cache.match(req)) ||
               (await cache.match('/offline')) ||
               Response.redirect('/', 302);
      }
    })());
    return;
  }

  // Same-origin static assets: stale-while-revalidate
  if (sameOrigin(url.href) && (
      url.pathname.startsWith('/build/') ||
      url.pathname.startsWith('/icons/') ||
      url.pathname.startsWith('/css/') ||
      url.pathname.startsWith('/js/')
    )) {
    event.respondWith((async () => {
      const cache = await caches.open(CACHE_VERSION);
      const cached = await cache.match(req);
      const networkPromise = fetch(req).then((res) => {
        // Cache successful (basic/opaque ok for CDNs)
        try { cache.put(req, res.clone()); } catch (_) {}
        return res;
      }).catch(() => null);
      return cached || (await networkPromise) || fetch(req);
    })());
    return;
  }

  // Default: try cache, then network
  event.respondWith((async () => {
    const cache = await caches.open(CACHE_VERSION);
    const cached = await cache.match(req);
    return cached || fetch(req).then((res) => {
      try { cache.put(req, res.clone()); } catch (_) {}
      return res;
    });
  })());
});
