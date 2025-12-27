// resources/js/chat/ChatCore.js - COMPLETE ENHANCED VERSION

/**
 * Production-ready ChatCore class for real-time chat functionality
 * Handles all events: messages, typing, reactions, presence, calls, etc.
 * Replaces both original ChatCore and ChatEventSystem functionality
 */
export class ChatCore {
    constructor(config = {}) {
        this.config = {
            // Required-ish (one of conversationId/groupId for room; userId for user-channel)
            conversationId: null,
            groupId: null,
            userId: null,

            // Endpoints
            typingUrl: null,
            messageUrl: null,
            reactionUrl: null,
            quickRepliesUrl: null,
            statusUrl: null,

            // UI Elements
            messageContainer: '.messages-container',
            messageInput: '#message-input',
            messageForm: '#message-form',
            typingIndicator: '.typing-indicator',

            // Callbacks
            onMessage: null,
            onMessageDeleted: null,
            onMessageEdited: null,
            onReaction: null,
            onTyping: null,
            onPresence: null,
            onCall: null,
            onError: null,
            onMessageSubmit: null,
            onQuickRepliesLoaded: null,
            onStatusesLoaded: null,

            // Settings
            autoScroll: true,
            showTyping: true,
            debug: false,

            // Advanced
            typingStopMethod: 'DELETE', // 'DELETE' | 'POST'
            reconnectMaxAttempts: 5,

            ...config
        };

        this.isInitialized = false;
        this.isConnected = false;
        this.isConnecting = false;

        // Enhanced state management
        this.echoChannels = new Map();
        this.typingTimeouts = new Map();
        this.currentTypers = new Map(); // Changed from Set to Map to store {id, name}
        this.reconnectionAttempts = 0;
        this.reconnectTimeout = null;

        // Connection state tracking
        this.connectionState = {
            lastPing: null,
            latency: null,
            quality: 'good' // good, degraded, poor
        };

        // Enhanced typing state
        this.isUserTyping = false;
        this.userTypingTimeout = null;

        // Quick Replies state
        this.quickReplies = [];
        this.frequentReplies = [];
        this.isQuickRepliesOpen = false;
        this.statuses = [];

        // Elements cache
        this.elements = {};

        // Event handlers
        this.messageHandlers = new Set();
        this.typingHandlers = new Set();
        this.reactionHandlers = new Set();
        this.presenceHandlers = new Set();
        this.callHandlers = new Set();
        this.errorHandlers = new Set();

        // Bindings
        this._onVisibility = this._onVisibility.bind(this);
        this._onBeforeUnload = this._onBeforeUnload.bind(this);

        this.init();
    }

    /* ======================== INIT ======================== */

    async init() {
        if (this.isInitialized) {
            this.log('ChatCore already initialized');
            return;
        }

        try {
            // Wait for Echo to be ready
            await this.waitForEcho();

            this.cacheElements();
            this.setupEventListeners();
            await this.setupRealtimeListeners();
            this.setupErrorHandling();
            this.setupAutoReconnect();
            this.setupConnectionMonitoring();

          

            this.isInitialized = true;
            this.isConnected = true;

            // Scroll to bottom when chat initializes
            setTimeout(() => {
                if (this.config.autoScroll) {
                    this.scrollToBottom();
                }
            }, 200);

            this.log('ChatCore initialized successfully', this.config);
            this.triggerEvent('initialized', this.config);
        } catch (error) {
            this.handleError('Initialization failed', error);
            // Don't throw, allow graceful degradation
        }
    }

    async waitForEcho(maxWait = 10000) {
        return new Promise((resolve, reject) => {
            if (window.Echo && window.Echo.socketId && window.Echo.socketId() !== 'no-op-socket-id') {
                resolve(window.Echo);
                return;
            }

            const timeout = setTimeout(() => {
                document.removeEventListener('echo:ready', onEchoReady);
                reject(new Error('Echo initialization timeout'));
            }, maxWait);

            const onEchoReady = (event) => {
                clearTimeout(timeout);
                if (event.detail.isNoOp) {
                    reject(new Error('Echo is in no-op mode - realtime features disabled'));
                } else {
                    resolve(event.detail.echo);
                }
            };

            document.addEventListener('echo:ready', onEchoReady, { once: true });
        });
    }

    cacheElements() {
        const map = {
            messageContainer: this.config.messageContainer,
            messageInput: this.config.messageInput,
            messageForm: this.config.messageForm,
            typingIndicator: this.config.typingIndicator,
        };
        Object.entries(map).forEach(([k, sel]) => {
            if (sel) this.elements[k] = document.querySelector(sel);
        });
        if (!this.elements.messageContainer) {
            throw new Error('Message container element not found');
        }
    }

    setupEventListeners() {
        this.setupMessageForm();
        this.setupTypingIndicator();
        this.setupMessageActions();
        this.setupScrollHandler();
        this.setupDOMEventListeners();

        document.addEventListener('visibilitychange', this._onVisibility);
        window.addEventListener('beforeunload', this._onBeforeUnload);
    }

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

    async setupRealtimeListeners() {
        if (!window.Echo || window.Echo.socketId() === 'no-op-socket-id') {
            throw new Error('Echo not available - realtime features disabled');
        }

        this.log('Setting up realtime listeners');

        // Clear any existing listeners first
        this.cleanupChannels();

        try {
            if (this.config.conversationId) {
                await this.setupConversationListeners();
            }
            if (this.config.groupId) {
                await this.setupGroupListeners();
            }

            this.setupPresenceListeners();
            this.setupUserListeners();

            this.log('Realtime listeners setup complete');
        } catch (error) {
            this.handleError('Failed to setup realtime listeners', error);
            throw error;
        }
    }

