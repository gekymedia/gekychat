// =======================================================
// GekyChat Service Worker — AUTH SAFE / PROD READY (FIXED)
// =======================================================

const CACHE_VERSION = 'gekychat-v8';

// ONLY cache files that NEVER redirect
const STATIC_ASSETS = [
  '/offline',
  '/manifest.json',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
];

// 🚨 AUTH / USER / ADMIN ROUTES — NEVER INTERCEPT
const PROTECTED_ROUTES = [
  '/',
  '/c',
  '/g',
  '/settings',
  '/profile',
  '/contacts',
  '/admin',
  '/login',
  '/verify',
];

// ---------------- ENV DETECTION ----------------
const isDevelopment =
  self.location.hostname === 'localhost' ||
  self.location.hostname === '127.0.0.1' ||
  self.location.hostname.includes('.local');

// ---------------- UTILITIES ----------------
const isHTML = (req) =>
  req.method === 'GET' &&
  (req.mode === 'navigate' ||
    (req.headers.get('accept') || '').includes('text/html'));

const isProtectedRoute = (url) =>
  PROTECTED_ROUTES.some((p) => url.pathname === p || url.pathname.startsWith(p + '/'));

const isStaticAsset = (url) =>
  url.pathname.startsWith('/build/') ||
  url.pathname.startsWith('/css/') ||
  url.pathname.startsWith('/js/') ||
  url.pathname.startsWith('/icons/') ||
  url.pathname.endsWith('.png') ||
  url.pathname.endsWith('.jpg') ||
  url.pathname.endsWith('.jpeg') ||
  url.pathname.endsWith('.webp') ||
  url.pathname.endsWith('.woff2');

// =======================================================
// INSTALL — SAFE (NO addAll, NO redirects)
// =======================================================
self.addEventListener('install', (event) => {
  self.skipWaiting();

  if (isDevelopment) return;

  event.waitUntil(
    (async () => {
      const cache = await caches.open(CACHE_VERSION);

      for (const url of STATIC_ASSETS) {
        try {
          const response = await fetch(url, { redirect: 'follow' });
          if (response.ok && response.type === 'basic') {
            await cache.put(url, response.clone());
          }
        } catch (e) {
          console.warn('[SW] Skipped caching:', url);
        }
      }
    })()
  );
});

// =======================================================
// ACTIVATE
// =======================================================
self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(
        keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))
      );
      await self.clients.claim();
    })()
  );
});

// =======================================================
// FETCH
// =======================================================
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // ❌ Never touch non-GET
  if (request.method !== 'GET') return;

  // ❌ NEVER intercept protected/auth routes (prevents redirect crash)
  if (isProtectedRoute(url)) {
    return; // Don't intercept protected routes at all
  }

  // ❌ Never intercept APIs / sockets
  if (
    url.pathname.startsWith('/api/') ||
    url.pathname.startsWith('/broadcasting') ||
    url.pathname.startsWith('/sanctum')
  ) {
    return;
  }

  // ---------------- HTML (PUBLIC ONLY) ----------------
  if (isHTML(request)) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Only cache clean public HTML (NO redirects)
          if (
            !isDevelopment &&
            response.ok &&
            response.status === 200 &&
            response.type === 'basic'
          ) {
            const clone = response.clone();
            caches.open(CACHE_VERSION).then((cache) => {
              cache.put(request, clone);
            });
          }
          return response;
        })
        .catch(async () => {
          const cache = await caches.open(CACHE_VERSION);
          return (await cache.match(request)) || cache.match('/offline');
        })
    );
    return;
  }

  // ---------------- STATIC ASSETS ----------------
  if (isStaticAsset(url)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;

        return fetch(request).then((response) => {
          if (!isDevelopment && response.ok) {
            const clone = response.clone();
            caches.open(CACHE_VERSION).then((cache) => {
              cache.put(request, clone);
            });
          }
          return response;
        });
      })
    );
  }
});

// =======================================================
// MESSAGE HANDLING
// =======================================================
self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

console.log(`[SW] GekyChat v8 loaded | ${isDevelopment ? 'DEV' : 'PROD'}`);
