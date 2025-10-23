// resources/js/app.js - FIXED COMPLETE VERSION

/**
 * -------------------------------------------------------------
 * Bootstrap (modals, tooltips, etc.)
 * -------------------------------------------------------------
 */
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

/**
 * -------------------------------------------------------------
 * Axios client (CSRF-based for session auth)
 * -------------------------------------------------------------
 */
import axios from 'axios';

// Get CSRF token ONCE at the top level
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

// Base URL for your API
const API_BASE = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/+$/, '');

export const api = axios.create({
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
    if (err?.response?.status === 401) {
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/phone-login';
      }
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

// Check if Reverb is configured
const hasReverbEnv = !!import.meta.env.VITE_REVERB_APP_KEY;

if (hasReverbEnv) {
  try {
    window.Echo = new Echo({
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
      wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
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
    });

    console.log('✅ Laravel Echo (Reverb) initialized successfully');

    // Connection monitoring
    const pusher = window.Echo.connector.pusher;
    pusher.connection.bind('connected', () => {
      console.log('🔗 Reverb connected');
      document.dispatchEvent(new CustomEvent('echo:connection:connected'));
    });

    pusher.connection.bind('error', (error) => {
      console.error('🔴 Reverb connection error:', error);
    });

    // Dispatch event for other scripts to know Echo is ready
    setTimeout(() => {
      document.dispatchEvent(new CustomEvent('echo:ready', {
        detail: { echo: window.Echo, isNoOp: false }
      }));
      console.log('🚀 Echo ready event dispatched');
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

  // Still dispatch ready event so other scripts don't hang
  setTimeout(() => {
    document.dispatchEvent(new CustomEvent('echo:ready', {
      detail: { echo: window.Echo, isNoOp: true, reason }
    }));
  }, 100);
}

/**
 * Connection status monitoring (only for real Echo instances)
 */
function setupConnectionMonitoring() {
  if (!window.Echo || !window.Echo.connector || !window.Echo.connector.pusher) {
    console.log('🔄 Skipping connection monitoring for no-op Echo');
    return;
  }

  // Monitor connection status
  window.Echo.connector.pusher.connection.bind('state_change', (states) => {
    const { previous, current } = states;
    console.log(`🔄 Echo connection: ${previous} → ${current}`);

    // Dispatch events for connection state changes
    document.dispatchEvent(new CustomEvent('echo:connection:state', {
      detail: { previous, current }
    }));

    // Show connection status in UI (optional)
    if (current === 'connected') {
      showConnectionStatus('connected', 'Connected');
    } else if (current === 'connecting') {
      showConnectionStatus('connecting', 'Connecting...');
    } else if (current === 'unavailable') {
      showConnectionStatus('disconnected', 'Disconnected');
    }
  });

  // Handle connection errors
  window.Echo.connector.pusher.connection.bind('error', (error) => {
    console.error('🔴 Echo connection error:', error);
    document.dispatchEvent(new CustomEvent('echo:connection:error', {
      detail: { error }
    }));
  });

  // Handle connected event
  window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Echo connected to Reverb server');
    document.dispatchEvent(new CustomEvent('echo:connection:connected'));
  });
}

/**
 * Optional: UI helper for connection status
 */
function showConnectionStatus(status, message) {
  // Remove existing status indicator
  const existing = document.getElementById('connection-status');
  if (existing) existing.remove();

  // Only show if not connected (or show connected briefly)
  if (status === 'connected') {
    // Show connected briefly then remove
    const indicator = createStatusIndicator(status, message);
    document.body.appendChild(indicator);
    setTimeout(() => indicator.remove(), 3000);
    return;
  }

  // Show persistent status for other states
  const indicator = createStatusIndicator(status, message);
  document.body.appendChild(indicator);
}

function createStatusIndicator(status, message) {
  const indicator = document.createElement('div');
  indicator.id = 'connection-status';
  indicator.innerHTML = `
    <div class="connection-status connection-${status}">
      <span class="connection-dot"></span>
      ${message}
    </div>
  `;
  return indicator;
}

// Add connection status styles once
if (!document.querySelector('#connection-status-styles')) {
  const styles = document.createElement('style');
  styles.id = 'connection-status-styles';
  styles.textContent = `
    .connection-status {
      position: fixed;
      top: 10px;
      right: 10px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 8px 16px;
      font-size: 0.875rem;
      z-index: 9999;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: var(--wa-shadow);
    }
    .connection-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
    }
    .connection-connected .connection-dot { background: var(--wa-green); }
    .connection-connecting .connection-dot { background: orange; animation: pulse 1.5s infinite; }
    .connection-disconnected .connection-dot { background: red; }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }
  `;
  document.head.appendChild(styles);
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
 * Chat-specific global helpers
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

  // Scroll to bottom of container
  scrollToBottom: (container) => {
    if (!container) return;
    setTimeout(() => {
      container.scrollTop = container.scrollHeight;
    }, 100);
  },

  // Escape HTML for safe rendering
  escapeHtml: (text) => {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },

  // Debounce function
  debounce: (func, wait) => {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  // Add message to UI with animation
  addMessageToUI: (html, containerId = 'messages-container') => {
    const container = document.getElementById(containerId);
    if (!container) {
      console.error('❌ Message container not found:', containerId);
      return false;
    }

    // Create temporary container to parse HTML
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    const newMessage = tempDiv.firstElementChild;

    if (!newMessage) {
      console.error('❌ Could not parse message HTML');
      return false;
    }

    // Add animation classes
    newMessage.classList.add('message-received');
    newMessage.style.opacity = '0';
    newMessage.style.transform = 'translateY(20px)';

    // Add to container
    container.appendChild(newMessage);

    // Animate in
    setTimeout(() => {
      newMessage.style.transition = 'all 0.3s ease';
      newMessage.style.opacity = '1';
      newMessage.style.transform = 'translateY(0)';
    }, 50);

    // Scroll to bottom
    this.scrollToBottom(container);

    console.log('✅ Message added to UI');
    return true;
  }
};

/**
 * -------------------------------------------------------------
 * Chat Auto-Scroll Functionality
 * -------------------------------------------------------------
 */
class ChatAutoScroll {
  constructor() {
    this.init();
  }

  init() {
    // Scroll to bottom when page loads
    this.scrollToBottom();

    // Also scroll after a short delay (in case of dynamic content)
    setTimeout(() => this.scrollToBottom(), 300);

    // Listen for URL changes (if using SPA-like navigation)
    this.setupNavigationListener();

    // Listen for new messages via events
    this.setupMessageListeners();

    console.log('📜 Chat auto-scroll initialized');
  }

  scrollToBottom() {
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
      console.log('📜 Auto-scrolling to bottom of chat');
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
  }

  setupNavigationListener() {
    // Handle browser back/forward buttons
    window.addEventListener('popstate', () => {
      setTimeout(() => this.scrollToBottom(), 200);
    });

    // Handle pushState changes (if you use AJAX navigation)
    if (window.history && window.history.pushState) {
      const originalPushState = history.pushState;
      history.pushState = function () {
        originalPushState.apply(this, arguments);
        setTimeout(() => this.scrollToBottom(), 200);
      }.bind(this);
    }
  }

  setupMessageListeners() {
    // Listen for custom events when new messages are added
    document.addEventListener('newMessageAdded', () => {
      setTimeout(() => this.scrollToBottom(), 100);
    });

    // Listen for reply preview clicks to scroll to original message
    document.addEventListener('replyClicked', (event) => {
      if (event.detail && event.detail.messageId) {
        this.scrollToMessage(event.detail.messageId);
      }
    });

    // Listen for Echo ready event to setup real-time scrolling
    document.addEventListener('echo:ready', () => {
      console.log('📜 Echo ready - setting up real-time scroll listeners');
      this.setupRealTimeListeners();
    });
  }

  scrollToMessage(messageId) {
    const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
    if (messageElement && messageElement.scrollIntoView) {
      messageElement.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
      });
    }
  }

  setupRealTimeListeners() {
    // Auto-scroll for new real-time messages if we can detect the current chat
    if (window.Echo && (!window.Echo.socketId || window.Echo.socketId() === 'no-op-socket-id')) {
      console.log('📜 Skipping real-time listeners for no-op Echo');
      return;
    }

    console.log('📜 Real-time scroll listeners ready - chat pages should configure specific channels');
  }

  setupChatSpecificListeners(chatType, chatId) {
    if (!window.Echo || window.Echo.socketId() === 'no-op-socket-id') return;

    console.log(`📜 Setting up real-time scroll for ${chatType} chat ${chatId}`);

    if (chatType === 'direct') {
      window.Echo.private(`chat.${chatId}`)
        .listen('MessageSent', (e) => {
          console.log('📜 New direct message - auto-scrolling');
          setTimeout(() => this.scrollToBottom(), 100);
        });
    } else if (chatType === 'group') {
      window.Echo.private(`group.${chatId}`)
        .listen('GroupMessageSent', (e) => {
          console.log('📜 New group message - auto-scrolling');
          setTimeout(() => this.scrollToBottom(), 100);
        });
    }
  }
}

/**
 * -------------------------------------------------------------
 * Initialize Auto-Scroll when DOM is ready
 * -------------------------------------------------------------
 */
window.onDomReady(() => {
  // Initialize auto-scroll for chats
  window.chatAutoScroll = new ChatAutoScroll();

  // Add scrollToBottom to chatHelpers for backward compatibility
  window.chatHelpers.scrollToBottom = (container) => {
    if (container) {
      container.scrollTop = container.scrollHeight;
    } else {
      // If no container specified, use the main messages container
      window.chatAutoScroll.scrollToBottom();
    }
  };
});

// Make it available globally for manual triggering
window.scrollChatToBottom = function () {
  if (window.chatAutoScroll) {
    window.chatAutoScroll.scrollToBottom();
  } else {
    // Fallback if auto-scroll not initialized yet
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
  }
};

console.log('🚀 App.js loaded successfully with auto-scroll');

// Debug: Log environment variables (remove in production)
console.log('🔧 Environment check:', {
  VITE_REVERB_APP_KEY: import.meta.env.VITE_REVERB_APP_KEY ? '✓ Set' : '✗ Missing',
  VITE_REVERB_HOST: import.meta.env.VITE_REVERB_HOST || 'Not set',
  VITE_REVERB_PORT: import.meta.env.VITE_REVERB_PORT || 'Not set',
  VITE_REVERB_SCHEME: import.meta.env.VITE_REVERB_SCHEME || 'Not set'
});