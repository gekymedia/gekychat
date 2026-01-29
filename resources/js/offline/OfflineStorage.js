/**
 * OfflineStorage.js
 * 
 * Manages local storage of messages, conversations, and pending messages
 * using IndexedDB for reliable offline-first functionality.
 */

export class OfflineStorage {
    constructor(dbName = 'GekyChatDB', version = 1) {
        this.dbName = dbName;
        this.version = version;
        this.db = null;
    }

    /**
     * Initialize IndexedDB database
     */
    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.version);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Messages store - stores all messages locally
                if (!db.objectStoreNames.contains('messages')) {
                    const messagesStore = db.createObjectStore('messages', { keyPath: 'id' });
                    messagesStore.createIndex('conversation_id', 'conversation_id', { unique: false });
                    messagesStore.createIndex('group_id', 'group_id', { unique: false });
                    messagesStore.createIndex('client_uuid', 'client_uuid', { unique: true });
                    messagesStore.createIndex('created_at', 'created_at', { unique: false });
                    messagesStore.createIndex('status', 'status', { unique: false });
                }

                // Pending messages store - messages waiting to be sent
                if (!db.objectStoreNames.contains('pending_messages')) {
                    const pendingStore = db.createObjectStore('pending_messages', { keyPath: 'client_uuid' });
                    pendingStore.createIndex('conversation_id', 'conversation_id', { unique: false });
                    pendingStore.createIndex('group_id', 'group_id', { unique: false });
                    pendingStore.createIndex('created_at', 'created_at', { unique: false });
                    pendingStore.createIndex('retry_count', 'retry_count', { unique: false });
                }

                // Conversations store - cache conversation metadata
                if (!db.objectStoreNames.contains('conversations')) {
                    const conversationsStore = db.createObjectStore('conversations', { keyPath: 'id' });
                    conversationsStore.createIndex('updated_at', 'updated_at', { unique: false });
                }

                // Groups store - cache group metadata
                if (!db.objectStoreNames.contains('groups')) {
                    const groupsStore = db.createObjectStore('groups', { keyPath: 'id' });
                    groupsStore.createIndex('updated_at', 'updated_at', { unique: false });
                }

                // Sync state store - tracks last sync timestamps
                if (!db.objectStoreNames.contains('sync_state')) {
                    const syncStore = db.createObjectStore('sync_state', { keyPath: 'key' });
                }

                // Media cache store - stores media metadata for offline access
                if (!db.objectStoreNames.contains('media_cache')) {
                    const mediaStore = db.createObjectStore('media_cache', { keyPath: 'url' });
                    mediaStore.createIndex('conversation_id', 'conversation_id', { unique: false });
                    mediaStore.createIndex('group_id', 'group_id', { unique: false });
                }
            };
        });
    }

    /**
     * Generate a unique client-side UUID for messages
     */
    generateClientUUID() {
        return `client_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }

    /**
     * Save a message locally
     */
    async saveMessage(message) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['messages'], 'readwrite');
            const store = transaction.objectStore('messages');

            // Ensure client_uuid exists
            if (!message.client_uuid) {
                message.client_uuid = this.generateClientUUID();
            }

            // Ensure status exists
            if (!message.status) {
                message.status = message.server_id ? 'sent' : 'pending';
            }

            // Store timestamp if not present
            if (!message.created_at) {
                message.created_at = new Date().toISOString();
            }

            const request = store.put(message);

            request.onsuccess = () => resolve(message);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get messages for a conversation or group
     */
    async getMessages(conversationId = null, groupId = null, limit = 100) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['messages'], 'readonly');
            const store = transaction.objectStore('messages');
            const index = conversationId 
                ? store.index('conversation_id')
                : store.index('group_id');
            
            const key = conversationId || groupId;
            const request = index.getAll(key);
            const messages = [];

            request.onsuccess = () => {
                const allMessages = request.result || [];
                
                // Sort by created_at descending
                allMessages.sort((a, b) => {
                    const timeA = new Date(a.created_at).getTime();
                    const timeB = new Date(b.created_at).getTime();
                    return timeB - timeA;
                });

                // Limit results
                const limited = allMessages.slice(0, limit);
                
                // Sort ascending for display
                limited.reverse();
                
                resolve(limited);
            };

            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get a single message by ID or client_uuid
     */
    async getMessage(idOrUUID) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['messages'], 'readonly');
            const store = transaction.objectStore('messages');
            
            // Try by ID first
            const request = store.get(idOrUUID);
            
            request.onsuccess = () => {
                if (request.result) {
                    resolve(request.result);
                    return;
                }
                
                // Try by client_uuid
                const uuidIndex = store.index('client_uuid');
                const uuidRequest = uuidIndex.get(idOrUUID);
                
                uuidRequest.onsuccess = () => resolve(uuidRequest.result || null);
                uuidRequest.onerror = () => reject(uuidRequest.error);
            };

            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Update message status
     */
    async updateMessageStatus(clientUUID, status, serverId = null) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['messages'], 'readwrite');
            const store = transaction.objectStore('messages');
            const index = store.index('client_uuid');
            const request = index.get(clientUUID);

            request.onsuccess = () => {
                const message = request.result;
                if (!message) {
                    reject(new Error('Message not found'));
                    return;
                }

                message.status = status;
                if (serverId) {
                    message.id = serverId;
                    message.server_id = serverId;
                }
                message.updated_at = new Date().toISOString();

                const updateRequest = store.put(message);
                updateRequest.onsuccess = () => resolve(message);
                updateRequest.onerror = () => reject(updateRequest.error);
            };

            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Add message to pending queue
     */
    async addPendingMessage(messageData) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['pending_messages'], 'readwrite');
            const store = transaction.objectStore('pending_messages');

            const pendingMessage = {
                client_uuid: messageData.client_uuid || this.generateClientUUID(),
                conversation_id: messageData.conversation_id || null,
                group_id: messageData.group_id || null,
                body: messageData.body,
                reply_to: messageData.reply_to || null,
                attachments: messageData.attachments || [],
                created_at: new Date().toISOString(),
                retry_count: 0,
                last_retry_at: null,
                status: 'pending',
                ...messageData
            };

            const request = store.put(pendingMessage);

            request.onsuccess = () => resolve(pendingMessage);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get all pending messages
     */
    async getPendingMessages() {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['pending_messages'], 'readonly');
            const store = transaction.objectStore('pending_messages');
            const request = store.getAll();

            request.onsuccess = () => {
                const messages = request.result || [];
                // Sort by created_at ascending (oldest first)
                messages.sort((a, b) => {
                    return new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
                });
                resolve(messages);
            };

            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Remove message from pending queue
     */
    async removePendingMessage(clientUUID) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['pending_messages'], 'readwrite');
            const store = transaction.objectStore('pending_messages');
            const request = store.delete(clientUUID);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Increment retry count for pending message
     */
    async incrementRetryCount(clientUUID) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['pending_messages'], 'readwrite');
            const store = transaction.objectStore('pending_messages');
            const request = store.get(clientUUID);

            request.onsuccess = () => {
                const message = request.result;
                if (!message) {
                    reject(new Error('Pending message not found'));
                    return;
                }

                message.retry_count = (message.retry_count || 0) + 1;
                message.last_retry_at = new Date().toISOString();

                const updateRequest = store.put(message);
                updateRequest.onsuccess = () => resolve(message);
                updateRequest.onerror = () => reject(updateRequest.error);
            };

            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Save conversation metadata
     */
    async saveConversation(conversation) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['conversations'], 'readwrite');
            const store = transaction.objectStore('conversations');
            
            conversation.updated_at = new Date().toISOString();
            const request = store.put(conversation);

            request.onsuccess = () => resolve(conversation);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get conversation metadata
     */
    async getConversation(id) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['conversations'], 'readonly');
            const store = transaction.objectStore('conversations');
            const request = store.get(id);

            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Save group metadata
     */
    async saveGroup(group) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['groups'], 'readwrite');
            const store = transaction.objectStore('groups');
            
            group.updated_at = new Date().toISOString();
            const request = store.put(group);

            request.onsuccess = () => resolve(group);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get group metadata
     */
    async getGroup(id) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['groups'], 'readonly');
            const store = transaction.objectStore('groups');
            const request = store.get(id);

            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Save sync state (last sync timestamp)
     */
    async saveSyncState(key, value) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['sync_state'], 'readwrite');
            const store = transaction.objectStore('sync_state');
            
            const request = store.put({ key, value, updated_at: new Date().toISOString() });

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get sync state
     */
    async getSyncState(key) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['sync_state'], 'readonly');
            const store = transaction.objectStore('sync_state');
            const request = store.get(key);

            request.onsuccess = () => {
                const result = request.result;
                resolve(result ? result.value : null);
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Clear all data (use with caution)
     */
    async clearAll() {
        if (!this.db) await this.init();

        const stores = ['messages', 'pending_messages', 'conversations', 'groups', 'sync_state', 'media_cache'];
        
        return Promise.all(
            stores.map(storeName => {
                return new Promise((resolve, reject) => {
                    const transaction = this.db.transaction([storeName], 'readwrite');
                    const store = transaction.objectStore(storeName);
                    const request = store.clear();

                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                });
            })
        );
    }

    /**
     * Get storage statistics
     */
    async getStats() {
        if (!this.db) await this.init();

        const stats = {};

        const stores = ['messages', 'pending_messages', 'conversations', 'groups'];
        
        for (const storeName of stores) {
            await new Promise((resolve) => {
                const transaction = this.db.transaction([storeName], 'readonly');
                const store = transaction.objectStore(storeName);
                const request = store.count();

                request.onsuccess = () => {
                    stats[storeName] = request.result;
                    resolve();
                };
            });
        }

        return stats;
    }
}

// Export singleton instance
export const offlineStorage = new OfflineStorage();
