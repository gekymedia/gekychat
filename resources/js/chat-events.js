// resources/js/chat-events.js - FIXED COMPLETE VERSION

/**
 * GekyChat Real-time Event System
 * Handles all WebSocket events for both direct messages and group chats
 */

class ChatEventSystem {
     constructor() {
        // Prevent duplicate initialization
        if (window.chatEventsInitialized) {
            console.log('🔄 Chat events already initialized, skipping...');
            return;
        }
        window.chatEventsInitialized = true;

        this.echo = window.Echo;
        this.currentUserId = window.currentUserId;
        this.currentConversationId = null;
        this.currentGroupId = null;
        this.typingTimeouts = new Map();
        this.typingStates = new Map();
        this.isTyping = false;
        this.typingTimeout = null;
        
        this.init();
    }

    init() {
        console.log('🎯 Chat Event System Initializing...');
        
        // Use onDomReady if available, otherwise wait for DOMContentLoaded
        if (typeof window.onDomReady === 'function') {
            window.onDomReady(() => {
                this.setupEventSystem();
            });
        } else {
            // Fallback if onDomReady not available yet
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    this.setupEventSystem();
                });
            } else {
                setTimeout(() => this.setupEventSystem(), 100);
            }
        }
    }

    setupEventSystem() {
        // Wait for Echo to be ready
        if (window.Echo && window.Echo.socketId && window.Echo.socketId() !== 'no-op-socket-id') {
            console.log('✅ Echo available - setting up event listeners');
            this.echo = window.Echo;
            this.setupGlobalEventListeners();
            this.setupChatSpecificListeners();
        } else {
            // Wait for echo:ready event
            document.addEventListener('echo:ready', (event) => {
                const { echo, isNoOp } = event.detail;
                if (isNoOp) {
                    console.log('⚠️ Using no-op Echo - real-time features disabled');
                    return;
                }
                this.echo = echo;
                console.log('✅ Echo ready - setting up event listeners');
                this.setupGlobalEventListeners();
                this.setupChatSpecificListeners();
            });
        }

        // Setup DOM event listeners
        this.setupDOMEventListeners();
    }

    setupGlobalEventListeners() {
        const conn = this.echo?.connector?.pusher?.connection;
        if (conn?.bind) {
            conn.bind('connected', () => {
                console.log('🔗 WebSocket connected - subscribing to channels');
                this.resubscribeToCurrentChat();
            });
            conn.bind('error', (error) => {
                console.error('🔴 WebSocket error:', error);
                this.showConnectionError();
            });
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) this.clearAllTypingIndicators();
        });
    }

    /**
     * Setup chat-specific listeners based on current page
     */
    setupChatSpecificListeners() {
        const config = window.__chatCoreConfig || {};
        
        if (config.conversationId) {
            this.setupDirectMessageListeners(config.conversationId);
        } else if (config.groupId) {
            this.setupGroupChatListeners(config.groupId);
        }
    }

    /**
     * Direct Message Event Listeners
     */
    setupDirectMessageListeners(conversationId) {
        if (!conversationId) return;

        this.currentConversationId = conversationId;
        const channel = `chat.${conversationId}`;

        console.log(`💬 Setting up DM listeners for conversation ${conversationId}`);

        // New message received
        this.echo.private(channel)
            .listen('MessageSent', (event) => {
                console.log('💌 New DM message received:', event);
                this.handleNewMessage(event, 'direct');
            })
            .error((error) => {
                console.error('❌ DM channel subscription error:', error);
            });

        // Message edited
        this.echo.private(channel)
            .listen('MessageEdited', (event) => {
                console.log('✏️ Message edited:', event);
                this.handleMessageEdited(event, 'direct');
            });

        // Message deleted
        this.echo.private(channel)
            .listen('MessageDeleted', (event) => {
                console.log('🗑️ Message deleted:', event);
                this.handleMessageDeleted(event, 'direct');
            });

        // Message read receipt
        this.echo.private(channel)
            .listen('MessageRead', (event) => {
                console.log('📖 Message read:', event);
                this.handleMessageRead(event, 'direct');
            });

        // Typing indicator
        this.echo.private(channel)
            .listen('UserTyping', (event) => {
                console.log('⌨️ User typing:', event);
                this.handleTypingIndicator(event, 'direct');
            });

        // Message reaction
        this.echo.private(channel)
            .listen('MessageReacted', (event) => {
                console.log('❤️ Message reaction:', event);
                this.handleMessageReaction(event, 'direct');
            });

        // Message status updated (sent/delivered/read)
        this.echo.private(channel)
            .listen('MessageStatusUpdated', (event) => {
                console.log('📊 Message status updated:', event);
                this.handleMessageStatus(event, 'direct');
            });
    }

    /**
     * Group Chat Event Listeners
     */
    setupGroupChatListeners(groupId) {
        if (!groupId) return;

        this.currentGroupId = groupId;
        const channel = `group.${groupId}`;

        console.log(`👥 Setting up group listeners for group ${groupId}`);

        // New group message
        this.echo.private(channel)
            .listen('GroupMessageSent', (event) => {
                console.log('💌 New group message received:', event);
                this.handleNewMessage(event, 'group');
            })
            .error((error) => {
                console.error('❌ Group channel subscription error:', error);
            });

        // Group message edited
        this.echo.private(channel)
            .listen('GroupMessageEdited', (event) => {
                console.log('✏️ Group message edited:', event);
                this.handleMessageEdited(event, 'group');
            });

        // Group message deleted
        this.echo.private(channel)
            .listen('GroupMessageDeleted', (event) => {
                console.log('🗑️ Group message deleted:', event);
                this.handleMessageDeleted(event, 'group');
            });

        // Group typing indicator
        this.echo.private(channel)
            .listen('GroupTyping', (event) => {
                console.log('⌨️ Group user typing:', event);
                this.handleTypingIndicator(event, 'group');
            });

        // Group message reaction
        this.echo.private(channel)
            .listen('MessageReacted', (event) => {
                console.log('❤️ Group message reaction:', event);
                this.handleMessageReaction(event, 'group');
            });

        // Group updates (member added/removed, etc.)
        this.echo.private(channel)
            .listen('GroupUpdated', (event) => {
                console.log('🔄 Group updated:', event);
                this.handleGroupUpdate(event);
            });

        // Group message read receipt
        this.echo.private(channel)
            .listen('GroupMessageRead', (event) => {
                console.log('📖 Group message read:', event);
                this.handleMessageRead(event, 'group');
            });
    }

    /**
     * Event Handlers
     */
    handleNewMessage(event, type) {
        // Don't show our own messages (they're already in the UI)
        if (event.message?.sender_id === this.currentUserId) {
            return;
        }

        // Add message to UI
        if (event.html) {
            // If HTML is provided, insert it directly
            this.insertMessageHTML(event.html, type);
        } else if (event.message) {
            // If message object is provided, render it
            this.renderMessage(event.message, type);
        }

        // Play notification sound
        this.playNotificationSound();

        // Update unread count
        this.updateUnreadCount(type, 1);

        // Mark as read if chat is active
        if (this.isChatActive()) {
            this.markMessageAsRead(event.message?.id, type);
        }
    }

    handleMessageEdited(event, type) {
        const messageId = event.message?.id || event.id;
        const newBody = event.message?.body || event.body;

        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
        if (messageElement) {
            const bodyElement = messageElement.querySelector('.message-body');
            if (bodyElement) {
                bodyElement.textContent = newBody;
                
                // Add edited indicator
                if (!messageElement.querySelector('.edited-indicator')) {
                    const indicator = document.createElement('span');
                    indicator.className = 'edited-indicator small text-muted ms-1';
                    indicator.textContent = '(edited)';
                    bodyElement.appendChild(indicator);
                }

                messageElement.classList.add('message-edited');
            }
        }
    }

    handleMessageDeleted(event, type) {
        const messageId = event.message_id || event.id;
        const deletedBy = event.deleted_by;

        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
        if (messageElement) {
            if (deletedBy === this.currentUserId) {
                // We deleted it - remove immediately
                messageElement.remove();
            } else {
                // Someone else deleted it - show "message deleted"
                this.showDeletedMessage(messageElement, type);
            }
        }
    }

    handleMessageRead(event, type) {
        const messageIds = event.message_ids || [event.message_id];
        const readerId = event.reader_id;

        // Don't update UI for our own read receipts
        if (readerId === this.currentUserId) return;

        messageIds.forEach(messageId => {
            const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
            if (messageElement) {
                // Update read status indicator
                const statusElement = messageElement.querySelector('.message-status');
                if (statusElement) {
                    statusElement.textContent = 'Read';
                    statusElement.classList.add('read');
                }
            }
        });
    }

    handleTypingIndicator(event, type) {
        const userId = event.user_id;
        const isTyping = event.is_typing;
        const userName = event.user_name || 'User';

        if (userId === this.currentUserId) return;

        const typingContainer = this.getTypingContainer(type);
        if (!typingContainer) return;

        if (isTyping) {
            this.showTypingIndicator(typingContainer, userId, userName);
        } else {
            this.hideTypingIndicator(typingContainer, userId);
        }
    }

    handleMessageReaction(event, type) {
        const messageId = event.message_id;
        const userId = event.user_id;
        const reaction = event.reaction;

        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!messageElement) return;

        // Update reactions UI
        this.updateMessageReactions(messageElement, userId, reaction);
    }

    handleMessageStatus(event, type) {
        const messageId = event.message_id;
        const status = event.status;

        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
        if (messageElement) {
            this.updateMessageStatus(messageElement, status);
        }
    }

    handleGroupUpdate(event) {
        const updateType = event.update_type;
        const changedData = event.changed_data;

        console.log(`🔄 Group update: ${updateType}`, changedData);

        switch (updateType) {
            case 'member_added':
                this.showGroupNotification(`${changedData.count} members added`, 'info');
                this.updateGroupMembersList();
                break;
                
            case 'member_removed':
                this.showGroupNotification(`Member removed: ${changedData.user_name}`, 'warning');
                this.updateGroupMembersList();
                break;
                
            case 'member_promoted':
                this.showGroupNotification(`Member promoted to admin`, 'info');
                break;
                
            case 'info_updated':
                this.showGroupNotification('Group info updated', 'info');
                this.updateGroupHeader();
                break;
                
            case 'ownership_transferred':
                this.showGroupNotification('Group ownership transferred', 'info');
                this.updateGroupHeader();
                break;
        }
    }

    /**
     * UI Update Methods
     */
    insertMessageHTML(html, type) {
        const container = document.getElementById('messages-container');
        if (!container) {
            console.error('❌ Messages container not found');
            return;
        }

        // Create temporary container
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        const newMessage = tempDiv.firstElementChild;

        if (!newMessage) {
            console.error('❌ Could not parse message HTML');
            return;
        }

        // Add animation
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
        if (typeof window.scrollChatToBottom === 'function') {
            window.scrollChatToBottom();
        }

        // Dispatch event for other components
        document.dispatchEvent(new CustomEvent('newMessageAdded', {
            detail: { message: newMessage, type }
        }));

        console.log('✅ Message added to UI via real-time');
    }

    renderMessage(messageData, type) {
        // This would use your existing message rendering logic
        // For now, we'll rely on the backend sending HTML
        console.log('📝 Rendering message from data:', messageData);
    }

    showTypingIndicator(container, userId, userName) {
        // Clear existing timeout for this user
        if (this.typingTimeouts.has(userId)) {
            clearTimeout(this.typingTimeouts.get(userId));
        }

        // Create or update typing indicator
        let indicator = container.querySelector(`[data-typing-user="${userId}"]`);
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'typing-indicator';
            indicator.setAttribute('data-typing-user', userId);
            indicator.innerHTML = `
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <span class="typing-text">${userName} is typing...</span>
            `;
            container.appendChild(indicator);
        }

        // Show container
        container.style.display = 'block';

        // Set timeout to hide indicator
        const timeout = setTimeout(() => {
            this.hideTypingIndicator(container, userId);
        }, 3000);

        this.typingTimeouts.set(userId, timeout);
    }

    hideTypingIndicator(container, userId) {
        const indicator = container.querySelector(`[data-typing-user="${userId}"]`);
        if (indicator) {
            indicator.remove();
        }

        // Hide container if no more typing indicators
        if (container.children.length === 0) {
            container.style.display = 'none';
        }

        // Clear timeout
        if (this.typingTimeouts.has(userId)) {
            clearTimeout(this.typingTimeouts.get(userId));
            this.typingTimeouts.delete(userId);
        }
    }

    clearAllTypingIndicators() {
        const containers = document.querySelectorAll('.typing-indicator-container');
        containers.forEach(container => {
            container.innerHTML = '';
            container.style.display = 'none';
        });

        // Clear all timeouts
        this.typingTimeouts.forEach((timeout, userId) => {
            clearTimeout(timeout);
        });
        this.typingTimeouts.clear();
    }

    showDeletedMessage(messageElement, type) {
        messageElement.classList.add('message-deleted');
        
        const bodyElement = messageElement.querySelector('.message-body');
        if (bodyElement) {
            bodyElement.innerHTML = '<em class="text-muted">This message was deleted</em>';
        }

        // Remove attachments and other interactive elements
        const attachments = messageElement.querySelector('.message-attachments');
        if (attachments) attachments.remove();

        const actions = messageElement.querySelector('.message-actions');
        if (actions) actions.remove();
    }

    updateMessageReactions(messageElement, userId, reaction) {
        // Implementation for updating reactions UI
        console.log('Updating reactions for message:', messageElement, userId, reaction);
    }

    updateMessageStatus(messageElement, status) {
        const statusElement = messageElement.querySelector('.message-status');
        if (statusElement) {
            statusElement.textContent = status;
            statusElement.className = `message-status status-${status}`;
        }
    }

    /**
     * Utility Methods
     */
    getTypingContainer(type) {
        if (type === 'direct') {
            return document.querySelector('.typing-indicator-container.direct');
        } else if (type === 'group') {
            return document.querySelector('.typing-indicator-container.group');
        }
        return document.querySelector('.typing-indicator-container');
    }

    isChatActive() {
        return !document.hidden && document.hasFocus();
    }

    playNotificationSound() {
        // Play notification sound if enabled
        if (window.Notification && Notification.permission === 'granted' && !this.isChatActive()) {
            try {
                const audio = new Audio('/sounds/notification.mp3');
                audio.play().catch(() => {
                    // Silent fail if audio can't play
                });
            } catch (error) {
                console.log('🔇 Notification sound disabled or failed');
            }
        }
    }

    updateUnreadCount(type, change) {
        // Update sidebar unread counts
        const event = new CustomEvent('unreadCountUpdated', {
            detail: { type, change }
        });
        document.dispatchEvent(event);
    }

    markMessageAsRead(messageId, type) {
        if (!messageId) return;

        const url = type === 'direct' 
            ? '/chat/messages/read'
            : `/groups/${this.currentGroupId}/messages/read`;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken
            },
            body: JSON.stringify({
                message_ids: [messageId]
            })
        }).catch(console.error);
    }

    showGroupNotification(message, type = 'info') {
        // Use your existing toast/notification system
        if (window.showToast) {
            window.showToast(message, type);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    updateGroupMembersList() {
        // Trigger group members list refresh
        const event = new CustomEvent('groupMembersUpdated');
        document.dispatchEvent(event);
    }

    updateGroupHeader() {
        // Trigger group header refresh
        const event = new CustomEvent('groupInfoUpdated');
        document.dispatchEvent(event);
    }

    showConnectionError() {
        if (window.showToast) {
            window.showToast('Connection lost. Reconnecting...', 'error');
        }
    }

    resubscribeToCurrentChat() {
        if (this.currentConversationId) {
            this.setupDirectMessageListeners(this.currentConversationId);
        } else if (this.currentGroupId) {
            this.setupGroupChatListeners(this.currentGroupId);
        }
    }

    /**
     * DOM Event Listeners for user interactions
     */
    setupDOMEventListeners() {
        // Handle message input typing
        document.addEventListener('input', (e) => {
            if (e.target.matches('#message-input, .message-input')) {
                this.handleUserTyping();
            }
        });

        // Handle message form submission
        document.addEventListener('submit', (e) => {
            if (e.target.matches('#chat-form, #message-form')) {
                this.handleUserStoppedTyping();
            }
        });

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.handleUserStoppedTyping();
            }
        });

        // Handle window focus/blur
        window.addEventListener('blur', () => {
            this.handleUserStoppedTyping();
        });
    }

   // In chat-events.js - Update the typing handlers
