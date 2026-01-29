# Offline-First Chat Implementation

This module provides offline-first messaging functionality for GekyChat, similar to WhatsApp.

## Features

- ✅ **Offline Message Queue**: Messages are saved locally when offline and synced when online
- ✅ **Local Message Storage**: All conversations are cached locally for offline reading
- ✅ **Connectivity Detection**: Reliable network status monitoring
- ✅ **Automatic Sync**: Messages sync automatically when connectivity is restored
- ✅ **Message Status Tracking**: Supports pending → sent → delivered → read status flow
- ✅ **Background Sync**: Service Worker handles sync even when app is closed

## Architecture

### Components

1. **OfflineStorage.js**: IndexedDB wrapper for local message storage
2. **ConnectivityManager.js**: Network connectivity detection and monitoring
3. **SyncManager.js**: Handles synchronization between local and server
4. **OfflineChatCore.js**: Enhanced ChatCore with offline support

## Usage

### Basic Setup

```javascript
import { OfflineChatCore } from './offline/index.js';

const chatCore = new OfflineChatCore({
    conversationId: 123,
    userId: 456,
    messageUrl: '/api/v1/conversations/123/messages',
    enableOffline: true, // Enable offline functionality
    loadFromCache: true, // Load cached messages on init
    autoSync: true, // Auto-sync when online
});
```

### Manual Sync

```javascript
// Force sync
await chatCore.forceSync();

// Get offline status
const status = chatCore.getOfflineStatus();
console.log('Pending messages:', status.pendingCount);
console.log('Is online:', status.isOnline);
```

### Listen to Events

```javascript
// Connection status changes
document.addEventListener('connectionStatusChanged', (e) => {
    console.log('Connection:', e.detail.isOnline ? 'Online' : 'Offline');
});

// Pending messages count
document.addEventListener('pendingMessagesCountChanged', (e) => {
    console.log('Pending messages:', e.detail.count);
});
```

## Data Models

### Message Status Flow

```
pending → sent → delivered → read
```

- **pending**: Saved locally, not yet sent to server
- **sent**: Successfully sent to server
- **delivered**: Delivered to recipient's device
- **read**: Read by recipient

### IndexedDB Stores

- `messages`: All messages (local + synced)
- `pending_messages`: Messages waiting to be sent
- `conversations`: Conversation metadata cache
- `groups`: Group metadata cache
- `sync_state`: Last sync timestamps per thread
- `media_cache`: Media metadata for offline access

## Backend API Requirements

The backend must support:

1. **client_uuid** parameter in message creation (for idempotency)
2. **after** timestamp parameter for incremental sync
3. Health check endpoint: `/api/v1/health`

### Example Message Creation

```php
// Backend already supports client_uuid
Message::create([
    'client_uuid' => $request->input('client_uuid'),
    'conversation_id' => $conversationId,
    'sender_id' => $userId,
    'body' => $request->body,
]);
```

## Service Worker

The service worker handles background sync when the app is closed. It:

- Syncs pending messages when connectivity is restored
- Works even when browser tab is closed
- Retries failed syncs automatically

## Testing Offline Functionality

1. **Enable Airplane Mode**: Send messages while offline
2. **Check Pending Queue**: Messages should appear with pending status
3. **Disable Airplane Mode**: Messages should sync automatically
4. **Verify Status Updates**: Status should change from pending → sent → delivered

## Performance Considerations

- Messages are synced in batches (default: 10 messages per batch)
- Sync happens every 5 seconds when online
- Cached messages are limited to 100 per conversation
- Old sync states are cleaned up automatically

## Security

- Messages are stored locally (not encrypted by default)
- Auth tokens are NOT stored in IndexedDB
- Architecture supports future encryption implementation

## Troubleshooting

### Messages not syncing

1. Check connectivity: `connectivityManager.isOnline`
2. Check sync status: `chatCore.getOfflineStatus()`
3. Check browser console for errors
4. Verify service worker is registered

### Messages lost

- Check IndexedDB: Open DevTools → Application → IndexedDB → GekyChatDB
- Verify pending_messages store has entries
- Check sync manager logs

## Future Enhancements

- [ ] Message encryption at rest
- [ ] Media offline caching
- [ ] Multi-device sync
- [ ] Conflict resolution for simultaneous edits
- [ ] Storage cleanup strategy
