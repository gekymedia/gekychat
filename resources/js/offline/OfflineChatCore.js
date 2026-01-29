/**
 * OfflineChatCore.js
 * 
 * Extension of ChatCore that adds offline-first functionality
 * Integrates with OfflineStorage, SyncManager, and ConnectivityManager
 */

import { ChatCore } from '../chat/ChatCore.js';
import { offlineStorage } from './OfflineStorage.js';
import { SyncManager } from './SyncManager.js';
import { connectivityManager } from './ConnectivityManager.js';

export class OfflineChatCore extends ChatCore {
    constructor(config = {}) {
        super(config);

        // Offline-specific config
        this.offlineConfig = {
            enableOffline: config.enableOffline !== false, // Default: enabled
            loadFromCache: config.loadFromCache !== false, // Default: enabled
            autoSync: config.autoSync !== false, // Default: enabled
            ...config.offline
        };

        // Offline state
        this.offlineInitialized = false;
        this.pendingMessagesCount = 0;
        this.isLoadingFromCache = false;

        // Initialize offline functionality
        if (this.offlineConfig.enableOffline) {
            this.initOffline();
        }
    }

    /**
     * Initialize offline functionality
     */
    async initOffline() {
        try {
            // Initialize IndexedDB
            await offlineStorage.init();

            // Initialize sync manager
            this.syncManager = new SyncManager({
                messageUrl: this.config.messageUrl,
                messagesUrl: '/api/v1/chats',
                debug: this.config.debug
            });

            // Listen to connectivity changes
            connectivityManager.onConnectivityChange((status) => {
                this.handleConnectivityChange(status);
            });

            // Listen to sync events
            this.syncManager.onSyncEvent((event) => {
                this.handleSyncEvent(event);
            });

            // Load cached messages if enabled
            if (this.offlineConfig.loadFromCache) {
                await this.loadCachedMessages();
            }

            // Update pending messages count
            await this.updatePendingCount();

            this.offlineInitialized = true;
            this.log('Offline functionality initialized');
        } catch (error) {
            this.handleError('Failed to initialize offline functionality', error);
        }
    }

    /**
     * Load cached messages from IndexedDB
     */
    async loadCachedMessages() {
        if (this.isLoadingFromCache) return;
        
        this.isLoadingFromCache = true;
        this.triggerEvent('loadingFromCache', { started: true });

        try {
            const conversationId = this.config.conversationId;
            const groupId = this.config.groupId;

            if (!conversationId && !groupId) {
                this.isLoadingFromCache = false;
                return;
            }

            // Get cached messages
            const cachedMessages = await offlineStorage.getMessages(conversationId, groupId, 100);

            if (cachedMessages.length > 0) {
                this.log(`Loading ${cachedMessages.length} cached messages`);

                // Clear existing messages in UI (optional, depends on your UI)
                // this.elements.messageContainer.innerHTML = '';

                // Add cached messages to UI
                for (const message of cachedMessages) {
                    // Only add if not already in UI
                    const existing = document.querySelector(`[data-message-id="${message.id || message.client_uuid}"]`);
                    if (!existing) {
                        this.addMessageToUI(this.normalizeMessagePayload(message), { prepend: false });
                    }
                }

                // Scroll to bottom after loading
                setTimeout(() => {
                    if (this.config.autoScroll) {
                        this.scrollToBottom();
                    }
                }, 100);

                this.triggerEvent('cacheLoaded', { count: cachedMessages.length });
            }

            // Sync new messages if online
            if (connectivityManager.isOnline) {
                this.syncManager.syncThread(
                    conversationId || groupId,
                    conversationId ? 'dm' : 'group'
                ).catch(error => {
                    this.log('Error syncing thread:', error);
                });
            }
        } catch (error) {
            this.handleError('Failed to load cached messages', error);
        } finally {
            this.isLoadingFromCache = false;
            this.triggerEvent('loadingFromCache', { started: false });
        }
    }

    /**
     * Enhanced sendMessage with offline support
     */
    async sendMessage(content, options = {}) {
        if (!this.config.messageUrl) {
            throw new Error('Message URL not configured');
        }

        // Generate client UUID for offline tracking
        const clientUUID = offlineStorage.generateClientUUID();

        // Prepare message data
        const messageData = {
            client_uuid: clientUUID,
            body: content,
            conversation_id: this.config.conversationId,
            group_id: this.config.groupId,
            reply_to: options.replyTo,
            attachments: options.attachments || [],
            sender_id: this.config.userId,
            status: 'pending',
            created_at: new Date().toISOString(),
            is_own: true
        };

        // Add to UI immediately (optimistic update)
        this.addMessageToUI(this.normalizeMessagePayload(messageData), { scrollTo: true });

        // Save to local storage
        await offlineStorage.saveMessage(messageData);
        await offlineStorage.addPendingMessage(messageData);

        // Update pending count
        await this.updatePendingCount();

        // Try to send immediately if online
        if (connectivityManager.isOnline) {
            try {
                const payload = {
                    client_uuid: clientUUID,
                    body: content,
                    conversation_id: this.config.conversationId,
                    group_id: this.config.groupId,
                    reply_to: options.replyTo,
                    forward_from: options.forwardFrom,
                    attachments: options.attachments || [],
                    ...options
                };

                const res = await window.api.post(this.config.messageUrl, payload);

                if (res.data?.message) {
                    const serverMessage = res.data.message;

                    // Update local message with server ID
                    await offlineStorage.updateMessageStatus(
                        clientUUID,
                        'sent',
                        serverMessage.id
                    );

                    // Remove from pending queue
                    await offlineStorage.removePendingMessage(clientUUID);

                    // Update UI message with server ID
                    const messageElement = document.querySelector(`[data-message-id="${clientUUID}"]`);
                    if (messageElement) {
                        messageElement.setAttribute('data-message-id', serverMessage.id);
                        messageElement.setAttribute('data-client-uuid', clientUUID);
                    }

                    // Update pending count
                    await this.updatePendingCount();

                    return res.data;
                }
            } catch (error) {
                // If send fails, message remains in pending queue
                this.log('Failed to send message immediately, will retry:', error);
                
                // Update UI to show pending status
                this.updateMessageStatusInUI(clientUUID, 'pending');
            }
        } else {
            // Offline - message is already saved and will sync when online
            this.log('Offline: Message saved locally, will sync when online');
            this.updateMessageStatusInUI(clientUUID, 'pending');
        }

        return { message: messageData, pending: true };
    }