    setupConnectionMonitoring() {
        if (!window.Echo?.connector?.pusher?.connection) {
            this.log('No Pusher connection available for monitoring');
            return;
        }

        const connection = window.Echo.connector.pusher.connection;

        connection.bind('connected', () => {
            this.log('WebSocket connected');
            this.isConnected = true;
            this.isConnecting = false;
            this.reconnectionAttempts = 0;
            this.connectionState.quality = 'good';
            this.triggerEvent('connected');
        });

        connection.bind('connecting', () => {
            this.log('WebSocket connecting...');
            this.isConnecting = true;
            this.triggerEvent('connecting');
        });

        connection.bind('disconnected', () => {
            this.log('WebSocket disconnected');
            this.isConnected = false;
            this.isConnecting = false;
            this.triggerEvent('disconnected');
            this.attemptReconnection();
        });

        connection.bind('error', (error) => {
            this.handleError('WebSocket connection error', error);
            this.connectionState.quality = 'poor';
        });

        // Monitor connection quality
        this.startConnectionQualityMonitor();
    }

    startConnectionQualityMonitor() {
        setInterval(() => {
            if (this.isConnected) {
                this.checkConnectionQuality();
            }
        }, 30000); // Check every 30 seconds
    }

    checkConnectionQuality() {
        const previousQuality = this.connectionState.quality;

        if (this.reconnectionAttempts > 2) {
            this.connectionState.quality = 'poor';
        } else if (this.reconnectionAttempts > 0) {
            this.connectionState.quality = 'degraded';
        } else {
            this.connectionState.quality = 'good';
        }

        if (previousQuality !== this.connectionState.quality) {
            this.triggerEvent('connectionQualityChanged', this.connectionState.quality);
        }
    }

    /* ================== CONVERSATION CHANNEL ================== */

    async setupConversationListeners() {
        const channel = `conversation.${this.config.conversationId}`;

        return new Promise((resolve, reject) => {
            this.log(`Setting up conversation listeners: ${channel}`);

            try {
                const ch = Echo.private(channel)
                    .listen('.MessageSent', (e) => {
                        this.log('✅ MessageSent received', e);
                        this.handleIncomingMessage(e);
                    })
                    .listen('.MessageDeleted', (e) => {
                        this.log('✅ MessageDeleted received', e);
                        this.handleMessageDeleted(e);
                    })
                    .listen('.MessageEdited', (e) => {
                        this.log('✅ MessageEdited received', e);
                        this.handleMessageEdited(e);
                    })
                    .listen('.MessageReacted', (e) => {
                        this.log('✅ MessageReacted received', e);
                        this.handleMessageReacted(e);
                    })
                    .listen('.MessageStatusUpdated', (e) => {
                        this.log('✅ MessageStatusUpdated received', e);
                        this.handleMessageStatusUpdated(e);
                    })
                    .listen('.UserTyping', (e) => {
                        this.log('✅ UserTyping received', e);
                        this.handleTypingEvent(e);
                    })
                    .subscribed(() => {
                        this.log(`✅ Successfully subscribed to conversation: ${channel}`);
                        resolve(ch);
                    })
                    .error((error) => {
                        this.log(`❌ Conversation subscription error: ${channel}`, error, 'error');
                        reject(error);
                    });

                this.echoChannels.set(channel, ch);
            } catch (error) {
                reject(error);
            }
        });
    }

    /* ======================= GROUP CHANNEL ======================= */

    async setupGroupListeners() {
        const channel = `group.${this.config.groupId}`;

        return new Promise((resolve, reject) => {
            this.log(`Setting up group listeners: ${channel}`);

            try {
                const ch = Echo.private(channel)
                    // Group message events
                    .listen('.GroupMessageSent', (e) => {
                        this.log('GroupMessageSent', e);
                        this.handleIncomingMessage(e);
                    })
                    .listen('.GroupMessageDeleted', (e) => {
                        this.log('GroupMessageDeleted', e);
                        this.handleMessageDeleted(e);
                    })
                    .listen('.GroupMessageEdited', (e) => {
                        this.log('GroupMessageEdited', e);
                        this.handleMessageEdited(e);
                    })
                    .listen('.GroupMessageReadEvent', (e) => {
                        this.log('GroupMessageReadEvent', e);
                        this.handleMessageRead(e);
                    })

                    // Group typing events
                    .listen('.GroupTyping', (e) => {
                        this.log('GroupTyping', e);
                        this.handleTypingEvent(e);
                    })
                    .listen('.TypingInGroup', (e) => {
                        this.log('TypingInGroup', e);
                        this.handleTypingEvent(e);
                    })

                    // Group management
                    .listen('.GroupUpdated', (e) => {
                        this.log('GroupUpdated', e);
                        this.handleGroupUpdated(e);
                    })
                    .subscribed(() => {
                        this.log(`✅ Successfully subscribed to group: ${channel}`);
                        resolve(ch);
                    })
                    .error((error) => {
                        this.log(`❌ Group subscription error: ${channel}`, error, 'error');
                        reject(error);
                    });

                this.echoChannels.set(channel, ch);
            } catch (error) {
                reject(error);
            }
        });
    }

    /* ======================= PRESENCE CHANNEL ======================= */

    setupPresenceListeners() {
        if (!this.config.conversationId && !this.config.groupId) return;

        // Echo.join() automatically adds 'presence-' prefix
        // So we pass 'conversation.5' and it becomes 'presence-conversation.5'
        const presenceName = this.config.conversationId
            ? `conversation.${this.config.conversationId}`
            : `group.${this.config.groupId}`;

        this.log(`Setup presence: ${presenceName} (Echo.join will make it presence-${presenceName})`);

        try {
            const ch = Echo.join(presenceName)
                .here((users) => {
                    this.log('presence:here', users);
                    this.handleUsersHere(users);
                })
                .joining((user) => {
                    this.log('presence:joining', user);
                    this.handleUserJoining(user);
                })
                .leaving((user) => {
                    this.log('presence:leaving', user);
                    this.handleUserLeaving(user);
                })
                .listen('.PresenceUpdated', (e) => {
                    this.log('PresenceUpdated', e);
                    this.handlePresenceUpdated(e);
                })
                .error((err) => this.handleError('Presence channel error', err));

            this.echoChannels.set(presenceName, ch);
        } catch (error) {
            this.handleError('Failed to setup presence listeners', error);
        }
    }

