// Service Worker for GekyChat PWA (Enhanced with Dev/Prod Detection)
const CACHE_VERSION = 'gekychat-v6';
const STATIC_ASSETS = [
  '/',
  '/css/app.css',
  '/js/app.js',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
  '/manifest.json',
  '/offline'
];

// Essential routes for the chat app
const ESSENTIAL_ROUTES = [
  '/login',
  '/register',
  '/chat',
  '/messages'
];

// ==================== ENVIRONMENT DETECTION ====================
const isDevelopment = 
  self.location.hostname === 'localhost' ||
  self.location.hostname === '127.0.0.1' ||
  self.location.hostname.includes('.local') ||
  self.location.port === '5173' || // Vite default
  self.location.port === '5174' || // Your Vite port
  self.location.port === '3000' || // Common dev port
  self.location.port === '8080';   // Common dev port

const isViteDevServer = (url) => {
  return (url.hostname === 'localhost' || url.hostname === '127.0.0.1') && 
         (url.port === '5173' || url.port === '5174' || url.port === '3000');
};

console.log(`[SW] Environment: ${isDevelopment ? 'DEVELOPMENT' : 'PRODUCTION'}`);
console.log(`[SW] Location: ${self.location.hostname}:${self.location.port}`);

// ==================== UTILITY FUNCTIONS ====================
const isHTML = (req) =>
  req.method === 'GET' &&
  (req.mode === 'navigate' ||
    (req.headers.get('accept') || '').includes('text/html'));

const sameOrigin = (url) => new URL(url).origin === self.location.origin;

const shouldCache = (url) => {
  const pathname = url.pathname;
  return (
    pathname.startsWith('/build/') ||
    pathname.startsWith('/icons/') ||
    pathname.startsWith('/css/') ||
    pathname.startsWith('/js/') ||
    pathname.startsWith('/storage/') || // Cache uploaded files
    pathname.endsWith('.png') ||
    pathname.endsWith('.jpg') ||
    pathname.endsWith('.jpeg') ||
    pathname.endsWith('.gif') ||
    pathname.endsWith('.webp') ||
    pathname.endsWith('.woff') ||
    pathname.endsWith('.woff2') ||
    pathname.endsWith('.ttf')
  );
};

// ==================== INSTALL EVENT ====================
self.addEventListener('install', (event) => {
  console.log(`[SW] Installing in ${isDevelopment ? 'DEV' : 'PROD'} mode...`);
  
  if (isDevelopment) {
    // In development, skip waiting immediately and don't cache
    console.log('[SW] DEV: Skipping waiting and caching');
    self.skipWaiting();
    return;
  }

  // PRODUCTION: Normal installation with caching
  event.waitUntil((async () => {
    try {
      const cache = await caches.open(CACHE_VERSION);
      
      // Cache essential static assets
      const assetsToCache = [...STATIC_ASSETS];
      const cachePromises = assetsToCache.map(async (url) => {
        try {
          await cache.add(new Request(url, { cache: 'reload' }));
          console.log('[SW] Cached:', url);
        } catch (err) {
          console.warn('[SW] Failed to cache:', url, err);
        }
      });
      
      await Promise.all(cachePromises);
      await self.skipWaiting();
      console.log('[SW] Production installation completed');
    } catch (error) {
      console.error('[SW] Installation failed:', error);
    }
  })());
});

// ==================== ACTIVATE EVENT ====================
self.addEventListener('activate', (event) => {
  console.log(`[SW] Activating in ${isDevelopment ? 'DEV' : 'PROD'} mode...`);
  
  if (isDevelopment) {
    // In development, claim clients immediately and don't clean caches
    event.waitUntil(self.clients.claim());
    console.log('[SW] DEV: Activation completed (no cache cleanup)');
    return;
  }

  // PRODUCTION: Clean old caches and claim clients
  event.waitUntil((async () => {
    try {
      // Clean up old caches
      const keys = await caches.keys();
      await Promise.all(
        keys.filter((k) => k !== CACHE_VERSION).map((k) => {
          console.log('[SW] Deleting old cache:', k);
          return caches.delete(k);
        })
      );
      
      // Claim clients immediately
      await self.clients.claim();
      console.log('[SW] Production activation completed');
    } catch (error) {
      console.error('[SW] Activation failed:', error);
    }
  })());
});

