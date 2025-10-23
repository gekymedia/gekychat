{{-- resources/views/chat/shared/message_composer.blade.php --}}
@php
    $action = $action ?? '';
    $conversationId = $conversationId ?? '';
    $placeholder = $placeholder ?? 'Type a message...';
    $context = $context ?? 'direct'; // 'direct' or 'group'
    $isGroup = $context === 'group';
    $groupId = $group->id ?? null;

    // Security settings (for direct chats)
    $securitySettings = $securitySettings ?? [
        'isEncrypted' => old('is_encrypted', '0') === '1',
        'expiresIn' => old('expires_in', ''),
    ];
@endphp

<footer class="message-input-container border-top bg-card position-sticky bottom-0" role="form"
    aria-label="Send message{{ $isGroup ? ' to group' : '' }}" data-context="{{ $context }}">
    <div class="container-fluid px-0">
        {{-- File Attachment Preview --}}
        <div class="attachment-preview-container border-bottom bg-light" id="attachment-preview" style="display: none;"
            data-context="{{ $context }}" aria-live="polite" role="region" aria-label="Attachment preview">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center py-2 px-3">
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-paperclip text-muted" aria-hidden="true"></i>
                            <span class="attachment-count text-muted small" id="attachment-count">0 files</span>
                        </div>
                        <div class="attachment-preview-list mt-1" id="attachment-preview-list"
                            style="max-height: 3em; overflow-y: auto;">
                            {{-- Attachment previews will be inserted here by JavaScript --}}
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger ms-3 flex-shrink-0" id="cancel-attachments"
                        aria-label="Remove all attachments" title="Remove all attachments">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>

        <form id="chat-form" action="{{ $action }}" method="POST" enctype="multipart/form-data" novalidate
            data-context="{{ $context }}" aria-label="Message composition form" class="px-3 py-2">
            @csrf

            {{-- Hidden Fields --}}
            <input type="hidden" name="conversation_id" value="{{ $conversationId }}">
            <input type="hidden" name="reply_to" id="reply-to-id" value="">
            <input type="hidden" name="forward_from_id" id="forward-from-id" value="">

            {{-- Security Settings (Direct chats only) --}}
            @if (!$isGroup)
                <input type="hidden" name="is_encrypted" id="is-encrypted"
                    value="{{ $securitySettings['isEncrypted'] ? '1' : '0' }}">
                <input type="hidden" name="expires_in" id="expires-in" value="{{ $securitySettings['expiresIn'] }}">
            @endif

            <div class="input-group composer align-items-end" id="drop-zone" role="group"
                aria-label="Message composer">
                {{-- Emoji Button --}}
                <button class="btn btn-ghost flex-shrink-0" type="button" id="emoji-btn" aria-label="Add emoji"
                    title="Add emoji">
                    <i class="bi bi-emoji-smile" aria-hidden="true"></i>
                </button>

                {{-- Textarea for multi-line input --}}
                <div class="form-control-wrapper flex-grow-1 position-relative" style="min-width: 0;">
                    <textarea name="body" class="form-control message-input flex-grow-1" placeholder="{{ $placeholder }}"
                        id="message-input" autocomplete="off" maxlength="1000" aria-label="Message input" aria-describedby="send-button"
                        aria-required="true" rows="1"
                        style="resize: none; overflow-y: auto; min-height: 48px; max-height: 120px; border: none; background: transparent; box-shadow: none;"></textarea>
                </div>

                {{-- Action Buttons --}}
                <div class="btn-group flex-shrink-0" role="group" aria-label="Message actions">
                    {{-- Security Button (Direct chats only) --}}
                    @if (!$isGroup)
                        <button class="btn btn-ghost" type="button" id="security-btn" aria-label="Security options"
                            title="Security options">
                            <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        </button>
                    @endif

                    {{-- Attachment Button --}}
                    <button class="btn btn-ghost dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false" aria-label="Attach files" title="Attach files">
                        <i class="bi bi-paperclip" aria-hidden="true"></i>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end" role="menu">
                        <li role="none">
                            <label class="dropdown-item d-flex align-items-center gap-2 cursor-pointer"
                                role="menuitem">
                                <i class="bi bi-image" aria-hidden="true"></i>
                                <span>Photo or Video</span>
                                <input type="file" name="attachments[]" accept="image/*,video/*" class="d-none"
                                    id="photo-upload" multiple aria-label="Upload photo or video">
                            </label>
                        </li>
                        <li role="none">
                            <label class="dropdown-item d-flex align-items-center gap-2 cursor-pointer"
                                role="menuitem">
                                <i class="bi bi-file-earmark" aria-hidden="true"></i>
                                <span>Document</span>
                                <input type="file" name="attachments[]" class="d-none" id="doc-upload" multiple
                                    accept=".pdf,.doc,.docx,.txt,.zip,.rar" aria-label="Upload document">
                            </label>
                        </li>
                    </ul>
                </div>

                {{-- Send Button --}}
                <button class="btn btn-wa flex-shrink-0" type="submit" id="send-btn" aria-label="Send message"
                    title="Send message" disabled>
                    <i class="bi bi-send" aria-hidden="true"></i>
                </button>
            </div>

            {{-- Upload Progress --}}
            <div id="upload-progress" class="progress mt-2" style="display: none;" role="progressbar"
                aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="File upload progress">
                <div class="progress-bar" style="width: 0%"></div>
            </div>
        </form>
    </div>