    /* ========================= USER CHANNEL ========================= */

    setupUserListeners() {
        if (!this.config.userId) return;

        // Echo.join() automatically adds 'presence-' prefix
        // So we pass 'user.1' and it becomes 'presence-user.1'
        const userChannel = `user.${this.config.userId}`;
        this.log(`Setup user presence: ${userChannel} (Echo.join will make it presence-${userChannel})`);

        try {
            const ch = Echo.join(userChannel)
                .listen('.CallSignal', (e) => {
                    this.log('CallSignal', e);
                    this.handleCallSignal(e);
                })
                .listen('.ReceiptUpdated', (e) => {
                    this.log('ReceiptUpdated', e);
                    this.handleReceiptUpdated(e);
                })
                .error((err) => this.handleError('User channel error', err));

            this.echoChannels.set(userChannel, ch);
        } catch (error) {
            this.handleError('Failed to setup user listeners', error);
        }
    }

    /* ===================== DOM EVENT WIRING ===================== */

    setupMessageForm() {
        if (!this.elements.messageForm) return;
        this.elements.messageForm.addEventListener('submit', (e) => {
            this.handleMessageSubmit(e).catch((error) => this.handleError('Message submit failed', error));
        });

        if (this.elements.messageInput) {
            this.elements.messageInput.addEventListener('paste', (e) => this.handlePaste?.(e));
        }
    }