// ==================== FETCH EVENT ====================
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // 🚨 DEVELOPMENT: Bypass service worker for most requests
  if (isDevelopment) {
    // Critical: Bypass Vite dev server completely
    if (isViteDevServer(url)) {
      console.log('[SW] DEV: Bypassing Vite request:', request.url);
      return;
    }

    // Bypass hot-reload and development resources
    if (url.pathname.includes('@vite') || 
        url.pathname.includes('@react-refresh') ||
        url.pathname.includes('resources/js/') ||
        url.pathname.includes('resources/css/') ||
        url.pathname.includes('node_modules/')) {
      console.log('[SW] DEV: Bypassing development resource:', request.url);
      return;
    }

    // Optional: Bypass all API calls in development for easier debugging
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/broadcasting/')) {
      console.log('[SW] DEV: Bypassing API call:', request.url);
      return;
    }
  }

  // Skip non-GET requests in both environments
  if (request.method !== 'GET') return;

  // Bypass for sensitive/real-time endpoints (both environments)
  const bypassEndpoints = [
    '/broadcasting', '/reverb', '/sanctum', '/logout', 
    '/api/auth', '/socket.io', '/ws', '/_ignition',
    '/telescope', '/horizon', '/storage/logs',
    '/debug', '/__webpack'
  ];

  if (bypassEndpoints.some(ep => url.pathname.startsWith(ep)) ||
      url.protocol === 'ws:' || url.protocol === 'wss:' ||
      (request.headers.get('X-Requested-With') === 'XMLHttpRequest' && 
       request.headers.get('Accept')?.includes('application/json'))) {
    return;
  }

  // Handle different resource types with appropriate strategies
  if (isHTML(request)) {
    event.respondWith(handleHTMLRequest(request));
  } else if (shouldCache(url)) {
    event.respondWith(handleStaticAssets(request));
  } else if (sameOrigin(url.href)) {
    event.respondWith(handleSameOriginAPI(request));
  } else {
    event.respondWith(handleExternalResources(request));
  }
});

// ==================== STRATEGY: HTML REQUESTS ====================
async function handleHTMLRequest(request) {
  // In development, always fetch fresh HTML
  if (isDevelopment) {
    console.log('[SW] DEV: Fetching fresh HTML');
    return fetch(request);
  }

  // PRODUCTION: Network first with offline fallback
  const cache = await caches.open(CACHE_VERSION);
  
  try {
    // Network-first for HTML (always try to get fresh content)
    const networkResponse = await fetch(request, { 
      cache: 'no-store',
      headers: {
        'Cache-Control': 'no-cache'
      }
    });
    
    if (networkResponse.ok) {
      // Cache the successful response for offline use
      cache.put(request, networkResponse.clone());
      return networkResponse;
    }
    throw new Error('Network response not ok');
  } catch (error) {
    console.log('[SW] Network failed, trying cache for:', request.url);
    
    // Try to serve from cache
    const cachedResponse = await cache.match(request);
    
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Fallback to offline page
    const offlinePage = await cache.match('/offline');
    if (offlinePage) {
      return offlinePage;
    }
    
    // Ultimate fallback - serve basic offline page (from your original code)
    return new Response(
      `
      <!DOCTYPE html>
      <html>
        <head>
          <title>GekyChat - Offline</title>
          <style>
            body { 
              font-family: system-ui, sans-serif; 
              display: flex; 
              justify-content: center; 
              align-items: center; 
              height: 100vh; 
              margin: 0; 
              background: #f5f5f5; 
            }
            .offline-container { 
              text-align: center; 
              padding: 2rem; 
              background: white; 
              border-radius: 12px; 
              box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            }
          </style>
        </head>
        <body>
          <div class="offline-container">
            <h1>📡 Offline</h1>
            <p>You're currently offline. Please check your connection.</p>
            <button onclick="location.reload()">Retry</button>
          </div>
        </body>
      </html>
      `,
      { 
        headers: { 
          'Content-Type': 'text/html; charset=utf-8' 
        } 
      }
    );
  }
}

