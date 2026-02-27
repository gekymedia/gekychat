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
    $senderName = $senderName ?? 'User';
@endphp

@if($messageId)
<div class="message-actions position-absolute" 
     data-message-actions="{{ $messageId }}"
     data-context="{{ $isGroup ? 'group' : 'direct' }}"
     aria-label="Message actions">
    
    {{-- Mobile: 3-dot menu button (visible only on mobile) --}}
    <button class="btn btn-sm btn-light mobile-actions-toggle d-md-none rounded-circle shadow-sm" 
            type="button"
            data-message-id="{{ $messageId }}"
            aria-label="Message options"
            title="Options">
        <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
    </button>

    {{-- Action buttons (hidden on mobile until 3-dot is clicked) --}}
    <div class="action-buttons d-none d-md-flex align-items-center gap-1 bg-white rounded-pill shadow-sm p-1 border"
         data-actions-panel="{{ $messageId }}">
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
                data-sender-name="{{ $isOwn ? 'You' : $senderName }}"
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

        {{-- Reply Privately Button (groups only, not own message) --}}
        @if($isGroup && !$isOwn)
            <button class="btn btn-sm btn-outline-secondary reply-private-btn"
                    type="button"
                    onclick="window.location.href='{{ route('groups.messages.reply-private', ['group' => $group->id ?? ($group ?? null), 'message' => $messageId]) }}'"
                    title="Reply privately"
                    aria-label="Reply privately to this message">
                <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
            </button>
        @endif

        {{-- Pin Message Button --}}
        <button class="btn btn-sm btn-outline-secondary pin-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                data-is-group="{{ $isGroup ? 'true' : 'false' }}"
                data-group-id="{{ $group->id ?? '' }}"
                title="Pin message"
                aria-label="Pin this message"
                onclick="pinMessage({{ $messageId }}, {{ $isGroup ? 'true' : 'false' }}, '{{ $group->id ?? '' }}')">
            <i class="bi bi-pin-angle" aria-hidden="true"></i>
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
                    data-is-group="{{ $isGroup ? 'true' : 'false' }}"
                    title="Delete"
                    aria-label="Delete message">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
        @endif
    </div>

    {{-- Mobile Bottom Sheet Menu --}}
    <div class="mobile-actions-sheet d-md-none" data-mobile-sheet="{{ $messageId }}">
        <div class="mobile-actions-backdrop"></div>
        <div class="mobile-actions-content">
            <div class="mobile-actions-handle"></div>
            <div class="mobile-actions-list">
                {{-- React --}}
                <button class="mobile-action-item react-btn" 
                        type="button"
                        data-message-id="{{ $messageId }}"
                        data-react-url="{{ $reactUrl }}">
                    <i class="bi bi-emoji-smile"></i>
                    <span>React</span>
                </button>
                {{-- Reply --}}
                <button class="mobile-action-item reply-btn" 
                        type="button"
                        data-message-id="{{ $messageId }}"
                        data-sender-name="{{ $isOwn ? 'You' : $senderName }}"
                        data-body-preview="{{ Str::limit($body, 50) }}">
                    <i class="bi bi-reply"></i>
                    <span>Reply</span>
                </button>
                {{-- Forward --}}
                <button class="mobile-action-item forward-btn" 
                        type="button"
                        data-message-id="{{ $messageId }}">
                    <i class="bi bi-forward"></i>
                    <span>Forward</span>
                </button>
                {{-- Reply Privately (groups only) --}}
                @if($isGroup && !$isOwn)
                <button class="mobile-action-item"
                        type="button"
                        onclick="window.location.href='{{ route('groups.messages.reply-private', ['group' => $group->id ?? ($group ?? null), 'message' => $messageId]) }}'">
                    <i class="bi bi-person-lines-fill"></i>
                    <span>Reply Privately</span>
                </button>
                @endif
                {{-- Pin --}}
                <button class="mobile-action-item pin-btn" 
                        type="button"
                        data-message-id="{{ $messageId }}"
                        onclick="pinMessage({{ $messageId }}, {{ $isGroup ? 'true' : 'false' }}, '{{ $group->id ?? '' }}')">
                    <i class="bi bi-pin-angle"></i>
                    <span>Pin Message</span>
                </button>
                {{-- Edit --}}
                @if($canEdit)
                <button class="mobile-action-item edit-btn" 
                        type="button"
                        data-message-id="{{ $messageId }}"
                        data-edit-url="{{ $editUrl }}"
                        data-original-body="{{ $body }}">
                    <i class="bi bi-pencil"></i>
                    <span>Edit</span>
                </button>
                @endif
                {{-- Delete --}}
                @if($canDelete)
                <button class="mobile-action-item delete-btn text-danger" 
                        type="button"
                        data-message-id="{{ $messageId }}"
                        data-delete-url="{{ $deleteUrl }}"
                        data-is-group="{{ $isGroup ? 'true' : 'false' }}">
                    <i class="bi bi-trash"></i>
                    <span>Delete</span>
                </button>
                @endif
            </div>
        </div>
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
@endif

