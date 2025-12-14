// =======================================================
// GekyChat Service Worker — AUTH SAFE / PROD READY
// =======================================================

const CACHE_VERSION = 'gekychat-v7';

// Only static assets are cached
const STATIC_ASSETS = [
  '/',
  '/offline',
  '/manifest.json',
  '/css/app.css',
  '/js/app.js',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
];

// 🚨 AUTH-PROTECTED ROUTES (NEVER INTERCEPT)
const PROTECTED_ROUTES = [
  '/c',
  '/g',
  '/settings',
  '/profile',
  '/contacts',
  '/admin',
];

// ---------------- ENV DETECTION ----------------
const isDevelopment =
  self.location.hostname === 'localhost' ||
  self.location.hostname === '127.0.0.1' ||
  self.location.hostname.includes('.local') ||
  self.location.port;

// ---------------- UTILITIES ----------------
const isHTML = (req) =>
  req.method === 'GET' &&
  (req.mode === 'navigate' ||
    (req.headers.get('accept') || '').includes('text/html'));

const isProtectedRoute = (url) =>
  PROTECTED_ROUTES.some((p) => url.pathname.startsWith(p));

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
// INSTALL
// =======================================================
self.addEventListener('install', (event) => {
  if (isDevelopment) {
    self.skipWaiting();
    return;
  }

  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    })
  );
});


// =======================================================
// ACTIVATE
// =======================================================
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((k) => k !== CACHE_VERSION)
          .map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
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

  // ❌ NEVER intercept protected/auth pages
  if (request.mode === 'navigate' && isProtectedRoute(url)) {
    return;
  }

  // ❌ Never intercept API calls
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/broadcasting')) {
    return;
  }

  // ---------------- HTML (PUBLIC ONLY) ----------------
  if (isHTML(request)) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Cache ONLY valid public HTML
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
          const cached = await cache.match(request);
          return cached || cache.match('/offline');
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

console.log(`[SW] GekyChat v7 loaded | ${isDevelopment ? 'DEV' : 'PROD'}`);
