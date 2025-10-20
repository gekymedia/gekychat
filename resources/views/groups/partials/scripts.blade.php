{{-- resources/views/groups/partials/scripts.blade.php --}}
<script>
(function() {
    'use strict';

    // ==== Configuration & Constants ====
    const CONFIG = {
        DEBOUNCE_DELAY: 300,
        TYPING_DELAY: 450,
        RETRY_DELAY: 2000,
        MAX_RETRY_DELAY: 30000,
        SCROLL_THRESHOLD: 300,
        MESSAGE_PAGE_SIZE: 20,
        UPLOAD_MAX_SIZE: 10 * 1024 * 1024, // 10MB
        TYPING_TIMEOUT: 1800,
        PRESENCE_TIMEOUT: 30000
    };

    // ==== State Management ====
    const state = {
        isLoading: false,
        hasMore: true,
        currentPage: 1,
        typingUsers: new Set(),
        typingTimer: null,
        typingHideTimer: null,
        retryTimer: null,
        retryAt: null,
        retryDelay: CONFIG.RETRY_DELAY,
        observer: null,
        resizeObserver: null,
        echo: null,
        presenceInterval: null
    };

    // ==== DOM Elements Cache ====
    const elements = {};

    // ==== Initialization ====
    document.addEventListener('DOMContentLoaded', function() {
        initializeGroupApp();
    });

    function initializeGroupApp() {
        cacheElements();
        initializeState();
        setupEventListeners();
        setupObservers();
        initializeRealtime();
        setupNetworkMonitoring();
        initializeGroupFeatures();
    }

    function cacheElements() {
        const selectors = {
            // Core containers
            chatBox: '#chat-box',
            messagesContainer: '#messages-container',
            messageForm: '#chat-form',
            
            // Form elements
            sendButton: '#send-btn',
            messageInput: '#message-input',
            replyInput: '#reply-to-id',
            replyPreview: '#reply-preview',
            cancelReply: '#cancel-reply',
            
            // UI Components
            emojiPickerWrap: '#emoji-picker-wrap',
            emojiPicker: '#emoji-picker',
            emojiButton: '#emoji-btn',
            progressBar: '#upload-progress',
            notificationSound: '#notification-sound',
            networkBanner: '#net-banner',
            networkRetry: '#net-retry-in',
            
            // Group specific
            groupDetails: '#groupDetails',
            editGroupModal: '#editGroupModal',
            editGroupForm: '#edit-group-form',
            groupAvatarInput: '#groupAvatarInput',
            groupAvatarPreview: '#groupAvatarPreview',
            copyInviteBtn: '#copy-invite',
            
            // Modals
            forwardModal: '#forward-modal',
            imageModal: '#image-modal',
            
            // Forward modal elements
            forwardSourceId: '#forward-source-id',
            forwardSearch: '#forward-search',
            forwardRecentList: '#forward-recent-list',
            forwardContactsList: '#forward-contacts-list',
            forwardGroupsList: '#forward-groups-list',
            forwardCount: '#forward-count',
            forwardConfirm: '#forward-confirm',
            
            // Data sources
            forwardDatasets: '#forward-datasets',
            messagesLoader: '#messages-loader',
            topSentinel: '#top-sentinel'
        };

        Object.keys(selectors).forEach(key => {
            elements[key] = document.querySelector(selectors[key]);
        });

        // Cache Bootstrap instances
        if (window.bootstrap) {
            elements.forwardModalInstance = elements.forwardModal ? 
                new bootstrap.Modal(elements.forwardModal) : null;
            elements.imageModalInstance = elements.imageModal ? 
                new bootstrap.Modal(elements.imageModal) : null;
            elements.groupDetailsInstance = elements.groupDetails ? 
                new bootstrap.Offcanvas(elements.groupDetails) : null;
            elements.editGroupModalInstance = elements.editGroupModal ? 
                new bootstrap.Modal(elements.editGroupModal) : null;
        }
    }

    function initializeState() {
        state.groupId = Number(@json($group->id));
        state.groupName = @json($group->name ?? 'Group');
        state.currentUserId = Number(@json(auth()->id()));
        state.csrfToken = @json(csrf_token());
        state.storageUrl = @json(Storage::url(''));
        state.userRole = @json($group->members->firstWhere('id', auth()->id())?->pivot?->role ?? 'member');
        state.isOwner = @json($group->owner_id === auth()->id());
        
        // API endpoints
        state.endpoints = {
            history: @json(route('groups.messages.history', $group)),
            send: @json(route('groups.messages.store', $group)),
            typing: @json(route('groups.typing', $group)),
            ping: @json(route('ping')),
            forward: @json(route('groups.forward.targets', $group))
        };

        // Forward modal state
        state.selectedTargets = new Set();
        state.forwardData = loadForwardData();
    }

    function loadForwardData() {
        if (!elements.forwardDatasets) return { conversations: [], groups: [] };
        
        try {
            return JSON.parse(elements.forwardDatasets.textContent || '{}');
        } catch (error) {
            console.error('Failed to parse forward data:', error);
            return { conversations: [], groups: [] };
        }
    }

    // ==== Group-Specific Features ====
    function initializeGroupFeatures() {
        setupGroupAvatarUpload();
        setupGroupFormValidation();
        setupInviteSystem();
        setupMemberManagement();
    }

    function setupGroupAvatarUpload() {
        const avatarInput = elements.groupAvatarInput;
        const avatarPreview = elements.groupAvatarPreview;

        if (avatarInput && avatarPreview) {
            avatarInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (!validateImageFile(file)) {
                        this.value = '';
                        showToast('Please select a valid image file (PNG, JPG, WebP) under 2MB', 'error');
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        avatarPreview.src = e.target.result;
                        avatarPreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Drag and drop for avatar
            const uploadArea = avatarPreview.parentElement;
            if (uploadArea) {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, preventDefaults, false);
                });

                ['dragenter', 'dragover'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, highlight, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, unhighlight, false);
                });

                uploadArea.addEventListener('drop', handleDrop, false);
            }

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function highlight() {
                uploadArea.classList.add('dragover');
            }

            function unhighlight() {
                uploadArea.classList.remove('dragover');
            }

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                avatarInput.files = files;
                avatarInput.dispatchEvent(new Event('change'));
            }
        }
    }

    function validateImageFile(file) {
        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
        const maxSize = 2 * 1024 * 1024; // 2MB

        if (!validTypes.includes(file.type)) {
            return false;
        }

        if (file.size > maxSize) {
            return false;
        }

        return true;
    }

    function setupGroupFormValidation() {
        const form = elements.editGroupForm;
        if (!form) return;

        const descriptionInput = form.querySelector('textarea[name="description"]');
        const nameInput = form.querySelector('input[name="name"]');
        const counter = form.querySelector('#description-counter');

        // Character counter for description
        if (descriptionInput && counter) {
            updateCounter(descriptionInput.value.length);
            descriptionInput.addEventListener('input', function() {
                updateCounter(this.value.length);
            });

            function updateCounter(length) {
                counter.textContent = length;
                if (length > 450) {
                    counter.classList.add('text-warning');
                } else {
                    counter.classList.remove('text-warning');
                }
            }
        }

        // Form submission
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('#edit-group-save');
            const spinner = submitBtn.querySelector('.spinner-border');
            
            submitBtn.disabled = true;
            spinner.style.display = 'inline-block';

            try {
                const formData = new FormData(this);
                const response = await apiCall(this.action, {
                    method: 'POST',
                    body: formData
                });

                showToast('Group updated successfully', 'success');
                elements.editGroupModalInstance?.hide();
                
                // Update UI with new data
                setTimeout(() => window.location.reload(), 1000);
                
            } catch (error) {
                handleGroupUpdateError(error);
            } finally {
                submitBtn.disabled = false;
                spinner.style.display = 'none';
            }
        });
    }

    function handleGroupUpdateError(error) {
        console.error('Group update error:', error);
        
        if (error.status === 422) {
            showToast('Please check your input and try again', 'error');
        } else if (error.status === 403) {
            showToast('You do not have permission to edit this group', 'error');
        } else {
            showToast('Failed to update group. Please try again.', 'error');
        }
    }

    function setupInviteSystem() {
        const copyBtn = elements.copyInviteBtn;
        if (!copyBtn) return;

        copyBtn.addEventListener('click', async function() {
            const inviteUrl = @json(route('groups.show', $group));
            
            try {
                await navigator.clipboard.writeText(inviteUrl);
                
                // Visual feedback
                const originalHtml = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check2 me-2"></i>Copied!';
                this.classList.add('copied');
                
                setTimeout(() => {
                    this.innerHTML = originalHtml;
                    this.classList.remove('copied');
                }, 2000);
                
                showToast('Invite link copied to clipboard', 'success');
            } catch (err) {
                // Fallback for browsers that don't support clipboard API
                const textArea = document.createElement('textarea');
                textArea.value = inviteUrl;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                showToast('Invite link copied to clipboard', 'success');
            }
        });
    }

    function setupMemberManagement() {
        // Delegated event handling for member actions
        document.addEventListener('click', async function(e) {
            const promoteBtn = e.target.closest('.promote-member-btn');
            const removeBtn = e.target.closest('.remove-member-btn');
            
            if (promoteBtn) {
                e.preventDefault();
                await handleMemberPromotion(promoteBtn);
            }
            
            if (removeBtn) {
                e.preventDefault();
                await handleMemberRemoval(removeBtn);
            }
        });
    }

    async function handleMemberPromotion(button) {
        const memberId = button.dataset.memberId;
        const memberName = button.dataset.memberName;
        
        if (!confirm(`Promote ${memberName} to admin?`)) return;
        
        try {
            const response = await apiCall(`/groups/${state.groupId}/members/${memberId}/promote`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            showToast(`${memberName} promoted to admin`, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            handleMemberActionError(error, 'promote');
        }
    }

    async function handleMemberRemoval(button) {
        const memberId = button.dataset.memberId;
        const memberName = button.dataset.memberName;
        
        if (!confirm(`Remove ${memberName} from the group?`)) return;
        
        try {
            const response = await apiCall(`/groups/${state.groupId}/members/${memberId}`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' }
            });
            
            showToast(`${memberName} removed from group`, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            handleMemberActionError(error, 'remove');
        }
    }

    function handleMemberActionError(error, action) {
        console.error(`Member ${action} error:`, error);
        
        if (error.status === 403) {
            showToast(`You don't have permission to ${action} members`, 'error');
        } else if (error.status === 404) {
            showToast('Member not found', 'error');
        } else {
            showToast(`Failed to ${action} member. Please try again.`, 'error');
        }
    }

    // ==== Event Listeners Setup ====
    function setupEventListeners() {
        setupFormListeners();
        setupMessageListeners();
        setupUIListeners();
        setupForwardModalListeners();
    }

    function setupFormListeners() {
        // Message form submission
        if (elements.messageForm) {
            elements.messageForm.addEventListener('submit', handleMessageSubmit);
        }

        // File upload handlers
        const photoUpload = document.getElementById('photo-upload');
        const docUpload = document.getElementById('doc-upload');
        
        photoUpload?.addEventListener('change', handleFileSelection);
        docUpload?.addEventListener('change', handleFileSelection);

        // Reply cancellation
        elements.cancelReply?.addEventListener('click', cancelReply);
    }

    function setupMessageListeners() {
        // Typing indicator
        elements.messageInput?.addEventListener('input', handleGroupTyping);
        
        // Input blur to stop typing indicator
        elements.messageInput?.addEventListener('blur', () => {
            notifyTypingStatus(false);
        });
    }

    function setupUIListeners() {
        // Emoji picker
        elements.emojiButton?.addEventListener('click', toggleEmojiPicker);
        document.addEventListener('click', handleOutsideClick);

        // Mobile navigation
        document.getElementById('back-to-conversations')?.addEventListener('click', handleBackNavigation);
        
        // Mute group button
        document.getElementById('mute-group-btn')?.addEventListener('click', handleMuteGroup);
    }

    // ==== Core Message Handling ====
    async function handleMessageSubmit(event) {
        event.preventDefault();
        
        if (!elements.messageForm || state.isLoading) return;

        const formData = new FormData(elements.messageForm);
        
        // Validate form data
        if (!await validateMessageForm(formData)) return;

        setLoadingState(true);
        
        try {
            const response = await apiCall(elements.messageForm.action, {
                method: 'POST',
                body: formData
            });

            if (response?.message) {
                handleSendSuccess(response.message);
            } else {
                throw new Error(response?.message || 'Failed to send message');
            }
        } catch (error) {
            handleSendError(error);
        } finally {
            setLoadingState(false);
        }
    }

    function handleSendSuccess(message) {
        appendMessage(message, true);
        resetMessageForm();
        scrollToBottom({ smooth: true });
        notifyTypingStatus(false); // Stop typing indicator
        showToast('Message sent', 'success');
    }

    function handleSendError(error) {
        console.error('Send message error:', error);
        
        if (error.status === 422) {
            showToast('Validation error: Please check your message', 'error');
        } else if (error.status === 413) {
            showToast('File too large. Please choose a smaller file.', 'error');
        } else if (!navigator.onLine) {
            showOfflineBanner();
        } else {
            showToast('Failed to send message. Please try again.', 'error');
        }
    }

    function resetMessageForm() {
        if (!elements.messageForm) return;
        
        elements.messageForm.reset();
        hideReplyPreview();
    }

    function hideReplyPreview() {
        if (elements.replyPreview) {
            elements.replyPreview.style.display = 'none';
        }
        if (elements.replyInput) {
            elements.replyInput.value = '';
        }
    }

    // ==== Group Typing Indicators ====
    function handleGroupTyping() {
        const isTyping = elements.messageInput?.value.trim().length > 0;
        updateGroupTypingIndicator(isTyping);
        notifyTypingStatus(isTyping);
    }

    function updateGroupTypingIndicator(isTyping) {
        const indicator = document.getElementById('typing-indicator');
        if (indicator) {
            indicator.style.display = isTyping ? 'block' : 'none';
        }
    }

    function notifyTypingStatus(isTyping) {
        if (!state.groupId) return;

        clearTimeout(state.typingTimer);
        state.typingTimer = setTimeout(() => {
            apiCall(state.endpoints.typing, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    is_typing: isTyping
                })
            }).catch(() => {}); // Silent fail for typing indicators
        }, CONFIG.TYPING_DELAY);
    }

    // ==== Message Display & Management ====
    function appendMessage(messageData, isOwn = false) {
        if (!elements.messagesContainer) return;

        const messageElement = createMessageElement(messageData, isOwn);
        elements.messagesContainer.appendChild(messageElement);
        addMessageEventListeners(messageElement);
        lazyLoadImages();
        
        // Observe for infinite scroll
        if (state.observer) {
            state.observer.observe(messageElement);
        }
    }

    function createMessageElement(message, isOwn = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message mb-3 d-flex ${isOwn ? 'justify-content-end' : 'justify-content-start'}`;
        messageDiv.dataset.messageId = message.id;
        messageDiv.dataset.fromMe = isOwn ? '1' : '0';
        messageDiv.dataset.senderRole = message.sender?.pivot?.role || 'member';

        const bubbleContent = generateBubbleContent(message, isOwn);
        const actionsContent = generateMessageActions(message, isOwn);

        messageDiv.innerHTML = `
            <div class="message-bubble ${isOwn ? 'sent' : 'received'}">
                ${bubbleContent}
            </div>
            ${actionsContent}
        `;

        return messageDiv;
    }

    function generateBubbleContent(message, isOwn) {
        const senderName = !isOwn && message.sender ? 
            `<small class="sender-name ${getSenderRoleClass(message.sender)}">${escapeHtml(message.sender.name || message.sender.phone || '')}</small>` : '';

        const replyPreview = message.reply_to_id ? generateReplyPreview(message.replyTo) : '';
        const forwardHeader = message.forwarded_from_id ? generateForwardHeader() : '';
        const messageText = generateMessageText(message);
        const attachments = generateAttachments(message.attachments || []);
        const footer = generateMessageFooter(message, isOwn);
        const reactions = generateReactions(message.reactions || []);

        return `
            ${senderName}
            <div class="message-content">
                ${replyPreview}
                ${forwardHeader}
                <div class="message-text">${messageText}</div>
                ${attachments}
            </div>
            ${footer}
            ${reactions}
        `;
    }

    function getSenderRoleClass(sender) {
        const role = sender.pivot?.role;
        if (role === 'owner') return 'owner';
        if (role === 'admin') return 'admin';
        return '';
    }

    function generateReplyPreview(replyTo) {
        const repliedText = replyTo?.body || '';
        const previewText = repliedText.length > 80 ? repliedText.slice(0, 80) + '…' : repliedText;
        
        return `
            <div class="reply-preview">
                <small><i class="bi bi-reply-fill me-1"></i>${escapeHtml(previewText)}</small>
            </div>
        `;
    }

    function generateForwardHeader() {
        return `
            <div class="forwarded-header mb-1">
                <small class="muted"><i class="bi bi-forward-fill me-1"></i>Forwarded</small>
            </div>
        `;
    }

    function generateMessageText(message) {
        const rawText = String(message.body ?? '');
        const escapedText = escapeHtml(rawText);
        
        // Convert URLs to clickable links
        return escapedText.replace(
            /(https?:\/\/[^\s]+)/g, 
            '<a class="linkify" target="_blank" rel="noopener noreferrer" href="$1">$1</a>'
        );
    }

    function generateAttachments(attachments) {
        if (!attachments.length) return '';

        return attachments.map(file => {
            const url = file.url || (state.storageUrl + file.file_path);
            const ext = (file.file_path?.split('.').pop() || '').toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
            const isVideo = ['mp4', 'mov', 'avi', 'webm'].includes(ext);
            const fileName = file.original_name || basename(file.file_path);

            if (isImage) {
                return `
                    <div class="mt-2">
                        <img data-src="${url}" alt="Shared image" class="img-fluid rounded media-img" 
                             loading="lazy" data-bs-toggle="modal" data-bs-target="#imageModal" 
                             data-image-src="${url}" width="220" height="220">
                    </div>
                `;
            } else if (isVideo) {
                return `
                    <div class="mt-2">
                        <video controls class="img-fluid rounded media-video" preload="metadata"
                               data-src="${url}" style="max-width: 220px;">
                            <source src="${url}" type="video/${ext}">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                `;
            } else {
                return `
                    <div class="mt-2">
                        <a href="${url}" target="_blank" class="d-inline-flex align-items-center doc-link" 
                           rel="noopener noreferrer" download="${fileName}">
                            <i class="bi bi-file-earmark me-1"></i> 
                            ${escapeHtml(fileName)}
                        </a>
                    </div>
                `;
            }
        }).join('');
    }

    function generateMessageFooter(message, isOwn) {
        const time = new Date(message.created_at || Date.now()).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });

        return `
            <div class="message-footer d-flex justify-content-between align-items-center mt-1">
                <small class="muted">${time}</small>
            </div>
        `;
    }

    function generateReactions(reactions) {
        if (!reactions.length) return '';

        const reactionHTML = reactions.map(r => {
            const emoji = r.emoji || r.reaction || '👍';
            const user = r.user?.name || 'User';
            const isOwnReaction = r.user_id === state.currentUserId;
            
            return `
                <span class="badge bg-reaction rounded-pill me-1 ${isOwnReaction ? 'own-reaction' : ''}" 
                      title="${escapeHtml(user)}">
                    ${escapeHtml(emoji)}
                </span>
            `;
        }).join('');

        return `<div class="reactions-container mt-1">${reactionHTML}</div>`;
    }

    function generateMessageActions(message, isOwn) {
        const canEdit = isOwn || state.isOwner || state.userRole === 'admin';
        const canDelete = isOwn || state.isOwner || state.userRole === 'admin';
        const reactUrl = `/groups/${state.groupId}/messages/${message.id}/reactions`;

        return `
            <div class="message-actions dropdown">
                <button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown" 
                        aria-expanded="false" aria-label="Message actions">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <button class="dropdown-item d-flex align-items-center gap-2 reply-btn" 
                                data-message-id="${message.id}">
                            <i class="bi bi-reply"></i>Reply
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item d-flex align-items-center gap-2 forward-btn" 
                                data-message-id="${message.id}">
                            <i class="bi bi-forward"></i>Forward
                        </button>
                    </li>
                    ${canEdit ? `
                        <li>
                            <button class="dropdown-item d-flex align-items-center gap-2 edit-btn"
                                    data-message-id="${message.id}"
                                    data-body="${escapeHtml(message.body || '')}"
                                    data-edit-url="/groups/${state.groupId}/messages/${message.id}">
                                <i class="bi bi-pencil"></i>Edit
                            </button>
                        </li>
                    ` : ''}
                    ${canDelete ? `
                        <li>
                            <button class="dropdown-item d-flex align-items-center gap-2 text-danger delete-btn" 
                                    data-message-id="${message.id}">
                                <i class="bi bi-trash"></i>Delete
                            </button>
                        </li>
                    ` : ''}
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <div class="d-flex px-3 py-1 reaction-buttons">
                            <button class="btn btn-sm reaction-btn" data-reaction="👍" data-react-url="${reactUrl}">👍</button>
                            <button class="btn btn-sm reaction-btn ms-1" data-reaction="❤️" data-react-url="${reactUrl}">❤️</button>
                            <button class="btn btn-sm reaction-btn ms-1" data-reaction="😂" data-react-url="${reactUrl}">😂</button>
                            <button class="btn btn-sm reaction-btn ms-1" data-reaction="😮" data-react-url="${reactUrl}">😮</button>
                        </div>
                    </li>
                </ul>
            </div>
        `;
    }

    // ==== Message Event Listeners ====
    function addMessageEventListeners(messageElement) {
        if (!messageElement) return;

        // These are handled by global delegation
        // The element is created for observation purposes
    }

    // Global delegated event handlers for messages
    document.addEventListener('click', async function(e) {
        // Reply
        const replyBtn = e.target.closest('.reply-btn');
        if (replyBtn) {
            const messageId = replyBtn.dataset.messageId;
            const messageEl = document.querySelector(`.message[data-message-id="${messageId}"]`);
            const messageText = messageEl?.querySelector('.message-text')?.textContent || '';
            
            if (elements.replyInput) {
                elements.replyInput.value = messageId;
            }
            
            showReplyPreview(messageText);
            elements.messageInput?.focus();
            return;
        }

        // Delete
        const deleteBtn = e.target.closest('.delete-btn');
        if (deleteBtn) {
            e.preventDefault();
            const messageId = deleteBtn.dataset.messageId;
            
            if (!confirm('Are you sure you want to delete this message?')) return;

            try {
                await apiCall(`/groups/${state.groupId}/messages/${messageId}`, {
                    method: 'DELETE'
                });
                
                document.querySelector(`.message[data-message-id="${messageId}"]`)?.remove();
                showToast('Message deleted', 'success');
            } catch (error) {
                handleMessageActionError(error, 'delete');
            }
            return;
        }

        // Edit
        const editBtn = e.target.closest('.edit-btn');
        if (editBtn) {
            e.preventDefault();
            const messageId = editBtn.dataset.messageId;
            const currentBody = editBtn.dataset.body;
            const editUrl = editBtn.dataset.editUrl;
            
            const newBody = prompt('Edit your message:', currentBody);
            if (newBody === null || newBody === currentBody) return;

            try {
                const response = await apiCall(editUrl, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ body: newBody })
                });
                
                const messageElement = document.querySelector(`.message[data-message-id="${messageId}"]`);
                const messageText = messageElement?.querySelector('.message-text');
                if (messageText) {
                    messageText.innerHTML = escapeHtml(newBody).replace(
                        /(https?:\/\/[^\s]+)/g, 
                        '<a class="linkify" target="_blank" rel="noopener noreferrer" href="$1">$1</a>'
                    );
                }
                
                showToast('Message updated', 'success');
            } catch (error) {
                handleMessageActionError(error, 'edit');
            }
            return;
        }

        // Reactions
        const reactionBtn = e.target.closest('.reaction-btn');
        if (reactionBtn) {
            e.preventDefault();
            const messageId = reactionBtn.closest('.message')?.dataset.messageId;
            const reaction = reactionBtn.dataset.reaction;
            const reactUrl = reactionBtn.dataset.reactUrl;
            
            if (!messageId || !reaction || !reactUrl) return;

            optimisticAddReaction(messageId, reaction);
            try {
                await apiCall(reactUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ emoji: reaction })
                });
            } catch (error) {
                showToast('Failed to add reaction', 'error');
            }
            return;
        }

        // Forward
        const forwardBtn = e.target.closest('.forward-btn');
        if (forwardBtn) {
            e.preventDefault();
            const messageId = forwardBtn.dataset.messageId;
            if (elements.forwardSourceId) {
                elements.forwardSourceId.value = messageId;
            }
            elements.forwardModalInstance?.show();
            return;
        }
    });

    function handleMessageActionError(error, action) {
        console.error(`Message ${action} error:`, error);
        
        if (error.status === 403) {
            showToast(`You don't have permission to ${action} this message`, 'error');
        } else if (error.status === 404) {
            showToast('Message not found', 'error');
        } else {
            showToast(`Failed to ${action} message. Please try again.`, 'error');
        }
    }

    function showReplyPreview(text) {
        if (!elements.replyPreview) return;
        
        const previewContent = elements.replyPreview.querySelector('.reply-preview-content');
        if (previewContent) {
            previewContent.textContent = text.length > 60 ? text.slice(0, 60) + '…' : text;
        }
        
        elements.replyPreview.style.display = 'block';
    }

    function cancelReply() {
        hideReplyPreview();
    }

    function optimisticAddReaction(messageId, emoji) {
        const wrapper = document.querySelector(`.message[data-message-id="${messageId}"]`);
        if (!wrapper) return;

        let container = wrapper.querySelector('.reactions-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'reactions-container mt-1';
            wrapper.querySelector('.message-bubble')?.appendChild(container);
        }

        // Check if user already reacted with this emoji
        const existingReaction = container.querySelector(`.badge[title*="You"]`);
        if (existingReaction) {
            existingReaction.remove();
        }

        const badge = document.createElement('span');
        badge.className = 'badge bg-reaction rounded-pill me-1 own-reaction';
        badge.title = 'You';
        badge.textContent = emoji;
        container.appendChild(badge);
    }

    // ==== File Upload Handling ====
    function handleFileSelection(event) {
        const files = Array.from(event.target.files);
        
        if (!validateFiles(files)) {
            event.target.value = '';
            return;
        }

        handleFileUpload(files);
        event.target.value = ''; // Reset input
    }

    function validateFiles(files) {
        for (const file of files) {
            if (file.size > CONFIG.UPLOAD_MAX_SIZE) {
                showToast(`File "${file.name}" is too large. Maximum size is 10MB.`, 'error');
                return false;
            }
        }
        return true;
    }

    function handleFileUpload(files) {
        if (!elements.messageForm) return;

        const formData = new FormData(elements.messageForm);
        formData.delete('attachments[]');
        
        files.forEach(file => formData.append('attachments[]', file));

        const xhr = new XMLHttpRequest();
        setupFileUploadXHR(xhr, formData);
        xhr.send(formData);
    }

    function setupFileUploadXHR(xhr, formData) {
        xhr.open('POST', elements.messageForm.action, true);
        xhr.withCredentials = true;
        xhr.setRequestHeader('X-CSRF-TOKEN', state.csrfToken);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable && elements.progressBar) {
                const percent = Math.round((e.loaded / e.total) * 100);
                elements.progressBar.style.display = 'block';
                elements.progressBar.querySelector('.progress-bar').style.width = percent + '%';
            }
        };

        xhr.onload = () => {
            if (elements.progressBar) {
                elements.progressBar.style.display = 'none';
            }

            try {
                const response = JSON.parse(xhr.responseText || '{}');
                
                if (xhr.status >= 200 && xhr.status < 300) {
                    if (response.message) {
                        handleSendSuccess(response.message);
                    }
                } else {
                    throw new Error(response.message || 'Upload failed');
                }
            } catch (error) {
                handleSendError(error);
            }
        };

        xhr.onerror = () => {
            if (elements.progressBar) {
                elements.progressBar.style.display = 'none';
            }
            showOfflineBanner();
        };
    }

    // ==== Scroll & Load More ====
    function setupObservers() {
        setupIntersectionObserver();
        setupResizeObserver();
    }

    function setupIntersectionObserver() {
        if (!elements.chatBox || !elements.topSentinel) return;

        state.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && entry.target === elements.topSentinel) {
                    loadMoreMessages();
                }
            });
        }, { 
            root: elements.chatBox,
            rootMargin: '200px 0px 0px 0px',
            threshold: 0.1
        });

        state.observer.observe(elements.topSentinel);
    }

    function setupResizeObserver() {
        state.resizeObserver = new ResizeObserver(() => {
            scrollToBottom();
        });
        
        if (elements.chatBox) {
            state.resizeObserver.observe(elements.chatBox);
        }
    }

    function scrollToBottom({ smooth = false, force = false } = {}) {
        if (!elements.chatBox) return;

        const scrollHeight = elements.chatBox.scrollHeight;
        const clientHeight = elements.chatBox.clientHeight;
        const scrollTop = elements.chatBox.scrollTop;
        const distanceToBottom = scrollHeight - (clientHeight + scrollTop);

        if (force || distanceToBottom < CONFIG.SCROLL_THRESHOLD) {
            if (smooth) {
                elements.chatBox.scrollTo({
                    top: scrollHeight,
                    behavior: 'smooth'
                });
            } else {
                elements.chatBox.scrollTop = scrollHeight;
            }
        }
    }

    async function loadMoreMessages() {
        if (state.isLoading || !state.hasMore) return;

        state.isLoading = true;
        showLoader(true);

        try {
            const nextPage = state.currentPage + 1;
            const response = await apiCall(`${state.endpoints.history}?page=${nextPage}`);
            
            const messages = response?.messages?.data || [];
            
            if (messages.length === 0) {
                state.hasMore = false;
                return;
            }

            const fragment = document.createDocumentFragment();
            messages.slice().reverse().forEach(message => {
                const element = createMessageElement(message, Number(message.sender_id) === state.currentUserId);
                fragment.prepend(element);
            });

            // Preserve scroll position
            const oldScrollHeight = elements.chatBox.scrollHeight;
            elements.messagesContainer.prepend(fragment);
            const newScrollHeight = elements.chatBox.scrollHeight;
            elements.chatBox.scrollTop += (newScrollHeight - oldScrollHeight);

            state.currentPage = nextPage;
            state.hasMore = !!response?.messages?.next_page_url;

        } catch (error) {
            console.error('Load more messages error:', error);
        } finally {
            state.isLoading = false;
            showLoader(false);
            lazyLoadImages();
        }
    }

    function showLoader(show) {
        if (elements.messagesLoader) {
            elements.messagesLoader.style.display = show ? 'block' : 'none';
        }
    }

    // ==== Real-time Communication ====
    function initializeRealtime() {
        whenEchoReady(setupEchoListeners);
    }

    function whenEchoReady(cb) {
        if (window.Echo && typeof window.Echo.private === 'function') {
            cb();
        } else {
            document.addEventListener('echo:ready', () => cb(), { once: true });
            let tries = 0;
            const interval = setInterval(() => {
                if (window.Echo && typeof window.Echo.private === 'function') {
                    clearInterval(interval);
                    cb();
                } else if (++tries > 40) {
                    clearInterval(interval);
                    console.warn('Echo still not ready after waiting.');
                }
            }, 50);
        }
    }

    function setupEchoListeners() {
        if (!window.Echo || !state.groupId) {
            console.warn('Echo not available for real-time features');
            return;
        }

        state.echo = window.Echo;

        // Private channel for group messages
        const groupChannel = state.echo.private(`group.${state.groupId}`);

        groupChannel
            .listen('GroupMessageSent', (event) => handleIncomingMessage(event))
            .listen('GroupMessageEdited', (event) => handleMessageEdited(event))
            .listen('GroupMessageDeleted', (event) => handleMessageDeleted(event))
            .listen('GroupTyping', (event) => handleGroupTypingEvent(event))
            .listen('GroupMemberJoined', (event) => handleMemberJoined(event))
            .listen('GroupMemberLeft', (event) => handleMemberLeft(event))
            .listen('GroupUpdated', (event) => handleGroupUpdated(event));

        // Presence channel for online members
        if (typeof state.echo.join === 'function') {
            const presenceChannel = state.echo.join(`group.${state.groupId}.presence`);
            
            presenceChannel
                .here((users) => updateOnlineMembers(users))
                .joining((user) => addOnlineMember(user))
                .leaving((user) => removeOnlineMember(user))
                .error((error) => console.error('Presence channel error:', error));

            // Setup presence interval
            state.presenceInterval = setInterval(() => {
                // Refresh presence data
            }, CONFIG.PRESENCE_TIMEOUT);
        }

        console.log(`[Group ${state.groupId}] Echo listeners attached`);
    }

    function handleIncomingMessage(event) {
        if (!event?.message || Number(event.message.sender_id) === state.currentUserId) return;

        appendMessage(event.message, false);
        playNotificationSound();
        scrollToBottom({ smooth: true });
    }

    function handleMessageEdited(event) {
        const messageId = event.message?.id;
        if (!messageId) return;

        const messageElement = document.querySelector(`.message[data-message-id="${messageId}"]`);
        if (!messageElement) return;

        const messageText = messageElement.querySelector('.message-text');
        if (messageText && event.message.body) {
            messageText.innerHTML = escapeHtml(event.message.body).replace(
                /(https?:\/\/[^\s]+)/g, 
                '<a class="linkify" target="_blank" rel="noopener noreferrer" href="$1">$1</a>'
            );
        }

        // Update reactions if provided
        if (event.message.reactions) {
            const container = messageElement.querySelector('.reactions-container');
            if (container) {
                container.innerHTML = generateReactions(event.message.reactions);
            }
        }
    }

    function handleMessageDeleted(event) {
        const messageElement = document.querySelector(`.message[data-message-id="${event.message_id}"]`);
        messageElement?.remove();
    }

    function handleGroupTypingEvent(event) {
        if (Number(event.user?.id) === state.currentUserId) return;

        const indicator = document.getElementById('typing-indicator');
        if (!indicator) return;

        if (event.is_typing) {
            state.typingUsers.add(event.user.id);
            updateGroupTypingDisplay();
        } else {
            state.typingUsers.delete(event.user.id);
            updateGroupTypingDisplay();
        }
    }

    function updateGroupTypingDisplay() {
        const indicator = document.getElementById('typing-indicator');
        if (!indicator) return;

        if (state.typingUsers.size > 0) {
            const userCount = state.typingUsers.size;
            const text = userCount === 1 ? 'is typing...' : `${userCount} people are typing...`;
            indicator.textContent = text;
            indicator.style.display = 'block';

            clearTimeout(state.typingHideTimer);
            state.typingHideTimer = setTimeout(() => {
                state.typingUsers.clear();
                indicator.style.display = 'none';
            }, CONFIG.TYPING_TIMEOUT);
        } else {
            indicator.style.display = 'none';
        }
    }

    function handleMemberJoined(event) {
        showToast(`${event.user.name} joined the group`, 'info');
        // In a real app, you might want to refresh the member list
    }

    function handleMemberLeft(event) {
        showToast(`${event.user.name} left the group`, 'info');
        // In a real app, you might want to refresh the member list
    }

    function handleGroupUpdated(event) {
        showToast('Group information updated', 'info');
        // Refresh group details if needed
        if (event.group?.id === state.groupId) {
            // Update group name in header, etc.
            const groupNameElement = document.querySelector('.chat-header-name');
            if (groupNameElement && event.group.name) {
                groupNameElement.textContent = event.group.name;
            }
        }
    }

    function updateOnlineMembers(users) {
        const onlineList = document.getElementById('online-list');
        if (!onlineList) return;

        onlineList.innerHTML = '';
        users.forEach(user => addOnlineMember(user));
    }

    function addOnlineMember(user) {
        if (Number(user.id) === state.currentUserId) return;

        const onlineList = document.getElementById('online-list');
        if (!onlineList) return;

        const memberElement = document.createElement('div');
        memberElement.className = 'online-member';
        memberElement.title = user.name || 'Online';
        memberElement.innerHTML = `
            <div class="member-avatar online">
                ${user.avatar ? 
                    `<img src="${user.avatar}" alt="${user.name}" class="rounded-circle" width="30" height="30">` :
                    `<div class="rounded-circle bg-avatar text-white d-flex align-items-center justify-content-center" style="width:30px;height:30px;">
                        ${(user.name?.charAt(0) || '?').toUpperCase()}
                    </div>`
                }
            </div>
        `;

        onlineList.appendChild(memberElement);
    }

    function removeOnlineMember(user) {
        // This would be more complex in a real app, tracking specific users
        // For simplicity, we'll just refresh the list periodically
    }

    // ==== Forward Modal Functionality ====
    function setupForwardModalListeners() {
        if (!elements.forwardModal) return;

        // Search functionality
        elements.forwardSearch?.addEventListener('input', debounce(handleForwardSearch, CONFIG.DEBOUNCE_DELAY));

        // Confirm forward
        elements.forwardConfirm?.addEventListener('click', handleForwardConfirm);

        // Modal show/hide events
        elements.forwardModal.addEventListener('show.bs.modal', resetForwardModal);
        elements.forwardModal.addEventListener('hidden.bs.modal', cleanupForwardModal);

        // Tab switching
        const forwardTabs = elements.forwardModal.querySelectorAll('[data-bs-toggle="tab"]');
        forwardTabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', handleForwardTabChange);
        });
    }

    function resetForwardModal() {
        state.selectedTargets.clear();
        updateForwardCount();
        renderForwardLists();
    }

    function cleanupForwardModal() {
        if (elements.forwardSourceId) {
            elements.forwardSourceId.value = '';
        }
    }

    function handleForwardSearch(event) {
        const query = event.target.value.toLowerCase().trim();
        renderForwardLists(query);
    }

    function handleForwardTabChange(event) {
        // Re-render lists when tab changes
        renderForwardLists();
    }

    function renderForwardLists(query = '') {
        renderForwardSection('recent', filterForwardData(state.forwardData.conversations, query));
        renderForwardSection('contacts', filterForwardData(state.forwardData.conversations, query));
        renderForwardSection('groups', filterForwardData(state.forwardData.groups, query));
    }

    function filterForwardData(items, query) {
        if (!query) return items;
        return items.filter(item => 
            (item.name || '').toLowerCase().includes(query)
        );
    }

    function renderForwardSection(type, items) {
        const container = elements[`forward${type.charAt(0).toUpperCase() + type.slice(1)}List`];
        if (!container) return;

        container.innerHTML = '';

        if (!items.length) {
            container.innerHTML = '<div class="text-center text-muted py-3">No results found</div>';
            return;
        }

        items.forEach(item => {
            const itemElement = createForwardListItem(item, type);
            container.appendChild(itemElement);
        });
    }

    function createForwardListItem(item, type) {
        const li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action d-flex align-items-center gap-3 py-3';
        
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'form-check-input flex-shrink-0';
        checkbox.checked = state.selectedTargets.has(`${type}-${item.id}`);
        
        checkbox.addEventListener('change', () => {
            const targetKey = `${type}-${item.id}`;
            if (checkbox.checked) {
                state.selectedTargets.add(targetKey);
            } else {
                state.selectedTargets.delete(targetKey);
            }
            updateForwardCount();
        });

        const avatar = createAvatarElement(item);
        const name = document.createElement('div');
        name.className = 'flex-grow-1';
        name.textContent = item.name || 'Unknown';

        li.appendChild(checkbox);
        li.appendChild(avatar);
        li.appendChild(name);

        // Click anywhere on item to toggle selection
        li.addEventListener('click', (e) => {
            if (e.target !== checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
            }
        });

        return li;
    }

    function createAvatarElement(item) {
        if (item.avatar) {
            const img = document.createElement('img');
            img.className = 'list-avatar flex-shrink-0';
            img.alt = '';
            img.src = item.avatar;
            img.onerror = () => {
                img.replaceWith(createInitialAvatar(item.name));
            };
            return img;
        } else {
            return createInitialAvatar(item.name);
        }
    }

    function createInitialAvatar(name) {
        const div = document.createElement('div');
        div.className = 'list-avatar d-flex align-items-center justify-content-center bg-avatar text-white flex-shrink-0';
        div.textContent = (name?.charAt(0) || '?').toUpperCase();
        return div;
    }

    function updateForwardCount() {
        const count = state.selectedTargets.size;
        if (elements.forwardCount) {
            elements.forwardCount.textContent = count;
        }
        if (elements.forwardConfirm) {
            elements.forwardConfirm.disabled = count === 0;
            elements.forwardConfirm.innerHTML = `Forward to <span id="forward-count">${count}</span>`;
        }
    }

    async function handleForwardConfirm() {
        const messageId = elements.forwardSourceId?.value;
        
        if (!messageId) {
            showToast('No message selected to forward', 'error');
            return;
        }

        if (state.selectedTargets.size === 0) {
            showToast('Please select at least one recipient', 'error');
            return;
        }

        const targets = Array.from(state.selectedTargets).map(targetKey => {
            const [type, id] = targetKey.split('-');
            return { type: type === 'groups' ? 'group' : 'conversation', id: parseInt(id) };
        });

        try {
            const response = await apiCall(state.endpoints.forward, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    message_id: messageId, 
                    targets 
                })
            });

            elements.forwardModalInstance?.hide();
            showToast(`Message forwarded to ${targets.length} conversation(s)`, 'success');

        } catch (error) {
            console.error('Forward error:', error);
            showToast('Failed to forward message', 'error');
        }
    }

    // ==== UI Components ====
    function toggleEmojiPicker(event) {
        event?.stopPropagation();
        
        if (!elements.emojiPickerWrap) return;

        const isVisible = elements.emojiPickerWrap.style.display === 'block';
        elements.emojiPickerWrap.style.display = isVisible ? 'none' : 'block';
        
        if (!isVisible) {
            elements.emojiPicker?.focus();
        }
    }

    function handleOutsideClick(event) {
        if (elements.emojiPickerWrap && 
            !elements.emojiPickerWrap.contains(event.target) && 
            event.target !== elements.emojiButton) {
            elements.emojiPickerWrap.style.display = 'none';
        }
    }

    function setupEmojiPicker() {
        if (!elements.emojiPicker) return;

        elements.emojiPicker.addEventListener('emoji-click', (event) => {
            if (!elements.messageInput) return;

            const cursorPos = elements.messageInput.selectionStart;
            const textBefore = elements.messageInput.value.substring(0, cursorPos);
            const textAfter = elements.messageInput.value.substring(cursorPos);
            
            elements.messageInput.value = textBefore + event.detail.unicode + textAfter;
            elements.messageInput.selectionStart = elements.messageInput.selectionEnd = 
                cursorPos + event.detail.unicode.length;
            elements.messageInput.focus();
        });
    }

    function handleBackNavigation() {
        document.querySelector('.chat-container')?.classList.remove('chat-active');
    }

    function handleMuteGroup() {
        // Implement group mute functionality
        showToast('Group notifications muted', 'info');
    }

    // ==== Network Monitoring ====
    function setupNetworkMonitoring() {
        window.addEventListener('offline', showOfflineBanner);
        window.addEventListener('online', handleOnlineStatus);
        
        // Monitor Echo/Pusher connection state
        if (state.echo?.connector?.pusher) {
            state.echo.connector.pusher.connection.bind('state_change', ({ current }) => {
                if (current === 'connected') hideOfflineBanner();
                if (['connecting', 'unavailable', 'failed', 'disconnected'].includes(current)) showOfflineBanner();
            });
        }

        if (!navigator.onLine) {
            showOfflineBanner();
        }
    }

    function showOfflineBanner() {
        if (!elements.networkBanner) return;
        
        elements.networkBanner.style.display = 'block';
        elements.sendButton?.setAttribute('disabled', 'disabled');
        scheduleRetry();
    }

    function hideOfflineBanner() {
        if (!elements.networkBanner) return;
        
        elements.networkBanner.style.display = 'none';
        if (elements.networkRetry) elements.networkRetry.textContent = '';
        clearTimeout(state.retryTimer);
        state.retryTimer = null;
        state.retryAt = null;
        state.retryDelay = CONFIG.RETRY_DELAY;
        elements.sendButton?.removeAttribute('disabled');
    }

    function handleOnlineStatus() {
        checkConnection().then(online => {
            if (online) hideOfflineBanner();
        });
    }

    function scheduleRetry() {
        clearTimeout(state.retryTimer);
        state.retryAt = Date.now() + state.retryDelay;
        updateRetryETA();
        
        state.retryTimer = setTimeout(async () => {
            const online = await checkConnection();
            if (online) {
                hideOfflineBanner();
            } else {
                state.retryDelay = Math.min(state.retryDelay * 1.75, CONFIG.MAX_RETRY_DELAY);
                scheduleRetry();
            }
        }, state.retryDelay);
    }

    function updateRetryETA() {
        if (!state.retryAt || !elements.networkRetry) return;
        
        const timeLeft = Math.max(0, state.retryAt - Date.now());
        elements.networkRetry.textContent = `Retry in ${Math.ceil(timeLeft / 1000)}s`;
        
        if (timeLeft > 0) {
            requestAnimationFrame(updateRetryETA);
        }
    }

    async function checkConnection() {
        try {
            const response = await fetch(state.endpoints.ping, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            return response.ok;
        } catch {
            return false;
        }
    }

    // ==== Utility Functions ====
    async function apiCall(url, options = {}) {
        const headers = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': state.csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers
        };

        const config = {
            credentials: 'same-origin',
            ...options,
            headers
        };

        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                const error = new Error(`HTTP ${response.status}`);
                error.status = response.status;
                error.response = response;
                throw error;
            }

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            }
            
            return await response.text();
        } catch (error) {
            if (!navigator.onLine) {
                error.status = 0; // Offline
            }
            throw error;
        }
    }

    async function validateMessageForm(formData) {
        const body = formData.get('body');
        const attachments = formData.getAll('attachments[]');

        if (!body?.trim() && attachments.length === 0) {
            showToast('Please enter a message or attach a file', 'error');
            return false;
        }

        if (attachments.length > 0) {
            for (const file of attachments) {
                if (file.size > CONFIG.UPLOAD_MAX_SIZE) {
                    showToast(`File "${file.name}" is too large. Maximum size is 10MB.`, 'error');
                    return false;
                }
            }
        }

        return true;
    }

    function setLoadingState(loading) {
        state.isLoading = loading;
        
        if (elements.sendButton) {
            elements.sendButton.disabled = loading;
            elements.sendButton.innerHTML = loading ? 
                '<span class="spinner-border spinner-border-sm" role="status"></span>' : 
                '<i class="bi bi-send"></i>';
        }
    }

    function playNotificationSound() {
        elements.notificationSound?.play()?.catch(() => {
            // Silent fail for audio playback
        });
    }

    function lazyLoadImages() {
        const lazyImages = document.querySelectorAll('.media-img[data-src]:not([src])');
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.add('loading');
                    img.onload = () => {
                        img.classList.remove('loading');
                        img.removeAttribute('data-src');
                    };
                    imageObserver.unobserve(img);
                }
            });
        }, { root: elements.chatBox, rootMargin: '200px 0px' });

        lazyImages.forEach(img => imageObserver.observe(img));
    }

    function showToast(message, type = 'info') {
        // Simple toast implementation - you might want to use a library like Toastify
        const toast = document.createElement('div');
        toast.className = `group-toast group-toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="bi bi-${getToastIcon(type)} me-2"></i>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(toast);

        // Animate in
        setTimeout(() => toast.classList.add('show'), 100);

        // Remove after delay
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function getToastIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function basename(path) {
        return path.split('/').pop() || path;
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // ==== Cleanup ====
    function cleanup() {
        state.observer?.disconnect();
        state.resizeObserver?.disconnect();
        state.echo?.leave(`group.${state.groupId}`);
        state.echo?.leave(`group.${state.groupId}.presence`);
        
        clearTimeout(state.typingTimer);
        clearTimeout(state.typingHideTimer);
        clearTimeout(state.retryTimer);
        clearInterval(state.presenceInterval);
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', cleanup);

})();
</script>