// ==================== STRATEGY: STATIC ASSETS ====================
async function handleStaticAssets(request) {
  // In development, always fetch fresh assets
  if (isDevelopment) {
    return fetch(request);
  }

  // PRODUCTION: Cache first with background update
  const cache = await caches.open(CACHE_VERSION);
  
  // Cache-first strategy for static assets
  const cachedResponse = await cache.match(request);
  
  if (cachedResponse) {
    // Update cache in background
    fetch(request)
      .then(response => {
        if (response.ok) {
          cache.put(request, response);
        }
      })
      .catch(() => {}); // Silent fail for background updates
    
    return cachedResponse;
  }
  
  // If not in cache, fetch from network
  try {
    const response = await fetch(request);
    if (response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.warn('[SW] Failed to fetch static asset:', request.url);
    // Return a placeholder for images (from your original code)
    if (request.url.match(/\.(png|jpg|jpeg|gif|webp)$/)) {
      return new Response(
        '<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="#f0f0f0"/><text x="50" y="50" font-family="Arial" font-size="10" text-anchor="middle" fill="#999">Image</text></svg>',
        { headers: { 'Content-Type': 'image/svg+xml' } }
      );
    }
    throw error;
  }
}

// ==================== STRATEGY: SAME-ORIGIN API ====================
async function handleSameOriginAPI(request) {
  // In development, always fetch fresh API data
  if (isDevelopment) {
    return fetch(request);
  }

  // PRODUCTION: Network first with cache fallback
  const cache = await caches.open(CACHE_VERSION);
  
  try {
    const response = await fetch(request);
    
    // Cache successful GET responses for essential data
    if (response.ok && request.method === 'GET') {
      // Only cache non-sensitive API data
      const url = new URL(request.url);
      if (!url.pathname.includes('/auth/') && !url.pathname.includes('/user/')) {
        cache.put(request, response.clone());
      }
    }
    
    return response;
  } catch (error) {
    console.log('[SW] API request failed, checking cache:', request.url);
    
    // For API failures, check cache for fallback data
    const cached = await cache.match(request);
    
    if (cached) {
      console.log('[SW] Serving cached API response');
      return cached;
    }
    
    // Return a meaningful error for API calls (from your original code)
    return new Response(
      JSON.stringify({ 
        error: 'You are offline', 
        offline: true,
        message: 'Please check your internet connection'
      }),
      { 
        status: 503,
        headers: { 
          'Content-Type': 'application/json',
          'X-Service-Worker': 'offline'
        }
      }
    );
  }
}

// ==================== STRATEGY: EXTERNAL RESOURCES ====================
async function handleExternalResources(request) {
  // In development, always fetch fresh external resources
  if (isDevelopment) {
    return fetch(request);
  }

  // PRODUCTION: Cache first strategy
  const cache = await caches.open(CACHE_VERSION);
  const cachedResponse = await cache.match(request);
  
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    const response = await fetch(request);
    if (response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.warn('[SW] Failed to fetch external resource:', request.url);
    
    // Return fallbacks for critical external resources (from your original code)
    if (request.url.includes('fonts.googleapis.com')) {
      return new Response('', { 
        status: 200,
        headers: { 'Content-Type': 'text/css' }
      });
    }
    
    if (request.url.includes('fonts.gstatic.com')) {
      return new Response('', { 
        status: 200,
        headers: { 'Content-Type': 'font/woff2' }
      });
    }
    
    throw error;
  }
}

// ==================== BACKGROUND SYNC (Production Only) ====================
self.addEventListener('sync', (event) => {
  if (isDevelopment) {
    console.log('[SW] DEV: Background sync ignored');
    return;
  }

  console.log('[SW] Background sync event:', event.tag);
  
  if (event.tag === 'background-sync-messages') {
    event.waitUntil(syncPendingMessages());
  } else if (event.tag === 'background-sync-api') {
    event.waitUntil(syncPendingAPIRequests());
  }
});

async function syncPendingMessages() {
  if (isDevelopment) return;
  
  try {
    // Get pending messages from IndexedDB
    const db = await openMessageDB();
    const pendingMessages = await getPendingMessages(db);
    
    for (const message of pendingMessages) {
      try {
        const response = await fetch('/api/messages', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': await getCSRFToken()
          },
          body: JSON.stringify(message)
        });
        
        if (response.ok) {
          await markMessageAsSent(db, message.id);
          console.log('[SW] Synced message:', message.id);
        }
      } catch (error) {
        console.warn('[SW] Failed to sync message:', message.id, error);
      }
    }
    
    console.log('[SW] Background message sync completed');
  } catch (error) {
    console.error('[SW] Background sync failed:', error);
  }
}

async function syncPendingAPIRequests() {
  if (isDevelopment) return;
  // Sync other pending API requests
  console.log('[SW] Syncing pending API requests...');
}

// ==================== PUSH NOTIFICATIONS (Production Only) ====================
self.addEventListener('push', (event) => {
  if (isDevelopment) {
    console.log('[SW] DEV: Push notification ignored');
    return;
  }

  console.log('[SW] Push notification received');
  
  if (!event.data) return;
  
  let data;
  try {
    data = event.data.json();
  } catch (error) {
    console.warn('[SW] Failed to parse push data:', error);
    data = {
      title: 'GekyChat',
      body: 'New message',
      icon: '/icons/icon-192x192.png'
    };
  }
  
  const options = {
    body: data.body || 'You have a new message',
    icon: data.icon || '/icons/icon-192x192.png',
    badge: '/icons/badge-72x72.png',
    image: data.image,
    vibrate: [100, 50, 100],
    data: {
      url: data.url || '/',
      messageId: data.messageId,
      chatId: data.chatId,
      timestamp: data.timestamp || Date.now()
    },
    actions: [
      {
        action: 'open',
        title: '💬 Open Chat'
      },
      {
        action: 'dismiss',
        title: '❌ Dismiss'
      }
    ],
    tag: data.chatId || 'gekychat-notification',
    renotify: true,
    requireInteraction: true
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title || 'GekyChat', options)
  );
});

