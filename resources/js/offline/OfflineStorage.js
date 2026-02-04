/**
 * OfflineStorage.js
 * 
 * Manages local storage of messages, conversations, and pending messages
 * using IndexedDB for reliable offline-first functionality.
 * 
 * MULTI-ACCOUNT SUPPORT: All data is isolated by account_id to prevent
 * data leakage between accounts on the same device.
 */

export class OfflineStorage {
    constructor(dbName = 'GekyChatDB', version = 3) {
        this.dbName = dbName;
        this.version = version;
        this.db = null;
        this.currentAccountId = this.getCurrentAccountId();
    }

    /**
     * Get current active account ID
     * Falls back to 1 if not set (backward compatibility)
     */
    getCurrentAccountId() {
        try {
            // Check if multi-account is enabled and get active account
            const activeAccount = localStorage.getItem('active_account_id');
            if (activeAccount) {
                return parseInt(activeAccount, 10);
            }
            
            // Fallback: use authenticated user's ID
            const userId = window.APP?.userId || window.currentUserId;
            if (userId) {
                return parseInt(userId, 10);
            }
            
            // Default account ID for backward compatibility
            return 1;
        } catch (error) {
            console.warn('Failed to get account ID, using default:', error);
            return 1;
        }
    }

    /**
     * Set active account ID (call this when switching accounts)
     */
    setAccountId(accountId) {
        this.currentAccountId = parseInt(accountId, 10);
        localStorage.setItem('active_account_id', this.currentAccountId.toString());
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
                const oldVersion = event.oldVersion;
                const transaction = event.target.transaction;

                // VERSION 1: Initial schema
                if (oldVersion < 1) {
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
                }

                // VERSION 2: Add account_id for multi-account support
                if (oldVersion < 2) {
                    console.log('Migrating IndexedDB to v2 - adding account isolation...');
                    
                    // Add account_id to messages
                    if (db.objectStoreNames.contains('messages')) {
                        const messagesStore = transaction.objectStore('messages');
                        
                        // Add new composite indexes with account_id
                        if (!messagesStore.indexNames.contains('account_conversation')) {
                            messagesStore.createIndex('account_conversation', ['account_id', 'conversation_id'], { unique: false });
                        }
                        if (!messagesStore.indexNames.contains('account_group')) {
                            messagesStore.createIndex('account_group', ['account_id', 'group_id'], { unique: false });
                        }
                        if (!messagesStore.indexNames.contains('account_id')) {
                            messagesStore.createIndex('account_id', 'account_id', { unique: false });
                        }

                        // Migrate existing messages: add account_id = 1
                        messagesStore.openCursor().onsuccess = (event) => {
                            const cursor = event.target.result;
                            if (cursor) {
                                const message = cursor.value;
                                if (!message.account_id) {
                                    message.account_id = 1;
                                    cursor.update(message);
                                }
                                cursor.continue();
                            }
                        };
                    }

                    // Add account_id to pending_messages
                    if (db.objectStoreNames.contains('pending_messages')) {
                        const pendingStore = transaction.objectStore('pending_messages');
                        
                        if (!pendingStore.indexNames.contains('account_conversation')) {
                            pendingStore.createIndex('account_conversation', ['account_id', 'conversation_id'], { unique: false });
                        }
                        if (!pendingStore.indexNames.contains('account_id')) {
                            pendingStore.createIndex('account_id', 'account_id', { unique: false });
                        }

                        pendingStore.openCursor().onsuccess = (event) => {
                            const cursor = event.target.result;
                            if (cursor) {
                                const message = cursor.value;
                                if (!message.account_id) {
                                    message.account_id = 1;
                                    cursor.update(message);
                                }
                                cursor.continue();
                            }
                        };
                    }

                    // Add account_id to conversations
                    if (db.objectStoreNames.contains('conversations')) {
                        const conversationsStore = transaction.objectStore('conversations');
                        
                        if (!conversationsStore.indexNames.contains('account_id')) {
                            conversationsStore.createIndex('account_id', 'account_id', { unique: false });
                        }

                        conversationsStore.openCursor().onsuccess = (event) => {
                            const cursor = event.target.result;
                            if (cursor) {
                                const conversation = cursor.value;
                                if (!conversation.account_id) {
                                    conversation.account_id = 1;
                                    cursor.update(conversation);
                                }
                                cursor.continue();
                            }
                        };
                    }

                    // Add account_id to groups
                    if (db.objectStoreNames.contains('groups')) {
                        const groupsStore = transaction.objectStore('groups');
                        
                        if (!groupsStore.indexNames.contains('account_id')) {
                            groupsStore.createIndex('account_id', 'account_id', { unique: false });
                        }

                        groupsStore.openCursor().onsuccess = (event) => {
                            const cursor = event.target.result;
                            if (cursor) {
                                const group = cursor.value;
                                if (!group.account_id) {
                                    group.account_id = 1;
                                    cursor.update(group);
                                }
                                cursor.continue();
                            }
                        };
                    }

                    // Add account_id to media_cache
                    if (db.objectStoreNames.contains('media_cache')) {
                        const mediaStore = transaction.objectStore('media_cache');
                        
                        if (!mediaStore.indexNames.contains('account_id')) {
                            mediaStore.createIndex('account_id', 'account_id', { unique: false });
                        }

                        mediaStore.openCursor().onsuccess = (event) => {
                            const cursor = event.target.result;
                            if (cursor) {
                                const media = cursor.value;
                                if (!media.account_id) {
                                    media.account_id = 1;
                                    cursor.update(media);
                                }
                                cursor.continue();
                            }
                        };
                    }

                    console.log('IndexedDB migration to v2 complete - account isolation enabled');
                }

                // VERSION 3: Add draft_messages store
                if (oldVersion < 3) {
                    console.log('Migrating IndexedDB to v3 - adding draft messages...');
                    
                    // Create draft_messages store
                    if (!db.objectStoreNames.contains('draft_messages')) {
                        const draftsStore = db.createObjectStore('draft_messages', { 
                            keyPath: ['account_id', 'conversation_id'] 
                        });
                        
                        // Indexes for drafts
                        draftsStore.createIndex('account_id', 'account_id', { unique: false });
                        draftsStore.createIndex('conversation_id', 'conversation_id', { unique: false });
                        draftsStore.createIndex('saved_at', 'saved_at', { unique: false });
                    }
                    
                    console.log('IndexedDB migration to v3 complete - draft messages enabled');
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

            // Ensure account_id is set
            if (!message.account_id) {
                message.account_id = this.currentAccountId;
            }

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
     * Get messages for a conversation or group (account-scoped)
     */
    async getMessages(conversationId = null, groupId = null, limit = 100) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['messages'], 'readonly');
            const store = transaction.objectStore('messages');
            
            // Use composite index for account-scoped queries
            const indexName = conversationId ? 'account_conversation' : 'account_group';
            const index = store.index(indexName);
            const key = [this.currentAccountId, conversationId || groupId];
            
            const request = index.getAll(key);

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
     * Add message to pending queue (account-scoped)
     */
    async addPendingMessage(messageData) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['pending_messages'], 'readwrite');
            const store = transaction.objectStore('pending_messages');

