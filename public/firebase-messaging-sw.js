/**
 * GekyChat PWA push handler (VAPID Web Push via laravel-notification-channels/webpush).
 * Loaded by service-worker.js via importScripts.
 */

self.addEventListener('push', (event) => {
  let payload = {};
  if (event.data) {
    try {
      payload = event.data.json();
    } catch (e) {
      payload = { title: 'GekyChat', body: event.data.text() };
    }
  }

  const title = payload.title || 'GekyChat';
  const body = payload.body || '';
  const data = payload.data || payload;
  const url = data.url || data.click_action || '/';

  const options = {
    body,
    icon: payload.icon || '/icons/icon-192x192.png',
    badge: '/icons/badge-96x96.png',
    vibrate: [200, 100, 200],
    data: {
      url,
      type: data.type || '',
      conversation_id: data.conversation_id || null,
      group_id: data.group_id || null,
      message_id: data.message_id || null,
      call_id: data.call_id || data.session_id || null,
    },
    tag: payload.tag || data.tag || 'gekychat',
    renotify: true,
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const data = event.notification.data || {};
  let url = data.url || '/';

  if (data.type === 'call_invite' && data.call_id) {
    url = '/calls/group/' + data.call_id;
  } else if (data.conversation_id && !data.group_id) {
    url = '/c/' + data.conversation_id;
  } else if (data.group_id) {
    url = '/g/' + data.group_id;
  }

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes(url) && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});
