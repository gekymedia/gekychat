// resources/js/app.js - CORRECTED VERSION

/**
 * -------------------------------------------------------------
 * Core Imports & Bootstrap
 * -------------------------------------------------------------
 */
import './chat/ChatCore'; // ChatCore handles all real-time chat functionality
import * as bootstrap from 'bootstrap';

// Make bootstrap available globally for your existing code
window.bootstrap = bootstrap;

/**
 * -------------------------------------------------------------
 * Axios Client (Single Source of Truth)
 * -------------------------------------------------------------
 */
import axios from 'axios';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const API_BASE = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/+$/, '');

const api = axios.create({
  baseURL: API_BASE + '/',
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
    'X-CSRF-TOKEN': csrfToken,
  },
});

// Handle 401s globally (session expired)
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err?.response?.status === 401 && !window.location.pathname.includes('/login')) {
      window.location.href = '/phone-login';
    }
    return Promise.reject(err);
  }
);

// Expose for global use
window.axios = api;
window.api = api;

/**
 * -------------------------------------------------------------
 * Laravel Echo (Reverb) with Session/CSRF auth
 * -------------------------------------------------------------
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const hasReverbEnv = !!import.meta.env.VITE_REVERB_APP_KEY;

if (hasReverbEnv) {
  try {
    const reverbConfig = {
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
      wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
      enabledTransports: ['ws', 'wss'],
      auth: {
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      },
      authEndpoint: '/broadcasting/auth',
    };

    console.log('🔧 Echo configuration:', {
      host: reverbConfig.wsHost,
      port: reverbConfig.wsPort,
      scheme: import.meta.env.VITE_REVERB_SCHEME
    });

    window.Echo = new Echo(reverbConfig);
    console.log('✅ Laravel Echo (Reverb) initialized successfully');

    // Enhanced connection monitoring for ChatCore integration
    const pusher = window.Echo.connector.pusher;
    
    pusher.connection.bind('connected', () => {
      console.log('🔗 Reverb connected');
      document.dispatchEvent(new CustomEvent('echo:connection:connected'));
    });

    pusher.connection.bind('error', (error) => {
      console.error('🔴 Reverb connection error:', error);
      document.dispatchEvent(new CustomEvent('echo:connection:error', { detail: { error } }));
    });

    // Dispatch enhanced ready event for ChatCore
    setTimeout(() => {
      const echoInfo = { 
        echo: window.Echo, 
        isNoOp: false,
        config: reverbConfig,
        socketId: window.Echo.socketId()
      };
      
      document.dispatchEvent(new CustomEvent('echo:ready', { detail: echoInfo }));
      window.echoReady = true;
      console.log('🚀 Echo ready event dispatched - ChatCore can initialize');
    }, 100);

  } catch (error) {
    console.error('❌ Failed to initialize Laravel Echo:', error);
    setupNoOpEcho('Echo initialization failed: ' + error.message);
  }
} else {
  console.warn('⚠️ Reverb environment variables not set. Using no-op Echo.');
  setupNoOpEcho('Reverb not configured');
}

/**
 * No-op Echo fallback for when realtime is disabled
 */
function setupNoOpEcho(reason = 'Realtime disabled') {
  const noOpChain = {
    listen() {
      console.log(`[Echo] No-op listen (${reason})`);
      return this;
    },
    stopListening() { return this; },
    whisper() { return this; },
    here() { return this; },
    joining() { return this; },
    leaving() { return this; },
    notification() { return this; },
    error() { return this; },
    stop() { return this; },
    subscribed() { return []; },
    subscribe() { return this; },
  };

  window.Echo = {
    private(channel) {
      console.log(`[Echo] No-op private channel: ${channel} (${reason})`);
      return noOpChain;
    },
    channel(channel) {
      console.log(`[Echo] No-op channel: ${channel} (${reason})`);
      return noOpChain;
    },
    join(channel) {
      console.log(`[Echo] No-op join: ${channel} (${reason})`);
      return noOpChain;
    },
    leave(channel) {
      console.log(`[Echo] No-op leave: ${channel}`);
      return this;
    },
    socketId() {
      return 'no-op-socket-id';
    },
    disconnect() {
      console.log('[Echo] No-op disconnect');
      return this;
    },
    connector: {
      pusher: {
        connection: {
          bind: () => { }
        }
      }
    }
  };

  // Still dispatch ready event so ChatCore doesn't hang
  setTimeout(() => {
    document.dispatchEvent(new CustomEvent('echo:ready', {
      detail: { echo: window.Echo, isNoOp: true, reason }
    }));
    window.echoReady = true;
  }, 100);
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

/**
 * -------------------------------------------------------------
 * Minimal Chat Helpers (ChatCore handles complex logic)
 * -------------------------------------------------------------
 */
window.chatHelpers = {
  // Format message timestamp
  formatTime: (timestamp) => {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  },

  // Format message date
  formatDate: (timestamp) => {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
      return 'Today';
    } else if (date.toDateString() === yesterday.toDateString()) {
      return 'Yesterday';
    } else {
      return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }
  },

  // Simple notification system
  showToast: (message, type = 'info') => {
    if (window.Toast) {
      window.Toast.show(message, type);
    } else {
      console.log(`[${type.toUpperCase()}] ${message}`);
    }
  },

  // Scroll to bottom helper (for non-ChatCore contexts)
  scrollToBottom: (container) => {
    if (!container) return;
    setTimeout(() => {
      container.scrollTop = container.scrollHeight;
    }, 100);
  }
};