</footer>

<style>
    .message-input-container {
        background: var(--card);
        border-top: 1px solid var(--border);
        z-index: 1050;
        backdrop-filter: blur(10px);
        margin-top: auto;
    }

    .chat-container .row {
        min-height: 100vh;
    }

    #chat-area {
        display: flex;
        flex-direction: column;
    }

    .messages-container {
        flex: 1;
        overflow-y: auto;
    }

    /* Attachment Preview Styles */
    .attachment-preview-container {
        background: var(--light);
        border-bottom: 1px solid var(--border);
        transition: all 0.3s ease;
    }

    .attachment-preview-container.showing {
        display: block !important;
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .attachment-preview-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .attachment-preview-item {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 4px 8px;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 4px;
        max-width: 200px;
    }

    .attachment-preview-item .file-name {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        flex: 1;
    }

    .attachment-preview-item .remove-file {
        background: none;
        border: none;
        color: var(--danger);
        padding: 0;
        font-size: 0.875rem;
        cursor: pointer;
    }

    /* Textarea Styles */
    .form-control-wrapper {
        background: var(--input-bg);
        border: 1px solid var(--input-border);
        border-radius: 24px;
        padding: 12px 16px;
        transition: all 0.2s ease;
        min-height: 48px;
        display: flex;
        align-items: center;
    }

    .form-control-wrapper:focus-within {
        border-color: var(--wa-green);
        box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
    }

    .message-input {
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        outline: none !important;
        padding: 0 !important;
        line-height: 1.5;
        font-family: inherit;
        font-size: 0.9375rem;
        color: var(--text) !important; /* ← ADD THIS LINE */
    }

    .message-input:focus {
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        outline: none !important;
        color: var(--text) !important; /* ← ADD THIS LINE */
    }

    /* Context-specific focus colors */
    [data-context="group"] .form-control-wrapper:focus-within {
        border-color: var(--group-accent);
        box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--group-accent) 25%, transparent);
    }

    /* Buttons */
    .btn-ghost {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .btn-ghost:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateY(-1px);
    }

    .btn-wa {
        background: var(--wa-green);
        border: none;
        color: white;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .btn-wa:hover:not(:disabled) {
        filter: brightness(1.1);
        transform: translateY(-1px);
    }

    .btn-wa:disabled {
        background: var(--muted);
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    /* Input group adjustments */
    .input-group.composer {
        gap: 8px;
        align-items: center;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .message-input-container {
            padding-left: 0;
            padding-right: 0;
        }

        .form-control-wrapper {
            padding: 10px 14px;
            border-radius: 20px;
        }

        .message-input {
            font-size: 16px;
            /* Prevents zoom on iOS */
        }

        .btn-ghost,
        .btn-wa {
            width: 36px;
            height: 36px;
        }
    }

    @media (max-width: 576px) {
        .form-control-wrapper {
            padding: 8px 12px;
            border-radius: 18px;
        }

        .btn-group .dropdown-menu {
            position: fixed !important;
            bottom: 70px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
        }
    }

    /* Focus styles for accessibility */
    .btn-ghost:focus,
    .btn-wa:focus,
    .form-control-wrapper:focus-within {
        outline: 2px solid var(--wa-green);
        outline-offset: 2px;
    }

    [data-context="group"] .btn-ghost:focus,
    [data-context="group"] .btn-wa:focus,
    [data-context="group"] .form-control-wrapper:focus-within {
        outline-color: var(--group-accent);
    }

    /* Reduced motion */
    @media (prefers-reduced-motion: reduce) {

        .form-control-wrapper,
        .btn-ghost,
        .btn-wa,
        .attachment-preview-container {
            transition: none;
            animation: none;
        }
    }

    /* Scrollbar styling for textarea */
    .message-input::-webkit-scrollbar {
        width: 6px;
    }

    .message-input::-webkit-scrollbar-track {
        background: transparent;
    }

    .message-input::-webkit-scrollbar-thumb {
        background: color-mix(in srgb, var(--text) 30%, transparent);
        border-radius: 3px;
    }

    .message-input::-webkit-scrollbar-thumb:hover {
        background: color-mix(in srgb, var(--text) 50%, transparent);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const messageForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        const sendButton = document.getElementById('send-btn');
        const emojiButton = document.getElementById('emoji-btn');
        const securityButton = document.getElementById('security-btn');
        const dropZone = document.getElementById('drop-zone');
        const photoUpload = document.getElementById('photo-upload');
        const docUpload = document.getElementById('doc-upload');
        const uploadProgress = document.getElementById('upload-progress');
        const progressBar = uploadProgress?.querySelector('.progress-bar');
        const attachmentPreview = document.getElementById('attachment-preview');
        const attachmentCount = document.getElementById('attachment-count');
        const attachmentPreviewList = document.getElementById('attachment-preview-list');
        const cancelAttachmentsBtn = document.getElementById('cancel-attachments');
        const replyToInput = document.getElementById('reply-to-id');

        const context = messageForm?.dataset.context || 'direct';
        const isGroup = context === 'group';

        let selectedFiles = [];

        // Initialize composer functionality
        function initializeComposer() {
            setupEventListeners();
            setupDragAndDrop();
            setupInputValidation();
            setupAttachmentHandling();
            setupAutoResize();
            setupEmojiPickerIntegration();
        }

        function setupEventListeners() {
            // Form submission
            messageForm?.addEventListener('submit', handleFormSubmit);

            // Input validation
            messageInput?.addEventListener('input', handleInputChange);

            // Security button (direct chats only)
            if (!isGroup && securityButton) {
                securityButton.addEventListener('click', handleSecurityButton);
            }

            // File upload triggers
            photoUpload?.addEventListener('change', handleFileSelection);
            docUpload?.addEventListener('change', handleFileSelection);

            // Enter key handling (Shift+Enter for new line, Enter to send)
            messageInput?.addEventListener('keydown', handleKeydown);
        }

        function setupEmojiPickerIntegration() {
            // Use the separate emoji picker component
            if (emojiButton) {
                emojiButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (window.emojiPicker && typeof window.emojiPicker.toggle === 'function') {
                        window.emojiPicker.toggle(e);
                    } else {
                        console.warn(
                            'Emoji picker not available - make sure emoji_picker.blade.php is included'
                            );
                    }
                });
            }
        }

        function setupAutoResize() {
            // Auto-resize textarea
            messageInput?.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }

        function setupAttachmentHandling() {
            // Cancel attachments button
            cancelAttachmentsBtn?.addEventListener('click', function(e) {
                e.preventDefault();
                clearAttachments();
            });

            // Escape key to cancel attachments
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && attachmentPreview?.style.display === 'block') {
                    clearAttachments();
                }
            });
        }

        function setupDragAndDrop() {
            if (!dropZone) return;

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, unhighlight, false);
            });

            dropZone.addEventListener('drop', handleDrop, false);

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function highlight() {
                dropZone.classList.add('drag-over');
            }

            function unhighlight() {
                dropZone.classList.remove('drag-over');
            }

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                handleFiles(files);
            }
        }

        function setupInputValidation() {
            messageInput?.addEventListener('input', function() {
                const hasContent = this.value.trim().length > 0;
                const hasFiles = selectedFiles.length > 0;

                if (sendButton) {
                    sendButton.disabled = !hasContent && !hasFiles;
                }
            });
        }

        // Attachment Functions
        function handleFileSelection(e) {
            const files = Array.from(e.target.files);
            handleFiles(files);
            e.target.value = ''; // Reset input to allow selecting same file again
        }

        function handleFiles(files) {
    if (files.length === 0) return;
    
    // ✅ FIXED: Filter out empty files
    const validFiles = files.filter(file => file.size > 0 && file.name);
    
    if (validFiles.length === 0) {
        showToast('No valid files selected', 'warning');
        return;
    }
    
    // Add new files to selected files
    selectedFiles = [...selectedFiles, ...validFiles];
    updateAttachmentPreview();
    showAttachmentPreview();
    
    // Enable send button if we have files
    if (sendButton && selectedFiles.length > 0) {
        sendButton.disabled = false;
    }
}

        function updateAttachmentPreview() {
            if (!attachmentPreviewList || !attachmentCount) return;

            // Update count
            attachmentCount.textContent =
            `${selectedFiles.length} file${selectedFiles.length !== 1 ? 's' : ''}`;

            // Clear existing preview
            attachmentPreviewList.innerHTML = '';

            // Add file previews
            selectedFiles.forEach((file, index) => {
                const fileElement = document.createElement('div');
                fileElement.className = 'attachment-preview-item';
                fileElement.innerHTML = `
        <i class="bi ${getFileIcon(file.type)} text-muted"></i>
        <span class="file-name" title="${file.name}">${file.name}</span>
        <button type="button" class="remove-file" data-index="${index}" aria-label="Remove ${file.name}">
          <i class="bi bi-x"></i>
        </button>
      `;
                attachmentPreviewList.appendChild(fileElement);
            });

            // Add event listeners to remove buttons
            attachmentPreviewList.querySelectorAll('.remove-file').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    removeFile(index);
                });
            });
        }

        function getFileIcon(fileType) {
            if (fileType.startsWith('image/')) return 'bi-image';
            if (fileType.startsWith('video/')) return 'bi-film';
            if (fileType.includes('pdf')) return 'bi-file-pdf';
            if (fileType.includes('word') || fileType.includes('document')) return 'bi-file-word';
            if (fileType.includes('zip') || fileType.includes('rar')) return 'bi-file-zip';
            return 'bi-file-earmark';
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            if (selectedFiles.length === 0) {
                hideAttachmentPreview();
            } else {
                updateAttachmentPreview();
            }

            // Update send button state
            if (sendButton) {
                const hasContent = messageInput?.value.trim().length > 0;
                sendButton.disabled = !hasContent && selectedFiles.length === 0;
            }
        }

        function clearAttachments() {
            selectedFiles = [];
            hideAttachmentPreview();

            // Update send button state
            if (sendButton) {
                const hasContent = messageInput?.value.trim().length > 0;
                sendButton.disabled = !hasContent;
            }
        }

        function showAttachmentPreview() {
            if (!attachmentPreview) return;
            attachmentPreview.style.display = 'block';
            attachmentPreview.classList.add('showing');
        }

        function hideAttachmentPreview() {
            if (!attachmentPreview) return;
            attachmentPreview.style.display = 'none';
            attachmentPreview.classList.remove('showing');
        }

        // Event handlers
        async function handleFormSubmit(e) {
            e.preventDefault();

            if (sendButton) {
                sendButton.disabled = true;
                sendButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm" role="status"></span>';
            }

            try {
                // Add files to form data
                const formData = new FormData(messageForm);

                // Append selected files
                selectedFiles.forEach(file => {
                    formData.append('attachments[]', file);
                });

                // Set reply_to from the reply preview if it exists
                const replyPreview = document.getElementById('reply-preview');
                if (replyPreview && replyPreview.style.display === 'block' && replyToInput) {
                    const replyToId = replyPreview.dataset.replyToId;
                    if (replyToId) {
                        replyToInput.value = replyToId;
                        formData.set('reply_to', replyToId);
                    }
                }

                if (window.chat && typeof window.chat.handleMessageSubmit === 'function') {
                    await window.chat.handleMessageSubmit(e, formData);
                } else {
                    await submitFormDirectly(formData);
                }
            } catch (error) {
                console.error('Message submission error:', error);
                showToast('Failed to send message', 'error');
            } finally {
                if (sendButton) {
                    sendButton.disabled = false;
                    sendButton.innerHTML = '<i class="bi bi-send" aria-hidden="true"></i>';
                }
            }
        }

       async function submitFormDirectly(formData) {
    try {
        console.log('FormData contents:');
        for (let [key, value] of formData.entries()) {
            console.log(key, value instanceof File ? `File: ${value.name}` : value);
        }

        const response = await fetch(messageForm.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });
        
        console.log('Response status:', response.status);
        
        const responseText = await response.text();
        let result;
        
        try {
            result = JSON.parse(responseText);
            console.log('Parsed result:', result);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            
            // If response is OK but not JSON, treat as success
            if (response.ok) {
                console.log('Response OK but not JSON - treating as success');
                handleSuccess();
                return { success: true };
            }
            
            throw new Error('Server returned invalid response format');
        }
        
        // ✅ FIXED: Check for all possible success indicators
        const isSuccess = response.ok && (
            result.status === 'success' || 
            result.status === 'ok' ||
            result.success === true ||
            (result.message && typeof result.message === 'object') // If there's a message object
        );
        
        if (isSuccess) {
            handleSuccess();
            return result;
        } else {
            // Handle error response
            const errorMessage = result.message || result.error || `HTTP ${response.status}`;
            throw new Error(errorMessage);
        }
        
    } catch (error) {
        console.error('Message submission failed:', error);
        showToast(error.message || 'Failed to send message', 'error');
        throw error;
    }
}

