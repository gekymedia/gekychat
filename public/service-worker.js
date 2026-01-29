// =======================================================
// GekyChat Service Worker — AUTH SAFE / PROD READY (FIXED)
// =======================================================

const CACHE_VERSION = 'gekychat-v9';

// ONLY cache files that NEVER redirect
const STATIC_ASSETS = [
  '/offline',
  '/manifest.json',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
  '/sounds/notification.wav',
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
  
  // Clear cache on demand
  if (event.data === 'CLEAR_CACHE') {
    event.waitUntil(
      caches.keys().then((keys) => {
        return Promise.all(keys.map((key) => caches.delete(key)));
      })
    );
  }
});

// =======================================================
// BACKGROUND SYNC
// =======================================================
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-messages') {
    event.waitUntil(syncMessages());
  }
});

// Register background sync for messages
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'REGISTER_SYNC') {
    self.registration.sync.register('sync-messages').catch(err => {
      console.error('[SW] Failed to register sync:', err);
    });
  }
});

async function syncMessages() {
  try {
    // Get pending messages from IndexedDB
    const db = await openDB();
    const pendingStore = db.transaction('pending_messages', 'readonly').objectStore('pending_messages');
    const pendingMessages = await new Promise((resolve, reject) => {
      const request = pendingStore.getAll();
      request.onsuccess = () => resolve(request.result || []);
      request.onerror = () => reject(request.error);
    });
    
    if (pendingMessages.length === 0) {
      return;
    }
    
    // Send each pending message
    for (const message of pendingMessages) {
      try {
        const conversationId = message.conversation_id || message.group_id;
        const url = message.conversation_id 
          ? `/api/v1/conversations/${conversationId}/messages`
          : `/api/v1/groups/${conversationId}/messages`;
        
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            client_uuid: message.client_uuid,
            body: message.body,
            conversation_id: message.conversation_id,
            group_id: message.group_id,
            reply_to: message.reply_to,
            attachments: message.attachments || [],
          }),
        });
        
        if (response.ok) {
          // Remove from pending queue
          const deleteTransaction = db.transaction('pending_messages', 'readwrite');
          const deleteStore = deleteTransaction.objectStore('pending_messages');
          await new Promise((resolve, reject) => {
            const deleteRequest = deleteStore.delete(message.client_uuid);
            deleteRequest.onsuccess = () => resolve();
            deleteRequest.onerror = () => reject(deleteRequest.error);
          });
        }
      } catch (error) {
        console.error('[SW] Failed to sync message:', error);
      }
    }
  } catch (error) {
    console.error('[SW] Background sync failed:', error);
  }
}

// =======================================================
// PUSH NOTIFICATIONS
// =======================================================
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  
  const options = {
    body: data.body || 'New message',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/badge-96x96.png',
    vibrate: [200, 100, 200],
    data: {
      url: data.url || '/',
      conversationId: data.conversationId,
    },
    actions: [
      {
        action: 'open',
        title: 'Open',
        icon: '/icons/open.png',
      },
      {
        action: 'reply',
        title: 'Reply',
        icon: '/icons/reply.png',
      },
    ],
    tag: data.tag || 'default',
    renotify: true,
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title || 'GekyChat', options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  
  const urlToOpen = event.notification.data?.url || '/';
  
  if (event.action === 'reply') {
    // Handle inline reply (would need additional implementation)
    event.waitUntil(
      clients.openWindow(urlToOpen)
    );
  } else {
    // Open the app
    event.waitUntil(
      clients.matchAll({ type: 'window', includeUncontrolled: true })
        .then((clientList) => {
          // Check if there's already a window open
          for (const client of clientList) {
            if (client.url === urlToOpen && 'focus' in client) {
              return client.focus();
            }
          }
          // Open new window
          if (clients.openWindow) {
            return clients.openWindow(urlToOpen);
          }
        })
    );
  }
});

// =======================================================
// INDEXEDDB HELPER
// =======================================================
function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('GekyChatDB', 1);
    
    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
    
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      
      // Pending messages store
      if (!db.objectStoreNames.contains('pending_messages')) {
        const pendingStore = db.createObjectStore('pending_messages', { keyPath: 'client_uuid' });
        pendingStore.createIndex('conversation_id', 'conversation_id', { unique: false });
        pendingStore.createIndex('group_id', 'group_id', { unique: false });
        pendingStore.createIndex('created_at', 'created_at', { unique: false });
      }
      
      // Messages store
      if (!db.objectStoreNames.contains('messages')) {
        const messagesStore = db.createObjectStore('messages', { keyPath: 'id' });
        messagesStore.createIndex('conversation_id', 'conversation_id', { unique: false });
        messagesStore.createIndex('group_id', 'group_id', { unique: false });
        messagesStore.createIndex('client_uuid', 'client_uuid', { unique: true });
        messagesStore.createIndex('created_at', 'created_at', { unique: false });
      }
    };
  });
}

console.log(`[SW] GekyChat v9 loaded | ${isDevelopment ? 'DEV' : 'PROD'}`);
