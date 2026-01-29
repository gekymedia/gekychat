/**
 * SyncManager.js
 * 
 * Handles synchronization of offline messages with the server
 * Implements server-authoritative sync strategy
 */

import { offlineStorage } from './OfflineStorage.js';
import { connectivityManager } from './ConnectivityManager.js';

export class SyncManager {
    constructor(config = {}) {
        this.config = {
            syncInterval: config.syncInterval || 5000, // Sync every 5 seconds when online
            maxRetries: config.maxRetries || 5,
            retryDelay: config.retryDelay || 2000, // 2 seconds base delay
            batchSize: config.batchSize || 10, // Send messages in batches
            messageUrl: config.messageUrl || '/api/v1/messages',
            messagesUrl: config.messagesUrl || '/api/v1/chats',
            debug: config.debug || false,
            ...config
        };

        this.isSyncing = false;
        this.syncIntervalId = null;
        this.syncListeners = new Set();
        this.lastSyncTime = null;

        this.init();
    }

    init() {
        // Listen to connectivity changes
        connectivityManager.onConnectivityChange((status) => {
            if (status.isOnline) {
                this.startAutoSync();
                this.sync(); // Immediate sync when coming online
            } else {
                this.stopAutoSync();
            }
        });

        // Start auto-sync if online
        if (connectivityManager.isOnline) {
            this.startAutoSync();
        }
    }

    /**
     * Start automatic periodic sync
     */
    startAutoSync() {
        if (this.syncIntervalId) return;

        this.log('Starting auto-sync');
        this.syncIntervalId = setInterval(() => {
            if (connectivityManager.isOnline && !this.isSyncing) {
                this.sync();
            }
        }, this.config.syncInterval);
    }

    /**
     * Stop automatic sync
     */
    stopAutoSync() {
        if (this.syncIntervalId) {
            clearInterval(this.syncIntervalId);
            this.syncIntervalId = null;
            this.log('Stopped auto-sync');
        }
    }

    /**
     * Main sync function - syncs pending messages and fetches new ones
     */
    async sync() {
        if (this.isSyncing) {
            this.log('Sync already in progress, skipping');
            return;
        }

        if (!connectivityManager.isOnline) {
            this.log('Offline, skipping sync');
            return;
        }

        this.isSyncing = true;
        this.notifyListeners('syncStarted');

        try {
            // Step 1: Send pending messages
            await this.syncPendingMessages();

            // Step 2: Fetch new messages from server
            await this.syncNewMessages();

            this.lastSyncTime = new Date().toISOString();
            this.notifyListeners('syncCompleted', { success: true });
        } catch (error) {
            this.log('Sync error:', error);
            this.notifyListeners('syncCompleted', { success: false, error });
        } finally {
            this.isSyncing = false;
        }
    }

    /**
     * Sync pending messages to server
     */
    async syncPendingMessages() {
        const pendingMessages = await offlineStorage.getPendingMessages();

        if (pendingMessages.length === 0) {
            this.log('No pending messages to sync');
            return;
        }

        this.log(`Syncing ${pendingMessages.length} pending messages`);

        // Process in batches
        const batches = this.chunkArray(pendingMessages, this.config.batchSize);

        for (const batch of batches) {
            await Promise.allSettled(
                batch.map(message => this.sendPendingMessage(message))
            );
        }
    }

    /**
     * Send a single pending message
     */
    async sendPendingMessage(pendingMessage) {
        try {
            // Check retry limit
            if (pendingMessage.retry_count >= this.config.maxRetries) {
                this.log(`Message ${pendingMessage.client_uuid} exceeded max retries, marking as failed`);
                await offlineStorage.updateMessageStatus(
                    pendingMessage.client_uuid,
                    'failed',
                    null
                );
                await offlineStorage.removePendingMessage(pendingMessage.client_uuid);
                return;
            }

            // Prepare payload
            const payload = {
                client_uuid: pendingMessage.client_uuid,
                body: pendingMessage.body,
                conversation_id: pendingMessage.conversation_id,
                group_id: pendingMessage.group_id,
                reply_to: pendingMessage.reply_to,
                attachments: pendingMessage.attachments || [],
            };

            // Send to server
            const response = await window.api.post(this.config.messageUrl, payload);

            if (response.data?.message) {
                const serverMessage = response.data.message;

                // Update local message with server ID and status
                await offlineStorage.updateMessageStatus(
                    pendingMessage.client_uuid,
                    'sent',
                    serverMessage.id
                );

                // Remove from pending queue
                await offlineStorage.removePendingMessage(pendingMessage.client_uuid);

                this.log(`Message ${pendingMessage.client_uuid} synced successfully`, serverMessage.id);
                this.notifyListeners('messageSynced', {
                    clientUUID: pendingMessage.client_uuid,
                    serverId: serverMessage.id,
                    message: serverMessage
                });
            }
        } catch (error) {
            this.log(`Failed to sync message ${pendingMessage.client_uuid}:`, error);

            // Increment retry count
            await offlineStorage.incrementRetryCount(pendingMessage.client_uuid);

            // Calculate exponential backoff delay
            const delay = this.config.retryDelay * Math.pow(2, pendingMessage.retry_count || 0);
            
            // Schedule retry
            setTimeout(() => {
                if (connectivityManager.isOnline) {
                    this.sendPendingMessage(pendingMessage);
                }
            }, delay);

            throw error;
        }
    }

