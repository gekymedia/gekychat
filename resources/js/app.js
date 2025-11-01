// resources/js/app.js - UPDATED FOR CHATCORE INTEGRATION
/**
 * -------------------------------------------------------------
 * ChatCore - Real-time chat functionality
 * -------------------------------------------------------------
 */
import './chat/ChatCore'; // ← ADD THIS LINE

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

    // Setup connection monitoring
    setupConnectionMonitoring();

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
      
      document.dispatchEvent(new CustomEvent('echo:ready', {
        detail: echoInfo
      }));
      
      // Set global flag for ChatCore
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
 * Chat-specific global helpers (Simplified - ChatCore handles complex logic)
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

  // Simple message UI helper (ChatCore has more advanced version)
  addMessageToUI: (html, containerId = 'messages-container') => {
    const container = document.getElementById(containerId);
    if (!container) {
      console.error('❌ Message container not found:', containerId);
      return false;
    }

    container.insertAdjacentHTML('beforeend', html);
    
    // Trigger event for auto-scroll
    document.dispatchEvent(new CustomEvent('newMessageAdded'));
    
    console.log('✅ Message added to UI via helper');
    return true;
  },

  // Simple notification system (ChatCore has enhanced version)
  showToast: (message, type = 'info') => {
    // Use existing toast system or fallback
    if (window.Toast) {
      window.Toast.show(message, type);
    } else {
      // Fallback notification
      console.log(`[${type.toUpperCase()}] ${message}`);
    }
  }
};

/**
 * -------------------------------------------------------------
 * Chat Auto-Scroll Functionality (Simplified - Works with ChatCore)
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

    // Listen for new messages via events from ChatCore
    this.setupMessageListeners();

    console.log('📜 Chat auto-scroll initialized (ChatCore compatible)');
  }

  scrollToBottom() {
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
  }

  setupMessageListeners() {
    // Listen for custom events when new messages are added by ChatCore
    document.addEventListener('newMessageAdded', () => {
      setTimeout(() => this.scrollToBottom(), 100);
    });

    // Listen for ChatCore message events
    document.addEventListener('chatcore:message', () => {
      setTimeout(() => this.scrollToBottom(), 100);
    });

    // Listen for reply preview clicks to scroll to original message
    document.addEventListener('replyClicked', (event) => {
      if (event.detail && event.detail.messageId) {
        this.scrollToMessage(event.detail.messageId);
      }
    });

    // Listen for ChatCore initialization to ensure proper timing
    document.addEventListener('chatcore:initialized', () => {
      console.log('📜 ChatCore initialized - auto-scroll ready');
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

  // Simple method to check if we're near bottom
  isNearBottom(container, threshold = 100) {
    if (!container) return false;
    const { scrollTop, scrollHeight, clientHeight } = container;
    return scrollHeight - scrollTop - clientHeight < threshold;
  }
}

/**
 * -------------------------------------------------------------
 * Initialize Core Systems when DOM is ready
 * -------------------------------------------------------------
 */
window.onDomReady(() => {
  // Initialize auto-scroll for chats
  window.chatAutoScroll = new ChatAutoScroll();

  // Enhanced scrollToBottom for global access
  window.scrollChatToBottom = function (container) {
    if (container) {
      container.scrollTop = container.scrollHeight;
    } else if (window.chatAutoScroll) {
      window.chatAutoScroll.scrollToBottom();
    } else {
      // Fallback
      const messagesContainer = document.getElementById('messages-container');
      if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
      }
    }
  };

  // Set up global error handler for chat operations
  window.addEventListener('chatcore:error', (event) => {
    console.error('ChatCore Error:', event.detail);
    // You can show user-friendly error messages here
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
    debug: import.meta.env.DEV, // Debug in development only
    // ChatCore will auto-detect elements with these selectors
    messageContainer: '#messages-container',
    messageInput: '#message-input',
    messageForm: '#message-form',
    typingIndicator: '.typing-indicator'
  };
};

console.log('🚀 App.js loaded successfully - Optimized for ChatCore');

// Enhanced environment debug
console.log('🔧 Environment check:', {
  VITE_REVERB_APP_KEY: import.meta.env.VITE_REVERB_APP_KEY ? '✓ Set' : '✗ Missing',
  VITE_REVERB_HOST: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
  VITE_REVERB_PORT: import.meta.env.VITE_REVERB_PORT || '8080',
  csrfToken: csrfToken ? '✓ Set' : '✗ Missing',
  currentUserId: document.querySelector('meta[name="current-user-id"]')?.content || 'Unknown',
  // ChatCore will handle all real-time functionality
});

// Debug Echo connection for development
if (import.meta.env.DEV && window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
  window.Echo.connector.pusher.connection.bind('connecting', () => {
    console.log('🔄 Echo connecting to Reverb...');
  });
  
  window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Echo connected - ChatCore can now establish channels');
  });
}

// Export for module usage
export { api };