{{-- Everything below renders ONLY ONCE regardless of how many messages --}}
@once('shared-message-modals-and-js')

{{-- Shared Edit Message Modal --}}
<div class="modal fade" id="shared-edit-message-modal" 
     tabindex="-1" aria-labelledby="shared-edit-message-label" aria-hidden="true"
     data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shared-edit-message-label">Edit Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="shared-edit-message-text" class="form-label">Message</label>
                    <textarea class="form-control shared-edit-message-text" 
                              id="shared-edit-message-text" 
                              rows="4" 
                              maxlength="1000"
                              placeholder="Type your message..."></textarea>
                    <div class="form-text text-end">
                        <span class="shared-char-count">0</span>/1000 characters
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary shared-save-edit-btn" 
                        data-message-id=""
                        data-edit-url="">
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Shared Delete Message Modal --}}
<div class="modal fade" id="shared-delete-message-modal" 
     tabindex="-1" aria-labelledby="shared-delete-message-label" aria-hidden="true"
     data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shared-delete-message-label">Delete Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this message? This action cannot be undone.</p>
                <div class="alert alert-info mb-0 shared-delete-context-info">
                    <small>
                        <i class="bi bi-info-circle"></i>
                        <span class="shared-delete-message-text">
                            This message will be deleted from both sides of the conversation.
                        </span>
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger shared-confirm-delete-btn" 
                        data-message-id=""
                        data-delete-url="">
                    Delete Message
                </button>
            </div>
        </div>
    </div>
</div>

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

/* ==========================================
   Mobile 3-Dot Menu Button
   ========================================== */
.mobile-actions-toggle {
    width: 32px;
    height: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.95) !important;
    border: 1px solid var(--border) !important;
    backdrop-filter: blur(10px);
    transition: all 0.2s ease;
}

.mobile-actions-toggle:hover,
.mobile-actions-toggle:active {
    background: rgba(255, 255, 255, 1) !important;
    transform: scale(1.1);
}

[data-theme="dark"] .mobile-actions-toggle {
    background: rgba(30, 30, 30, 0.95) !important;
    border-color: #444 !important;
    color: var(--text);
}

[data-theme="dark"] .mobile-actions-toggle:hover {
    background: rgba(40, 40, 40, 1) !important;
}

/* ==========================================
   Mobile Bottom Sheet
   ========================================== */
.mobile-actions-sheet {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1100;
    display: none;
    pointer-events: none;
}

.mobile-actions-sheet.show {
    display: block;
    pointer-events: auto;
}

.mobile-actions-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.mobile-actions-sheet.show .mobile-actions-backdrop {
    opacity: 1;
}

.mobile-actions-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--card, #fff);
    border-radius: 20px 20px 0 0;
    padding: 8px 0 calc(env(safe-area-inset-bottom, 0px) + 16px);
    transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
    max-height: 70vh;
    overflow-y: auto;
}

.mobile-actions-sheet.show .mobile-actions-content {
    transform: translateY(0);
}