handleUserTyping() {
    const config = window.__chatCoreConfig;
    if (!config || !config.typingUrl) return;

    if (!this.isTyping) {
        this.isTyping = true;
        
        // Send typing started with proper headers
        fetch(config.typingUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                is_typing: true,
                _token: window.csrfToken // Also include in body for Laravel
            })
        }).catch(error => {
            console.error('❌ Typing request failed:', error);
        });
    }

    // Reset typing timeout
    clearTimeout(this.typingTimeout);
    this.typingTimeout = setTimeout(() => {
        this.handleUserStoppedTyping();
    }, 1000);
}

handleUserStoppedTyping() {
    if (!this.isTyping) return;

    const config = window.__chatCoreConfig;
    if (!config || !config.typingUrl) return;

    this.isTyping = false;
    clearTimeout(this.typingTimeout);

    fetch(config.typingUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            is_typing: false,
            _token: window.csrfToken // Also include in body for Laravel
        })
    }).catch(error => {
        console.error('❌ Stop typing request failed:', error);
    });
}

  
    /**
     * Public API
     */
    setCurrentChat(conversationId = null, groupId = null) {
        // Leave previous channels
        if (this.currentConversationId) {
            this.echo.leave(`chat.${this.currentConversationId}`);
        }
        if (this.currentGroupId) {
            this.echo.leave(`group.${this.currentGroupId}`);
        }

        // Set new current chat
        this.currentConversationId = conversationId;
        this.currentGroupId = groupId;

        // Setup listeners for new chat
        if (conversationId) {
            this.setupDirectMessageListeners(conversationId);
        } else if (groupId) {
            this.setupGroupChatListeners(groupId);
        }
    }

    disconnect() {
        this.clearAllTypingIndicators();
        this.handleUserStoppedTyping();
        
        if (this.currentConversationId) {
            this.echo.leave(`chat.${this.currentConversationId}`);
        }
        if (this.currentGroupId) {
            this.echo.leave(`group.${this.currentGroupId}`);
        }
    }
}

/**
 * Initialize Chat Event System
 */
function initializeChatEvents() {
    window.chatEvents = new ChatEventSystem();
    console.log('🎯 Chat Event System ready');
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeChatEvents);
} else {
    setTimeout(initializeChatEvents, 100);
}

// Also support window.onDomReady if available
if (typeof window.onDomReady === 'function') {
    window.onDomReady(initializeChatEvents);
}

// Export for module usage
export default ChatEventSystem;