function handleSuccess() {
    // Clear form and reset
    messageInput.value = '';
    messageInput.style.height = 'auto';
    if (window.hideReplyPreview) {
        window.hideReplyPreview();
    }
    clearAttachments();
    resetSecuritySettings();
    showToast('Message sent', 'success');
}

function handleInputChange() {
            // Typing indicator logic handled by chat classes
            if (window.chat && typeof window.chat.handleTyping === 'function') {
                window.chat.handleTyping();
            }
        }

        function handleSecurityButton() {
            if (window.chat && typeof window.chat.showSecurityModal === 'function') {
                window.chat.showSecurityModal();
            }
        }

        function handleKeydown(e) {
            if (e.key === 'Enter') {
                if (e.shiftKey) {
                    // Shift+Enter: Allow new line (default behavior)
                    return;
                } else {
                    // Enter: Submit form
                    e.preventDefault();
                    if (!sendButton.disabled) {
                        messageForm?.dispatchEvent(new Event('submit'));
                    }
                }
            }
        }

        // Public API for chat classes
        window.messageComposer = {
            focusInput: () => messageInput?.focus(),
            clearInput: () => {
                if (messageInput) {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                }
                if (window.hideReplyPreview) {
                    window.hideReplyPreview();
                }
                clearAttachments();
                resetSecuritySettings();
            },
            setReplyTo: (messageId, messageText, senderName = null) => {
                if (window.showReplyPreview) {
                    window.showReplyPreview(messageText, senderName, messageId);
                }
            },
            showUploadProgress: (percent) => {
                if (uploadProgress && progressBar) {
                    uploadProgress.style.display = 'block';
                    progressBar.style.width = percent + '%';
                    progressBar.setAttribute('aria-valuenow', percent);
                }
            },
            hideUploadProgress: () => {
                if (uploadProgress) {
                    uploadProgress.style.display = 'none';
                    progressBar.style.width = '0%';
                    progressBar.setAttribute('aria-valuenow', 0);
                }
            },
            resetSecuritySettings: () => {
                if (!isGroup && securityButton) {
                    securityButton.classList.remove('active');
                    securityButton.innerHTML = '<i class="bi bi-shield-lock" aria-hidden="true"></i>';

                    const encryptInput = document.getElementById('is-encrypted');
                    const expireInput = document.getElementById('expires-in');
                    if (encryptInput) encryptInput.value = '0';
                    if (expireInput) expireInput.value = '';
                }
            }
        };

        // Initialize
        initializeComposer();
    });

    // Global helper functions
    function resetSecuritySettings() {
        if (window.messageComposer) {
            window.messageComposer.resetSecuritySettings();
        }
    }

    function showToast(message, type = 'info') {
        // Use your existing toast implementation
        console.log(`[${type.toUpperCase()}] ${message}`);
        // You can integrate with your existing toast system here
    }
</script>
{{-- Include emoji picker --}}
@include('chat.shared.emoji_picker')

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize emoji picker with composer elements
        const messageInput = document.getElementById('message-input');
        const emojiButton = document.getElementById('emoji-btn');

        if (window.emojiPicker && messageInput && emojiButton) {
            window.emojiPicker.setMessageInput(messageInput);
            window.emojiPicker.setEmojiButton(emojiButton);
        }
    });
</script>