    /**
     * Handle connectivity changes
     */
    handleConnectivityChange(status) {
        this.log('Connectivity changed:', status);

        if (status.isOnline) {
            // Coming online - trigger sync
            this.triggerEvent('online');
            this.syncManager.sync();
        } else {
            // Going offline
            this.triggerEvent('offline');
        }

        // Update UI indicators
        this.updateConnectionIndicator(status);
    }

    /**
     * Handle sync events
     */
    handleSyncEvent(event) {
        this.log('Sync event:', event);

        if (event.event === 'messageSynced') {
            const { clientUUID, serverId } = event.data;
            
            // Update UI message
            const messageElement = document.querySelector(`[data-client-uuid="${clientUUID}"]`);
            if (messageElement) {
                messageElement.setAttribute('data-message-id', serverId);
                this.updateMessageStatusInUI(clientUUID, 'sent');
            }
        }

        if (event.event === 'syncCompleted') {
            // Update pending count
            this.updatePendingCount();
        }
    }

    /**
     * Update message status in UI
     */
    updateMessageStatusInUI(clientUUID, status) {
        const messageElement = document.querySelector(`[data-client-uuid="${clientUUID}"]`);
        if (!messageElement) return;

        const statusIndicator = messageElement.querySelector('.status-indicator');
        if (!statusIndicator) return;

        let icon = '';
        switch (status) {
            case 'pending':
                icon = '<i class="bi bi-clock text-muted" title="Pending"></i>';
                break;
            case 'sent':
                icon = '<i class="bi bi-check2 text-muted" title="Sent"></i>';
                break;
            case 'delivered':
                icon = '<i class="bi bi-check2-all text-muted" title="Delivered"></i>';
                break;
            case 'read':
                icon = '<i class="bi bi-check2-all text-primary" title="Read"></i>';
                break;
            case 'failed':
                icon = '<i class="bi bi-x-circle text-danger" title="Failed"></i>';
                break;
        }

        statusIndicator.innerHTML = icon;
    }

    /**
     * Update connection indicator in UI
     */
    updateConnectionIndicator(status) {
        // Dispatch event for UI to handle
        const event = new CustomEvent('connectionStatusChanged', {
            detail: {
                isOnline: status.isOnline,
                quality: status.quality
            }
        });
        document.dispatchEvent(event);
    }

    /**
     * Update pending messages count
     */
    async updatePendingCount() {
        try {
            const pending = await offlineStorage.getPendingMessages();
            this.pendingMessagesCount = pending.length;

            // Dispatch event for UI
            const event = new CustomEvent('pendingMessagesCountChanged', {
                detail: { count: this.pendingMessagesCount }
            });
            document.dispatchEvent(event);
        } catch (error) {
            this.log('Error updating pending count:', error);
        }
    }

    /**
     * Handle incoming message - also save to cache
     */
    handleIncomingMessage(event) {
        // Call parent handler
        super.handleIncomingMessage(event);

        // Save to local storage
        if (this.offlineInitialized) {
            const msg = this.normalizeMessagePayload(event);
            offlineStorage.saveMessage({
                ...msg,
                status: 'sent',
                server_id: msg.id
            }).catch(error => {
                this.log('Error saving incoming message to cache:', error);
            });
        }
    }

    /**
     * Get offline status
     */
    getOfflineStatus() {
        return {
            isOnline: connectivityManager.isOnline,
            quality: connectivityManager.connectionQuality,
            pendingCount: this.pendingMessagesCount,
            isSyncing: this.syncManager?.isSyncing || false,
            lastSyncTime: this.syncManager?.lastSyncTime || null
        };
    }

    /**
     * Force sync
     */
    async forceSync() {
        if (!this.syncManager) {
            throw new Error('Sync manager not initialized');
        }

        return this.syncManager.sync();
    }

    /**
     * Cleanup
     */
    destroy() {
        // Cleanup offline resources
        if (this.syncManager) {
            this.syncManager.destroy();
        }

        // Call parent cleanup
        super.destroy();
    }
}
