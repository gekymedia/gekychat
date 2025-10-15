// resources/js/app.js

/**
 * -------------------------------------------------------------
 * Bootstrap (modals, tooltips, etc.)
 * -------------------------------------------------------------
 */
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

/**
 * -------------------------------------------------------------
 * Axios client (Bearer token based)
 * -------------------------------------------------------------
 */
import axios from 'axios';

// Base URL for your API (e.g. http://localhost:8000/api/v1)
// Configure in .env(.local): VITE_API_BASE_URL="http://127.0.0.1:8000/api/v1"
const API_BASE = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/+$/, '');

export const api = axios.create({
  baseURL: API_BASE + '/',
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
  },
});

// Tiny token helpers
const TOKEN_KEY = 'token';
export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || '';
}
export function setToken(token) {
  localStorage.setItem(TOKEN_KEY, token);
  api.defaults.headers.common.Authorization = `Bearer ${token}`;
}
export function clearToken() {
  localStorage.removeItem(TOKEN_KEY);
  delete api.defaults.headers.common.Authorization;
}

// Load token on boot (stay signed in)
const savedToken = getToken();
if (savedToken) setToken(savedToken);

// Optional: handle 401s globally (auto-logout UX)
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err?.response?.status === 401) {
      clearToken();
      // If you have a SPA router, redirect to login here.
      // location.href = '/login';
    }
    return Promise.reject(err);
  }
);

// Expose for inline Blade scripts if needed
window.axios = api;
window.api = api;
window.auth = { getToken, setToken, clearToken };

/**
 * -------------------------------------------------------------
 * Laravel Echo (Pusher) with Bearer auth
 * -------------------------------------------------------------
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

// If you want realtime, set VITE_PUSHER_APP_KEY and related vars in .env
const hasPusherEnv = !!import.meta.env.VITE_PUSHER_APP_KEY;

if (hasPusherEnv && getToken()) {
  const scheme  = (import.meta.env.VITE_PUSHER_SCHEME ?? location.protocol.replace(':',''));
  const host    = import.meta.env.VITE_PUSHER_HOST ?? location.hostname;
  const port    = Number(import.meta.env.VITE_PUSHER_PORT ?? (scheme === 'https' ? 443 : 6001));
  const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1';

  // Where Echo should POST for private/presence channel auth
  // If you’re using API tokens (not session cookies), point this to any route
  // that uses the API guard and accepts Bearer tokens.
  //
  // Prefer to set it in .env:
  //   VITE_BROADCAST_AUTH="http://127.0.0.1:8000/api/v1/broadcasting/auth"
  // Fallback to default Laravel endpoint on same origin:
  const BROADCAST_AUTH =
    import.meta.env.VITE_BROADCAST_AUTH ||
    `${location.origin}/broadcasting/auth`;

  window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,

    // IMPORTANT: We are NOT using cookies/withCredentials for API tokens.
    withCredentials: false,
    authEndpoint: BROADCAST_AUTH,
    auth: {
      headers: {
        // Send PAT instead of relying on session cookies
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    },
  });
} else {
  // Fallback: no-op Echo so UI doesn't crash when realtime is disabled
  const chain = {
    listen()  { return this; },
    whisper() { return this; },
    here()    { return this; },
    joining() { return this; },
    leaving() { return this; },
    notification() { return this; },
  };
  window.Echo = {
    private() { return chain; },
    channel() { return chain; },
    join()    { return chain; },
  };
  if (!hasPusherEnv) {
    console.warn('[Echo] VITE_PUSHER_* not set. Using a no-op Echo (realtime disabled).');
  } else {
    console.warn('[Echo] No token in storage. Set a token before joining channels.');
  }
}

/**
 * -------------------------------------------------------------
 * DOM-ready helper for Blade snippets
 * -------------------------------------------------------------
 */
window.onDomReady = (fn) => {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fn, { once: true });
  } else {
    fn();
  }
};