    setupTypingIndicator() {
        if (!this.elements.messageInput || !this.config.typingUrl) return;

        let typingTimer;
        let isTyping = false;

        const kick = () => {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                this.stopTyping();
                isTyping = false;
            }, 1000);
        };

        this.elements.messageInput.addEventListener('input', () => {
            if (!isTyping) {
                this.startTyping();
                isTyping = true;
            }
            kick();
        });

        this.elements.messageInput.addEventListener('blur', () => {
            if (isTyping) {
                this.stopTyping();
                isTyping = false;
            }
        });
    }

    setupMessageActions() {
        document.addEventListener('click', (e) => {
            const t = e.target;
            if (t.closest('.reply-btn')) this.handleReply?.(t.closest('.reply-btn'));
            else if (t.closest('.react-btn')) this.handleReaction(t.closest('.react-btn'));
            else if (t.closest('.edit-btn')) this.handleEdit?.(t.closest('.edit-btn'));
            else if (t.closest('.delete-btn')) this.handleDelete?.(t.closest('.delete-btn'));
            else if (t.closest('.forward-btn')) this.handleForward?.(t.closest('.forward-btn'));
        });
    }

    setupScrollHandler() {
        if (!this.elements.messageContainer) return;
        let debounce;
        this.elements.messageContainer.addEventListener('scroll', () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => this.handleScroll(), 100);
        });
    }

    setupErrorHandling() {
        window.addEventListener('error', (evt) => this.handleError('Global error', evt.error));
        window.addEventListener('unhandledrejection', (evt) => this.handleError('Unhandled promise rejection', evt.reason));
    }

    setupAutoReconnect() {
        // Try to hook into the connector regardless of backend
        const c = Echo?.connector;
        if (!c) return;

        // Pusher
        if (c.pusher?.connection) {
            c.pusher.connection.bind('disconnected', () => this.handleDisconnect());
            c.pusher.connection.bind('connected', () => this.handleReconnect());
        }

        // Socket.IO or Reverb
        if (c.socket?.on) {
            c.socket.on('disconnect', () => this.handleDisconnect());
            c.socket.on('reconnect', () => this.handleReconnect());
        }

        // Reverb (official)
        if (c.connection?.on) {
            c.connection.on('close', () => this.handleDisconnect());
            c.connection.on('open', () => this.handleReconnect());
        }
    }

    /* ===================== EVENT HANDLERS ===================== */

    async handleMessageSubmit(event) {
        event.preventDefault();
        if (!this.elements.messageInput) return;

        const message = this.elements.messageInput.value.trim();
        if (!message) return;

        try {
            this.elements.messageInput.value = '';
            if (this.config.onMessageSubmit) {
                await this.config.onMessageSubmit(message);
            } else {
                await this.sendMessage(message);
            }
        } catch (error) {
            this.elements.messageInput.value = message;
            this.handleError('Message send failed', error);
        }
    }

    async sendMessage(content, options = {}) {
        if (!this.config.messageUrl) throw new Error('Message URL not configured');

        const payload = {
            body: content,
            conversation_id: this.config.conversationId,
            group_id: this.config.groupId,
            reply_to: options.replyTo,
            forward_from: options.forwardFrom,
            ...options
        };

        const res = await window.api.post(this.config.messageUrl, payload);
        
        // Add message to UI immediately (optimistic update)
        if (res.data?.message) {
            const message = res.data.message;
            
            // Use HTML if available (from server response), otherwise render from data
            if (res.data.html) {
                this.insertMessageHTML(res.data.html);
            } else {
                // Normalize and add message
                const messageData = this.normalizeMessagePayload({
                    id: message.id,
                    body: message.body_plain || message.body,
                    sender_id: message.sender_id || this.config.userId,
                    conversation_id: message.conversation_id,
                    created_at: message.created_at,
                    is_own: true
                });
                
                this.addMessageToUI(messageData, { scrollTo: true });
            }
            
            // Scroll to bottom
            if (this.config.autoScroll) {
                this.scrollToBottom();
            }
        }
        
        return res.data;
    }

    handleIncomingMessage(event) {
        // Don't show our own messages (they're already in the UI from sendMessage)
        const senderId = event.message?.sender_id ?? event.sender_id ?? event.message?.user_id ?? event.user_id;
        if (senderId && String(senderId) === String(this.config.userId)) {
            return;
        }

        const msg = this.normalizeMessagePayload(event);
        this.triggerEvent('message', msg);

        // Call registered handlers
        this.messageHandlers.forEach(handler => {
            try {
                handler(msg);
            } catch (error) {
                console.error('Error in message handler:', error);
            }
        });

        if (this.config.onMessage) this.config.onMessage(msg);

        // Add message to UI if HTML is provided
        if (event.html) {
            // For bot messages or messages that might arrive out of order, sort by timestamp
            const isBotMessage = msg.sender_id && String(msg.sender_id) !== String(this.config.userId);
            this.insertMessageHTML(event.html, 'messages-container', { 
                sortByTime: isBotMessage // Sort bot messages by time to ensure correct order
            });
        } else {
            // Fallback: use existing message rendering
            this.addMessageToUI(msg);
        }

        // Play notification sound
        this.playNotificationSound();

        // Update unread count
        this.updateUnreadCount(1);

        // Dispatch sidebar update event for last message preview
        const isDirect = !!this.config.conversationId;
        const id = this.config.conversationId ?? this.config.groupId ?? null;
        const sidebarUpdateEvent = new CustomEvent('sidebarMessageUpdate', {
            detail: {
                type: isDirect ? 'direct' : 'group',
                id: id,
                message: {
                    body: msg.body || msg.body_plain || '',
                    display_body: msg.display_body || msg.body || msg.body_plain || '',
                    created_at: msg.created_at || new Date().toISOString()
                }
            }
        });
        document.dispatchEvent(sidebarUpdateEvent);

        if (this.config.autoScroll && this.isNearBottom()) this.scrollToBottom();
    }

    handleMessageDeleted(event) {
        const id = event?.message_id ?? event?.id ?? event?.message?.id;
        this.triggerEvent('messageDeleted', id);

        if (this.config.onMessageDeleted) this.config.onMessageDeleted(id);
        else this.removeMessageFromUI(id);
    }

    handleMessageEdited(event) {
        const id = event?.message_id ?? event?.id ?? event?.message?.id;
        const body = event?.body ?? event?.message?.body ?? '';
        const normalized = { id, body, raw: event };

        this.triggerEvent('messageEdited', normalized);

        if (this.config.onMessageEdited) this.config.onMessageEdited(normalized);
        else this.updateMessageInUI(id, body);
    }

    handleMessageReacted(event) {
        this.triggerEvent('reaction', event);

        this.reactionHandlers.forEach(handler => {
            try {
                handler(event);
            } catch (error) {
                console.error('Error in reaction handler:', error);
            }
        });

        if (this.config.onReaction) this.config.onReaction(event);
    }

    handleMessageRead(event) {
        this.triggerEvent('messageRead', event);
    }

    handleMessageStatusUpdated(event) {
        this.triggerEvent('statusUpdated', event);
    }

    handleTypingEvent(event) {
        const payload = this.normalizeTypingPayload(event);
        this.triggerEvent('typing', payload);

        // Call registered handlers
        this.typingHandlers.forEach(handler => {
            try {
                handler(payload);
            } catch (error) {
                console.error('Error in typing handler:', error);
            }
        });

        if (this.config.onTyping) {
            this.config.onTyping(payload);
            return;
        }
        if (!this.config.showTyping) return;

        this.showTypingIndicator(payload.user_id, payload.is_typing === true, payload.user_name);

        // Auto-hide after 3s if it's a "typing" ping without explicit stop
        if (payload.is_typing) {
            clearTimeout(this.typingTimeouts.get(payload.user_id));
            const to = setTimeout(() => this.showTypingIndicator(payload.user_id, false, payload.user_name), 3000);
            this.typingTimeouts.set(payload.user_id, to);
        }
    }

    handleGroupUpdated(event) {
        this.triggerEvent('groupUpdated', event);
        this.showGroupNotification(`Group updated: ${event.update_type || 'changes made'}`);
    }

    handleUsersHere(users) {
        this.triggerEvent('presence', { type: 'here', users });

        this.presenceHandlers.forEach(handler => {
            try {
                handler('here', users);
            } catch (error) {
                console.error('Error in presence handler:', error);
            }
        });

        if (this.config.onPresence) this.config.onPresence('here', users);
    }

    handleUserJoining(user) {
        this.triggerEvent('presence', { type: 'joining', user });

        this.presenceHandlers.forEach(handler => {
            try {
                handler('joining', user);
            } catch (error) {
                console.error('Error in presence handler:', error);
            }
        });

        if (this.config.onPresence) this.config.onPresence('joining', user);

        this.showGroupNotification(`${user.name || 'User'} joined`);
    }

    handleUserLeaving(user) {
        this.triggerEvent('presence', { type: 'leaving', user });

        this.presenceHandlers.forEach(handler => {
            try {
                handler('leaving', user);
            } catch (error) {
                console.error('Error in presence handler:', error);
            }
        });

        if (this.config.onPresence) this.config.onPresence('leaving', user);

        this.showGroupNotification(`${user.name || 'User'} left`);
    }

    handlePresenceUpdated(event) {
        this.triggerEvent('presence', { type: 'updated', ...event });
        if (this.config.onPresence) this.config.onPresence('updated', event);
    }

    handleCallSignal(event) {
        this.triggerEvent('call', event);

        this.callHandlers.forEach(handler => {
            try {
                handler(event);
            } catch (error) {
                console.error('Error in call handler:', error);
            }
        });

        if (this.config.onCall) this.config.onCall(event);
    }

    handleReceiptUpdated(event) {
        this.triggerEvent('receiptUpdated', event);
    }

    handleScroll() {
        const c = this.elements.messageContainer;
        if (!c) return;
        if (c.scrollTop === 0) this.triggerEvent('scrollTop');
    }

    handleDisconnect() {
        this.isConnected = false;
        this.log('WebSocket disconnected');
        this.triggerEvent('disconnected');
        this.attemptReconnection();
    }

    handleReconnect() {
        this.isConnected = true;
        this.reconnectionAttempts = 0;
        this.log('WebSocket reconnected');
        this.triggerEvent('reconnected');
    }

    handleError(context, error) {
        const err = {
            context,
            error: error instanceof Error ? error : new Error(String(error)),
            timestamp: new Date().toISOString(),
            connectionState: {
                isConnected: this.isConnected,
                isConnecting: this.isConnecting,
                reconnectionAttempts: this.reconnectionAttempts,
                quality: this.connectionState.quality
            },
            channels: Array.from(this.echoChannels.keys())
        };

        this.log(`Error: ${context}`, err, 'error');

        // Call error handlers
        this.errorHandlers.forEach(handler => {
            try {
                handler(err);
            } catch (e) {
                console.error('Error in error handler:', e);
            }
        });

        if (this.config.onError) this.config.onError(err);

        this.triggerEvent('error', err);
    }

    /* ===================== USER TYPING HANDLERS ===================== */

    handleUserTyping() {
        if (!this.config.typingUrl || (!this.config.conversationId && !this.config.groupId)) return;

        if (!this.isUserTyping) {
            this.isUserTyping = true;

            window.api.post(this.config.typingUrl, {
                conversation_id: this.config.conversationId,
                group_id: this.config.groupId,
                is_typing: true
            }).catch(error => {
                console.error('❌ Typing request failed:', error);
            });
        }

        // Reset typing timeout
        clearTimeout(this.userTypingTimeout);
        this.userTypingTimeout = setTimeout(() => {
            this.handleUserStoppedTyping();
        }, 1000);
    }

    handleUserStoppedTyping() {
        if (!this.isUserTyping) return;

        if (!this.config.typingUrl || (!this.config.conversationId && !this.config.groupId)) return;

        this.isUserTyping = false;
        clearTimeout(this.userTypingTimeout);

        window.api.post(this.config.typingUrl, {
            conversation_id: this.config.conversationId,
            group_id: this.config.groupId,
            is_typing: false
        }).catch(error => {
            console.error('❌ Stop typing request failed:', error);
        });
    }

    /* ===================== PUBLIC API ===================== */

    async startTyping() {
        if (!this.config.typingUrl) return;
        try {
            await window.api.post(this.config.typingUrl, {
                conversation_id: this.config.conversationId,
                group_id: this.config.groupId,
                is_typing: true
            });
        } catch (e) {
            this.handleError('Typing start failed', e);
        }
    }

    async stopTyping() {
        if (!this.config.typingUrl) return;
        try {
            if (this.config.typingStopMethod === 'DELETE') {
                // For DELETE requests, pass conversation_id as query parameter
                const conversationId = this.config.conversationId || this.config.groupId;
                const url = conversationId 
                    ? `${this.config.typingUrl}?conversation_id=${conversationId}`
                    : this.config.typingUrl;
                await window.api.delete(url);
            } else {
                await window.api.post(this.config.typingUrl, {
                    conversation_id: this.config.conversationId,
                    group_id: this.config.groupId,
                    is_typing: false
                });
            }
        } catch (e) {
            this.handleError('Typing stop failed', e);
        }
    }

    addMessageToUI(messageData, options = {}) {
        const el = this.createMessageElement(messageData);
        if (options.prepend) this.elements.messageContainer.prepend(el);
        else this.elements.messageContainer.appendChild(el);
        if (options.scrollTo && this.config.autoScroll) this.scrollToBottom();
    }

    insertMessageHTML(html, containerId = 'messages-container', options = {}) {
        const container = this.elements.messageContainer || document.getElementById(containerId);
        if (!container) {
            console.error('❌ Message container not found');
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

        // Function to add message to DOM
        const addMessageToDOM = () => {
            // Add to container
            if (options.prepend) {
                container.prepend(newMessage);
            } else {
                container.appendChild(newMessage);
            }

            // Add animation
            newMessage.classList.add('message-received');
            newMessage.style.opacity = '0';
            newMessage.style.transform = 'translateY(20px)';

            // Animate in
            setTimeout(() => {
                newMessage.style.transition = 'all 0.3s ease';
                newMessage.style.opacity = '1';
                newMessage.style.transform = 'translateY(0)';
            }, 50);

            // Scroll to bottom
            if (this.config.autoScroll) {
                this.scrollToBottom();
            }

            // Dispatch event for other components
            document.dispatchEvent(new CustomEvent('newMessageAdded', {
                detail: { message: newMessage, type: this.config.conversationId ? 'direct' : 'group' }
            }));

            this.log('✅ Message added to UI via real-time');
        };

        // For bot messages, add a small delay to ensure user's message appears first
        // The server already adds a delay, but this provides extra safety on client side
        if (options.sortByTime) {
            setTimeout(addMessageToDOM, 200);
        } else {
            addMessageToDOM();
        }

        return true;
    }

    removeMessageFromUI(messageId) {
        const el = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!el) return;
        el.style.transition = 'all 0.3s ease';
        el.style.opacity = '0';
        el.style.maxHeight = '0';
        el.style.overflow = 'hidden';
        setTimeout(() => {
            el.remove();
            this.checkEmptyState();
        }, 300);
    }

    updateMessageInUI(messageId, newBody) {
        const el = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!el) return;
        const body = el.querySelector('.message-body');
        if (body) {
            body.textContent = newBody;
            el.classList.add('edited');
            if (!el.querySelector('.edited-indicator')) {
                const i = document.createElement('span');
                i.className = 'edited-indicator';
                i.textContent = ' (edited)';
                body.appendChild(i);
            }
        }
    }

    showTypingIndicator(userId, show, userName = null) {
        if (show) {
            // Store user info: {id, name}
            this.currentTypers.set(userId, {
                id: userId,
                name: userName || `User ${userId}`
            });
        } else {
            this.currentTypers.delete(userId);
        }
        this.updateTypingIndicator();
    }

    updateTypingIndicator() {
        if (!this.elements.typingIndicator) return;
        if (this.currentTypers.size > 0) {
            const typers = Array.from(this.currentTypers.values());
            const names = typers.slice(0, 3).map(t => t.name).join(', ');
            const more = this.currentTypers.size > 3 ? ` and ${this.currentTypers.size - 3} more` : '';
            this.elements.typingIndicator.textContent = `${names}${more} ${this.currentTypers.size === 1 ? 'is' : 'are'} typing...`;
            this.elements.typingIndicator.style.display = 'block';
        } else {
            this.elements.typingIndicator.style.display = 'none';
        }
    }

    scrollToBottom() {
        const c = this.elements.messageContainer;
        if (!c) return;
        c.scrollTop = c.scrollHeight;
    }

    isNearBottom(threshold = 100) {
        const c = this.elements.messageContainer;
        if (!c) return false;
        const { scrollTop, scrollHeight, clientHeight } = c;
        return scrollHeight - scrollTop - clientHeight < threshold;
    }

    checkEmptyState() {
        const c = this.elements.messageContainer;
        const msgs = c.querySelectorAll('.message');
        const empty = c.querySelector('.empty-state');
        if (msgs.length === 0 && !empty) {
            const e = document.createElement('div');
            e.className = 'empty-state';
            e.innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="bi bi-chat-dots display-4"></i>
                    <p class="mt-3">No messages yet</p>
                    <small>Start a conversation by sending a message</small>
                </div>
            `;
            c.appendChild(e);
        } else if (msgs.length > 0 && empty) {
            empty.remove();
        }
    }

    async attemptReconnection() {
        if (this.reconnectionAttempts >= (this.config.reconnectMaxAttempts ?? 5)) {
            this.log('Max reconnection attempts reached');
            this.triggerEvent('reconnectionFailed');
            return;
        }

        this.reconnectionAttempts++;
        const delay = Math.min(1000 * Math.pow(2, this.reconnectionAttempts), 30000);

        this.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectionAttempts})`);
        this.triggerEvent('reconnecting', { attempt: this.reconnectionAttempts, delay });

        // Clear any existing timeout
        if (this.reconnectTimeout) {
            clearTimeout(this.reconnectTimeout);
        }

        this.reconnectTimeout = setTimeout(async () => {
            try {
                await this.reconnect();
            } catch (error) {
                this.handleError('Reconnection failed', error);
                this.attemptReconnection(); // Retry
            }
        }, delay);
    }

    async reconnect() {
        this.log('Attempting reconnection...');

        try {
            this.cleanupChannels();
            await this.setupRealtimeListeners();

            this.isConnected = true;
            this.reconnectionAttempts = 0;

            this.log('Reconnection successful');
            this.triggerEvent('reconnected');
        } catch (error) {
            this.isConnected = false;
            throw error;
        }
    }

    cleanupChannels() {
        // Leave all existing channels
        this.echoChannels.forEach((channel, name) => {
            try {
                Echo.leave(name);
                this.log(`Left channel: ${name}`);
            } catch (error) {
                this.log(`Error leaving channel ${name}:`, error, 'warn');
            }
        });
        this.echoChannels.clear();
    }

    /* ==================== QUICK REPLIES FEATURE ==================== */
