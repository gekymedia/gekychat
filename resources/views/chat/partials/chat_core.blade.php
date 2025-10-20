<script>
class ChatCore {
    constructor(config) {
        this.config = {
            DEBOUNCE_DELAY: 300,
            TYPING_DELAY: 450,
            RETRY_DELAY: 2000,
            SCROLL_THRESHOLD: 300,
            ...config
        };
        
        this.state = {
            isLoading: false,
            hasMore: true,
            currentPage: 1,
            typingTimer: null,
            retryTimer: null,
            observer: null
        };
        
        this.elements = {};
        this.init();
    }
    
    init() {
        this.cacheElements();
        this.setupEventListeners();
        this.setupObservers();
        this.setupNetworkMonitoring();
    }
    
    // Shared methods that can be overridden
    cacheElements() {
        // Base elements both chats need
        this.elements.chatBox = document.getElementById('chat-box');
        this.elements.messagesContainer = document.getElementById('messages-container');
        this.elements.messageForm = document.getElementById('chat-form');
        this.elements.sendButton = document.getElementById('send-btn');
        this.elements.messageInput = document.getElementById('message-input');
    }
    
    setupEventListeners() {
        // Shared event listeners
        this.elements.messageForm?.addEventListener('submit', (e) => this.handleMessageSubmit(e));
        this.elements.messageInput?.addEventListener('input', () => this.handleTyping());
        
        // File upload handlers
        const photoUpload = document.getElementById('photo-upload');
        const docUpload = document.getElementById('doc-upload');
        photoUpload?.addEventListener('change', (e) => this.handleFileSelection(e));
        docUpload?.addEventListener('change', (e) => this.handleFileSelection(e));
    }
    
    // Abstract methods that must be implemented by child classes
    async handleMessageSubmit(event) {
        throw new Error('handleMessageSubmit must be implemented by child class');
    }
    
    setupRealtimeListeners() {
        throw new Error('setupRealtimeListeners must be implemented by child class');
    }
    
    // Shared concrete methods
    async handleFileSelection(event) {
        const files = Array.from(event.target.files);
        if (!this.validateFiles(files)) {
            event.target.value = '';
            return;
        }
        this.handleFileUpload(files);
        event.target.value = '';
    }
    
    validateFiles(files) {
        for (const file of files) {
            if (file.size > (10 * 1024 * 1024)) { // 10MB
                this.showToast(`File "${file.name}" is too large. Maximum size is 10MB.`, 'error');
                return false;
            }
        }
        return true;
    }
    
    handleFileUpload(files) {
        // Shared file upload logic
    }
    
    scrollToBottom(options = {}) {
        if (!this.elements.chatBox) return;
        
        const scrollHeight = this.elements.chatBox.scrollHeight;
        const clientHeight = this.elements.chatBox.clientHeight;
        const scrollTop = this.elements.chatBox.scrollTop;
        const distanceToBottom = scrollHeight - (clientHeight + scrollTop);
        
        if (options.force || distanceToBottom < this.config.SCROLL_THRESHOLD) {
            if (options.smooth) {
                this.elements.chatBox.scrollTo({ top: scrollHeight, behavior: 'smooth' });
            } else {
                this.elements.chatBox.scrollTop = scrollHeight;
            }
        }
    }
    
    showToast(message, type = 'info') {
        // Shared toast implementation
    }
    
    // ... more shared methods
}
</script>