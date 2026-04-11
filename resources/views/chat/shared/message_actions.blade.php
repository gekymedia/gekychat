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
        {{-- Select Button (for bulk selection) --}}
        <button class="btn btn-sm btn-outline-secondary select-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                title="Select for bulk actions"
                aria-label="Select this message for bulk actions">
            <i class="bi bi-check2-square" aria-hidden="true"></i>
        </button>

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

        {{-- Read Aloud Button (TTS) --}}
        @if(!empty($body))
        <button class="btn btn-sm btn-outline-secondary read-aloud-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                data-message-text="{{ $body }}"
                title="Read aloud"
                aria-label="Read this message aloud">
            <i class="bi bi-volume-up" aria-hidden="true"></i>
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

    {{-- Mobile Horizontal Action Bar (appears on tap) --}}
    <div class="mobile-actions-bar d-md-none" data-mobile-bar="{{ $messageId }}">
        {{-- React --}}
        <button class="btn btn-sm btn-light mobile-bar-btn react-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                data-react-url="{{ $reactUrl }}"
                title="React">
            <i class="bi bi-emoji-smile"></i>
        </button>
        {{-- Reply --}}
        <button class="btn btn-sm btn-light mobile-bar-btn reply-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                data-sender-name="{{ $isOwn ? 'You' : $senderName }}"
                data-body-preview="{{ Str::limit($body, 50) }}"
                title="Reply">
            <i class="bi bi-reply"></i>
        </button>
        {{-- Forward --}}
        <button class="btn btn-sm btn-light mobile-bar-btn forward-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                title="Forward">
            <i class="bi bi-forward"></i>
        </button>
        {{-- Pin --}}
        <button class="btn btn-sm btn-light mobile-bar-btn pin-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                onclick="pinMessage({{ $messageId }}, {{ $isGroup ? 'true' : 'false' }}, '{{ $group->id ?? '' }}')"
                title="Pin">
            <i class="bi bi-pin-angle"></i>
        </button>
        {{-- Edit --}}
        @if($canEdit)
        <button class="btn btn-sm btn-light mobile-bar-btn edit-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                data-edit-url="{{ $editUrl }}"
                data-original-body="{{ $body }}"
                title="Edit">
            <i class="bi bi-pencil"></i>
        </button>
        @endif
        {{-- Delete --}}
        @if($canDelete)
        <button class="btn btn-sm btn-outline-danger mobile-bar-btn delete-btn" 
                type="button"
                data-message-id="{{ $messageId }}"
                data-delete-url="{{ $deleteUrl }}"
                data-is-group="{{ $isGroup ? 'true' : 'false' }}"
                title="Delete">
            <i class="bi bi-trash"></i>
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
                <p>How would you like to delete this message?</p>
                <div class="alert alert-info mb-0 shared-delete-context-info">
                    <small>
                        <i class="bi bi-info-circle"></i>
                        <span class="shared-delete-message-text">Choose a delete option below.</span>
                    </small>
                </div>
            </div>
            <div class="modal-footer gap-2 flex-wrap">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                {{-- Delete for me: removes only from the current user's view --}}
                <button type="button" class="btn btn-outline-danger shared-confirm-delete-btn"
                        data-message-id=""
                        data-delete-url=""
                        data-delete-for="me">
                    <i class="bi bi-person-dash me-1"></i>Delete for me
                </button>
                {{-- Delete for everyone: shown only for own messages; hidden via JS for received messages --}}
                <button type="button" class="btn btn-danger shared-confirm-delete-everyone-btn"
                        data-message-id=""
                        data-delete-url=""
                        data-delete-for="everyone">
                    <i class="bi bi-people me-1"></i>Delete for everyone
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
    width: 28px;
    height: 28px;
    padding: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid rgba(0, 0, 0, 0.1) !important;
    backdrop-filter: blur(10px);
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.mobile-actions-toggle:hover,
.mobile-actions-toggle:active {
    background: rgba(255, 255, 255, 1) !important;
}

[data-theme="dark"] .mobile-actions-toggle {
    background: rgba(50, 50, 50, 0.9) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: var(--text);
}

/* ==========================================
   Mobile Horizontal Action Bar
   ========================================== */
.mobile-actions-bar {
    display: none;
    position: absolute;
    flex-direction: row;
    align-items: center;
    gap: 4px;
    padding: 6px 8px;
    background: rgba(255, 255, 255, 0.98);
    border-radius: 24px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(0, 0, 0, 0.08);
    backdrop-filter: blur(10px);
    z-index: 15;
    opacity: 0;
    transform: scale(0.9);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
}

.mobile-actions-bar.show {
    display: flex;
    opacity: 1;
    transform: scale(1);
    pointer-events: auto;
}

[data-theme="dark"] .mobile-actions-bar {
    background: rgba(40, 40, 40, 0.98);
    border-color: rgba(255, 255, 255, 0.1);
}

.mobile-bar-btn {
    width: 36px;
    height: 36px;
    padding: 0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent !important;
    border: none !important;
    color: var(--text-secondary, #666);
    transition: all 0.15s ease;
}

.mobile-bar-btn:hover,
.mobile-bar-btn:active {
    background: rgba(0, 0, 0, 0.08) !important;
    color: var(--text, #333);
    transform: scale(1.1);
}

[data-theme="dark"] .mobile-bar-btn {
    color: var(--text-secondary, #aaa);
}

[data-theme="dark"] .mobile-bar-btn:hover,
[data-theme="dark"] .mobile-bar-btn:active {
    background: rgba(255, 255, 255, 0.1) !important;
    color: var(--text, #eee);
}

.mobile-bar-btn i {
    font-size: 1.125rem;
}

.mobile-bar-btn.btn-outline-danger {
    color: #dc3545 !important;
}

.mobile-bar-btn.btn-outline-danger:hover {
    background: rgba(220, 53, 69, 0.1) !important;
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
        top: -8px;
        opacity: 1;
        visibility: visible;
        transform: none;
    }
    
    /* Position 3-dot button outside the message bubble */
    .message-bubble.sent .message-actions {
        left: auto;
        right: 100%;
        margin-right: 4px;
    }
    
    .message-bubble.received .message-actions {
        right: auto;
        left: 100%;
        margin-left: 4px;
    }
    
    /* Hide desktop action buttons on mobile */
    .action-buttons {
        display: none !important;
    }
    
    /* Show 3-dot menu on mobile */
    .mobile-actions-toggle {
        display: flex !important;
    }
    
    /* Position the horizontal action bar */
    .mobile-actions-bar {
        position: fixed;
        top: auto;
        bottom: auto;
        left: 50%;
        transform: translateX(-50%) scale(0.9);
    }
    
    .mobile-actions-bar.show {
        transform: translateX(-50%) scale(1);
    }
    
    /* Position bar above the message for sent messages */
    .message-bubble.sent .mobile-actions-bar.show {
        position: fixed;
    }
    
    /* Position bar above the message for received messages */
    .message-bubble.received .mobile-actions-bar.show {
        position: fixed;
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
    
    .mobile-actions-bar {
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

    // Mobile 3-dot menu toggle - shows horizontal action bar
    document.addEventListener('click', function(e) {
        const toggleBtn = e.target.closest('.mobile-actions-toggle');
        if (!toggleBtn) return;
        
        e.stopPropagation();
        const messageId = toggleBtn.dataset.messageId;
        const bar = document.querySelector(`[data-mobile-bar="${messageId}"]`);
        
        if (bar) {
            // Close any other open bars first
            document.querySelectorAll('.mobile-actions-bar.show').forEach(b => {
                if (b !== bar) b.classList.remove('show');
            });
            
            // Toggle this bar
            const isShowing = bar.classList.contains('show');
            if (isShowing) {
                bar.classList.remove('show');
            } else {
                // Position the bar near the toggle button
                const toggleRect = toggleBtn.getBoundingClientRect();
                const barWidth = 250; // Approximate width
                
                // Position horizontally centered on the button, but keep on screen
                let left = toggleRect.left + (toggleRect.width / 2);
                
                // Keep bar on screen
                const padding = 10;
                if (left - barWidth/2 < padding) {
                    left = padding + barWidth/2;
                } else if (left + barWidth/2 > window.innerWidth - padding) {
                    left = window.innerWidth - padding - barWidth/2;
                }
                
                // Position above or below the button depending on space
                const spaceAbove = toggleRect.top;
                const spaceBelow = window.innerHeight - toggleRect.bottom;
                
                if (spaceAbove > 60) {
                    // Position above
                    bar.style.top = (toggleRect.top - 50) + 'px';
                } else {
                    // Position below
                    bar.style.top = (toggleRect.bottom + 8) + 'px';
                }
                
                bar.style.left = left + 'px';
                bar.classList.add('show');
            }
        }
    });

    // Close mobile action bar when clicking elsewhere
    document.addEventListener('click', function(e) {
        if (e.target.closest('.mobile-actions-toggle') || e.target.closest('.mobile-actions-bar')) return;
        
        document.querySelectorAll('.mobile-actions-bar.show').forEach(bar => {
            bar.classList.remove('show');
        });
    });

    // Close mobile action bar when action is clicked
    document.addEventListener('click', function(e) {
        const actionBtn = e.target.closest('.mobile-bar-btn');
        if (!actionBtn) return;
        
        const bar = actionBtn.closest('.mobile-actions-bar');
        if (bar) {
            // Small delay to allow the action to process
            setTimeout(() => {
                bar.classList.remove('show');
            }, 150);
        }
    });

    // Close mobile action bar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.mobile-actions-bar.show').forEach(bar => {
                bar.classList.remove('show');
            });
        }
    });
    
    // Close mobile action bar on scroll
    document.addEventListener('scroll', function() {
        document.querySelectorAll('.mobile-actions-bar.show').forEach(bar => {
            bar.classList.remove('show');
        });
    }, true);

    // ===== READ ALOUD (TTS) =====
    // Speech synthesis for reading messages aloud
    window.speechSynthesisInstance = null;
    
    window.readMessageAloud = function(text) {
        if (!text || text.trim() === '') {
            showToast('No text to read aloud', 'warning');
            return;
        }

        // Check if speech synthesis is supported
        if (!('speechSynthesis' in window)) {
            showToast('Text-to-speech is not supported in your browser', 'error');
            return;
        }

        // Stop any ongoing speech
        window.speechSynthesis.cancel();

        // Create utterance
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-US';
        utterance.rate = 1.0;
        utterance.pitch = 1.0;
        utterance.volume = 1.0;

        // Get available voices and prefer a natural-sounding one
        const voices = window.speechSynthesis.getVoices();
        const preferredVoice = voices.find(v => v.lang.startsWith('en') && v.name.includes('Google')) 
            || voices.find(v => v.lang.startsWith('en'))
            || voices[0];
        if (preferredVoice) {
            utterance.voice = preferredVoice;
        }

        // Event handlers
        utterance.onstart = () => {
            console.log('Started reading aloud');
            showToast('Reading message...', 'info');
        };

        utterance.onend = () => {
            console.log('Finished reading aloud');
        };

        utterance.onerror = (e) => {
            console.error('TTS error:', e);
            showToast('Failed to read message', 'error');
        };

        // Speak
        window.speechSynthesis.speak(utterance);
        window.speechSynthesisInstance = utterance;
    };

    window.stopReadingAloud = function() {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
    };

    // Handle read aloud button click
    document.addEventListener('click', function(e) {
        const readAloudBtn = e.target.closest('.read-aloud-btn');
        if (!readAloudBtn) return;

        e.preventDefault();
        e.stopPropagation();

        const messageText = readAloudBtn.dataset.messageText;
        if (messageText) {
            // Decode HTML entities
            const textarea = document.createElement('textarea');
            textarea.innerHTML = messageText;
            const decodedText = textarea.value;
            
            readMessageAloud(decodedText);
        } else {
            showToast('No text to read aloud', 'warning');
        }
    });

    // Load voices when they become available
    if ('speechSynthesis' in window) {
        window.speechSynthesis.onvoiceschanged = () => {
            console.log('TTS voices loaded:', window.speechSynthesis.getVoices().length);
        };
    }

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
        
        const everyoneBtn = document.querySelector('.shared-confirm-delete-everyone-btn');

        if (confirmBtn) {
            confirmBtn.dataset.messageId = messageId;
            confirmBtn.dataset.deleteUrl = deleteUrl;
        }
        if (everyoneBtn) {
            everyoneBtn.dataset.messageId = messageId;
            // Append ?delete_for=everyone to the delete URL
            everyoneBtn.dataset.deleteUrl = deleteUrl + (deleteUrl.includes('?') ? '&' : '?') + 'delete_for=everyone';
            // Only show "Delete for everyone" if the current user owns the message
            const isOwnMessage = document.querySelector(`[data-message-id="${messageId}"]`)?.dataset?.fromMe === '1';
            everyoneBtn.style.display = isOwnMessage ? '' : 'none';
        }

        // Update context message
        if (contextInfo && messageText) {
            if (isGroup) {
                contextInfo.className = 'alert alert-warning mb-0 shared-delete-context-info';
                messageText.textContent = 'Choose how to delete this group message.';
            } else {
                contextInfo.className = 'alert alert-info mb-0 shared-delete-context-info';
                messageText.textContent = '"Delete for me" removes it only from your view. "Delete for everyone" removes it from both sides.';
            }
        }

        // Show modal
        getDeleteModal()?.show();
    });

    // Shared delete handler for both "for me" and "for everyone" buttons
    async function handleDeleteConfirm(btn) {
        const messageId = btn.dataset.messageId;
        const deleteUrl = btn.dataset.deleteUrl;
        const deleteFor = btn.dataset.deleteFor ?? 'me'; // 'me' | 'everyone'

        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Deleting...';
        btn.disabled = true;

        try {
            // For "delete for me" append query param if not already present
            const url = deleteFor === 'everyone'
                ? deleteUrl  // already has ?delete_for=everyone appended above
                : deleteUrl + (deleteUrl.includes('?') ? '&' : '?') + 'delete_for=me';

            const response = await fetch(url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
            });

            const result = await response.json();

            if (response.ok && result.success) {
                getDeleteModal()?.hide();

                const msgEl = document.querySelector(`[data-message-id="${messageId}"]`);
                if (msgEl) {
                    if (deleteFor === 'everyone') {
                        // Show deleted stub — consistent with mobile behaviour
                        const bubble = msgEl.querySelector('.message-bubble');
                        if (bubble) {
                            bubble.classList.add('deleted-message');
                            bubble.innerHTML = `
                                <div class="message-content">
                                    <div class="message-text text-muted fst-italic d-flex align-items-center gap-1">
                                        <i class="bi bi-slash-circle"></i>
                                        <span>You deleted this message</span>
                                    </div>
                                </div>`;
                        }
                        msgEl.classList.add('deleted-message');
                    } else {
                        // Delete for me — remove from local DOM only
                        msgEl.style.transition = 'all 0.3s ease';
                        msgEl.style.opacity = '0';
                        setTimeout(() => msgEl.remove(), 300);
                    }
                }

                showToast('Message deleted', 'success');
            } else {
                throw new Error(result.message || 'Failed to delete message');
            }
        } catch (error) {
            console.error('Delete error:', error);
            showToast(error.message || 'Failed to delete message', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    // Wire both delete buttons
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.shared-confirm-delete-btn, .shared-confirm-delete-everyone-btn');
        if (btn) handleDeleteConfirm(btn);
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

    // ==== Pin Message Functions ====
    window.pinMessage = async function(messageId, isGroup, groupId) {
        let endpoint;
        if (isGroup && groupId) {
            // For groups, use the group API endpoint
            endpoint = `/groups/${groupId}/messages/${messageId}/pin`;
        } else {
            // For direct conversations, get the conversation slug from the URL
            const pathParts = window.location.pathname.split('/');
            const conversationSlug = pathParts[pathParts.length - 1] || pathParts[pathParts.length - 2];
            
            if (!conversationSlug || conversationSlug === 'c') {
                showToast('Cannot pin message', 'error');
                return;
            }
            endpoint = `/conversation/${conversationSlug}/messages/${messageId}/pin`;
        }
        
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to pin message');
            }
            
            const data = await response.json();
            showToast('Message pinned', 'success');
            
            // Show pinned message bar if function exists
            if (data.pinned_message && typeof window.showPinnedMessageBar === 'function') {
                window.showPinnedMessageBar(data.pinned_message);
            }
            
        } catch (error) {
            console.error('Pin error:', error);
            showToast(error.message || 'Failed to pin message', 'error');
        }
    };
    
    window.unpinMessage = async function(isGroup, groupId) {
        let endpoint;
        if (isGroup && groupId) {
            endpoint = `/groups/${groupId}/messages/unpin`;
        } else {
            // For direct conversations, get the conversation slug from the URL
            const pathParts = window.location.pathname.split('/');
            const conversationSlug = pathParts[pathParts.length - 1] || pathParts[pathParts.length - 2];
            
            if (!conversationSlug || conversationSlug === 'c') {
                return;
            }
            endpoint = `/conversation/${conversationSlug}/messages/unpin`;
        }
        
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                showToast('Message unpinned', 'success');
                // Hide pinned message bar if function exists
                if (typeof window.hidePinnedMessageBar === 'function') {
                    window.hidePinnedMessageBar();
                }
            }
        } catch (error) {
            console.error('Unpin error:', error);
            showToast('Failed to unpin message', 'error');
        }
    };

    // ===== BULK MESSAGE SELECTION =====
    // Store for selected message IDs
    window.selectedMessages = window.selectedMessages || new Set();

    // Toggle selection mode
    window.toggleBulkSelectionMode = function(enable = true) {
        const messagesContainer = document.querySelector('.messages-container, #messages-container, .chat-messages');
        if (!messagesContainer) return;

        if (enable) {
            messagesContainer.classList.add('bulk-selection-mode');
            showBulkActionBar();
        } else {
            messagesContainer.classList.remove('bulk-selection-mode');
            hideBulkActionBar();
            clearAllSelections();
        }
    };

    // Select/deselect a message
    window.toggleMessageSelection = function(messageId) {
        const messageBubble = document.querySelector(`[data-message-id="${messageId}"]`)?.closest('.message-bubble, .message');
        if (!messageBubble) return;

        if (window.selectedMessages.has(messageId)) {
            window.selectedMessages.delete(messageId);
            messageBubble.classList.remove('selected');
        } else {
            window.selectedMessages.add(messageId);
            messageBubble.classList.add('selected');
        }

        updateBulkActionBar();

        // If no messages selected, exit selection mode
        if (window.selectedMessages.size === 0) {
            toggleBulkSelectionMode(false);
        }
    };

    // Clear all selections
    function clearAllSelections() {
        window.selectedMessages.clear();
        document.querySelectorAll('.message-bubble.selected, .message.selected').forEach(el => {
            el.classList.remove('selected');
        });
    }

    // Show bulk action bar
    function showBulkActionBar() {
        let bar = document.getElementById('bulk-action-bar');
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'bulk-action-bar';
            bar.className = 'bulk-action-bar position-fixed bottom-0 start-50 translate-middle-x mb-3 bg-white rounded-pill shadow-lg p-2 d-flex align-items-center gap-2';
            bar.innerHTML = `
                <span class="badge bg-primary rounded-pill selection-count">0</span>
                <span class="text-muted small">selected</span>
                <div class="vr mx-2"></div>
                <button class="btn btn-sm btn-outline-primary bulk-forward-btn" title="Forward selected">
                    <i class="bi bi-forward"></i> Forward
                </button>
                <button class="btn btn-sm btn-outline-danger bulk-delete-btn" title="Delete selected">
                    <i class="bi bi-trash"></i> Delete
                </button>
                <div class="vr mx-2"></div>
                <button class="btn btn-sm btn-outline-secondary bulk-cancel-btn" title="Cancel selection">
                    <i class="bi bi-x-lg"></i>
                </button>
            `;
            bar.style.zIndex = '1050';
            document.body.appendChild(bar);

            // Add event listeners
            bar.querySelector('.bulk-forward-btn').addEventListener('click', bulkForwardMessages);
            bar.querySelector('.bulk-delete-btn').addEventListener('click', bulkDeleteMessages);
            bar.querySelector('.bulk-cancel-btn').addEventListener('click', () => toggleBulkSelectionMode(false));
        }
        bar.style.display = 'flex';
        updateBulkActionBar();
    }

    // Hide bulk action bar
    function hideBulkActionBar() {
        const bar = document.getElementById('bulk-action-bar');
        if (bar) {
            bar.style.display = 'none';
        }
    }

    // Update bulk action bar count
    function updateBulkActionBar() {
        const bar = document.getElementById('bulk-action-bar');
        if (bar) {
            const count = window.selectedMessages.size;
            bar.querySelector('.selection-count').textContent = count;
        }
    }

    // Bulk forward messages
    async function bulkForwardMessages() {
        if (window.selectedMessages.size === 0) {
            showToast('No messages selected', 'warning');
            return;
        }

        const messageIds = Array.from(window.selectedMessages);
        
        // Use existing forward modal if available, or show a simple forward dialog
        if (window.openForwardModal) {
            window.openForwardModal(messageIds);
        } else {
            // Fallback: redirect to forward page with message IDs
            const url = new URL(window.location.href);
            url.searchParams.set('forward_messages', messageIds.join(','));
            showToast(`Ready to forward ${messageIds.length} message(s). Use the forward feature.`, 'info');
        }
    }

    // Bulk delete messages
    async function bulkDeleteMessages() {
        if (window.selectedMessages.size === 0) {
            showToast('No messages selected', 'warning');
            return;
        }

        const count = window.selectedMessages.size;
        if (!confirm(`Delete ${count} message(s) for yourself? This cannot be undone.`)) {
            return;
        }

        const messageIds = Array.from(window.selectedMessages);
        
        try {
            // Delete each message
            for (const messageId of messageIds) {
                const deleteBtn = document.querySelector(`[data-message-id="${messageId}"] .delete-btn, [data-delete-message="${messageId}"]`);
                if (deleteBtn) {
                    const deleteUrl = deleteBtn.dataset.deleteUrl;
                    if (deleteUrl) {
                        await fetch(deleteUrl, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrf(),
                                'Accept': 'application/json',
                            }
                        });
                    }
                }
            }
            
            showToast(`Deleted ${count} message(s)`, 'success');
            toggleBulkSelectionMode(false);
            
            // Refresh messages if possible
            if (window.refreshMessages) {
                window.refreshMessages();
            }
        } catch (error) {
            console.error('Bulk delete error:', error);
            showToast('Failed to delete some messages', 'error');
        }
    }

    // Handle select button click
    document.addEventListener('click', function(e) {
        const selectBtn = e.target.closest('.select-btn');
        if (!selectBtn) return;

        e.preventDefault();
        e.stopPropagation();

        const messageId = selectBtn.dataset.messageId;
        if (!messageId) return;

        // Enter selection mode if not already
        const messagesContainer = document.querySelector('.messages-container, #messages-container, .chat-messages');
        if (messagesContainer && !messagesContainer.classList.contains('bulk-selection-mode')) {
            toggleBulkSelectionMode(true);
        }

        toggleMessageSelection(parseInt(messageId));
    });

    // Initialize everything when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        setupCharacterCount();
        setupModalCleanup();
        console.log('Shared message actions initialized successfully');
    });

})();
</script>