    /**
     * Fetch new messages from server
     */
    async syncNewMessages() {
        try {
            // Get list of conversations/groups
            const response = await window.api.get(this.config.messagesUrl || '/api/v1/chats');
            const threads = response.data?.data || [];

            // Get last sync timestamp for each thread
            for (const thread of threads) {
                const lastSyncKey = `last_sync_${thread.type}_${thread.id}`;
                const lastSync = await offlineStorage.getSyncState(lastSyncKey);
                const after = lastSync || new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString(); // Default: 7 days ago

                // Fetch new messages
                const messagesResponse = await window.api.get(
                    `${this.config.messagesUrl}/${thread.id}/messages`,
                    {
                        params: {
                            type: thread.type,
                            after: after,
                            limit: 100
                        }
                    }
                );

                const newMessages = messagesResponse.data?.data || [];

                // Save messages locally
                for (const message of newMessages) {
                    await offlineStorage.saveMessage({
                        ...message,
                        status: 'sent', // Messages from server are already sent
                        server_id: message.id
                    });
                }

                // Update sync timestamp
                if (newMessages.length > 0) {
                    const latestMessage = newMessages[newMessages.length - 1];
                    await offlineStorage.saveSyncState(
                        lastSyncKey,
                        latestMessage.created_at
                    );
                }

                this.log(`Synced ${newMessages.length} new messages for ${thread.type} ${thread.id}`);
            }
        } catch (error) {
            this.log('Error syncing new messages:', error);
            throw error;
        }
    }

    /**
     * Force sync for a specific conversation/group
     */
    async syncThread(threadId, threadType = 'dm') {
        try {
            const lastSyncKey = `last_sync_${threadType}_${threadId}`;
            const lastSync = await offlineStorage.getSyncState(lastSyncKey);
            const after = lastSync || new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString();

            const response = await window.api.get(
                `${this.config.messagesUrl}/${threadId}/messages`,
                {
                    params: {
                        type: threadType,
                        after: after,
                        limit: 100
                    }
                }
            );

            const messages = response.data?.data || [];

            for (const message of messages) {
                await offlineStorage.saveMessage({
                    ...message,
                    status: 'sent',
                    server_id: message.id
                });
            }

            if (messages.length > 0) {
                const latestMessage = messages[messages.length - 1];
                await offlineStorage.saveSyncState(lastSyncKey, latestMessage.created_at);
            }

            return messages;
        } catch (error) {
            this.log(`Error syncing thread ${threadId}:`, error);
            throw error;
        }
    }

    /**
     * Register sync event listener
     */
    onSyncEvent(callback) {
        this.syncListeners.add(callback);
        return () => this.syncListeners.delete(callback);
    }

    /**
     * Notify listeners of sync events
     */
    notifyListeners(event, data = null) {
        this.syncListeners.forEach(callback => {
            try {
                callback({ event, data, timestamp: new Date().toISOString() });
            } catch (error) {
                console.error('Error in sync listener:', error);
            }
        });
    }

    /**
     * Utility: Chunk array into smaller arrays
     */
    chunkArray(array, size) {
        const chunks = [];
        for (let i = 0; i < array.length; i += size) {
            chunks.push(array.slice(i, i + size));
        }
        return chunks;
    }

    /**
     * Get sync status
     */
    getStatus() {
        return {
            isSyncing: this.isSyncing,
            lastSyncTime: this.lastSyncTime,
            isAutoSyncActive: !!this.syncIntervalId
        };
    }

    /**
     * Logging helper
     */
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[SyncManager] ${message}`, data);
        }
    }

    /**
     * Cleanup
     */
    destroy() {
        this.stopAutoSync();
        this.syncListeners.clear();
    }
}
