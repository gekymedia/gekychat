{{-- resources/views/chat/shared/message_composer.blade.php --}}
{{-- @php
dd($membersData); // Use the correct variable name
@endphp --}}
@php
    $action = $action ?? '';
    $conversationId = $conversationId ?? '';
    $placeholder = $placeholder ?? 'Type a message...';
    $context = $context ?? 'direct'; // 'direct' or 'group'
    $isGroup = $context === 'group';
    $groupId = $group->id ?? null;

@endphp

<footer class="message-input-container border-top bg-card position-sticky bottom-0" role="form"
    aria-label="Send message{{ $isGroup ? ' to group' : '' }}" data-context="{{ $context }}"
    @if ($isGroup && $groupId) data-group-id="{{ $groupId }}" @endif>

    @if ($isGroup && $membersData && $membersData->count() > 0)
        @php
            $membersJson = $membersData
                ->map(function ($member) {
                    // Use phone number as the mention tag instead of ID
                    $mentionTag = $member->phone; // This will create @0248229540

                    // Or if you want to use the contact name (if saved in contacts)
                    // $mentionTag = $member->name ?? $member->phone;

                    return [
                        'id' => $member->id,
                        'name' => $member->name ?? $member->phone,
                        'phone' => $member->phone,
                        'avatar_path' => $member->avatar_path,
                        'initial' => substr($member->name ?? $member->phone, 0, 1),
                        'mention_tag' => $mentionTag, // Add this field
                    ];
                })
                ->toArray();
        @endphp
        <script type="application/json" id="group-members-data">