// In ChatCore.js - add this simple method
async loadQuickReplies() {
    if (!this.config.quickRepliesUrl) return;
    
    try {
        const response = await window.api.get(this.config.quickRepliesUrl);
        this.quickReplies = response.data.quick_replies || [];
        
        // Trigger event for UI updates
        this.triggerEvent('quickRepliesLoaded', this.quickReplies);
        
        // Call config callback if set
        if (this.config.onQuickRepliesLoaded) {
            try {
                this.config.onQuickRepliesLoaded(this.quickReplies);
            } catch (error) {
                console.error('Error in onQuickRepliesLoaded callback:', error);
            }
        }
    } catch (error) {
        this.log('Failed to load quick replies', error);
    }
}

/* ==================== STATUSES FEATURE ==================== */
async loadStatuses() {
    if (!this.config.statusUrl) return;
    
    try {
        const response = await window.api.get(this.config.statusUrl);
        this.statuses = response.data.statuses || [];
        
        // Trigger event for UI updates
        this.triggerEvent('statusesLoaded', this.statuses);
        
        // Call config callback if set
        if (this.config.onStatusesLoaded) {
            try {
                this.config.onStatusesLoaded(this.statuses);
            } catch (error) {
                console.error('Error in onStatusesLoaded callback:', error);
            }
        }
    } catch (error) {
        this.log('Failed to load statuses', error);
    }
}


    /* ===================== HANDLER REGISTRATION ===================== */

    onMessage(handler) { this.messageHandlers.add(handler); return this; }
    onTyping(handler) { this.typingHandlers.add(handler); return this; }
    onReaction(handler) { this.reactionHandlers.add(handler); return this; }
    onPresence(handler) { this.presenceHandlers.add(handler); return this; }
    onCall(handler) { this.callHandlers.add(handler); return this; }
    onError(handler) { this.errorHandlers.add(handler); return this; }

    // Add these for the method chain (NEW):
    onMessageDeleted(handler) {
        this.config.onMessageDeleted = handler;
        return this;
    }

    onMessageEdited(handler) {
        this.config.onMessageEdited = handler;
        return this;
    }

    onQuickRepliesLoaded(handler) {
        this.config.onQuickRepliesLoaded = handler;
        return this;
    }

    onStatusesLoaded(handler) {
        this.config.onStatusesLoaded = handler;
        return this;
    }



    offMessage(handler) { this.messageHandlers.delete(handler); return this; }
    offTyping(handler) { this.typingHandlers.delete(handler); return this; }
    offReaction(handler) { this.reactionHandlers.delete(handler); return this; }
    offPresence(handler) { this.presenceHandlers.delete(handler); return this; }
    offCall(handler) { this.callHandlers.delete(handler); return this; }
    offError(handler) { this.errorHandlers.delete(handler); return this; }
    /* ===================== UTILS ===================== */

    _onVisibility() {
        if (document.hidden) {
            this.log('Page hidden');
            this.handleUserStoppedTyping();
        } else {
            this.log('Page visible');
        }
    }

    _onBeforeUnload() {
        this.handleUserStoppedTyping();
    }

    log(message, data = null, level = 'log') {
        if (!this.config.debug) return;
        const ts = new Date().toISOString();
        const prefix = `[ChatCore ${ts}]`;
        if (data) console[level](prefix, message, data);
        else console[level](prefix, message);
    }

    triggerEvent(eventName, data = null) {
        const ev = new CustomEvent(`chatcore:${eventName}`, { detail: data, bubbles: true });
        document.dispatchEvent(ev);
    }

    playNotificationSound() {
        // Play notification sound if enabled and chat is not active
        if (window.Notification && Notification.permission === 'granted' && !this.isChatActive()) {
            try {
                const audio = new Audio('/sounds/notification.mp3');
                audio.play().catch(() => {
                    // Silent fail if audio can't play
                });
            } catch (error) {
                this.log('🔇 Notification sound disabled or failed');
            }
        }
    }

    isChatActive() {
        return !document.hidden && document.hasFocus();
    }

    /**
     * Bump the unread count for the current chat.  When a new message arrives
     * ChatCore will call this with a positive change (usually +1).  When
     * messages are marked as read the change may be negative.  We include
     * enough context on the event payload so that the UI can update the
     * specific conversation or group in the sidebar without having to
     * query state from ChatCore.  See resources/js/app.js for the
     * corresponding listener implementation.
     *
     * @param {number} change The delta to apply to the unread count
     */
    updateUnreadCount(change) {
        const isDirect = !!this.config.conversationId;
        const id = this.config.conversationId ?? this.config.groupId ?? null;
        const payload = {
            type: isDirect ? 'direct' : 'group',
            change,
            id,
        };
        const event = new CustomEvent('unreadCountUpdated', { detail: payload });
        document.dispatchEvent(event);
    }

    showGroupNotification(message, type = 'info') {
        // Use your existing toast/notification system
        if (window.showToast) {
            window.showToast(message, type);
        } else {
            this.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    // Normalize server payloads into stable shapes for the UI
    normalizeMessagePayload(event) {
        const raw = event?.message ?? event ?? {};
        const id = event?.message_id ?? raw?.id ?? event?.id;
        const body = raw?.body_plain ?? raw?.body ?? event?.body_plain ?? event?.body ?? '';
        const user_id = raw?.user_id ?? event?.user_id ?? raw?.sender_id ?? event?.sender_id;
        const time_ago = raw?.time_ago ?? event?.time_ago ?? '';
        const is_own = !!(raw?.is_own ?? (user_id && String(user_id) === String(this.config.userId)));
        
        // Include full event data for rendering
        return { 
            id, 
            body, 
            user_id, 
            time_ago, 
            is_own, 
            raw: event,
            sender: event?.sender ?? raw?.sender,
            attachments: event?.attachments ?? raw?.attachments ?? [],
            reply_to: event?.reply_to ?? raw?.reply_to,
            forwarded_from: event?.forwarded_from ?? raw?.forwarded_from,
            link_previews: event?.link_previews ?? raw?.link_previews ?? [],
            created_at: raw?.created_at ?? event?.created_at,
            conversation_id: raw?.conversation_id ?? event?.conversation_id,
            group_id: raw?.group_id ?? event?.group_id,
        };
    }

    normalizeTypingPayload(event) {
        // PHP events usually broadcast: user_id, user_name, is_typing, conversation_id|group_id
        return {
            user_id: event?.user_id ?? event?.user?.id ?? event?.id,
            user_name: event?.user_name ?? event?.user?.name ?? event?.user?.phone ?? `User ${event?.user_id ?? event?.user?.id ?? 'Unknown'}`,
            is_typing: event?.is_typing ?? event?.typing ?? true,
            conversation_id: event?.conversation_id ?? this.config.conversationId ?? null,
            group_id: event?.group_id ?? this.config.groupId ?? null,
            raw: event
        };
    }

    createMessageElement(messageData) {
        const isOwn = messageData.is_own || messageData.sender_id === this.config.userId;
        const el = document.createElement('div');
        el.className = `message mb-3 d-flex ${isOwn ? 'justify-content-end' : 'justify-content-start'}`;
        el.setAttribute('data-message-id', messageData.id);
        el.setAttribute('data-from-me', isOwn ? '1' : '0');
        el.setAttribute('data-read', messageData.read_at ? '1' : '0');
        
        // Format timestamp
        const createdAt = messageData.created_at || new Date().toISOString();
        const time = new Date(createdAt).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Build message text with linkification
        const body = messageData.display_body || messageData.body || '';
        const escapedText = this.escapeHtml(body);
        const messageText = escapedText.replace(
            /(https?:\/\/[^\s]+)/g, 
            '<a class="linkify" target="_blank" rel="noopener noreferrer" href="$1">$1</a>'
        );
        
        // Build link previews HTML if they exist
        let linkPreviewsHTML = '';
        if (messageData.link_previews && messageData.link_previews.length > 0) {
            linkPreviewsHTML = '<div class="link-previews-container mt-2">';
            messageData.link_previews.forEach(preview => {
                const imageHTML = preview.image 
                    ? `<div class="link-preview-image position-relative">
                        <img src="${this.escapeHtml(preview.image)}" alt="${this.escapeHtml(preview.title || 'Preview image')}" class="img-fluid rounded-top" loading="lazy" onerror="this.style.display='none'">
                        <div class="image-overlay"></div>
                    </div>`
                    : '';
                
                const siteNameHTML = preview.site_name 
                    ? `<small class="text-muted d-block mb-1"><i class="bi bi-globe me-1"></i>${this.escapeHtml(preview.site_name)}</small>`
                    : '';
                
                const titleHTML = preview.title 
                    ? `<h6 class="mb-1 fw-semibold text-dark">${this.escapeHtml(preview.title.length > 70 ? preview.title.substring(0, 70) + '...' : preview.title)}</h6>`
                    : '';
                
                const descHTML = preview.description 
                    ? `<p class="mb-1 text-muted small lh-sm">${this.escapeHtml(preview.description.length > 120 ? preview.description.substring(0, 120) + '...' : preview.description)}</p>`
                    : '';
                
                const host = preview.url ? new URL(preview.url).hostname : '';
                
                linkPreviewsHTML += `
                    <div class="link-preview-card rounded border bg-light" role="article">
                        ${imageHTML}
                        <div class="link-preview-content p-2 position-relative">
                            ${siteNameHTML}
                            ${titleHTML}
                            ${descHTML}
                            <small class="text-primary">${this.escapeHtml(host)}</small>
                            <a href="${this.escapeHtml(preview.url)}" target="_blank" rel="noopener noreferrer" class="stretched-link"></a>
                        </div>
                    </div>
                `;
            });
            linkPreviewsHTML += '</div>';
        }
        
        // Build status indicator for own messages
        let statusIcon = '';
        if (isOwn) {
            if (messageData.read_at) {
                statusIcon = '<i class="bi bi-check2-all text-primary" title="Read"></i>';
            } else if (messageData.delivered_at) {
                statusIcon = '<i class="bi bi-check2-all muted" title="Delivered"></i>';
            } else {
                statusIcon = '<i class="bi bi-check2 muted" title="Sent"></i>';
            }
        }
        
        // Build sender name for received messages (if available)
        const senderName = !isOwn && messageData.sender ? 
            `<small class="sender-name">${this.escapeHtml(messageData.sender.name || messageData.sender.phone || '')}</small>` : '';
        
        el.innerHTML = `
            <div class="message-bubble ${isOwn ? 'sent' : 'received'}">
                ${senderName}
                <div class="message-content">
                    <div class="message-text">${messageText}</div>
                    ${linkPreviewsHTML}
                </div>
                <div class="message-footer d-flex justify-content-between align-items-center mt-1">
                    <small class="muted message-time">
                        <time datetime="${createdAt}">${time}</time>
                    </small>
                    ${isOwn ? `<div class="status-indicator">${statusIcon}</div>` : ''}
                </div>
            </div>
        `;
        return el;
    }

    escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* ===================== HANDLER REGISTRATION ===================== */

    onMessage(handler) { this.messageHandlers.add(handler); return this; }
    onTyping(handler) { this.typingHandlers.add(handler); return this; }
    onReaction(handler) { this.reactionHandlers.add(handler); return this; }
    onPresence(handler) { this.presenceHandlers.add(handler); return this; }
    onCall(handler) { this.callHandlers.add(handler); return this; }
    onError(handler) { this.errorHandlers.add(handler); return this; }

    offMessage(handler) { this.messageHandlers.delete(handler); return this; }
    offTyping(handler) { this.typingHandlers.delete(handler); return this; }
    offReaction(handler) { this.reactionHandlers.delete(handler); return this; }
    offPresence(handler) { this.presenceHandlers.delete(handler); return this; }
    offCall(handler) { this.callHandlers.delete(handler); return this; }
    offError(handler) { this.errorHandlers.delete(handler); return this; }

    /* ===================== CHAT MANAGEMENT ===================== */

    setCurrentChat(conversationId = null, groupId = null) {
        // Leave previous channels
        this.cleanupChannels();

        // Set new current chat
        this.config.conversationId = conversationId;
        this.config.groupId = groupId;

        // Setup listeners for new chat
        this.setupRealtimeListeners().catch(error => {
            this.handleError('Failed to setup new chat listeners', error);
        });
    }

    getConnectionQuality() {
        return {
            ...this.connectionState,
            isConnected: this.isConnected,
            isConnecting: this.isConnecting,
            reconnectionAttempts: this.reconnectionAttempts
        };
    }

    /* ===================== CLEANUP ===================== */

    destroy() {
        this.log('Destroying ChatCore instance');

        // Clear reconnection timeout
        if (this.reconnectTimeout) {
            clearTimeout(this.reconnectTimeout);
            this.reconnectTimeout = null;
        }

        // Clear user typing timeout
        if (this.userTypingTimeout) {
            clearTimeout(this.userTypingTimeout);
        }

        // Cleanup channels
        this.cleanupChannels();

        // Clear typing timeouts
        this.typingTimeouts.forEach((timeout) => clearTimeout(timeout));
        this.typingTimeouts.clear();

        // Clear all handlers
        this.messageHandlers.clear();
        this.typingHandlers.clear();
        this.reactionHandlers.clear();
        this.presenceHandlers.clear();
        this.callHandlers.clear();
        this.errorHandlers.clear();

        // Reset state
        this.currentTypers.clear();
        this.isInitialized = false;
        this.isConnected = false;
        this.isConnecting = false;
        this.reconnectionAttempts = 0;
        this.isUserTyping = false;

        // Remove event listeners
        document.removeEventListener('visibilitychange', this._onVisibility);
        window.removeEventListener('beforeunload', this._onBeforeUnload);

        this.triggerEvent('destroyed');
        this.log('ChatCore instance destroyed');
    }

    getStatus() {
        return {
            isInitialized: this.isInitialized,
            isConnected: this.isConnected,
            isConnecting: this.isConnecting,
            reconnectionAttempts: this.reconnectionAttempts,
            activeChannels: Array.from(this.echoChannels.keys()),
            currentTypers: Array.from(this.currentTypers.values()),
            connectionQuality: this.connectionState.quality,
            quickRepliesCount: this.quickReplies.length,
            statusesCount: this.statuses.length,
            config: { ...this.config }
        };
    }
}

// Make available globally
window.ChatCore = ChatCore;
export default ChatCore;