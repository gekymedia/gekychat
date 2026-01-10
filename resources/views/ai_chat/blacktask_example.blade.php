{{-- Example: BlackTask Integration in AI Chat --}}

<div class="chat-container">
    <!-- BlackTask Quick Actions Button -->
    <button 
        class="blacktask-fab" 
        onclick="document.getElementById('blacktask-commands-panel').classList.toggle('hidden')"
        title="BlackTask Quick Actions"
    >
        <i class="fas fa-tasks"></i>
    </button>

    <!-- Chat Messages Area -->
    <div id="messages-container">
        <!-- Messages will appear here -->
    </div>

    <!-- Message Input -->
    <div class="message-input-container">
        <input 
            type="text" 
            id="message-input" 
            placeholder="Type a message or BlackTask command..."
            class="message-input"
        >
        <button onclick="sendMessage()" class="send-btn">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="{{ asset('css/blacktask-commands.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/blacktask-commands.js') }}"></script>
<script>
// Initialize BlackTask commands
const blackTaskCommands = new BlackTaskCommands('message-input', sendMessage);

function sendMessage(text) {
    const input = document.getElementById('message-input');
    const message = text || input.value.trim();
    
    if (!message) return;
    
    // Send message to bot
    fetch('/api/bot/message', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            message: message,
            conversation_id: {{ $conversationId ?? 'null' }}
        })
    })
    .then(response => response.json())
    .then(data => {
        // Display user message
        displayMessage(message, 'user');
        
        // Display bot response
        if (data.response) {
            displayMessage(data.response, 'bot');
        }
        
        // Clear input
        input.value = '';
    })
    .catch(error => {
        console.error('Error sending message:', error);
        alert('Failed to send message. Please try again.');
    });
}

function displayMessage(text, type) {
    const container = document.getElementById('messages-container');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message message-${type}`;
    messageDiv.innerHTML = `
        <div class="message-bubble">
            ${formatMessage(text)}
        </div>
        <div class="message-time">${new Date().toLocaleTimeString()}</div>
    `;
    container.appendChild(messageDiv);
    container.scrollTop = container.scrollHeight;
}

function formatMessage(text) {
    // Convert markdown-style formatting to HTML
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
    text = text.replace(/\n/g, '<br>');
    return text;
}

// Enter key to send
document.getElementById('message-input').addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});
</script>
@endpush