self.addEventListener('notificationclick', (event) => {
  if (isDevelopment) {
    console.log('[SW] DEV: Notification click ignored');
    return;
  }

  console.log('[SW] Notification clicked:', event.action);
  event.notification.close();
  
  const notificationData = event.notification.data;
  
  if (event.action === 'open' || !event.action) {
    event.waitUntil(
      clients.matchAll({ 
        type: 'window',
        includeUncontrolled: true 
      }).then((clientList) => {
        // Focus on existing chat window if open
        for (const client of clientList) {
          if (client.url.includes('/chat') && 'focus' in client) {
            client.postMessage({
              type: 'NOTIFICATION_CLICK',
              data: notificationData
            });
            return client.focus();
          }
        }
        
        // Open new window to the chat
        if (clients.openWindow) {
          return clients.openWindow(notificationData.url || '/chat');
        }
      })
    );
  }
  
  // Handle dismiss action
  if (event.action === 'dismiss') {
    console.log('[SW] Notification dismissed');
  }
});

self.addEventListener('notificationclose', (event) => {
  if (isDevelopment) return;
  console.log('[SW] Notification closed');
});

// ==================== MESSAGE HANDLING ====================
self.addEventListener('message', (event) => {
  console.log('[SW] Message received from client:', event.data);
  
  const { type, data } = event.data;
  
  switch (type) {
    case 'SKIP_WAITING':
      self.skipWaiting();
      break;
      
    case 'GET_CACHE_STATUS':
      event.ports[0]?.postMessage({
        cacheVersion: CACHE_VERSION,
        isOnline: navigator.onLine,
        isDevelopment: isDevelopment
      });
      break;
      
    case 'CACHE_API_RESPONSE':
      if (!isDevelopment) {
        cacheAPIResponse(data.key, data.response);
      }
      break;
      
    case 'CLEAR_CACHE':
      clearOldCaches();
      break;

    case 'GET_ENVIRONMENT':
      event.ports[0]?.postMessage({
        isDevelopment,
        cacheVersion: CACHE_VERSION,
        isOnline: navigator.onLine
      });
      break;

    case 'FORCE_DEVELOPMENT_MODE':
      console.log('[SW] Development mode forced by client');
      break;
  }
});

// ==================== HELPER FUNCTIONS (From Your Original) ====================
async function openMessageDB() {
  // This would open your IndexedDB for offline messages
  return new Promise((resolve) => {
    // Mock implementation - replace with actual IndexedDB logic
    resolve({});
  });
}

async function getPendingMessages(db) {
  // Get messages pending sync from IndexedDB
  return [];
}

async function markMessageAsSent(db, messageId) {
  // Mark message as successfully synced
  console.log('[SW] Marking message as sent:', messageId);
}

async function getCSRFToken() {
  if (isDevelopment) return '';
  
  const cache = await caches.open(CACHE_VERSION);
  const response = await cache.match('/');
  if (response) {
    const html = await response.text();
    const match = html.match(/name="csrf-token" content="([^"]*)"/);
    return match ? match[1] : '';
  }
  return '';
}

async function cacheAPIResponse(key, response) {
  if (isDevelopment) return;
  
  try {
    const cache = await caches.open(CACHE_VERSION);
    await cache.put(new Request(`/api/cache/${key}`), new Response(JSON.stringify(response)));
  } catch (error) {
    console.warn('[SW] Failed to cache API response:', error);
  }
}

async function clearOldCaches() {
  const keys = await caches.keys();
  await Promise.all(keys.map(key => caches.delete(key)));
  console.log('[SW] All caches cleared');
}

// ==================== PERIODIC SYNC (Production Only) ====================
self.addEventListener('periodicsync', (event) => {
  if (isDevelopment) {
    console.log('[SW] DEV: Periodic sync ignored');
    return;
  }

  if (event.tag === 'cache-cleanup') {
    event.waitUntil(cleanupOldCacheEntries());
  }
});

async function cleanupOldCacheEntries() {
  if (isDevelopment) return;
  
  // Clean up cache entries older than 7 days
  const cache = await caches.open(CACHE_VERSION);
  const requests = await cache.keys();
  const weekAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
  
  for (const request of requests) {
    const response = await cache.match(request);
    if (response) {
      const dateHeader = response.headers.get('date');
      if (dateHeader && new Date(dateHeader).getTime() < weekAgo) {
        await cache.delete(request);
      }
    }
  }
}

console.log(`[SW] GekyChat Service Worker loaded in ${isDevelopment ? 'DEVELOPMENT' : 'PRODUCTION'} mode`);