[data-theme="dark"] .mobile-actions-content {
    background: var(--card, #1e1e1e);
}

.mobile-actions-handle {
    width: 36px;
    height: 4px;
    background: var(--border, #ddd);
    border-radius: 2px;
    margin: 0 auto 12px;
}

[data-theme="dark"] .mobile-actions-handle {
    background: #444;
}

.mobile-actions-list {
    display: flex;
    flex-direction: column;
}

.mobile-action-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 20px;
    background: transparent;
    border: none;
    text-align: left;
    font-size: 1rem;
    color: var(--text, #333);
    transition: background 0.15s ease;
    cursor: pointer;
    width: 100%;
}

.mobile-action-item:hover,
.mobile-action-item:active {
    background: rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .mobile-action-item:hover,
[data-theme="dark"] .mobile-action-item:active {
    background: rgba(255, 255, 255, 0.08);
}

.mobile-action-item i {
    font-size: 1.25rem;
    width: 24px;
    text-align: center;
    color: var(--text-secondary, #666);
}

.mobile-action-item.text-danger {
    color: #dc3545;
}

.mobile-action-item.text-danger i {
    color: #dc3545;
}

[data-theme="dark"] .mobile-action-item {
    color: var(--text, #eee);
}

[data-theme="dark"] .mobile-action-item i {
    color: var(--text-secondary, #aaa);
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

/* FIXED: Modal z-index hierarchy */
.modal {
    z-index: 1060 !important;
}

.modal-backdrop {
    z-index: 1050 !important;
}

.modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    z-index: 1061 !important;
    position: relative;
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

.modal-backdrop.show {
    opacity: 0.5 !important;
}

/* Character count styling */
.shared-char-count {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.shared-char-count.warning {
    color: #ffc107;
}

.shared-char-count.danger {
    color: #dc3545;
}

/* Textarea styling for edit modal */
.shared-edit-message-text {
    resize: vertical;
    min-height: 100px;
    border-radius: 8px;
    border: 1px solid var(--border);
    padding: 12px;
    font-family: inherit;
    transition: all 0.2s ease;
}

.shared-edit-message-text:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
}

/* Responsive design */
@media (max-width: 767.98px) {
    .message-actions {
        position: absolute !important;
        bottom: auto;
        top: 50%;
        transform: translateY(-50%);
        opacity: 1;
        visibility: visible;
    }
    
    .message-bubble.sent .message-actions {
        left: -40px;
        right: auto;
    }
    
    .message-bubble.received .message-actions {
        right: -40px;
        left: auto;
    }
    
    /* Hide desktop action buttons on mobile */
    .action-buttons {
        display: none !important;
    }
    
    /* Show 3-dot menu on mobile */
    .mobile-actions-toggle {
        display: flex !important;
    }
    
    .modal-dialog {
        margin: 1rem;
    }
    
    .modal-content {
        border-radius: 16px;
    }
}

/* Desktop: hide mobile elements */
@media (min-width: 768px) {
    .mobile-actions-toggle {
        display: none !important;
    }
    
    .mobile-actions-sheet {
        display: none !important;
    }
    
    .action-buttons {
        display: flex !important;
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

[data-theme="dark"] .shared-edit-message-text {
    background: var(--input-bg);
    color: var(--text);
    border-color: #444;
}

/* Ensure no other elements have higher z-index */
.chat-container,
.message-container,
.message-bubble {
    z-index: auto !important;
    position: relative;
}

/* Fix for any parent containers that might be creating stacking contexts */
.modal-open {
    overflow: hidden;
}

.modal-open .modal {
    overflow-x: hidden;
    overflow-y: auto;
}
</style>

<script>
(function() {
    // ✅ Hard guard against double-init even if this block somehow renders twice
    if (window.__sharedMessageActionsInit) return;
    window.__sharedMessageActionsInit = true;

    console.log('Initializing shared message actions (runs once)...');

    // ----- Bootstrap modal singletons (no global lets to redeclare) -----
    function getEditModal() {
        const el = document.getElementById('shared-edit-message-modal');
        if (!el) {
            console.warn('Edit modal element not found');
            return null;
        }
        el.style.zIndex = '1060';
        return bootstrap.Modal.getOrCreateInstance(el, { 
            backdrop: true, 
            keyboard: true, 
            focus: true 
        });
    }

    function getDeleteModal() {
        const el = document.getElementById('shared-delete-message-modal');
        if (!el) {
            console.warn('Delete modal element not found');
            return null;
        }
        el.style.zIndex = '1060';
        return bootstrap.Modal.getOrCreateInstance(el, { 
            backdrop: true, 
            keyboard: true, 
            focus: true 
        });
    }

    // ----- Utilities -----
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Toast notification helper
    window.showToast = window.showToast || function(message, type = 'info') {
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
    };

    // Character counter
    function setupCharacterCount() {
        const textarea = document.getElementById('shared-edit-message-text');
        const charCount = document.querySelector('.shared-char-count');
        if (!textarea || !charCount) return;
        
        textarea.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;
            charCount.classList.remove('warning', 'danger');
            if (length > 800) charCount.classList.add('warning');
            if (length > 950) charCount.classList.add('danger');
        });
    }

    // Cleanup when modals hide
    function setupModalCleanup() {
        const editModalEl = document.getElementById('shared-edit-message-modal');
        if (editModalEl) {
            editModalEl.addEventListener('hidden.bs.modal', function() {
                const textarea = document.getElementById('shared-edit-message-text');
                const charCount = document.querySelector('.shared-char-count');
                const saveBtn = document.querySelector('.shared-save-edit-btn');
                
                if (textarea) textarea.value = '';
                if (charCount) {
                    charCount.textContent = '0';
                    charCount.classList.remove('warning', 'danger');
                }
                if (saveBtn) {
                    saveBtn.dataset.messageId = '';
                    saveBtn.dataset.editUrl = '';
                }
            });
        }

        const deleteModalEl = document.getElementById('shared-delete-message-modal');
        if (deleteModalEl) {
            deleteModalEl.addEventListener('hidden.bs.modal', function() {
                const confirmBtn = document.querySelector('.shared-confirm-delete-btn');
                if (confirmBtn) {
                    confirmBtn.dataset.messageId = '';
                    confirmBtn.dataset.deleteUrl = '';
                }
            });
        }

        // Remove stray extra backdrops
        document.addEventListener('hidden.bs.modal', function() {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length > 1) {
                for (let i = 1; i < backdrops.length; i++) {
                    backdrops[i].remove();
                }
            }
        });
    }

    // ----- Event delegation (single listeners for all messages) -----

    // Mobile 3-dot menu toggle
    document.addEventListener('click', function(e) {
        const toggleBtn = e.target.closest('.mobile-actions-toggle');
        if (!toggleBtn) return;
        
        e.stopPropagation();
        const messageId = toggleBtn.dataset.messageId;
        const sheet = document.querySelector(`[data-mobile-sheet="${messageId}"]`);
        
        if (sheet) {
            // Close any other open sheets first
            document.querySelectorAll('.mobile-actions-sheet.show').forEach(s => {
                if (s !== sheet) s.classList.remove('show');
            });
            sheet.classList.add('show');
        }
    });

    // Close mobile sheet when clicking backdrop
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('mobile-actions-backdrop')) {
            const sheet = e.target.closest('.mobile-actions-sheet');
            if (sheet) {
                sheet.classList.remove('show');
            }
        }
    });

    // Close mobile sheet when action is clicked
    document.addEventListener('click', function(e) {
        const actionItem = e.target.closest('.mobile-action-item');
        if (!actionItem) return;
        
        const sheet = actionItem.closest('.mobile-actions-sheet');
        if (sheet) {
            // Small delay to allow the action to process
            setTimeout(() => {
                sheet.classList.remove('show');
            }, 100);
        }
    });

    // Close mobile sheet on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.mobile-actions-sheet.show').forEach(sheet => {
                sheet.classList.remove('show');
            });
        }
    });

    // Toggle reaction picker
    document.addEventListener('click', function(e) {
        const reactBtn = e.target.closest('.react-btn');
        if (!reactBtn) return;
        
        e.stopPropagation();
        const messageId = reactBtn.dataset.messageId;
        const picker = document.querySelector(`[data-reaction-picker="${messageId}"]`);
        if (!picker) return;
        
        // Hide all other pickers
        document.querySelectorAll('.reaction-picker').forEach(p => p.classList.add('d-none'));
        
        // Toggle this picker
        const isVisible = !picker.classList.contains('d-none');
        if (!isVisible) {
            picker.classList.remove('d-none');
            // Position picker
            const rect = reactBtn.getBoundingClientRect();
            picker.style.left = '0';
            picker.style.bottom = '100%';
        }
    });

    // Add reaction
    document.addEventListener('click', async function(e) {
        const option = e.target.closest('.reaction-option');
        if (!option) return;
        
        e.stopPropagation();
        const messageId = option.dataset.messageId;
        const emoji = option.dataset.emoji;
        const reactBtn = document.querySelector(`.react-btn[data-message-id="${messageId}"]`);
        
        if (!reactBtn) return;
        
        const reactUrl = reactBtn.dataset.reactUrl;
        
        try {
            const response = await fetch(reactUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json'
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
                showToast('Reaction added', 'success');
                
                // Optional: Update UI without reload for better UX
                // You can implement real-time reaction updates here
                
            } else {
                throw new Error('Failed to add reaction');
            }
        } catch (error) {
            console.error('Reaction error:', error);
            showToast('Failed to add reaction', 'error');
        }
    });

    // Close reaction pickers when clicking elsewhere
    document.addEventListener('click', function(e) {
        if (e.target.closest('.react-btn') || e.target.closest('.reaction-option')) return;
        document.querySelectorAll('.reaction-picker').forEach(picker => {
            picker.classList.add('d-none');
        });
    });

    // Reply button
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.reply-btn');
        if (!btn) return;
        
        e.stopPropagation();
        const messageId = btn.dataset.messageId;
        const senderName = btn.dataset.senderName;
        const bodyPreview = btn.dataset.bodyPreview;
        
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
            window.showReplyPreview(bodyPreview, senderName, messageId);
        }
    });

    // Forward button
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.forward-btn');
        if (!btn) return;
        
        e.stopPropagation();
        const messageId = btn.dataset.messageId;
        
        // Use the global forward function
        if (typeof window.openForwardModal === 'function') {
            window.openForwardModal(messageId);
        }
    });

    // Edit button (open modal)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.edit-btn');
        if (!btn) return;
        
        e.stopPropagation();
        const messageId = btn.dataset.messageId;
        const originalBody = btn.dataset.originalBody;
        const editUrl = btn.dataset.editUrl;
        
        // Update shared modal content
        const textarea = document.getElementById('shared-edit-message-text');
        const charCount = document.querySelector('.shared-char-count');
        const saveBtn = document.querySelector('.shared-save-edit-btn');
        
        if (textarea && saveBtn) {
            textarea.value = originalBody;
            saveBtn.dataset.messageId = messageId;
            saveBtn.dataset.editUrl = editUrl;
            
            // Update character count
            if (charCount) {
                charCount.textContent = originalBody.length;
                charCount.classList.remove('warning', 'danger');
                if (originalBody.length > 800) charCount.classList.add('warning');
                if (originalBody.length > 950) charCount.classList.add('danger');
            }
            
            // Show modal
            getEditModal()?.show();
        }
    });

    // Edit (save)
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.shared-save-edit-btn');
        if (!btn) return;
        
        const messageId = btn.dataset.messageId;
        const editUrl = btn.dataset.editUrl;
        const textarea = document.getElementById('shared-edit-message-text');
        
        if (!textarea) return;
        
        const newBody = textarea.value.trim();
        
        if (!newBody) {
            showToast('Message cannot be empty', 'error');
            return;
        }
        
        // Show loading state
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Saving...';
        btn.disabled = true;
        
        try {
            const response = await fetch(editUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    body: newBody
                })
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                // Close modal
                getEditModal()?.hide();
                
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
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // Delete button (open modal)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.delete-btn');
        if (!btn) return;
        
        e.stopPropagation();
        const messageId = btn.dataset.messageId;
        const deleteUrl = btn.dataset.deleteUrl;
        const isGroup = btn.dataset.isGroup === 'true';
        
        // Update shared modal content
        const contextInfo = document.querySelector('.shared-delete-context-info');
        const messageText = document.querySelector('.shared-delete-message-text');
        const confirmBtn = document.querySelector('.shared-confirm-delete-btn');
        
        if (confirmBtn) {
            confirmBtn.dataset.messageId = messageId;
            confirmBtn.dataset.deleteUrl = deleteUrl;
            
            // Update context message
            if (contextInfo && messageText) {
                if (isGroup) {
                    contextInfo.className = 'alert alert-warning mb-0 shared-delete-context-info';
                    messageText.textContent = 'This message will be deleted for everyone in the group.';
                } else {
                    contextInfo.className = 'alert alert-info mb-0 shared-delete-context-info';
                    messageText.textContent = 'This message will be deleted from both sides of the conversation.';
                }
            }
            
            // Show modal
            getDeleteModal()?.show();
        }
    });

    // Delete (confirm)
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.shared-confirm-delete-btn');
        if (!btn) return;
        
        const messageId = btn.dataset.messageId;
        const deleteUrl = btn.dataset.deleteUrl;
        
        // Show loading state
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Deleting...';
        btn.disabled = true;
        
        try {
            const response = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json'
                }
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                // Close modal
                getDeleteModal()?.hide();
                
                // Remove message from UI
                const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
                if (messageElement) {
                    const messageContainer = messageElement.closest('.message');
                    if (messageContainer) {
                        messageContainer.remove();
                    }
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
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // Reply preview consumer
    document.addEventListener('message-reply', function(e) {
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

    // Initialize everything when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        setupCharacterCount();
        setupModalCleanup();
        console.log('Shared message actions initialized successfully');
    });

})();
</script>

@endonce