@extends('layouts.app')

@section('title', 'AI Chat - ' . config('app.name', 'GekyChat'))

{{-- Sidebar data is loaded by controller --}}

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="chat-header border-bottom p-3">
        <h4 class="mb-0">AI Assistant</h4>
        <small class="text-muted">Chat with AI</small>
    </div>
    
    <div class="flex-grow-1 d-flex flex-column">
        <!-- Messages -->
        <div class="flex-grow-1 overflow-auto p-4" id="ai-messages-container">
            <div class="text-center mb-4">
                <i class="bi bi-robot display-1 text-primary mb-3"></i>
                <h5>AI Assistant</h5>
                <p class="text-muted">Ask me anything! I'm here to help.</p>
            </div>
            
            <div id="ai-messages">
                <!-- Messages will appear here -->
            </div>
        </div>
        
        <!-- Input -->
        <div class="border-top p-3">
            <form id="ai-chat-form" onsubmit="sendAiMessage(event)">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Type your message..." id="ai-input" required>
                    <button class="btn btn-wa" type="submit">
                        <i class="bi bi-send"></i> Send
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.sendAiMessage = async function(e) {
        e.preventDefault();
        const input = document.getElementById('ai-input');
        const message = input.value.trim();
        if (!message) return;
        
        // Add user message to UI
        addMessage('user', message);
        input.value = '';
        
        // Show loading
        const loadingId = addMessage('ai', 'Thinking...', true);
        
        try {
            // TODO: Replace with actual AI chat endpoint when available
            // For now, show a placeholder response
            setTimeout(() => {
                removeMessage(loadingId);
                addMessage('ai', 'I\'m still learning! This feature will be available soon. For now, you can interact with GekyBot in your chats.');
            }, 1000);
        } catch (error) {
            console.error('Error sending message:', error);
            removeMessage(loadingId);
            addMessage('ai', 'Sorry, I encountered an error. Please try again.');
        }
    };
    
    function addMessage(type, text, isLoading = false) {
        const container = document.getElementById('ai-messages');
        const id = 'msg-' + Date.now();
        const messageDiv = document.createElement('div');
        messageDiv.id = id;
        messageDiv.className = `mb-3 ${type === 'user' ? 'text-end' : ''}`;
        messageDiv.innerHTML = `
            <div class="d-inline-block p-3 rounded ${type === 'user' ? 'bg-primary text-white' : 'bg-light'}" style="max-width: 70%;">
                ${isLoading ? '<div class="spinner-border spinner-border-sm"></div>' : text}
            </div>
        `;
        container.appendChild(messageDiv);
        container.scrollTop = container.scrollHeight;
        return id;
    }
    
    function removeMessage(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }
});
</script>
@endpush
@endsection
