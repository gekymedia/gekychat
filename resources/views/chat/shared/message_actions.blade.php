{{-- resources/views/chat/shared/message_actions.blade.php --}}
@php
    $messageId = $messageId ?? null;
    $isOwn = $isOwn ?? false;
    $isGroup = $isGroup ?? false;
    $canEdit = $canEdit ?? false;
    $canDelete = $canDelete ?? false;
    $body = $body ?? '';
    $deleteUrl = $deleteUrl ?? '#';
    $editUrl = $editUrl ?? '#';
    $reactUrl = $reactUrl ?? '#';
    $group = $group ?? null;
@endphp

@if($messageId)
<div class="message-actions position-absolute" 
     data-message-actions="{{ $messageId }}"
     data-context="{{ $isGroup ? 'group' : 'direct' }}"
     aria-label="Message actions">
    
    {{-- Your action buttons remain the same --}}
    <div class="action-buttons d-flex align-items-center gap-1 bg-white rounded-pill shadow-sm p-1 border">
        {{-- React Button --}}
        <button class="btn btn-sm btn-outline-secondary react-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                data-react-url="{{ $reactUrl }}"
                title="Add reaction"
                aria-label="Add reaction to message">
            <i class="bi bi-emoji-smile" aria-hidden="true"></i>
        </button>

        {{-- Reply Button --}}
        <button class="btn btn-sm btn-outline-secondary reply-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                data-sender-name="{{ $isOwn ? 'You' : ($senderName ?? 'User') }}"
                data-body-preview="{{ Str::limit($body, 50) }}"
                title="Reply"
                aria-label="Reply to message">
            <i class="bi bi-reply" aria-hidden="true"></i>
        </button>

        {{-- Forward Button --}}
        <button class="btn btn-sm btn-outline-secondary forward-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                title="Forward"
                aria-label="Forward message">
            <i class="bi bi-forward" aria-hidden="true"></i>
        </button>

        {{-- Edit Button (only for own messages) --}}
        @if($canEdit)
            <button class="btn btn-sm btn-outline-secondary edit-btn" 
                    type="button"
                    data-message-id="{{ $messageId }}"
                    data-edit-url="{{ $editUrl }}"
                    data-original-body="{{ $body }}"
                    title="Edit"
                    aria-label="Edit message">
                <i class="bi bi-pencil" aria-hidden="true"></i>
            </button>
        @endif

        {{-- Delete Button --}}
        @if($canDelete)
            <button class="btn btn-sm btn-outline-danger delete-btn" 
                    type="button"
                    data-message-id="{{ $messageId }}"
                    data-delete-url="{{ $deleteUrl }}"
                    title="Delete"
                    aria-label="Delete message">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
        @endif
    </div>

    {{-- Reaction Picker --}}
    <div class="reaction-picker position-absolute bottom-100 start-0 bg-white rounded shadow-lg border p-2 d-none" 
         data-reaction-picker="{{ $messageId }}"
         aria-label="Reaction picker">
        <div class="d-flex gap-1">
            @foreach(['👍', '❤️', '😂', '😮', '😢', '😡'] as $emoji)
                <button class="btn btn-sm reaction-option" 
                        type="button"
                        data-emoji="{{ $emoji }}"
                        data-message-id="{{ $messageId }}"
                        aria-label="React with {{ $emoji }}">
                    {{ $emoji }}
                </button>
            @endforeach
        </div>
    </div>
</div>

{{-- Edit Message Modal --}}
@if($canEdit)
<div class="modal fade" id="edit-message-modal-{{ $messageId }}" 
     tabindex="-1" aria-labelledby="edit-message-label-{{ $messageId }}" aria-hidden="true"
     data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="edit-message-label-{{ $messageId }}">Edit Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit-message-text-{{ $messageId }}" class="form-label">Message</label>
                    <textarea class="form-control edit-message-text" 
                              id="edit-message-text-{{ $messageId }}" 
                              rows="4" 
                              maxlength="1000"
                              placeholder="Type your message...">{{ $body }}</textarea>
                    <div class="form-text text-end">
                        <span class="char-count">0</span>/1000 characters
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary save-edit-btn" 
                        data-message-id="{{ $messageId }}"
                        data-edit-url="{{ $editUrl }}">
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Delete Message Modal --}}
@if($canDelete)
<div class="modal fade" id="delete-message-modal-{{ $messageId }}" 
     tabindex="-1" aria-labelledby="delete-message-label-{{ $messageId }}" aria-hidden="true"
     data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="delete-message-label-{{ $messageId }}">Delete Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this message? This action cannot be undone.</p>
                @if($isGroup)
                <div class="alert alert-warning mb-0">
                    <small>
                        <i class="bi bi-info-circle"></i>
                        This message will be deleted for everyone in the group.
                    </small>
                </div>
                @else
                <div class="alert alert-info mb-0">
                    <small>
                        <i class="bi bi-info-circle"></i>
                        This message will be deleted from both sides of the conversation.
                    </small>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger confirm-delete-btn" 
                        data-message-id="{{ $messageId }}"
                        data-delete-url="{{ $deleteUrl }}">
                    Delete Message
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@endif

