# Offline-First Integration Guide

This guide explains how to integrate offline-first functionality into your existing GekyChat application.

## Quick Start

### 1. Import Offline Modules

Update your main JavaScript file (e.g., `resources/js/app.js`):

```javascript
// Import offline functionality
import { OfflineChatCore } from './offline/index.js';
import { OfflineUI } from './offline/OfflineUI.js';

// Replace ChatCore with OfflineChatCore
window.OfflineChatCore = OfflineChatCore;
window.OfflineUI = OfflineUI;
```

### 2. Update Chat Initialization

Replace your existing ChatCore initialization:

**Before:**
```javascript
const chatCore = new ChatCore({
    conversationId: conversationId,
    userId: userId,
    messageUrl: `/api/v1/conversations/${conversationId}/messages`,
    // ... other config
});
```

**After:**
```javascript
const chatCore = new OfflineChatCore({
    conversationId: conversationId,
    userId: userId,
    messageUrl: `/api/v1/conversations/${conversationId}/messages`,
    enableOffline: true, // Enable offline functionality
    loadFromCache: true, // Load cached messages on init
    autoSync: true, // Auto-sync when online
    // ... other config
});
```

### 3. Add UI Indicators

Add offline status indicators to your chat UI:

```javascript
// Initialize offline UI
const offlineUI = new OfflineUI('.chat-header'); // Pass your header selector

// Listen to sync events
document.addEventListener('forceSync', async () => {
    try {
        offlineUI.showSyncProgress();
        await chatCore.forceSync();
        offlineUI.hideSyncProgress();
        offlineUI.showToast('Messages synced successfully', 'success');
    } catch (error) {
        offlineUI.hideSyncProgress();
        offlineUI.showToast('Sync failed. Please try again.', 'error');
    }
});
```

### 4. Update Blade Templates

Add offline status indicator to your chat header:

```blade
<!-- resources/views/chat/partials/chat_header.blade.php -->
<div class="chat-header">
    <!-- Your existing header content -->
    
    <!-- Offline status indicator will be added here automatically -->
</div>
```

### 5. Handle Message Status in UI

Update your message rendering to show status indicators:

```javascript
// In your message rendering function
function renderMessage(message) {
    const statusIcon = getStatusIcon(message.status);
    return `
        <div class="message" data-message-id="${message.id}" data-client-uuid="${message.client_uuid}">
            <div class="message-body">${message.body}</div>
            <div class="message-footer">
                <span class="message-time">${formatTime(message.created_at)}</span>
                ${message.is_own ? `<span class="status-indicator">${statusIcon}</span>` : ''}
            </div>
        </div>
    `;
}

function getStatusIcon(status) {
    switch (status) {
        case 'pending':
            return '<i class="bi bi-clock text-muted" title="Pending"></i>';
        case 'sent':
            return '<i class="bi bi-check2 text-muted" title="Sent"></i>';
        case 'delivered':
            return '<i class="bi bi-check2-all text-muted" title="Delivered"></i>';
        case 'read':
            return '<i class="bi bi-check2-all text-primary" title="Read"></i>';
        default:
            return '';
    }
}
```

## Advanced Integration

### Custom Sync Strategy

You can customize the sync behavior:

```javascript
const chatCore = new OfflineChatCore({
    // ... config
    offline: {
        syncInterval: 10000, // Sync every 10 seconds
        maxRetries: 3, // Max retry attempts
        retryDelay: 3000, // Base retry delay
        batchSize: 5, // Messages per batch
    }
});
```

### Manual Cache Management

```javascript
import { offlineStorage } from './offline/index.js';

// Clear all cached data
await offlineStorage.clearAll();

// Get storage statistics
const stats = await offlineStorage.getStats();
console.log('Cached messages:', stats.messages);
console.log('Pending messages:', stats.pending_messages);
```

### Listen to Events

```javascript
// Connection status
document.addEventListener('connectionStatusChanged', (e) => {
    const { isOnline, quality } = e.detail;
    console.log(`Connection: ${isOnline ? 'Online' : 'Offline'} (${quality})`);
});

// Pending messages
document.addEventListener('pendingMessagesCountChanged', (e) => {
    console.log(`Pending messages: ${e.detail.count}`);
});

// Cache loaded
document.addEventListener('cacheLoaded', (e) => {
    console.log(`Loaded ${e.detail.count} cached messages`);
});

// Sync events
document.addEventListener('syncStarted', () => {
    console.log('Sync started');
});

document.addEventListener('syncCompleted', (e) => {
    if (e.detail.success) {
        console.log('Sync completed successfully');
    } else {
        console.error('Sync failed:', e.detail.error);
    }
});
```

## Testing

### Test Offline Functionality

1. **Enable Airplane Mode** in browser DevTools (Network tab)
2. **Send a message** - should appear with pending status
3. **Disable Airplane Mode** - message should sync automatically
4. **Check status** - should change from pending → sent → delivered

### Test Cache Loading

1. **Load a conversation** while online
2. **Enable Airplane Mode**
3. **Reload page** - messages should load from cache
4. **Disable Airplane Mode** - new messages should sync

### Test Background Sync

1. **Send messages while offline**
2. **Close browser tab**
3. **Wait for connectivity** - Service Worker should sync messages
4. **Reopen tab** - messages should be synced

## Troubleshooting

### Messages Not Syncing

1. Check browser console for errors
2. Verify service worker is registered: `navigator.serviceWorker.controller`
3. Check IndexedDB: DevTools → Application → IndexedDB → GekyChatDB
4. Verify connectivity: `connectivityManager.isOnline`

### UI Not Updating

1. Check if events are being dispatched
2. Verify event listeners are registered
3. Check browser console for JavaScript errors

### Performance Issues

1. Reduce `syncInterval` if too frequent
2. Increase `batchSize` for faster sync
3. Limit cached messages per conversation
4. Implement storage cleanup strategy

## Migration Checklist

- [ ] Import offline modules
- [ ] Replace ChatCore with OfflineChatCore
- [ ] Add UI indicators
- [ ] Update message rendering for status
- [ ] Test offline functionality
- [ ] Test cache loading
- [ ] Test background sync
- [ ] Update documentation
- [ ] Deploy to production

## Backward Compatibility

The offline functionality is designed to be backward compatible:

- Existing ChatCore code continues to work
- Offline features are opt-in via `enableOffline: true`
- No breaking changes to existing APIs
- Can be enabled/disabled per chat instance

## Next Steps

1. **Test thoroughly** in development
2. **Monitor performance** in production
3. **Gather user feedback** on offline experience
4. **Iterate** based on usage patterns
5. **Add encryption** for enhanced security (future)