            const pendingMessage = {
                client_uuid: messageData.client_uuid || this.generateClientUUID(),
                account_id: this.currentAccountId,
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
     * Get all pending messages for current account
     */
    async getPendingMessages() {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['pending_messages'], 'readonly');
            const store = transaction.objectStore('pending_messages');
            const index = store.index('account_id');
            const request = index.getAll(this.currentAccountId);

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
     * Save conversation metadata (account-scoped)
     */
    async saveConversation(conversation) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['conversations'], 'readwrite');
            const store = transaction.objectStore('conversations');
            
            // Ensure account_id is set
            if (!conversation.account_id) {
                conversation.account_id = this.currentAccountId;
            }
            
            conversation.updated_at = new Date().toISOString();
            const request = store.put(conversation);

            request.onsuccess = () => resolve(conversation);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get conversation metadata (account-scoped)
     */
    async getConversation(id) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['conversations'], 'readonly');
            const store = transaction.objectStore('conversations');
            const request = store.get(id);

            request.onsuccess = () => {
                const result = request.result;
                // Verify it belongs to current account
                if (result && result.account_id === this.currentAccountId) {
                    resolve(result);
                } else {
                    resolve(null);
                }
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Save group metadata (account-scoped)
     */
    async saveGroup(group) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['groups'], 'readwrite');
            const store = transaction.objectStore('groups');
            
            // Ensure account_id is set
            if (!group.account_id) {
                group.account_id = this.currentAccountId;
            }
            
            group.updated_at = new Date().toISOString();
            const request = store.put(group);

            request.onsuccess = () => resolve(group);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get group metadata (account-scoped)
     */
    async getGroup(id) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['groups'], 'readonly');
            const store = transaction.objectStore('groups');
            const request = store.get(id);

            request.onsuccess = () => {
                const result = request.result;
                // Verify it belongs to current account
                if (result && result.account_id === this.currentAccountId) {
                    resolve(result);
                } else {
                    resolve(null);
                }
            };
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
     * Clear all data for current account only (account-scoped)
     */
    async clearAll() {
        if (!this.db) await this.init();

        const stores = ['messages', 'pending_messages', 'conversations', 'groups', 'media_cache'];
        
        return Promise.all(
            stores.map(storeName => {
                return new Promise((resolve, reject) => {
                    const transaction = this.db.transaction([storeName], 'readwrite');
                    const store = transaction.objectStore(storeName);
                    const index = store.index('account_id');
                    const request = index.openCursor(IDBKeyRange.only(this.currentAccountId));

                    request.onsuccess = (event) => {
                        const cursor = event.target.result;
                        if (cursor) {
                            cursor.delete();
                            cursor.continue();
                        } else {
                            resolve();
                        }
                    };

                    request.onerror = () => reject(request.error);
                });
            })
        );
    }

    /**
     * Clear all data for ALL accounts (use with extreme caution)
     */
    async clearAllAccounts() {
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
     * Get storage statistics for current account
     */
    async getStats() {
        if (!this.db) await this.init();

        const stats = { account_id: this.currentAccountId };

        const stores = ['messages', 'pending_messages', 'conversations', 'groups', 'draft_messages'];
        
        for (const storeName of stores) {
            await new Promise((resolve) => {
                const transaction = this.db.transaction([storeName], 'readonly');
                const store = transaction.objectStore(storeName);
                
                if (storeName === 'draft_messages') {
                    // Count drafts for current account
                    const index = store.index('account_id');
                    const request = index.count(this.currentAccountId);
                    request.onsuccess = () => {
                        stats[storeName] = request.result;
                        resolve();
                    };
                } else {
                    const index = store.index('account_id');
                    const request = index.count(this.currentAccountId);
                    request.onsuccess = () => {
                        stats[storeName] = request.result;
                        resolve();
                    };
                }
            });
        }

        return stats;
    }

    /* ===================== DRAFT MESSAGES ===================== */

    /**
     * Save or update draft message (account-scoped)
     */
    async saveDraft(conversationId, content, options = {}) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['draft_messages'], 'readwrite');
            const store = transaction.objectStore('draft_messages');

            const draft = {
                account_id: this.currentAccountId,
                conversation_id: conversationId,
                content: content || '',
                media_urls: options.media_urls || [],
                reply_to_id: options.reply_to_id || null,
                mentions: options.mentions || [],
                saved_at: new Date().toISOString(),
                created_at: options.created_at || new Date().toISOString(),
            };

            const request = store.put(draft);

            request.onsuccess = () => resolve(draft);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get draft for a conversation (account-scoped)
     */
    async getDraft(conversationId) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['draft_messages'], 'readonly');
            const store = transaction.objectStore('draft_messages');
            const key = [this.currentAccountId, conversationId];
            const request = store.get(key);

            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Delete draft for a conversation (account-scoped)
     */
    async deleteDraft(conversationId) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['draft_messages'], 'readwrite');
            const store = transaction.objectStore('draft_messages');
            const key = [this.currentAccountId, conversationId];
            const request = store.delete(key);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get all drafts for current account
     */
    async getAllDrafts() {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['draft_messages'], 'readonly');
            const store = transaction.objectStore('draft_messages');
            const index = store.index('account_id');
            const request = index.getAll(this.currentAccountId);

            request.onsuccess = () => {
                const drafts = request.result || [];
                // Sort by saved_at descending
                drafts.sort((a, b) => {
                    return new Date(b.saved_at).getTime() - new Date(a.saved_at).getTime();
                });
                resolve(drafts);
            };

            request.onerror = () => reject(request.error);
        });
    }
}

// Export singleton instance
export const offlineStorage = new OfflineStorage();
