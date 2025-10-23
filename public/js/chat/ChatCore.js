// resources/js/chat/ChatCore.js

/**
 * Production-ready ChatCore class for real-time chat functionality
 * Handles all events: messages, typing, reactions, presence, calls, etc.
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

        // State / handlers
        this.echoChannels = new Map();   // name -> joined channel (for presence we also keep names)
        this.typingTimeouts = new Map(); // userId -> timeout
        this.currentTypers = new Set();
        this.reconnectionAttempts = 0;

        // Elements cache
        this.elements = {};

        // Bindings
        this._onVisibility = this._onVisibility.bind(this);

        this.init();
    }

    /* ======================== INIT ======================== */

    async init() {
        if (this.isInitialized) {
            this.log('ChatCore already initialized');
            return;
        }
        try {
            this.cacheElements();
            this.setupEventListeners();
            await this.setupRealtimeListeners();
            this.setupErrorHandling();
            this.setupAutoReconnect();

            this.isInitialized = true;
            this.isConnected = true;

            this.log('ChatCore initialized successfully', this.config);
            this.triggerEvent('initialized', this.config);
        } catch (error) {
            this.handleError('Initialization failed', error);
        }
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
        document.addEventListener('visibilitychange', this._onVisibility);
    }

    async setupRealtimeListeners() {
        if (typeof Echo === 'undefined') {
            throw new Error('Echo not available - realtime features disabled');
        }
        this.log('Setting up realtime listeners');

        if (this.config.conversationId) this.setupConversationListeners();
        if (this.config.groupId) this.setupGroupListeners();

        this.setupPresenceListeners();
        this.setupUserListeners();

        this.log('Realtime listeners setup complete');
    }

    /* ================== CONVERSATION CHANNEL ================== */

    setupConversationListeners() {
        const channel = `chat.${this.config.conversationId}`;
        this.log(`Setup conversation: ${channel}`);

        const ch = Echo.private(channel)
            // Message events (dot-prefixed because PHP uses broadcastAs())
            .listen('.MessageSent', (e) => { this.log('MessageSent', e); this.handleIncomingMessage(e); })
            .listen('.MessageDeleted', (e) => { this.log('MessageDeleted', e); this.handleMessageDeleted(e); })
            .listen('.MessageEdited', (e) => { this.log('MessageEdited', e); this.handleMessageEdited(e); })
            .listen('.MessageReacted', (e) => { this.log('MessageReacted', e); this.handleMessageReacted(e); })
            .listen('.MessageRead', (e) => { this.log('MessageRead', e); this.handleMessageRead(e); })
            .listen('.MessageStatusUpdated', (e) => { this.log('MessageStatusUpdated', e); this.handleMessageStatusUpdated(e); })

            // Typing events
            .listen('.UserTyping', (e) => { this.log('UserTyping', e); this.handleTypingEvent(e); })
            .listen('.TypingInConversation', (e) => { this.log('TypingInConversation', e); this.handleTypingEvent(e); })
            .listen('.TypingStarted', (e) => { this.log('TypingStarted', e); this.handleTypingStarted(e); })
            .listen('.TypingStopped', (e) => { this.log('TypingStopped', e); this.handleTypingStopped(e); })

            .error((err) => this.handleError('Conversation channel error', err));

        this.echoChannels.set(channel, ch);
    }

    /* ======================= GROUP CHANNEL ======================= */

    setupGroupListeners() {
        const channel = `group.${this.config.groupId}`;
        this.log(`Setup group: ${channel}`);

        const ch = Echo.private(channel)
            // Group message events
            .listen('.GroupMessageSent', (e) => { this.log('GroupMessageSent', e); this.handleIncomingMessage(e); })
            .listen('.GroupMessageDeleted', (e) => { this.log('GroupMessageDeleted', e); this.handleMessageDeleted(e); })
            .listen('.GroupMessageEdited', (e) => { this.log('GroupMessageEdited', e); this.handleMessageEdited(e); })
            .listen('.GroupMessageReadEvent', (e) => { this.log('GroupMessageReadEvent', e); this.handleMessageRead(e); })

            // Group typing events
            .listen('.GroupTyping', (e) => { this.log('GroupTyping', e); this.handleTypingEvent(e); })
            .listen('.TypingInGroup', (e) => { this.log('TypingInGroup', e); this.handleTypingEvent(e); })

            // Group management
            .listen('.GroupUpdated', (e) => { this.log('GroupUpdated', e); this.handleGroupUpdated(e); })

            .error((err) => this.handleError('Group channel error', err));

        this.echoChannels.set(channel, ch);
    }

    /* ======================= PRESENCE CHANNEL ======================= */

    setupPresenceListeners() {
        // Only when attached to a room
        if (!this.config.conversationId && !this.config.groupId) return;

        const presenceName = this.config.conversationId
            ? `presence-chat.${this.config.conversationId}`
            : `presence-group.${this.config.groupId}`;

        this.log(`Setup presence: ${presenceName}`);

        const ch = Echo.join(presenceName)
            .here((users) => { this.log('presence:here', users); this.handleUsersHere(users); })
            .joining((user) => { this.log('presence:joining', user); this.handleUserJoining(user); })
            .leaving((user) => { this.log('presence:leaving', user); this.handleUserLeaving(user); })
            .listen('.PresenceUpdated', (e) => { this.log('PresenceUpdated', e); this.handlePresenceUpdated(e); })
            .error((err) => this.handleError('Presence channel error', err));

        this.echoChannels.set(presenceName, ch);
    }

    /* ========================= USER CHANNEL ========================= */

    setupUserListeners() {
        if (!this.config.userId) return;

        const userChannel = `user.${this.config.userId}`;
        this.log(`Setup user: ${userChannel}`);

        const ch = Echo.private(userChannel)
            .listen('.CallSignal', (e) => { this.log('CallSignal', e); this.handleCallSignal(e); })
            .listen('.ReceiptUpdated', (e) => { this.log('ReceiptUpdated', e); this.handleReceiptUpdated(e); })
            .error((err) => this.handleError('User channel error', err));

        this.echoChannels.set(userChannel, ch);
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
            if (t.closest('.reply-btn'))   this.handleReply?.(t.closest('.reply-btn'));
            else if (t.closest('.react-btn'))   this.handleReaction(t.closest('.react-btn'));
            else if (t.closest('.edit-btn'))    this.handleEdit?.(t.closest('.edit-btn'));
            else if (t.closest('.delete-btn'))  this.handleDelete?.(t.closest('.delete-btn'));
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

        const res = await fetch(this.config.messageUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.getCsrfToken(),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload)
        });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(`HTTP ${res.status}: ${text}`);
        }
        return await res.json();
    }

    handleIncomingMessage(event) {
        const msg = this.normalizeMessagePayload(event);
        this.triggerEvent('message', msg);
        if (this.config.onMessage) this.config.onMessage(msg);

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

        if (this.config.onTyping) {
            this.config.onTyping(payload);
            return;
        }
        if (!this.config.showTyping) return;

        this.showTypingIndicator(payload.user_id, payload.is_typing === true);

        // Auto-hide after 3s if it's a "typing" ping without explicit stop
        if (payload.is_typing) {
            clearTimeout(this.typingTimeouts.get(payload.user_id));
            const to = setTimeout(() => this.showTypingIndicator(payload.user_id, false), 3000);
            this.typingTimeouts.set(payload.user_id, to);
        }
    }

    handleTypingStarted(event) {
        this.handleTypingEvent({ ...event, is_typing: true });
    }

    handleTypingStopped(event) {
        this.handleTypingEvent({ ...event, is_typing: false });
    }

    handleGroupUpdated(event) {
        this.triggerEvent('groupUpdated', event);
    }

    handleUsersHere(users) {
        this.triggerEvent('presence', { type: 'here', users });
        if (this.config.onPresence) this.config.onPresence('here', users);
    }

    handleUserJoining(user) {
        this.triggerEvent('presence', { type: 'joining', user });
        if (this.config.onPresence) this.config.onPresence('joining', user);
    }

    handleUserLeaving(user) {
        this.triggerEvent('presence', { type: 'leaving', user });
        if (this.config.onPresence) this.config.onPresence('leaving', user);
    }

    handlePresenceUpdated(event) {
        this.triggerEvent('presence', { type: 'updated', ...event });
        if (this.config.onPresence) this.config.onPresence('updated', event);
    }

    handleCallSignal(event) {
        this.triggerEvent('call', event);
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
            timestamp: new Date().toISOString()
        };
        this.log(`Error: ${context}`, err, 'error');

        this.errorHandlers?.forEach?.((h) => {
            try { h(err); } catch (e) { console.error('Error in error handler:', e); }
        });
        if (this.config.onError) this.config.onError(err);

        this.triggerEvent('error', err);
    }

    /* ===================== PUBLIC API ===================== */

    async startTyping() {
        if (!this.config.typingUrl) return;
        try {
            await fetch(this.config.typingUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    conversation_id: this.config.conversationId,
                    group_id: this.config.groupId,
                    is_typing: true
                })
            });
        } catch (e) {
            this.handleError('Typing start failed', e);
        }
    }

    async stopTyping() {
        if (!this.config.typingUrl) return;
        try {
            if (this.config.typingStopMethod === 'DELETE') {
                const res = await fetch(this.config.typingUrl, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': this.getCsrfToken(), 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
            } else {
                await fetch(this.config.typingUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        conversation_id: this.config.conversationId,
                        group_id: this.config.groupId,
                        is_typing: false
                    })
                });
            }
        } catch (e) {
            // Fallback to POST false if DELETE not supported
            try {
                await fetch(this.config.typingUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        conversation_id: this.config.conversationId,
                        group_id: this.config.groupId,
                        is_typing: false
                    })
                });
            } catch (e2) {
                this.handleError('Typing stop failed', e2);
            }
        }
    }

    addMessageToUI(messageData, options = {}) {
        const el = this.createMessageElement(messageData);
        if (options.prepend) this.elements.messageContainer.prepend(el);
        else this.elements.messageContainer.appendChild(el);
        if (options.scrollTo && this.config.autoScroll) this.scrollToBottom();
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

    showTypingIndicator(userId, show) {
        if (show) this.currentTypers.add(userId);
        else this.currentTypers.delete(userId);
        this.updateTypingIndicator();
    }

    updateTypingIndicator() {
        if (!this.elements.typingIndicator) return;
        if (this.currentTypers.size > 0) {
            const names = Array.from(this.currentTypers).slice(0, 3).join(', ');
            const more = this.currentTypers.size > 3 ? ` and ${this.currentTypers.size - 3} more` : '';
            this.elements.typingIndicator.textContent = `${names}${more} is typing...`;
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
            return;
        }
        this.reconnectionAttempts++;
        const delay = Math.min(1000 * Math.pow(2, this.reconnectionAttempts), 30000);
        this.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectionAttempts})`);

        setTimeout(async () => {
            try {
                await this.reconnect();
            } catch (e) {
                this.handleError('Reconnection failed', e);
                this.attemptReconnection();
            }
        }, delay);
    }

    async reconnect() {
        this.log('Reconnecting...');
        // Leave channels
        Array.from(this.echoChannels.keys()).forEach((name) => {
            try { Echo.leave(name); } catch (_) {}
        });
        this.echoChannels.clear();

        // Re-setup
        await this.setupRealtimeListeners();

        this.isConnected = true;
        this.log('Reconnection successful');
    }

    /* ===================== UTILS ===================== */

    _onVisibility() {
        if (document.hidden) this.log('Page hidden');
        else this.log('Page visible');
    }

    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
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

    // Normalize server payloads into stable shapes for the UI
    normalizeMessagePayload(event) {
        const raw = event?.message ?? event ?? {};
        const id = event?.message_id ?? raw?.id ?? event?.id;
        const body = raw?.body ?? event?.body ?? '';
        const user_id = raw?.user_id ?? event?.user_id ?? raw?.sender_id;
        const time_ago = raw?.time_ago ?? event?.time_ago ?? '';
        const is_own = !!(raw?.is_own ?? (user_id && String(user_id) === String(this.config.userId)));
        return { id, body, user_id, time_ago, is_own, raw: event };
    }

    normalizeTypingPayload(event) {
        // PHP events usually broadcast: user_id, is_typing, conversation_id|group_id
        return {
            user_id: event?.user_id ?? event?.user?.id ?? event?.id,
            is_typing: event?.is_typing ?? event?.typing ?? true,
            conversation_id: event?.conversation_id ?? this.config.conversationId ?? null,
            group_id: event?.group_id ?? this.config.groupId ?? null,
            raw: event
        };
    }

    createMessageElement(messageData) {
        const el = document.createElement('div');
        el.className = `message ${messageData.is_own ? 'own-message' : 'other-message'}`;
        el.setAttribute('data-message-id', messageData.id);
        el.innerHTML = `
            <div class="message-content">
                <div class="message-body">${this.escapeHtml(messageData.body)}</div>
                <div class="message-meta">
                    <span class="message-time">${this.escapeHtml(messageData.time_ago || '')}</span>
                    ${messageData.is_own ? '<span class="message-status">✓✓</span>' : ''}
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

    onMessage(handler)    { (this.messageHandlers ??= new Set()).add(handler); return this; }
    onTyping(handler)     { (this.typingHandlers ??= new Set()).add(handler); return this; }
    onReaction(handler)   { (this.reactionHandlers ??= new Set()).add(handler); return this; }
    onPresence(handler)   { (this.presenceHandlers ??= new Set()).add(handler); return this; }
    onCall(handler)       { (this.callHandlers ??= new Set()).add(handler); return this; }
    onError(handler)      { (this.errorHandlers ??= new Set()).add(handler); return this; }

    offMessage(handler)   { this.messageHandlers?.delete(handler); return this; }
    offTyping(handler)    { this.typingHandlers?.delete(handler); return this; }
    offReaction(handler)  { this.reactionHandlers?.delete(handler); return this; }
    offPresence(handler)  { this.presenceHandlers?.delete(handler); return this; }
    offCall(handler)      { this.callHandlers?.delete(handler); return this; }
    offError(handler)     { this.errorHandlers?.delete(handler); return this; }

    /* ===================== CLEANUP ===================== */

    destroy() {
        this.log('Destroying ChatCore instance');
        Array.from(this.echoChannels.keys()).forEach((name) => {
            this.log(`Leaving channel: ${name}`);
            try { Echo.leave(name); } catch (_) {}
        });
        this.echoChannels.clear();

        this.typingTimeouts.forEach((t) => clearTimeout(t));
        this.typingTimeouts.clear();

        this.messageHandlers?.clear?.();
        this.typingHandlers?.clear?.();
        this.reactionHandlers?.clear?.();
        this.presenceHandlers?.clear?.();
        this.callHandlers?.clear?.();
        this.errorHandlers?.clear?.();

        this.currentTypers.clear();
        this.isInitialized = false;
        this.isConnected = false;
        this.reconnectionAttempts = 0;

        document.removeEventListener('visibilitychange', this._onVisibility);

        this.triggerEvent('destroyed');
        this.log('ChatCore instance destroyed');
    }

    getStatus() {
        return {
            isInitialized: this.isInitialized,
            isConnected: this.isConnected,
            reconnectionAttempts: this.reconnectionAttempts,
            activeChannels: Array.from(this.echoChannels.keys()),
            currentTypers: Array.from(this.currentTypers),
            config: { ...this.config }
        };
    }
}

window.ChatCore = ChatCore;
export default ChatCore;