/**
 * -------------------------------------------------------------
 * Sidebar Unread Badge Updater
 * -------------------------------------------------------------
 */
document.addEventListener('unreadCountUpdated', (event) => {
  const { type, change, id } = event.detail || {};
  if (!id) return;

  const selector = type === 'direct'
    ? `[data-conversation-id="${id}"]`
    : `[data-group-id="${id}"]`;
  const item = document.querySelector(selector);
  if (!item) return;

  let current = parseInt(item.getAttribute('data-unread') || '0', 10);
  if (isNaN(current)) current = 0;
  let newCount = current + change;
  if (newCount < 0) newCount = 0;

  item.setAttribute('data-unread', newCount);

  let badge = item.querySelector('.unread-badge');
  if (newCount > 0) {
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'unread-badge rounded-pill';
      const timeElem = item.querySelector('.conversation-time');
      if (timeElem && timeElem.parentNode) {
        timeElem.parentNode.insertBefore(badge, timeElem);
      } else {
        item.appendChild(badge);
      }
    }
    badge.textContent = newCount;
    item.classList.add('unread');
  } else {
    if (badge) badge.remove();
    item.classList.remove('unread');
  }
});

/**
 * -------------------------------------------------------------
 * Desktop/Browser Notifications for Incoming Messages
 * -------------------------------------------------------------
 */
document.addEventListener('chatcore:message', (event) => {
  const msg = event.detail;
  if (!msg) return;
  
  const isActive = !document.hidden && document.hasFocus();
  if (isActive) return;

  const sender = msg.sender || {};
  const title = sender.name || 'New message';
  const body = msg.body_plain || msg.body || '';
  const icon = sender.avatar || null;

  if (window.Notification) {
    if (Notification.permission === 'granted') {
      try {
        const notification = new Notification(title, { body, icon });
        setTimeout(() => notification.close(), 5000);
        return;
      } catch (error) {
        console.warn('Failed to show browser notification', error);
      }
    } else if (Notification.permission !== 'denied') {
      Notification.requestPermission().catch(() => {});
    }
  }
  
  if (window.chatHelpers?.showToast) {
    window.chatHelpers.showToast(`${title}: ${body}`, 'info');
  } else {
    console.log(`[Notification] ${title}: ${body}`);
  }
});

/**
 * -------------------------------------------------------------
 * Initialize Core Systems when DOM is ready
 * -------------------------------------------------------------
 */
window.onDomReady(() => {
  // Set up global error handler for chat operations
  window.addEventListener('chatcore:error', (event) => {
    console.error('ChatCore Error:', event.detail);
    if (event.detail.context.includes('connection')) {
      window.chatHelpers.showToast('Connection issue - reconnecting...', 'warning');
    }
  });

  // Listen for connection quality changes
  window.addEventListener('chatcore:connectionQualityChanged', (event) => {
    const quality = event.detail;
    if (quality === 'poor') {
      window.chatHelpers.showToast('Connection quality poor', 'warning');
    }
  });

  console.log('🎯 App.js initialized - Ready for ChatCore');
});

/**
 * -------------------------------------------------------------
 * Global Chat Configuration Helper
 * -------------------------------------------------------------
 */
window.getChatConfig = function() {
  const currentUserId = document.querySelector('meta[name="current-user-id"]')?.content;
  const conversationId = document.querySelector('meta[name="conversation-id"]')?.content;
  const groupId = document.querySelector('meta[name="group-id"]')?.content;
  
  return {
    userId: currentUserId ? parseInt(currentUserId) : null,
    conversationId: conversationId ? parseInt(conversationId) : null,
    groupId: groupId ? parseInt(groupId) : null,
    typingUrl: '/c/typing',
    messageUrl: conversationId ? '/c/send' : (groupId ? `/g/${groupId}/messages` : null),
    reactionUrl: '/messages/react',
    debug: import.meta.env.DEV,
    // ChatCore will auto-detect elements with these selectors
    messageContainer: '#messages-container',
    messageInput: '#message-input',
    messageForm: '#message-form',
    typingIndicator: '.typing-indicator'
  };
};

console.log('🚀 App.js loaded successfully - Optimized for ChatCore');

// Debug Echo connection for development
if (import.meta.env.DEV && window.Echo?.connector?.pusher) {
  window.Echo.connector.pusher.connection.bind('connecting', () => {
    console.log('🔄 Echo connecting to Reverb...');
  });
  
  window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Echo connected - ChatCore can now establish channels');
  });
}

// Enhanced environment debug
console.log('🔧 Environment check:', {
  VITE_REVERB_APP_KEY: import.meta.env.VITE_REVERB_APP_KEY ? '✓ Set' : '✗ Missing',
  VITE_REVERB_HOST: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
  VITE_REVERB_PORT: import.meta.env.VITE_REVERB_PORT || '8080',
  csrfToken: csrfToken ? '✓ Set' : '✗ Missing',
  currentUserId: document.querySelector('meta[name="current-user-id"]')?.content || 'Unknown',
});

// Remove the duplicate export - only expose via window
// export { api }; // REMOVED - causing duplicate export error