<style>
.message-actions {
    z-index: 10;
    bottom: -10px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
}

.message-bubble.sent .message-actions {
    right: -10px;
    left: auto;
}

.message-bubble.received .message-actions {
    left: -10px;
    right: auto;
}

/* Show actions on message hover */
.message:hover .message-actions {
    opacity: 1;
    visibility: visible;
}

.action-buttons {
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95) !important;
    border: 1px solid var(--border);
}

[data-theme="dark"] .action-buttons {
    background: rgba(30, 30, 30, 0.95) !important;
    border-color: #444 !important;
}

.action-buttons .btn {
    padding: 4px 8px;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.action-buttons .btn:hover {
    transform: scale(1.1);
}

.reaction-picker {
    z-index: 20;
    min-width: 200px;
}

.reaction-option {
    font-size: 1.2rem;
    padding: 4px 8px;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.reaction-option:hover {
    background: #f8f9fa;
    transform: scale(1.1);
}

[data-theme="dark"] .reaction-option:hover {
    background: #2d3748;
}

/* FIXED: Modal Styles - Remove custom backdrop styles that conflict with Bootstrap */
.modal-backdrop {
    /* Let Bootstrap handle the backdrop */
    z-index: 1040; /* Bootstrap default */
}

.modal {
    z-index: 1055 !important; /* Higher than backdrop */
}

.modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(10px);
}

.modal-header {
    border-bottom: 1px solid var(--border);
    padding: 1rem 1.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid var(--border);
    padding: 1rem 1.5rem;
}

/* Light modal backdrop - override Bootstrap's default */
.modal-backdrop.show {
    opacity: 0.1 !important; /* Very low opacity */
    background-color: #000 !important;
}

/* Ensure modal is above everything with proper stacking */
.modal.show {
    display: block !important;
    background-color: rgba(0, 0, 0, 0.1); /* Light backdrop */
}

/* Character count styling */
.char-count {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.char-count.warning {
    color: #ffc107;
}

.char-count.danger {
    color: #dc3545;
}

/* Textarea styling for edit modal */
.edit-message-text {
    resize: vertical;
    min-height: 100px;
    border-radius: 8px;
    border: 1px solid var(--border);
    padding: 12px;
    font-family: inherit;
    transition: all 0.2s ease;
}

.edit-message-text:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
}

/* Responsive design */
@media (max-width: 768px) {
    .message-actions {
        position: relative !important;
        bottom: auto;
        right: auto;
        left: auto;
        margin-top: 8px;
        justify-content: center;
        opacity: 1;
        visibility: visible;
    }
    
    .message-bubble.sent .message-actions,
    .message-bubble.received .message-actions {
        left: auto;
        right: auto;
    }
    
    .action-buttons {
        background: rgba(255, 255, 255, 0.98) !important;
    }
    
    .modal-dialog {
        margin: 1rem;
    }
    
    .modal-content {
        border-radius: 16px;
    }
}

/* Animation for modal appearance */
@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-content {
    animation: modalSlideIn 0.2s ease-out;
}

/* Dark theme support */
[data-theme="dark"] .modal-content {
    background: var(--card);
    color: var(--text);
}

[data-theme="dark"] .modal-header,
[data-theme="dark"] .modal-footer {
    border-color: #444;
}

[data-theme="dark"] .edit-message-text {
    background: var(--input-bg);
    color: var(--text);
    border-color: #444;
}

/* Ensure proper stacking context */
.chat-container {
    position: relative;
    z-index: 1;
}

/* Make sure modals are above chat content */
.modal {
    z-index: 1060 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing message actions...');
    
    // Initialize message actions
    initializeMessageActions();
    
    function initializeMessageActions() {
        console.log('Setting up message action handlers...');
        
        // Reaction functionality
        setupReactionHandlers();
        
        // Reply functionality
        setupReplyHandlers();
        
        // Forward functionality
        setupForwardHandlers();
        
        // Edit functionality
        setupEditHandlers();
        
        // Delete functionality
        setupDeleteHandlers();
        
        // Character count for edit modals
        setupCharacterCount();
        
        // Fix modal backdrop issues
        setupModalCleanup();
    }
    
    function setupReactionHandlers() {
        // ... your existing reaction handlers ...
        document.querySelectorAll('.react-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const messageId = this.dataset.messageId;
                const picker = document.querySelector(`[data-reaction-picker="${messageId}"]`);
                const isVisible = !picker.classList.contains('d-none');
                
                // Hide all other pickers
                document.querySelectorAll('.reaction-picker').forEach(p => {
                    p.classList.add('d-none');
                });
                
                // Toggle this picker
                if (!isVisible) {
                    picker.classList.remove('d-none');
                    
                    // Position picker
                    const rect = this.getBoundingClientRect();
                    picker.style.left = '0';
                    picker.style.bottom = '100%';
                }
            });
        });
        
        // Add reaction
        document.querySelectorAll('.reaction-option').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.stopPropagation();
                const messageId = this.dataset.messageId;
                const emoji = this.dataset.emoji;
                const reactUrl = document.querySelector(`.react-btn[data-message-id="${messageId}"]`).dataset.reactUrl;
                
                try {
                    const response = await fetch(reactUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            message_id: messageId,
                            emoji: emoji
                        })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        // Hide picker
                        document.querySelector(`[data-reaction-picker="${messageId}"]`).classList.add('d-none');
                        // Reload message or update UI
                        location.reload();
                    }
                } catch (error) {
                    console.error('Reaction error:', error);
                    showToast('Failed to add reaction', 'error');
                }
            });
        });
        
        // Close reaction picker when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('.reaction-picker').forEach(picker => {
                picker.classList.add('d-none');
            });
        });
    }
    
    function setupReplyHandlers() {
        // ... your existing reply handlers ...
        document.querySelectorAll('.reply-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('Reply button clicked');
                
                const messageId = this.dataset.messageId;
                const senderName = this.dataset.senderName;
                const bodyPreview = this.dataset.bodyPreview;
                
                console.log('Reply data:', { messageId, senderName, bodyPreview });
                
                // Trigger reply event
                const event = new CustomEvent('message-reply', {
                    detail: {
                        messageId: messageId,
                        senderName: senderName,
                        bodyPreview: bodyPreview
                    }
                });
                document.dispatchEvent(event);
                
                // Also call the global reply function directly
                if (window.showReplyPreview) {
                    console.log('Calling showReplyPreview directly');
                    window.showReplyPreview(bodyPreview, senderName, messageId);
                }
            });
        });
    }
    
    function setupForwardHandlers() {
        // ... your existing forward handlers ...
        document.querySelectorAll('.forward-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const messageId = this.dataset.messageId;
                
                // Use the global forward function
                if (typeof window.openForwardModal === 'function') {
                    window.openForwardModal(messageId);
                }
            });
        });
    }
    
    function setupEditHandlers() {
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const messageId = this.dataset.messageId;
                const modalElement = document.getElementById(`edit-message-modal-${messageId}`);
                
                if (modalElement) {
                    // Initialize character count
                    const textarea = modalElement.querySelector('.edit-message-text');
                    const charCount = modalElement.querySelector('.char-count');
                    if (textarea && charCount) {
                        charCount.textContent = textarea.value.length;
                    }
                    
                    // Use Bootstrap's modal properly
                    const modal = new bootstrap.Modal(modalElement, {
                        backdrop: true, // Enable backdrop
                        keyboard: true  // Allow ESC key to close
                    });
                    modal.show();
                }
            });
        });
        
        // Save edit
        document.querySelectorAll('.save-edit-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const messageId = this.dataset.messageId;
                const editUrl = this.dataset.editUrl;
                const modalElement = document.getElementById(`edit-message-modal-${messageId}`);
                const textarea = modalElement.querySelector('.edit-message-text');
                const newBody = textarea.value.trim();
                
                if (!newBody) {
                    showToast('Message cannot be empty', 'error');
                    return;
                }
                
                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Saving...';
                this.disabled = true;
                
                try {
                    const response = await fetch(editUrl, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            body: newBody
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        // Close modal using Bootstrap's method
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        modal.hide();
                        
                        // Update message in UI
                        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
                        if (messageElement) {
                            const messageBody = messageElement.querySelector('.message-body');
                            if (messageBody) {
                                messageBody.textContent = newBody;
                                // Add edited indicator
                                messageElement.classList.add('edited');
                                if (!messageElement.querySelector('.edited-indicator')) {
                                    const indicator = document.createElement('span');
                                    indicator.className = 'edited-indicator small text-muted ms-1';
                                    indicator.textContent = '(edited)';
                                    messageBody.appendChild(indicator);
                                }
                            }
                        }
                        
                        showToast('Message updated successfully', 'success');
                    } else {
                        throw new Error(result.message || 'Failed to update message');
                    }
                } catch (error) {
                    console.error('Edit error:', error);
                    showToast(error.message || 'Failed to update message', 'error');
                } finally {
                    // Reset button state
                    this.innerHTML = originalText;
                    this.disabled = false;
                }
            });
        });
    }
    
    function setupDeleteHandlers() {
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const messageId = this.dataset.messageId;
                const modalElement = document.getElementById(`delete-message-modal-${messageId}`);
                
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement, {
                        backdrop: true,
                        keyboard: true
                    });
                    modal.show();
                }
            });
        });
        
        // Confirm delete
        document.querySelectorAll('.confirm-delete-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const messageId = this.dataset.messageId;
                const deleteUrl = this.dataset.deleteUrl;
                const modalElement = document.getElementById(`delete-message-modal-${messageId}`);
                
                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Deleting...';
                this.disabled = true;
                
                try {
                    const response = await fetch(deleteUrl, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        modal.hide();
                        
                        // Remove message from UI
                        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
                        if (messageElement) {
                            messageElement.remove();
                        }
                        
                        showToast('Message deleted successfully', 'success');
                    } else {
                        throw new Error(result.message || 'Failed to delete message');
                    }
                } catch (error) {
                    console.error('Delete error:', error);
                    showToast(error.message || 'Failed to delete message', 'error');
                } finally {
                    // Reset button state
                    this.innerHTML = originalText;
                    this.disabled = false;
                }
            });
        });
    }
    
    function setupCharacterCount() {
        // Character count for edit textareas
        document.querySelectorAll('.edit-message-text').forEach(textarea => {
            const charCount = textarea.closest('.form-group').querySelector('.char-count');
            
            textarea.addEventListener('input', function() {
                const length = this.value.length;
                charCount.textContent = length;
                
                // Update color based on length
                charCount.classList.remove('warning', 'danger');
                if (length > 800) {
                    charCount.classList.add('warning');
                }
                if (length > 950) {
                    charCount.classList.add('danger');
                }
            });
            
            // Initialize count
            charCount.textContent = textarea.value.length;
        });
    }
    
    function setupModalCleanup() {
        // Ensure modals close properly when backdrop is clicked
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                // Find the modal instance and hide it
                const modalElement = e.target;
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
            }
        });
        
        // Also handle ESC key for all modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    const modal = bootstrap.Modal.getInstance(openModal);
                    if (modal) {
                        modal.hide();
                    }
                }
            }
        });
    }
});

// Global reply event listener
document.addEventListener('message-reply', function(e) {
    console.log('Message reply event received:', e.detail);
    
    const { messageId, senderName, bodyPreview } = e.detail;
    
    // Focus on message input
    const messageInput = document.querySelector('#message-input, [name="message"], .message-input');
    if (messageInput) {
        // Add reply context
        messageInput.focus();
        messageInput.setAttribute('data-reply-to', messageId);
        messageInput.setAttribute('placeholder', `Replying to ${senderName}: ${bodyPreview}...`);
        
        // You can also show a reply preview above the input
        if (window.showReplyPreview) {
            window.showReplyPreview(bodyPreview, senderName, messageId);
        }
    }
});

// Toast notification helper
function showToast(message, type = 'info') {
    // Use your existing toast implementation or create a simple one
    if (typeof window.toast === 'function') {
        window.toast(message, type);
    } else {
        console.log(`[${type.toUpperCase()}] ${message}`);
        // Fallback: Use Bootstrap toast if available
        const toastElement = document.createElement('div');
        toastElement.className = `toast align-items-center text-bg-${type === 'error' ? 'danger' : type} border-0`;
        toastElement.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toastElement);
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        
        // Remove after hide
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }
}
</script>