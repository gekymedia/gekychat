/**
 * Register VAPID Web Push subscriptions for the GekyChat PWA.
 */

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) {
    output[i] = raw.charCodeAt(i);
  }
  return output;
}

async function fetchVapidPublicKey() {
  const meta = document.querySelector('meta[name="vapid-public-key"]')?.getAttribute('content');
  if (meta) return meta;

  const envKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;
  if (envKey) return envKey;

  try {
    const res = await fetch('/web-push/vapid-public-key', { credentials: 'same-origin' });
    if (!res.ok) return null;
    const json = await res.json();
    return json.public_key || null;
  } catch {
    return null;
  }
}

export async function initWebPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    return;
  }

  if (!window.APP?.userId) {
    return;
  }

  const permission = await Notification.requestPermission();
  if (permission !== 'granted') {
    console.warn('Web Push: notification permission not granted');
    return;
  }

  const vapidKey = await fetchVapidPublicKey();
  if (!vapidKey) {
    console.warn('Web Push: VAPID public key not configured');
    return;
  }

  const registration = await navigator.serviceWorker.ready;
  let subscription = await registration.pushManager.getSubscription();

  if (!subscription) {
    subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapidKey),
    });
  }

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const body = subscription.toJSON();

  await fetch('/web-push/subscribe', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf,
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      endpoint: body.endpoint,
      keys: body.keys,
      contentEncoding: 'aesgcm',
    }),
  });

  console.log('✅ Web Push subscription registered');
}
