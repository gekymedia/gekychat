// resources/js/chat/ChatCore.js
export class ChatCore {
    constructor(config = {}) {
        this.config = config;
        this.isInitialized = false;
        this.messageHandlers = new Set();
        this.init();
    }
    
    init() {
        if (this.isInitialized) return;
        
        this.setupEventListeners();
        this.setupRealtimeListeners();
        this.isInitialized = true;
        
        console.log('ChatCore initialized with config:', this.config);
    }
    
    setupEventListeners() {
        // Base event listeners for all chat types
        this.setupMessageForm();
        this.setupTypingIndicator();
        this.setupMessageActions();
    }
    
    setupRealtimeListeners() {
        // Base realtime listeners
        if (typeof Echo !== 'undefined') {
            console.log('Echo available for realtime features');
        }
    }
    
    setupMessageForm() {
        const form = document.getElementById('message-form');
        if (form) {
            form.addEventListener('submit', (e) => this.handleMessageSubmit(e));
        }
    }
    
    setupTypingIndicator() {
        // Common typing indicator logic
        const messageInput = document.getElementById('message-input');
        if (messageInput) {
            let typingTimeout;
            
            messageInput.addEventListener('input', () => {
                this.startTyping();
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => this.stopTyping(), 1000);
            });
            
            messageInput.addEventListener('blur', () => this.stopTyping());
        }
    }
    
    setupMessageActions() {
        // Common message actions (reply, react, delete, etc.)
        document.addEventListener('click', (e) => {
            if (e.target.closest('.reply-btn')) {
                this.handleReply(e.target.closest('.reply-btn'));
            }
            
            if (e.target.closest('.react-btn')) {
                this.handleReaction(e.target.closest('.react-btn'));
            }
        });
    }
    
    async startTyping() {
        if (!this.config.typingUrl) return;
        
        try {
            await fetch(this.config.typingUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json'
                }
            });
        } catch (error) {
            console.error('Error sending typing indicator:', error);
        }
    }
    
    async stopTyping() {
        if (!this.config.typingUrl) return;
        
        try {
            await fetch(this.config.typingUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
        } catch (error) {
            console.error('Error stopping typing indicator:', error);
        }
    }
    
    async handleMessageSubmit(event) {
        event.preventDefault();
        // To be implemented by child classes
        console.log('Base message submit handler - override in child class');
    }
    
    handleIncomingMessage(event) {
        console.log('Incoming message:', event);
        // To be implemented by child classes
    }
    
    handleTypingEvent(event) {
        console.log('Typing event:', event);
        // To be implemented by child classes
    }
    
    addMessageHandler(handler) {
        this.messageHandlers.add(handler);
    }
    
    removeMessageHandler(handler) {
        this.messageHandlers.delete(handler);
    }
}

// Make available globally
window.ChatCore = ChatCore;