<style>
/* Bulk selection mode styles */
.bulk-selection-mode .message-bubble,
.bulk-selection-mode .message {
    cursor: pointer;
    position: relative;
}

.bulk-selection-mode .message-bubble::before,
.bulk-selection-mode .message::before {
    content: '';
    position: absolute;
    left: -30px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    border: 2px solid #6c757d;
    border-radius: 4px;
    background: white;
}

.bulk-selection-mode .message-bubble.selected::before,
.bulk-selection-mode .message.selected::before {
    background: #0d6efd;
    border-color: #0d6efd;
}

.bulk-selection-mode .message-bubble.selected::after,
.bulk-selection-mode .message.selected::after {
    content: '✓';
    position: absolute;
    left: -27px;
    top: 50%;
    transform: translateY(-50%);
    color: white;
    font-size: 14px;
    font-weight: bold;
}

.bulk-selection-mode .message-bubble.selected,
.bulk-selection-mode .message.selected {
    background-color: rgba(13, 110, 253, 0.1) !important;
}

.bulk-action-bar {
    animation: slideUp 0.2s ease-out;
}

@keyframes slideUp {
    from {
        transform: translate(-50%, 100%);
        opacity: 0;
    }
    to {
        transform: translate(-50%, 0);
        opacity: 1;
    }
}
</style>

@endonce