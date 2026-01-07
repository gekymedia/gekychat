@extends('layouts.app')

@section('title', 'Email Chat - ' . config('app.name', 'GekyChat'))

{{-- Sidebar data is loaded by controller --}}

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="chat-header border-bottom p-3">
        <h4 class="mb-0">Email Chat</h4>
        <small class="text-muted">View and reply to emails as chat messages</small>
    </div>
    
    <div class="flex-grow-1 d-flex">
        <!-- Email Conversations List -->
        <div class="border-end" style="width: 360px; overflow-y: auto;">
            <div class="p-3 border-bottom">
                <input type="text" class="form-control form-control-sm" placeholder="Search emails..." id="email-search">
            </div>
            <div id="email-conversations-list">
                <div class="text-center py-5">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2 small">Loading emails...</p>
                </div>
            </div>
        </div>
        
        <!-- Email Conversation View -->
        <div class="flex-grow-1 d-flex flex-column" id="email-conversation-view">
            <div class="flex-grow-1 d-flex align-items-center justify-content-center">
                <div class="text-center">
                    <i class="bi bi-envelope-fill display-1 text-muted mb-3"></i>
                    <h5 class="mb-2">Select an email</h5>
                    <p class="text-muted">Choose an email from the list to view messages</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let selectedConversationId = null;
    
    async function loadEmailConversations() {
        try {
            const response = await fetch('/api/v1/mail', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to load emails');
            }
            
            const data = await response.json();
            const conversations = data.data || [];
            renderConversations(conversations);
        } catch (error) {
            console.error('Error loading emails:', error);
            const errorMsg = error.message || 'Failed to load emails';
            document.getElementById('email-conversations-list').innerHTML = 
                `<div class="text-center py-5">
                    <p class="text-danger">${errorMsg}</p>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="location.reload()">Retry</button>
                </div>`;
        }
    }
    
    function renderConversations(conversations) {
        const container = document.getElementById('email-conversations-list');
        
        if (conversations.length === 0) {
            container.innerHTML = '<div class="text-center py-5"><p class="text-muted">No emails</p></div>';
            return;
        }
        
        container.innerHTML = conversations.map(conv => `
            <div class="conversation-item p-3 border-bottom cursor-pointer" 
                 onclick="selectConversation(${conv.id})" 
                 data-conv-id="${conv.id}">
                <div class="d-flex align-items-center">
                    <i class="bi bi-envelope-fill text-primary me-2"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${conv.name || 'Email Conversation'}</div>
                        ${conv.last_message ? `
                            <div class="text-muted small text-truncate">${conv.last_message.body}</div>
                            <small class="text-muted">${new Date(conv.last_message.created_at).toLocaleString()}</small>
                        ` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    window.selectConversation = async function(conversationId) {
        selectedConversationId = conversationId;
        
        // Highlight selected
        document.querySelectorAll('.conversation-item').forEach(el => {
            el.classList.toggle('bg-light', el.dataset.convId == conversationId);
        });
        
        // Load messages
        try {
            const response = await fetch(`/api/v1/mail/conversations/${conversationId}/messages`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to load messages');
            }
            
            const data = await response.json();
            const messages = data.data || [];
            renderMessages(messages);
        } catch (error) {
            console.error('Error loading messages:', error);
            const errorMsg = error.message || 'Failed to load messages';
            document.getElementById('email-conversation-view').innerHTML = 
                `<div class="text-center p-5">
                    <p class="text-danger">${errorMsg}</p>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="selectConversation(${conversationId})">Retry</button>
                </div>`;
        }
    };
    
    function renderMessages(messages) {
        const container = document.getElementById('email-conversation-view');
            const currentUserId = {{ auth()->id() }};
            container.innerHTML = `
            <div class="flex-grow-1 overflow-auto p-3">
                ${messages.map(msg => {
                    const isOwn = msg.sender_id === currentUserId;
                    return `
                    <div class="mb-3 ${isOwn ? 'text-end' : ''}">
                        <div class="d-inline-block p-3 rounded ${isOwn ? 'bg-primary text-white' : 'bg-light'}">
                            ${msg.is_email ? '<small class="d-block mb-1"><i class="bi bi-envelope"></i> Email</small>' : ''}
                            ${msg.subject ? `<strong class="d-block mb-1">${msg.subject}</strong>` : ''}
                            <div>${msg.body}</div>
                            <small class="d-block mt-1 opacity-75">${new Date(msg.created_at).toLocaleString()}</small>
                        </div>
                    </div>
                `;
                }).join('')}
            </div>
            <div class="border-top p-3">
                <form id="reply-form" onsubmit="sendReply(event)">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Type a reply..." id="reply-input" required>
                        <button class="btn btn-wa" type="submit">Send</button>
                    </div>
                </form>
            </div>
        `;
    }
    
    window.sendReply = async function(e) {
        e.preventDefault();
        const input = document.getElementById('reply-input');
        const text = input.value.trim();
        if (!text || !selectedConversationId) return;
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';
        
        try {
            const response = await fetch(`/api/v1/mail/conversations/${selectedConversationId}/reply`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ body: text })
            });
            
            const data = await response.json();
            
            if (response.ok) {
                input.value = '';
                selectConversation(selectedConversationId); // Reload messages
            } else {
                alert(data.message || 'Failed to send reply');
            }
        } catch (error) {
            console.error('Error sending reply:', error);
            alert('Failed to send reply. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    };
    
    loadEmailConversations();
});
</script>
@endpush
@endsection
