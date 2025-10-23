// resources/js/chat-realtime.js
class ChatRealTime {
    constructor() {
        this.isConnected = false;
        this.currentChat = null;
        this.typingUsers = new Set();
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.connectToEcho();
        this.setupPollingFallback();
    }

    setupEventListeners() {
        // Message sent event
        document.addEventListener('message:sent', (e) => {
            this.handleNewMessage(e.detail.message, e.detail.html);
        });

        // Typing indicators
        let typingTimer;
        const messageInput = document.getElementById('message-input');
        
        if (messageInput) {
            messageInput.addEventListener('input', () => {
                this.broadcastTyping();
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => {
                    this.broadcastStopTyping();
                }, 1000);
            });
        }
    }

    connectToEcho() {
        if (typeof Echo === 'undefined') {
            console.warn('Echo not available, using polling fallback');
            return;
        }

        const chatType = window.currentChatType; // 'direct' or 'group'
        const chatId = window.currentChatId;

        if (!chatId) return;

        // Join presence channel
        if (chatType === 'group') {
            window.Echo.join(`group-chat.${chatId}`)
                .here((users) => {
                    console.log('Users in chat:', users);
                    this.updateOnlineUsers(users);
                })
                .joining((user) => {
                    console.log('User joined:', user);
                    this.showUserJoined(user);
                })
                .leaving((user) => {
                    console.log('User left:', user);
                    this.showUserLeft(user);
                })
                .listen('.message.sent', (e) => {
                    this.handleNewMessage(e.message, e.html);
                })
                .listenForWhisper('typing', (e) => {
                    this.handleUserTyping(e.user);
                })
                .listenForWhisper('stop-typing', (e) => {
                    this.handleUserStopTyping(e.user);
                });
        } else {
            window.Echo.join(`chat.${chatId}`)
                .here((users) => {
                    this.updateOnlineUsers(users);
                })
                .listen('.message.sent', (e) => {
                    this.handleNewMessage(e.message, e.html);
                });
        }

        this.isConnected = true;
    }

    handleNewMessage(message, html = null) {
        // Don't add if it's our own message (already added via AJAX)
        if (message.sender_id === window.currentUserId) {
            return;
        }

        // Add message to chat
        if (html) {
            this.appendMessageHTML(html);
        } else {
            this.appendMessage(message);
        }

        // Scroll to bottom
        this.scrollToBottom();
        
        // Play notification sound
        this.playNotificationSound();
        
        // Update unread count
        this.updateUnreadCount();
        
        // Show notification
        this.showDesktopNotification(message);
    }

    appendMessageHTML(html) {
        const messagesContainer = document.getElementById('messages-container');
        if (messagesContainer) {
            // Create temporary container
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const newMessage = tempDiv.firstElementChild;
            
            // Add with animation
            newMessage.style.opacity = '0';
            newMessage.style.transform = 'translateY(20px)';
            messagesContainer.appendChild(newMessage);
            
            // Animate in
            setTimeout(() => {
                newMessage.style.transition = 'all 0.3s ease';
                newMessage.style.opacity = '1';
                newMessage.style.transform = 'translateY(0)';
            }, 50);
            
            // Clean up temporary container
            tempDiv.remove();
        }
    }

    appendMessage(message) {
        // Fallback: Make AJAX request to get message HTML
        fetch(`/messages/${message.id}/html`)
            .then(response => response.text())
            .then(html => {
                this.appendMessageHTML(html);
            })
            .catch(error => {
                console.error('Failed to load message HTML:', error);
            });
    }

    scrollToBottom() {
        const container = document.getElementById('messages-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    playNotificationSound() {
        const audio = new Audio('/sounds/notification.mp3');
        audio.volume = 0.3;
        audio.play().catch(e => console.log('Audio play failed:', e));
    }

    updateUnreadCount() {
        // Update unread badge if needed
        const badge = document.getElementById('unread-badge');
        if (badge) {
            const current = parseInt(badge.textContent) || 0;
            badge.textContent = current + 1;
            badge.style.display = 'inline';
        }
    }

    showDesktopNotification(message) {
        if (document.hidden && Notification.permission === 'granted') {
            const notification = new Notification(`${message.sender_name} said:`, {
                body: message.body ? message.body.substring(0, 100) : 'Sent an attachment',
                icon: '/icons/icon-192x192.png',
                tag: `message-${message.id}`
            });

            notification.onclick = () => {
                window.focus();
                notification.close();
            };
        }
    }

    broadcastTyping() {
        if (!this.isConnected) return;
        
        const chatType = window.currentChatType;
        const chatId = window.currentChatId;
        
        if (chatType === 'group') {
            window.Echo.join(`group-chat.${chatId}`)
                .whisper('typing', {
                    user: window.currentUser
                });
        }
    }

    broadcastStopTyping() {
        if (!this.isConnected) return;
        
        const chatType = window.currentChatType;
        const chatId = window.currentChatId;
        
        if (chatType === 'group') {
            window.Echo.join(`group-chat.${chatId}`)
                .whisper('stop-typing', {
                    user: window.currentUser
                });
        }
    }

    handleUserTyping(user) {
        this.typingUsers.add(user.id);
        this.updateTypingIndicator();
    }

    handleUserStopTyping(user) {
        this.typingUsers.delete(user.id);
        this.updateTypingIndicator();
    }

    updateTypingIndicator() {
        const indicator = document.getElementById('typing-indicator');
        if (!indicator) return;

        if (this.typingUsers.size > 0) {
            const names = Array.from(this.typingUsers).map(id => 
                this.getUserName(id) || 'Someone'
            );
            
            let text = '';
            if (names.length === 1) {
                text = `${names[0]} is typing...`;
            } else if (names.length === 2) {
                text = `${names[0]} and ${names[1]} are typing...`;
            } else {
                text = `${names[0]} and ${names.length - 1} others are typing...`;
            }
            
            indicator.textContent = text;
            indicator.style.display = 'block';
        } else {
            indicator.style.display = 'none';
        }
    }

    getUserName(userId) {
        // Implement based on your user data structure
        return null;
    }

    updateOnlineUsers(users) {
        const onlineList = document.getElementById('online-users');
        if (onlineList) {
            onlineList.innerHTML = users.map(user => 
                `<div class="online-user" data-user-id="${user.id}">
                    <span class="status-dot"></span>
                    ${user.name}
                </div>`
            ).join('');
        }
    }

    showUserJoined(user) {
        this.showSystemMessage(`${user.name} joined the chat`);
    }

    showUserLeft(user) {
        this.showSystemMessage(`${user.name} left the chat`);
    }

    showSystemMessage(text) {
        const messagesContainer = document.getElementById('messages-container');
        if (messagesContainer) {
            const systemMsg = document.createElement('div');
            systemMsg.className = 'system-message text-center text-muted my-2';
            systemMsg.textContent = text;
            messagesContainer.appendChild(systemMsg);
            this.scrollToBottom();
        }
    }

    // Polling fallback for when WebSockets fail
    setupPollingFallback() {
        if (!this.isConnected) {
            this.startPolling();
        }
    }

    startPolling() {
        setInterval(() => {
            this.checkForNewMessages();
        }, 3000); // Check every 3 seconds
    }

    checkForNewMessages() {
        const lastMessage = this.getLastMessageId();
        
        fetch(`/api/chat/${window.currentChatId}/messages/new?after=${lastMessage}`)
            .then(response => response.json())
            .then(messages => {
                messages.forEach(message => {
                    this.handleNewMessage(message);
                });
            })
            .catch(error => console.error('Polling error:', error));
    }

    getLastMessageId() {
        const messages = document.querySelectorAll('[data-message-id]');
        if (messages.length > 0) {
            return messages[messages.length - 1].getAttribute('data-message-id');
        }
        return 0;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.chatRealTime = new ChatRealTime();
});