@json($membersJson)
</script>
    @endif
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
                {{-- NEW: Quick Replies Button --}}
                <button class="btn btn-ghost flex-shrink-0" type="button" id="quick-replies-btn"
                    aria-label="Quick replies" title="Quick replies (Ctrl+/)">
                    <i class="bi bi-reply-all" aria-hidden="true"></i>
                </button>
                {{-- Textarea with Mention Support --}}
                <div class="form-control-wrapper flex-grow-1 position-relative" style="min-width: 0;">
                    <textarea name="body" class="form-control message-input flex-grow-1" placeholder="{{ $placeholder }}"
                        id="message-input" autocomplete="off" maxlength="1000" aria-label="Message input" aria-describedby="send-button"
                        aria-required="true" rows="1"
                        style="resize: none; overflow-y: auto; min-height: 48px; max-height: 120px; border: none; background: transparent; box-shadow: none;"></textarea>

                    {{-- Mention Suggestions Dropdown --}}
                    <div id="mention-suggestions" class="mention-suggestions dropdown-menu" style="display: none;">
                        <div class="mention-suggestions-list" id="mention-suggestions-list">
                            {{-- Suggestions will be populated here --}}
                        </div>
                    </div>

                    {{-- Add this inside your form-control-wrapper div, after the mention-suggestions --}}
                    <div id="quick-reply-suggestions" class="quick-reply-suggestions dropdown-menu"
                        style="display: none;">
                        <div class="quick-reply-suggestions-list" id="quick-reply-suggestions-list">
                            {{-- Quick reply suggestions will be populated here --}}
                        </div>
                    </div>
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
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li role="none">
                            <button type="button"
                                class="dropdown-item d-flex align-items-center gap-2 cursor-pointer"
                                onclick="shareLocation()" role="menuitem">
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                <span>Share Location</span>
                            </button>
                        </li>
                        <li role="none">
                            <button type="button"
                                class="dropdown-item d-flex align-items-center gap-2 cursor-pointer"
                                onclick="shareContact()" role="menuitem">
                                <i class="bi bi-person" aria-hidden="true"></i>
                                <span>Share Contact</span>
                            </button>
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
        position: relative;
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
        color: var(--text) !important;
    }

    .message-input:focus {
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        outline: none !important;
        color: var(--text) !important;
    }

    /* Context-specific focus colors */
    [data-context="group"] .form-control-wrapper:focus-within {
        border-color: var(--group-accent, var(--wa-green));
        box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--group-accent, var(--wa-green)) 25%, transparent);
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
        /* border-radius: 50%; */
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

    /* Mention Suggestions Styles */
    .mention-suggestions {
        position: absolute;
        bottom: 100%;
        left: 0;
        right: 0;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: var(--wa-shadow);
        max-height: 200px;
        overflow-y: auto;
        z-index: 1060;
        margin-bottom: 8px;
    }

    .mention-suggestion {
        padding: 8px 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        border-bottom: 1px solid var(--border);
        transition: background-color 0.2s ease;
    }

    .mention-suggestion:last-child {
        border-bottom: none;
    }

    .mention-suggestion:hover,
    .mention-suggestion.active {
        background: color-mix(in srgb, var(--wa-green) 15%, transparent);
    }

    .mention-suggestion-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: var(--wa-green);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.75rem;
        font-weight: 600;
        flex-shrink: 0;
    }

    .mention-suggestion-name {
        font-weight: 500;
        color: var(--text);
        flex: 1;
        min-width: 0;
    }

    .mention-suggestion-phone {
        font-size: 0.75rem;
        color: var(--wa-muted);
    }

    .mention-tag {
        background: color-mix(in srgb, var(--wa-green) 20%, transparent);
        color: var(--wa-green);
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 500;
        margin: 0 2px;
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

        .mention-suggestions {
            max-height: 150px;
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

        .mention-suggestions {
            max-height: 120px;
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
        outline-color: var(--group-accent, var(--wa-green));
    }

    /* Quick Reply Suggestions Styles */
    .quick-reply-suggestions {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: var(--wa-shadow);
        max-height: 200px;
        overflow-y: auto;
        z-index: 1060;
    }

    .quick-reply-suggestion {
        padding: 10px 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: 1px solid var(--border);
        transition: background-color 0.2s ease;
    }

    .quick-reply-suggestion:last-child {
        border-bottom: none;
    }

    .quick-reply-suggestion:hover,
    .quick-reply-suggestion.active {
        background: color-mix(in srgb, var(--wa-green) 15%, transparent);
    }

    .quick-reply-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: color-mix(in srgb, var(--wa-green) 15%, transparent);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--wa-green);
        font-size: 1rem;
        flex-shrink: 0;
    }

    .quick-reply-content {
        flex: 1;
        min-width: 0;
    }

    .quick-reply-title {
        font-weight: 600;
        color: var(--text);
        margin-bottom: 2px;
        font-size: 0.875rem;
    }

    .quick-reply-preview {
        font-size: 0.75rem;
        color: var(--muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .quick-reply-usage {
        flex-shrink: 0;
    }

    .usage-count {
        background: var(--wa-green);
        color: white;
        border-radius: 10px;
        padding: 2px 6px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    /* Quick Reply Modal Styles */
    .quick-reply-item {
        transition: background-color 0.2s ease;
        border-radius: 8px;
        margin-bottom: 4px;
    }

    .quick-reply-item:hover {
        background: color-mix(in srgb, var(--wa-green) 8%, transparent);
    }

    .quick-reply-item:last-child {
        border-bottom: none !important;
    }

    /* Slash Command Hint */
    .slash-command-hint {
        position: absolute;
        top: -25px;
        left: 0;
        background: var(--wa-green);
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 500;
        z-index: 1050;
    }

    .slash-command-hint::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 10px;
        border: 4px solid transparent;
        border-top-color: var(--wa-green);
    }

    /* Reduced motion */
    @media (prefers-reduced-motion: reduce) {

        .form-control-wrapper,
        .btn-ghost,
        .btn-wa,
        .attachment-preview-container,
        .mention-suggestion {
            transition: none;
            animation: none;
        }
    }

    /* Scrollbar styling */
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

    .mention-suggestions::-webkit-scrollbar {
        width: 4px;
    }

    .mention-suggestions::-webkit-scrollbar-track {
        background: transparent;
    }

    .mention-suggestions::-webkit-scrollbar-thumb {
        background: color-mix(in srgb, var(--text) 20%, transparent);
        border-radius: 2px;
    }
</style>

@push('scripts')
    <script>
        // Phone number handling functions (keep as utilities)
        function normalizePhoneNumber(phone) {
            let cleaned = phone.replace(/[^\d+]/g, '');

            if (cleaned.startsWith('233')) {
                return '+' + cleaned;
            } else if (cleaned.startsWith('0')) {
                return '+233' + cleaned.substring(1);
            } else if (cleaned.length === 9 && !cleaned.startsWith('0')) {
                return '+233' + cleaned;
            }

            return cleaned;
        }

        // Global helper functions
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className =
                `alert alert-${type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'success'} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; padding: 12px 16px;';
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        function resetSecuritySettings() {
            if (window.messageComposer) {
                window.messageComposer.resetSecuritySettings();
            }
        }

        // ChatCore-integrated Message Composer
        class MessageComposer {
            constructor() {
                this.messageForm = document.getElementById('chat-form');
                this.messageInput = document.getElementById('message-input');
                this.sendButton = document.getElementById('send-btn');
                this.emojiButton = document.getElementById('emoji-btn');
                this.securityButton = document.getElementById('security-btn');
                this.dropZone = document.getElementById('drop-zone');
                this.photoUpload = document.getElementById('photo-upload');
                this.docUpload = document.getElementById('doc-upload');
                this.uploadProgress = document.getElementById('upload-progress');
                this.progressBar = this.uploadProgress?.querySelector('.progress-bar');
                this.attachmentPreview = document.getElementById('attachment-preview');
                this.attachmentCount = document.getElementById('attachment-count');
                this.attachmentPreviewList = document.getElementById('attachment-preview-list');
                this.cancelAttachmentsBtn = document.getElementById('cancel-attachments');
                this.replyToInput = document.getElementById('reply-to-id');

                this.context = this.messageForm?.dataset.context || 'direct';
                this.isGroup = this.context === 'group';
                this.groupId = this.messageForm?.dataset.groupId;

                this.selectedFiles = [];
                this.mentionState = {
                    active: false,
                    query: '',
                    startPos: 0,
                    suggestions: [],
                    selectedIndex: -1
                };
                this.groupMembers = [];

                this.quickReplyState = {
                    active: false,
                    query: '',
                    startPos: 0,
                    suggestions: [],
                    selectedIndex: -1
                };
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.setupDragAndDrop();
                this.setupInputValidation();
                this.setupAttachmentHandling();
                this.setupAutoResize();
                this.setupEmojiPickerIntegration();
                this.setupQuickReplySystem();
                if (this.isGroup) {
                    this.initializeMentionSystem();
                }

                // Expose to global scope
                window.messageComposer = this;
            }

            setupQuickReplySystem() {
                // Quick replies button click
                const quickRepliesBtn = document.getElementById('quick-replies-btn');
                if (quickRepliesBtn) {
                    quickRepliesBtn.addEventListener('click', () => this.showQuickRepliesModal());
                }

                // Slash command detection
                this.messageInput?.addEventListener('input', (e) => this.handleQuickReplyInput(e));
                this.messageInput?.addEventListener('keydown', (e) => this.handleQuickReplyNavigation(e));

                document.addEventListener('click', (e) => {
                    if (!e.target.closest('#quick-reply-suggestions') && !e.target.closest(
                            '.form-control-wrapper')) {
                        this.hideQuickReplySuggestions();
                    }
                });
            }

            handleQuickReplyInput(e) {
                const cursorPos = e.target.selectionStart;
                const textBeforeCursor = e.target.value.substring(0, cursorPos);

                // Check for slash command
                const slashIndex = textBeforeCursor.lastIndexOf('/');

                if (slashIndex !== -1) {
                    const textAfterSlash = textBeforeCursor.substring(slashIndex + 1);
                    const hasSpace = textAfterSlash.includes(' ');

                    if (!hasSpace) {
                        this.quickReplyState.active = true;
                        this.quickReplyState.query = textAfterSlash.toLowerCase();
                        this.quickReplyState.startPos = slashIndex;
                        this.showQuickReplySuggestions(textAfterSlash);
                        return;
                    }
                }

                this.hideQuickReplySuggestions();
            }

            async showQuickReplySuggestions(query) {
                try {
                    const response = await fetch('/api/quick-replies');
                    const result = await response.json();

                    if (result.success && result.quick_replies) {
                        const suggestions = result.quick_replies.filter(reply =>
                            reply.title.toLowerCase().includes(query) ||
                            reply.message.toLowerCase().includes(query)
                        ).slice(0, 5);

                        this.quickReplyState.suggestions = suggestions;
                        this.renderQuickReplySuggestions(suggestions);
                    } else {
                        this.hideQuickReplySuggestions();
                    }
                } catch (error) {
                    console.error('Failed to load quick replies:', error);
                    this.hideQuickReplySuggestions();
                }
            }

            renderQuickReplySuggestions(suggestions) {
                let suggestionsContainer = document.getElementById('quick-reply-suggestions');

                // Create container if it doesn't exist
                if (!suggestionsContainer) {
                    suggestionsContainer = document.createElement('div');
                    suggestionsContainer.id = 'quick-reply-suggestions';
                    suggestionsContainer.className = 'quick-reply-suggestions dropdown-menu';
                    suggestionsContainer.style.cssText =
                        'position: absolute; bottom: 100%; left: 0; right: 0; background: var(--card); border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--wa-shadow); max-height: 200px; overflow-y: auto; z-index: 1060; margin-bottom: 8px; display: none;';

                    const suggestionsList = document.createElement('div');
                    suggestionsList.id = 'quick-reply-suggestions-list';
                    suggestionsList.className = 'quick-reply-suggestions-list';
                    suggestionsContainer.appendChild(suggestionsList);

                    document.querySelector('.form-control-wrapper').appendChild(suggestionsContainer);
                }

                const suggestionsList = document.getElementById('quick-reply-suggestions-list');

                if (suggestions.length > 0) {
                    suggestionsList.innerHTML = suggestions.map((reply, index) => `
                <div class="quick-reply-suggestion ${index === 0 ? 'active' : ''}" 
                     data-reply-id="${reply.id}" 
                     data-reply-message="${reply.message}">
                    <div class="quick-reply-icon">
                        <i class="bi bi-reply-all"></i>
                    </div>
                    <div class="quick-reply-content">
                        <div class="quick-reply-title">${reply.title}</div>
                        <div class="quick-reply-preview">${reply.message.substring(0, 50)}${reply.message.length > 50 ? '...' : ''}</div>
                    </div>
                    <div class="quick-reply-usage">
                        ${reply.usage_count > 0 ? `<span class="usage-count">${reply.usage_count}</span>` : ''}
                    </div>
                </div>
            `).join('');

                    suggestionsContainer.style.display = 'block';
                    this.quickReplyState.selectedIndex = 0;

                    suggestionsList.querySelectorAll('.quick-reply-suggestion').forEach((suggestion, index) => {
                        suggestion.addEventListener('click', () => this.selectQuickReply(index));
                    });
                } else {
                    this.hideQuickReplySuggestions();
                }
            }

            hideQuickReplySuggestions() {
                const suggestionsContainer = document.getElementById('quick-reply-suggestions');
                if (suggestionsContainer) {
                    suggestionsContainer.style.display = 'none';
                }
                this.quickReplyState.active = false;
                this.quickReplyState.selectedIndex = -1;
            }

            handleQuickReplyNavigation(e) {
                if (!this.quickReplyState.active) return;

                const suggestions = document.querySelectorAll('.quick-reply-suggestion');

                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.quickReplyState.selectedIndex = Math.min(this.quickReplyState.selectedIndex + 1,
                            suggestions.length - 1);
                        this.updateQuickReplySelection();
                        break;

                    case 'ArrowUp':
                        e.preventDefault();
                        this.quickReplyState.selectedIndex = Math.max(this.quickReplyState.selectedIndex - 1, 0);
                        this.updateQuickReplySelection();
                        break;

                    case 'Enter':
                        e.preventDefault();
                        if (this.quickReplyState.selectedIndex >= 0) {
                            this.selectQuickReply(this.quickReplyState.selectedIndex);
                        }
                        break;

                    case 'Escape':
                        e.preventDefault();
                        this.hideQuickReplySuggestions();
                        break;

                    case 'Tab':
                        if (this.quickReplyState.active) {
                            e.preventDefault();
                            if (this.quickReplyState.selectedIndex >= 0) {
                                this.selectQuickReply(this.quickReplyState.selectedIndex);
                            }
                        }
                        break;
                }
            }

            updateQuickReplySelection() {
                document.querySelectorAll('.quick-reply-suggestion').forEach((suggestion, index) => {
                    suggestion.classList.toggle('active', index === this.quickReplyState.selectedIndex);
                });
            }

            selectQuickReply(index) {
                const selectedSuggestion = this.quickReplyState.suggestions[index];
                if (!selectedSuggestion) return;

                const currentValue = this.messageInput.value;
                const textBeforeSlash = currentValue.substring(0, this.quickReplyState.startPos);

                // Replace the slash command with the quick reply message
                const newValue = textBeforeSlash + selectedSuggestion.message + ' ';

                this.messageInput.value = newValue;
                this.messageInput.focus();

                const newCursorPos = newValue.length;
                this.messageInput.setSelectionRange(newCursorPos, newCursorPos);

                // Record usage
                this.recordQuickReplyUsage(selectedSuggestion.id);

                this.hideQuickReplySuggestions();
                this.handleInputChange();
            }

            async recordQuickReplyUsage(replyId) {
                try {
                    await fetch(`/api/quick-replies/${replyId}/record-usage`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                                'content') || ''
                        }
                    });
                } catch (error) {
                    console.error('Failed to record quick reply usage:', error);
                }
            }

            async showQuickRepliesModal() {
                try {
                    const response = await fetch('/api/quick-replies');
                    const result = await response.json();

                    if (result.success && result.quick_replies) {
                        this.renderQuickRepliesModal(result.quick_replies);
                    } else {
                        showToast('No quick replies available', 'warning');
                    }
                } catch (error) {
                    console.error('Failed to load quick replies:', error);
                    showToast('Failed to load quick replies', 'error');
                }
            }

            renderQuickRepliesModal(quickReplies) {
                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Quick Replies</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="quick-replies-list" style="max-height: 400px; overflow-y: auto;">
                            ${quickReplies.map(reply => `
                                    <div class="quick-reply-item p-3 border-bottom cursor-pointer" 
                                         data-reply-id="${reply.id}"
                                         onclick="window.messageComposerInstance.selectQuickReplyFromModal(this)">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="quick-reply-icon mt-1">
                                                <i class="bi bi-reply-all text-primary"></i>
                                            </div>
                                            <div class="quick-reply-content flex-grow-1">
                                                <h6 class="mb-1 fw-semibold">${reply.title}</h6>
                                                <p class="mb-1 text-muted">${reply.message}</p>
                                                ${reply.usage_count > 0 ? 
                                                    `<small class="text-muted">Used ${reply.usage_count} time${reply.usage_count !== 1 ? 's' : ''}</small>` : 
                                                    ''}
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="window.messageComposerInstance.manageQuickReplies()">
                            Manage Quick Replies
                        </button>
                    </div>
                </div>
            </div>
        `;

                document.body.appendChild(modal);
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();

                modal.addEventListener('hidden.bs.modal', () => {
                    modal.remove();
                });
            }

            selectQuickReplyFromModal(quickReplyElement) {
                const replyId = quickReplyElement.getAttribute('data-reply-id');
                const quickReply = this.quickReplyState.suggestions.find(r => r.id == replyId) ||
                    this.findQuickReplyById(replyId);

                if (quickReply) {
                    this.insertQuickReply(quickReply);
                    this.recordQuickReplyUsage(replyId);

                    // Close modal
                    const modal = quickReplyElement.closest('.modal');
                    if (modal) {
                        bootstrap.Modal.getInstance(modal).hide();
                    }
                }
            }

            findQuickReplyById(replyId) {
                // This would need to be implemented to find the quick reply by ID
                // You might want to load all quick replies when the composer initializes
                return null;
            }

            insertQuickReply(quickReply) {
                const currentValue = this.messageInput.value;
                const cursorPos = this.messageInput.selectionStart;

                const textBeforeCursor = currentValue.substring(0, cursorPos);
                const textAfterCursor = currentValue.substring(cursorPos);

                const newValue = textBeforeCursor + quickReply.message + ' ' + textAfterCursor;

                this.messageInput.value = newValue;
                this.messageInput.focus();

                const newCursorPos = textBeforeCursor.length + quickReply.message.length + 1;
                this.messageInput.setSelectionRange(newCursorPos, newCursorPos);

                this.handleInputChange();
            }

            manageQuickReplies() {
                window.open('/settings/quick-replies', '_blank');
            }

            setupEventListeners() {
                // Form submission - let ChatCore handle this
                this.messageForm?.addEventListener('submit', (e) => this.handleFormSubmit(e));

                // Input validation
                this.messageInput?.addEventListener('input', () => this.handleInputChange());

                // Security button (direct chats only)
                if (!this.isGroup && this.securityButton) {
                    this.securityButton.addEventListener('click', () => this.handleSecurityButton());
                }

                // File upload triggers
                this.photoUpload?.addEventListener('change', (e) => this.handleFileSelection(e));
                this.docUpload?.addEventListener('change', (e) => this.handleFileSelection(e));

                // Enter key handling
                this.messageInput?.addEventListener('keydown', (e) => this.handleKeydown(e));
            }

            setupEmojiPickerIntegration() {
                if (this.emojiButton) {
                    this.emojiButton.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (window.emojiPicker && typeof window.emojiPicker.toggle === 'function') {
                            window.emojiPicker.toggle(e);
                        }
                    });
                }
            }

            setupAutoResize() {
                this.messageInput?.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                });
            }

            setupAttachmentHandling() {
                this.cancelAttachmentsBtn?.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.clearAttachments();
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this.attachmentPreview?.style.display === 'block') {
                        this.clearAttachments();
                    }
                });
            }

            setupDragAndDrop() {
                if (!this.dropZone) return;

                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    this.dropZone.addEventListener(eventName, this.preventDefaults, false);
                });

                ['dragenter', 'dragover'].forEach(eventName => {
                    this.dropZone.addEventListener(eventName, this.highlight, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    this.dropZone.addEventListener(eventName, this.unhighlight, false);
                });

                this.dropZone.addEventListener('drop', (e) => this.handleDrop(e), false);
            }

            preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            highlight() {
                this.dropZone.classList.add('drag-over');
            }

            unhighlight() {
                this.dropZone.classList.remove('drag-over');
            }

            handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                this.handleFiles(files);
            }

            setupInputValidation() {
                this.messageInput?.addEventListener('input', () => {
                    const hasContent = this.messageInput.value.trim().length > 0;
                    const hasFiles = this.selectedFiles.length > 0;

                    if (this.sendButton) {
                        this.sendButton.disabled = !hasContent && !hasFiles;
                    }
                });
            }

            // Mention System
            initializeMentionSystem() {
                this.loadGroupMembers();

                this.messageInput?.addEventListener('input', (e) => this.handleMentionInput(e));
                this.messageInput?.addEventListener('keydown', (e) => this.handleMentionNavigation(e));

                document.addEventListener('click', (e) => {
                    if (!e.target.closest('#mention-suggestions') && !e.target.closest(
                            '.form-control-wrapper')) {
                        this.hideMentionSuggestions();
                    }
                });
            }

            loadGroupMembers() {
                const groupDataElement = document.getElementById('group-members-data');
                if (groupDataElement) {
                    try {
                        this.groupMembers = JSON.parse(groupDataElement.textContent);
                    } catch (e) {
                        console.error('Failed to parse group members data:', e);
                    }
                }
            }

            handleMentionInput(e) {
                if (!this.isGroup) return;

                const cursorPos = e.target.selectionStart;
                const textBeforeCursor = e.target.value.substring(0, cursorPos);

                const atSymbolIndex = textBeforeCursor.lastIndexOf('@');

                if (atSymbolIndex !== -1) {
                    const textAfterAt = textBeforeCursor.substring(atSymbolIndex + 1);
                    const hasSpace = textAfterAt.includes(' ');

                    if (!hasSpace) {
                        this.mentionState.active = true;
                        this.mentionState.query = textAfterAt;
                        this.mentionState.startPos = atSymbolIndex;
                        this.showMentionSuggestions(textAfterAt);
                        return;
                    }
                }

                this.hideMentionSuggestions();
            }

            showMentionSuggestions(query) {
                if (this.groupMembers.length === 0) {
                    this.hideMentionSuggestions();
                    return;
                }

                const suggestions = this.groupMembers.filter(member =>
                    member.name?.toLowerCase().includes(query.toLowerCase()) ||
                    member.phone?.includes(query)
                ).slice(0, 5);

                this.mentionState.suggestions = suggestions;

                const suggestionsContainer = document.getElementById('mention-suggestions-list');
                const suggestionsDropdown = document.getElementById('mention-suggestions');

                if (suggestions.length > 0) {
                    suggestionsContainer.innerHTML = suggestions.map((member, index) => `
                <div class="mention-suggestion ${index === 0 ? 'active' : ''}" 
                     data-user-id="${member.id}" 
                     data-user-name="${member.name}">
                    <div class="mention-suggestion-avatar">
                        ${member.initial || (member.name || member.phone).charAt(0).toUpperCase()}
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div class="mention-suggestion-name">${member.name || member.phone}</div>
                        ${member.name ? `<div class="mention-suggestion-phone">${member.phone}</div>` : ''}
                    </div>
                </div>
            `).join('');

                    suggestionsDropdown.style.display = 'block';
                    this.mentionState.selectedIndex = 0;

                    suggestionsContainer.querySelectorAll('.mention-suggestion').forEach((suggestion, index) => {
                        suggestion.addEventListener('click', () => this.selectMention(index));
                    });
                } else {
                    this.hideMentionSuggestions();
                }
            }

            hideMentionSuggestions() {
                const suggestionsDropdown = document.getElementById('mention-suggestions');
                if (suggestionsDropdown) {
                    suggestionsDropdown.style.display = 'none';
                }
                this.mentionState.active = false;
                this.mentionState.selectedIndex = -1;
            }

            handleMentionNavigation(e) {
                if (!this.mentionState.active) return;

                const suggestions = document.querySelectorAll('.mention-suggestion');

                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.mentionState.selectedIndex = Math.min(this.mentionState.selectedIndex + 1, suggestions
                            .length - 1);
                        this.updateMentionSelection();
                        break;

                    case 'ArrowUp':
                        e.preventDefault();
                        this.mentionState.selectedIndex = Math.max(this.mentionState.selectedIndex - 1, 0);
                        this.updateMentionSelection();
                        break;

                    case 'Enter':
                        e.preventDefault();
                        if (this.mentionState.selectedIndex >= 0) {
                            this.selectMention(this.mentionState.selectedIndex);
                        }
                        break;

                    case 'Escape':
                        e.preventDefault();
                        this.hideMentionSuggestions();
                        break;
                }
            }

            updateMentionSelection() {
                document.querySelectorAll('.mention-suggestion').forEach((suggestion, index) => {
                    suggestion.classList.toggle('active', index === this.mentionState.selectedIndex);
                });
            }

            selectMention(index) {
                const selectedSuggestion = this.mentionState.suggestions[index];
                if (!selectedSuggestion) return;

                const currentValue = this.messageInput.value;
                const textBeforeMention = currentValue.substring(0, this.mentionState.startPos);
                const textAfterMention = currentValue.substring(this.mentionState.startPos + this.mentionState.query
                    .length + 1);

                const mentionTag = `@${selectedSuggestion.phone}`;
                const newValue = textBeforeMention + mentionTag + ' ' + textAfterMention;

                this.messageInput.value = newValue;
                this.messageInput.focus();

                const newCursorPos = textBeforeMention.length + mentionTag.length + 1;
                this.messageInput.setSelectionRange(newCursorPos, newCursorPos);

                this.hideMentionSuggestions();
                this.handleInputChange();
            }

            // Attachment Functions
            handleFileSelection(e) {
                const files = Array.from(e.target.files);
                this.handleFiles(files);
                e.target.value = '';
            }

            handleFiles(files) {
                if (files.length === 0) return;

                const validFiles = files.filter(file => file.size > 0 && file.name);

                if (validFiles.length === 0) {
                    showToast('No valid files selected', 'warning');
                    return;
                }

                this.selectedFiles = [...this.selectedFiles, ...validFiles];
                this.updateAttachmentPreview();
                this.showAttachmentPreview();

                if (this.sendButton && this.selectedFiles.length > 0) {
                    this.sendButton.disabled = false;
                }
            }

            updateAttachmentPreview() {
                if (!this.attachmentPreviewList || !this.attachmentCount) return;

                this.attachmentCount.textContent =
                    `${this.selectedFiles.length} file${this.selectedFiles.length !== 1 ? 's' : ''}`;
                this.attachmentPreviewList.innerHTML = '';

                this.selectedFiles.forEach((file, index) => {
                    const fileElement = document.createElement('div');
                    fileElement.className = 'attachment-preview-item';
                    fileElement.innerHTML = `
                <i class="bi ${this.getFileIcon(file.type)} text-muted"></i>
                <span class="file-name" title="${file.name}">${file.name}</span>
                <button type="button" class="remove-file" data-index="${index}" aria-label="Remove ${file.name}">
                    <i class="bi bi-x"></i>
                </button>
            `;
                    this.attachmentPreviewList.appendChild(fileElement);
                });

                this.attachmentPreviewList.querySelectorAll('.remove-file').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const index = parseInt(e.target.closest('.remove-file').getAttribute(
                            'data-index'));
                        this.removeFile(index);
                    });
                });
            }

            getFileIcon(fileType) {
                if (fileType.startsWith('image/')) return 'bi-image';
                if (fileType.startsWith('video/')) return 'bi-film';
                if (fileType.includes('pdf')) return 'bi-file-pdf';
                if (fileType.includes('word') || fileType.includes('document')) return 'bi-file-word';
                if (fileType.includes('zip') || fileType.includes('rar')) return 'bi-file-zip';
                return 'bi-file-earmark';
            }

            removeFile(index) {
                this.selectedFiles.splice(index, 1);
                if (this.selectedFiles.length === 0) {
                    this.hideAttachmentPreview();
                } else {
                    this.updateAttachmentPreview();
                }

                if (this.sendButton) {
                    const hasContent = this.messageInput?.value.trim().length > 0;
                    this.sendButton.disabled = !hasContent && this.selectedFiles.length === 0;
                }
            }

            clearAttachments() {
                this.selectedFiles = [];
                this.hideAttachmentPreview();

                if (this.sendButton) {
                    const hasContent = this.messageInput?.value.trim().length > 0;
                    this.sendButton.disabled = !hasContent;
                }
            }

            showAttachmentPreview() {
                if (!this.attachmentPreview) return;
                this.attachmentPreview.style.display = 'block';
                this.attachmentPreview.classList.add('showing');
            }

            hideAttachmentPreview() {
                if (!this.attachmentPreview) return;
                this.attachmentPreview.style.display = 'none';
                this.attachmentPreview.classList.remove('showing');
            }

            // Event handlers
            async handleFormSubmit(e) {
                e.preventDefault();

                // Let ChatCore handle the submission if available
                if (window.chatInstance && typeof window.chatInstance.handleMessageSubmit === 'function') {
                    await window.chatInstance.handleMessageSubmit(e);
                    this.clearAfterSend();
                } else {
                    await this.submitFormDirectly();
                }
            }

            async submitFormDirectly() {
                // Your existing form submission logic
                // ... (keep your existing submitFormDirectly logic)
            }

            clearAfterSend() {
                this.messageInput.value = '';
                this.messageInput.style.height = 'auto';
                this.clearAttachments();
                this.resetSecuritySettings();
                this.hideMentionSuggestions();

                if (window.hideReplyPreview) {
                    window.hideReplyPreview();
                }
            }

            handleInputChange() {
                // Let ChatCore handle typing indicators
                if (window.chatInstance && typeof window.chatInstance.handleUserTyping === 'function') {
                    window.chatInstance.handleUserTyping();
                }
            }

            handleSecurityButton() {
                // Your security modal logic
            }

            handleKeydown(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (!this.sendButton.disabled) {
                        this.messageForm?.dispatchEvent(new Event('submit'));
                    }
                }

                // Quick replies shortcut: Ctrl+/
                if (e.ctrlKey && e.key === '/') {
                    e.preventDefault();
                    this.showQuickRepliesModal();
                }
            }

            // Public API methods
            focusInput() {
                this.messageInput?.focus();
            }

            clearInput() {
                this.messageInput.value = '';
                this.messageInput.style.height = 'auto';
                this.clearAttachments();
                this.resetSecuritySettings();
                this.hideMentionSuggestions();

                if (window.hideReplyPreview) {
                    window.hideReplyPreview();
                }
            }

            setReplyTo(messageId, messageText, senderName = null) {
                if (window.showReplyPreview) {
                    window.showReplyPreview(messageText, senderName, messageId);
                }
            }

            resetSecuritySettings() {
                if (!this.isGroup && this.securityButton) {
                    this.securityButton.classList.remove('active');
                    this.securityButton.innerHTML = '<i class="bi bi-shield-lock" aria-hidden="true"></i>';

                    const encryptInput = document.getElementById('is-encrypted');
                    const expireInput = document.getElementById('expires-in');
                    if (encryptInput) encryptInput.value = '0';
                    if (expireInput) expireInput.value = '';
                }
            }
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            window.messageComposerInstance = new MessageComposer();
        });

        // Location Sharing
        async function shareLocation() {
            if (!navigator.geolocation) {
                showToast('Geolocation is not supported by your browser', 'warning');
                return;
            }

            showToast('Getting your location...', 'info');

            try {
                const position = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 60000
                    });
                });

                const {
                    latitude,
                    longitude
                } = position.coords;

                // Get address using reverse geocoding
                let address = null;
                let placeName = null;

                try {
                    const response = await fetch(
                        `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`
                    );
                    const data = await response.json();

                    if (data && data.display_name) {
                        address = data.display_name;
                        placeName = data.name || data.display_name.split(',')[0];
                    }
                } catch (geocodeError) {
                    console.warn('Geocoding failed:', geocodeError);
                    // Continue without address
                }

                // Show location confirmation modal
                showLocationConfirmation(latitude, longitude, address, placeName);

            } catch (error) {
                console.error('Location error:', error);
                let errorMessage = 'Failed to get location';

                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage = 'Location access denied. Please enable location permissions.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage = 'Location information unavailable.';
                        break;
                    case error.TIMEOUT:
                        errorMessage = 'Location request timed out.';
                        break;
                }

                showToast(errorMessage, 'error');
            }
        }

        function showLocationConfirmation(latitude, longitude, address, placeName) {
            // Create confirmation modal
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Share Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="location-preview mb-3 p-3 bg-light rounded text-center">
                        <i class="bi bi-geo-alt-fill text-primary display-4 mb-2"></i>
                        <p class="mb-1 fw-semibold">${placeName || 'Current Location'}</p>
                        ${address ? `<p class="small text-muted mb-2">${address}</p>` : ''}
                        <p class="small text-muted">Lat: ${latitude.toFixed(6)}, Lng: ${longitude.toFixed(6)}</p>
                    </div>
                    <p class="text-muted small">Your current location will be shared in this chat.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-share-location">Share Location</button>
                </div>
            </div>
        </div>
    `;

            document.body.appendChild(modal);

            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();

            // Handle confirmation
            modal.querySelector('#confirm-share-location').addEventListener('click', async () => {
                bsModal.hide();

                try {
                    const context = messageForm?.dataset.context || 'direct';
                    const url = context === 'group' ?
                        `/api/groups/${groupId}/share-location` :
                        '/api/share-location';

                    const payload = {
                        latitude,
                        longitude,
                        address,
                        place_name: placeName,
                        ...(context === 'direct' ? {
                            conversation_id: conversationId
                        } : {})
                    };

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || ''
                        },
                        body: JSON.stringify(payload)
                    });

                    const result = await response.json();

                    if (result.status === 'success') {
                        showToast('Location shared successfully', 'success');

                        // Broadcast the message if needed
                        if (window.chat && typeof window.chat.handleNewMessage === 'function') {
                            window.chat.handleNewMessage(result.message);
                        }
                    } else {
                        throw new Error(result.message || 'Failed to share location');
                    }

                } catch (error) {
                    console.error('Share location error:', error);
                    showToast(error.message || 'Failed to share location', 'error');
                }
            });

            // Clean up modal after hide
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        }

        // Contact Sharing
        async function shareContact() {
            // Load user's contacts
            try {
                const response = await fetch('/api/contacts');
                const result = await response.json();

                if (result.success && result.contacts && result.contacts.length > 0) {
                    showContactSelectionModal(result.contacts);
                } else {
                    showToast('No contacts available to share', 'warning');
                }
            } catch (error) {
                console.error('Load contacts error:', error);
                showToast('Failed to load contacts', 'error');
            }
        }

        function showContactSelectionModal(contacts) {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Share Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="contacts-list" style="max-height: 300px; overflow-y: auto;">
                        ${contacts.map(contact => `
                                            <div class="contact-item p-3 border-bottom cursor-pointer" 
                                                 data-contact-id="${contact.id}"
                                                 onclick="selectContact(this)">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="contact-avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px; font-size: 1rem; font-weight: 600;">
                                                        ${(contact.display_name || contact.phone).charAt(0).toUpperCase()}
                                                    </div>
                                                    <div class="contact-info flex-grow-1">
                                                        <h6 class="mb-1 fw-semibold">${contact.display_name || contact.phone}</h6>
                                                        <p class="mb-0 text-muted small">${contact.phone}</p>
                                                        ${contact.email ? `<p class="mb-0 text-muted small">${contact.email}</p>` : ''}
                                                    </div>
                                                    <i class="bi bi-check-circle-fill text-primary" style="display: none;"></i>
                                                </div>
                                            </div>
                                        `).join('')}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-share-contact" disabled>Share Contact</button>
                </div>
            </div>
        </div>
    `;

            document.body.appendChild(modal);

            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();

            let selectedContactId = null;

            // Handle contact selection
            window.selectContact = function(contactElement) {
                modal.querySelectorAll('.contact-item').forEach(item => {
                    item.classList.remove('selected');
                    item.querySelector('.bi-check-circle-fill').style.display = 'none';
                });

                contactElement.classList.add('selected');
                contactElement.querySelector('.bi-check-circle-fill').style.display = 'block';
                selectedContactId = contactElement.getAttribute('data-contact-id');

                modal.querySelector('#confirm-share-contact').disabled = false;
            };

            // Handle confirmation
            modal.querySelector('#confirm-share-contact').addEventListener('click', async () => {
                if (!selectedContactId) return;

                bsModal.hide();

                try {
                    const context = messageForm?.dataset.context || 'direct';
                    const url = context === 'group' ?
                        `/api/groups/${groupId}/share-contact` :
                        '/api/share-contact';

                    const payload = {
                        contact_id: selectedContactId,
                        ...(context === 'direct' ? {
                            conversation_id: conversationId
                        } : {})
                    };

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || ''
                        },
                        body: JSON.stringify(payload)
                    });

                    const result = await response.json();

                    if (result.status === 'success') {
                        showToast('Contact shared successfully', 'success');

                        // Broadcast the message if needed
                        if (window.chat && typeof window.chat.handleNewMessage === 'function') {
                            window.chat.handleNewMessage(result.message);
                        }
                    } else {
                        throw new Error(result.message || 'Failed to share contact');
                    }

                } catch (error) {
                    console.error('Share contact error:', error);
                    showToast(error.message || 'Failed to share contact', 'error');
                }
            });

            // Clean up modal after hide
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
                delete window.selectContact;
            });
        }
    </script>
@endpush

{{-- Include emoji picker --}}
@include('chat.shared.emoji_picker')

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const messageInput = document.getElementById('message-input');
        const emojiButton = document.getElementById('emoji-btn');

        if (window.emojiPicker && messageInput && emojiButton) {
            window.emojiPicker.setMessageInput(messageInput);
            window.emojiPicker.setEmojiButton(emojiButton);
        }
    });
</script>
