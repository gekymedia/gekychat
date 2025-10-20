{{-- resources/views/chat/partials/scripts.blade.php --}}
<script>
    (function() {
        'use strict';

        // ==== Configuration & Constants ====
        const CONFIG = {
            DEBOUNCE_DELAY: 300,
            TYPING_DELAY: 450,
            READ_DELAY: 600,
            RETRY_DELAY: 2000,
            MAX_RETRY_DELAY: 30000,
            SCROLL_THRESHOLD: 300,
            MESSAGE_PAGE_SIZE: 20,
            UPLOAD_MAX_SIZE: 10 * 1024 * 1024 // 10MB
        };

        // ==== State Management ====
        const state = {
            isLoading: false,
            hasMore: true,
            currentPage: 1,
            pendingReads: new Set(),
            typingTimer: null,
            readTimer: null,
            retryTimer: null,
            retryAt: null,
            retryDelay: CONFIG.RETRY_DELAY,
            observer: null,
            resizeObserver: null,
            echo: null
        };

        // ==== DOM Elements Cache ====
        const elements = {};

        // ==== Initialization ====
        document.addEventListener('DOMContentLoaded', function() {
            initializeApp();
        });

        function initializeApp() {
            cacheElements();
            initializeState();
            setupEventListeners();
            setupObservers();
            initializeRealtime();
            setupNetworkMonitoring();
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
                replyInput: '#reply-to',
                replyPreview: '#reply-preview',
                cancelReply: '#cancel-reply',
                
                // UI Components
                emojiPickerWrap: '#emoji-picker-wrap',
                emojiPicker: '#emoji-picker',
                emojiButton: '#emoji-btn',
                securityButton: '#security-btn',
                progressBar: '#upload-progress',
                notificationSound: '#notification-sound',
                networkBanner: '#net-banner',
                networkRetry: '#net-retry-in',
                
                // Modals
                securityModal: '#security-modal',
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
                messagesLoader: '#messages-loader'
            };

            Object.keys(selectors).forEach(key => {
                elements[key] = document.querySelector(selectors[key]);
            });

            // Cache Bootstrap modal instances
            if (window.bootstrap) {
                elements.securityModalInstance = elements.securityModal ? 
                    new bootstrap.Modal(elements.securityModal) : null;
                elements.forwardModalInstance = elements.forwardModal ? 
                    new bootstrap.Modal(elements.forwardModal) : null;
                elements.imageModalInstance = elements.imageModal ? 
                    new bootstrap.Modal(elements.imageModal) : null;
            }
        }

        function initializeState() {
            state.conversationId = "{{ $conversation->id ?? '' }}";
            state.currentUserId = "{{ auth()->id() }}";
            state.csrfToken = "{{ csrf_token() }}";
            state.storageUrl = "{{ Storage::url('') }}";
            
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
            // Add listeners to existing messages
            document.querySelectorAll('.message').forEach(message => {
                addMessageEventListeners(message);
            });

            // Typing indicator
            elements.messageInput?.addEventListener('input', handleTyping);
        }

        function setupUIListeners() {
            // Emoji picker
            elements.emojiButton?.addEventListener('click', toggleEmojiPicker);
            document.addEventListener('click', handleOutsideClick);

            // Security modal
            elements.securityButton?.addEventListener('click', showSecurityModal);
            document.getElementById('apply-security')?.addEventListener('click', applySecuritySettings);

            // Mobile navigation
            document.getElementById('back-to-conversations')?.addEventListener('click', handleBackNavigation);
            
            // Empty state buttons
            document.getElementById('open-new-chat-empty')?.addEventListener('click', () => {
                document.getElementById('open-new-chat')?.click();
            });

            // Clear chat
            document.getElementById('clear-chat-btn')?.addEventListener('click', handleClearChat);
        }

        function setupForwardModalListeners() {
            if (!elements.forwardModal) return;

            // Search functionality
            elements.forwardSearch?.addEventListener('input', debounce(handleForwardSearch, CONFIG.DEBOUNCE_DELAY));

            // Confirm forward
            elements.forwardConfirm?.addEventListener('click', handleForwardConfirm);

            // Modal show/hide events
            elements.forwardModal.addEventListener('show.bs.modal', resetForwardModal);
            elements.forwardModal.addEventListener('hidden.bs.modal', cleanupForwardModal);
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
            showToast('Message sent successfully', 'success');
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
            resetSecuritySettings();
        }

        function hideReplyPreview() {
            if (elements.replyPreview) {
                elements.replyPreview.style.display = 'none';
            }
            if (elements.replyInput) {
                elements.replyInput.value = '';
            }
        }

        function resetSecuritySettings() {
            document.getElementById('is-encrypted').value = '0';
            document.getElementById('expires-in').value = '';
            
            if (elements.securityButton) {
                elements.securityButton.innerHTML = '<i class="bi bi-shield-lock"></i>';
                elements.securityButton.classList.remove('text-primary');
            }
        }

        // ==== Message Display & Management ====
        function appendMessage(messageData, isOwn = false) {
            if (!elements.messagesContainer) return;

            const messageElement = createMessageElement(messageData, isOwn);
            elements.messagesContainer.appendChild(messageElement);
            addMessageEventListeners(messageElement);
            lazyLoadImages();
            
            // Observe for read tracking
            if (state.observer && !isOwn) {
                state.observer.observe(messageElement);
            }
        }

        function createMessageElement(message, isOwn = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message mb-3 d-flex ${isOwn ? 'justify-content-end' : 'justify-content-start'}`;
            messageDiv.dataset.messageId = message.id;
            messageDiv.dataset.fromMe = isOwn ? '1' : '0';
            messageDiv.dataset.read = isOwn ? '1' : (message.read_at ? '1' : '0');

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
                `<small class="sender-name">${escapeHtml(message.sender.name || message.sender.phone || '')}</small>` : '';

            const replyPreview = message.reply_to ? generateReplyPreview(message.reply_to) : '';
            const forwardHeader = message.is_forwarded ? generateForwardHeader() : '';
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

        function generateReplyPreview(replyTo) {
            const repliedText = replyTo.display_body || replyTo.body || '';
            const previewText = repliedText.length > 100 ? repliedText.slice(0, 100) + '…' : repliedText;
            
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
            if (message.is_encrypted && message.sender_id !== state.currentUserId) {
                return '<i class="bi bi-lock-fill me-1"></i> Encrypted message';
            }

            const rawText = String(message.display_body ?? message.body ?? '');
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
                const ext = (file.file_path?.split('.').pop() || '').toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
                const fileUrl = state.storageUrl + file.file_path;

                if (isImage) {
                    return `
                        <img class="img-fluid rounded media-img" alt="Shared image" loading="lazy" 
                             data-src="${fileUrl}" data-bs-toggle="modal" data-bs-target="#imageModal" 
                             data-image-src="${fileUrl}">
                    `;
                } else {
                    return `
                        <div class="mt-2">
                            <a href="${fileUrl}" target="_blank" class="d-inline-flex align-items-center doc-link" 
                               rel="noopener noreferrer">
                                <i class="bi bi-file-earmark me-1"></i> 
                                ${escapeHtml(file.original_name || 'file')}
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

            const statusIcon = generateStatusIcon(message, isOwn);

            return `
                <div class="message-footer d-flex justify-content-between align-items-center mt-1">
                    <small class="muted">${time}</small>
                    ${isOwn ? `<div class="status-indicator">${statusIcon}</div>` : ''}
                </div>
            `;
        }

        function generateStatusIcon(message, isOwn) {
            if (!isOwn) return '';

            if (message.read_at) {
                return '<i class="bi bi-check2-all text-primary" title="Read"></i>';
            } else if (message.delivered_at) {
                return '<i class="bi bi-check2-all muted" title="Delivered"></i>';
            } else {
                return '<i class="bi bi-check2 muted" title="Sent"></i>';
            }
        }

        function generateReactions(reactions) {
            if (!reactions.length) return '';

            const reactionHTML = reactions.map(r => 
                `<span class="badge bg-reaction rounded-pill me-1" title="${escapeHtml(r.user?.name || 'User')}">
                    ${escapeHtml(r.reaction)}
                </span>`
            ).join('');

            return `<div class="reactions-container mt-1">${reactionHTML}</div>`;
        }

        function generateMessageActions(message, isOwn) {
            return `
                <div class="message-actions dropdown">
                    <button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown" aria-label="Message actions">
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
                        ${isOwn ? `
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
                                <button class="btn btn-sm reaction-btn" data-reaction="👍">👍</button>
                                <button class="btn btn-sm reaction-btn ms-1" data-reaction="❤️">❤️</button>
                                <button class="btn btn-sm reaction-btn ms-1" data-reaction="😂">😂</button>
                                <button class="btn btn-sm reaction-btn ms-1" data-reaction="😮">😮</button>
                            </div>
                        </li>
                    </ul>
                </div>
            `;
        }

        // ==== Message Event Listeners ====
        function addMessageEventListeners(messageElement) {
            if (!messageElement) return;

            // Reply functionality
            const replyBtn = messageElement.querySelector('.reply-btn');
            replyBtn?.addEventListener('click', handleReply);

            // Forward functionality
            const forwardBtn = messageElement.querySelector('.forward-btn');
            forwardBtn?.addEventListener('click', handleForward);

            // Delete functionality (own messages only)
            const deleteBtn = messageElement.querySelector('.delete-btn');
            deleteBtn?.addEventListener('click', handleDelete);

            // Reactions
            const reactionBtns = messageElement.querySelectorAll('.reaction-btn');
            reactionBtns.forEach(btn => {
                btn.addEventListener('click', handleReaction);
            });

            // Image click for modal
            const images = messageElement.querySelectorAll('.media-img[data-image-src]');
            images.forEach(img => {
                img.addEventListener('click', handleImageClick);
            });
        }

        function handleReply(event) {
            const messageId = event.currentTarget.dataset.messageId;
            const messageEl = document.querySelector(`.message[data-message-id="${messageId}"]`);
            const messageText = messageEl?.querySelector('.message-text')?.textContent || '';
            
            if (elements.replyInput) {
                elements.replyInput.value = messageId;
            }
            
            showReplyPreview(messageText);
            elements.messageInput?.focus();
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

        function handleForward(event) {
            const messageId = event.currentTarget.dataset.messageId;
            if (elements.forwardSourceId) {
                elements.forwardSourceId.value = messageId;
            }
            elements.forwardModalInstance?.show();
        }

        async function handleDelete(event) {
            const messageId = event.currentTarget.dataset.messageId;
            
            if (!confirm('Are you sure you want to delete this message?')) return;

            try {
                await apiCall(`/messages/${messageId}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' }
                });
                
                document.querySelector(`.message[data-message-id="${messageId}"]`)?.remove();
                showToast('Message deleted', 'success');
            } catch (error) {
                console.error('Delete message error:', error);
                showToast('Failed to delete message', 'error');
            }
        }

        async function handleReaction(event) {
            const messageId = event.currentTarget.closest('.message')?.dataset.messageId;
            const reaction = event.currentTarget.dataset.reaction;
            
            if (!messageId || !reaction) return;

            try {
                await apiCall('/messages/react', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message_id: messageId, reaction })
                });
            } catch (error) {
                console.error('Reaction error:', error);
            }
        }

        function handleImageClick(event) {
            const imageSrc = event.currentTarget.dataset.imageSrc;
            const modalImage = document.querySelector('#imageModal img');
            
            if (modalImage && imageSrc) {
                modalImage.src = imageSrc;
                modalImage.alt = 'Enlarged view of shared image';
            }
        }

        // ==== Forward Modal Functionality ====
        function resetForwardModal() {
            state.selectedTargets.clear();
            updateForwardCount();
            renderForwardLists();
        }

        function cleanupForwardModal() {
            // Clear any temporary state
            if (elements.forwardSourceId) {
                elements.forwardSourceId.value = '';
            }
        }

        function handleForwardSearch(event) {
            const query = event.target.value.toLowerCase().trim();
            renderForwardLists(query);
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
            checkbox.className = 'form-check-input';
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
                img.className = 'list-avatar';
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
            div.className = 'list-avatar d-flex align-items-center justify-content-center bg-avatar text-white';
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
                const response = await apiCall("{{ route('chat.forward.targets') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message_id: messageId, targets })
                });

                elements.forwardModalInstance?.hide();
                showToast(`Message forwarded to ${targets.length} conversation(s)`, 'success');

                // Add forwarded messages to current chat if applicable
                if (Array.isArray(response?.results?.conversations)) {
                    response.results.conversations.forEach(message => {
                        if (String(message.conversation_id) === String(state.conversationId)) {
                            appendMessage(message, true);
                        }
                    });
                }
            } catch (error) {
                console.error('Forward error:', error);
                showToast('Failed to forward message', 'error');
            }
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

        function showSecurityModal() {
            elements.securityModalInstance?.show();
        }

        function applySecuritySettings() {
            const isEncrypted = document.getElementById('encrypt-toggle').checked;
            const expiresIn = document.getElementById('expiration-select').value;
            
            document.getElementById('is-encrypted').value = isEncrypted ? '1' : '0';
            document.getElementById('expires-in').value = expiresIn === '0' ? '' : expiresIn;
            
            // Update security button appearance
            if (elements.securityButton) {
                if (isEncrypted || expiresIn !== '0') {
                    elements.securityButton.innerHTML = '<i class="bi bi-shield-lock-fill"></i>';
                    elements.securityButton.classList.add('text-primary');
                } else {
                    elements.securityButton.innerHTML = '<i class="bi bi-shield-lock"></i>';
                    elements.securityButton.classList.remove('text-primary');
                }
            }
            
            elements.securityModalInstance?.hide();
            showToast('Security settings applied', 'success');
        }

        // ==== Scroll & Load More ====
        function setupObservers() {
            setupIntersectionObserver();
            setupResizeObserver();
        }

        function setupIntersectionObserver() {
            if (!elements.chatBox) return;

            state.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        
                        // Load more messages when near top
                        if (element.classList.contains('message') && 
                            elements.chatBox.scrollTop < 500 && 
                            entry.boundingClientRect.top < elements.chatBox.clientHeight) {
                            loadMoreMessages();
                        }
                        
                        // Mark messages as read
                        if (element.dataset.fromMe === '0' && element.dataset.read === '0') {
                            markMessageAsRead(parseInt(element.dataset.messageId));
                        }
                    }
                });
            }, {
                root: elements.chatBox,
                rootMargin: '0px 0px 100px 0px',
                threshold: 0.1
            });

            // Observe existing messages
            document.querySelectorAll('.message').forEach(message => {
                state.observer.observe(message);
            });
        }

        function setupResizeObserver() {
            state.resizeObserver = new ResizeObserver(() => {
                scrollToBottom({ force: true });
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
            if (state.isLoading || !state.hasMore || !state.conversationId) return;

            state.isLoading = true;
            showLoader(true);

            try {
                const nextPage = state.currentPage + 1;
                const response = await apiCall(`/chat/${state.conversationId}/history?page=${nextPage}`);
                
                const messages = response?.messages?.data || [];
                
                if (messages.length === 0) {
                    state.hasMore = false;
                    return;
                }

                const fragment = document.createDocumentFragment();
                messages.slice().reverse().forEach(message => {
                    const element = createMessageElement(message, message.sender_id === state.currentUserId);
                    fragment.prepend(element);
                });

                elements.messagesContainer.prepend(fragment);
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

        // ==== Message Read Tracking ====
        function markMessageAsRead(messageId) {
            state.pendingReads.add(messageId);
            
            // Update UI immediately
            const messageElement = document.querySelector(`.message[data-message-id="${messageId}"]`);
            if (messageElement) {
                messageElement.dataset.read = '1';
            }
            
            // Debounce server call
            clearTimeout(state.readTimer);
            state.readTimer = setTimeout(sendReadReceipts, CONFIG.READ_DELAY);
        }

        async function sendReadReceipts() {
            if (state.pendingReads.size === 0 || !state.conversationId) return;

            const messageIds = Array.from(state.pendingReads);
            state.pendingReads.clear();

            try {
                await apiCall("{{ route('chat.read') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: state.conversationId,
                        message_ids: messageIds
                    })
                });
            } catch (error) {
                // Re-add failed reads to pending set
                messageIds.forEach(id => state.pendingReads.add(id));
            }
        }

        // ==== Typing Indicators ====
        function handleTyping() {
            const isTyping = elements.messageInput?.value.trim().length > 0;
            updateLocalTypingIndicator(isTyping);
            notifyTypingStatus(isTyping);
        }

        function updateLocalTypingIndicator(isTyping) {
            const indicator = document.getElementById('typing-indicator');
            if (indicator) {
                indicator.style.display = isTyping ? 'block' : 'none';
            }
        }

        function notifyTypingStatus(isTyping) {
            if (!state.conversationId) return;

            clearTimeout(state.typingTimer);
            state.typingTimer = setTimeout(() => {
                apiCall("{{ route('chat.typing') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: state.conversationId,
                        is_typing: isTyping
                    })
                }).catch(() => {}); // Silent fail for typing indicators
            }, CONFIG.TYPING_DELAY);
        }

        // ==== Real-time Communication ====
        function initializeRealtime() {
            if (!window.Echo || !state.conversationId) return;

            state.echo = window.Echo;
            setupEchoListeners();
        }

        function setupEchoListeners() {
            const channel = state.echo.private(`chat.${state.conversationId}`);

            channel
                .listen('MessageSent', (event) => handleIncomingMessage(event))
                .listen('UserTyping', (event) => handleUserTyping(event))
                .listen('MessageRead', (event) => handleMessageRead(event))
                .listen('MessageStatusUpdated', (event) => handleMessageStatusUpdate(event))
                .listen('MessageDeleted', (event) => handleMessageDeleted(event))
                .listen('ChatCleared', (event) => handleChatCleared(event));

            // Optional: Presence channel for online status
            if (typeof state.echo.join === 'function') {
                state.echo.join('chat.presence')
                    .here(users => updateOnlineList(users))
                    .joining(user => addToOnlineList(user))
                    .leaving(user => removeFromOnlineList(user));
            }
        }

        function handleIncomingMessage(event) {
            if (!event?.message || event.message.sender_id === state.currentUserId) return;

            // Handle encrypted messages
            if (event.message.is_encrypted) {
                event.message.display_body = '[Encrypted message]';
            }

            appendMessage(event.message, false);
            playNotificationSound();
            
            // Mark as read if visible
            const messageElement = document.querySelector(`.message[data-message-id="${event.message.id}"]`);
            if (messageElement && messageElement.dataset.fromMe === '0') {
                markMessageAsRead(parseInt(event.message.id));
            }
            
            scrollToBottom({ smooth: true });
        }

        function handleUserTyping(event) {
            if (event?.user_id === state.currentUserId) return;

            const indicator = document.getElementById('typing-indicator');
            if (indicator) {
                indicator.style.display = 'block';
                clearTimeout(window.typingHideTimeout);
                window.typingHideTimeout = setTimeout(() => {
                    indicator.style.display = 'none';
                }, 1800);
            }
        }

        function handleMessageRead(event) {
            (event?.message_ids || []).forEach(messageId => {
                const statusElement = document.querySelector(
                    `.message[data-message-id="${messageId}"] .status-indicator`
                );
                if (statusElement) {
                    statusElement.innerHTML = '<i class="bi bi-check2-all text-primary" title="Read"></i>';
                }
            });
        }

        function handleMessageStatusUpdate(event) {
            const statusElement = document.querySelector(
                `.message[data-message-id="${event?.message_id}"] .status-indicator`
            );
            
            if (!statusElement) return;

            switch (event?.status) {
                case 'delivered':
                    statusElement.innerHTML = '<i class="bi bi-check2-all muted" title="Delivered"></i>';
                    break;
                case 'read':
                    statusElement.innerHTML = '<i class="bi bi-check2-all text-primary" title="Read"></i>';
                    break;
            }
        }

        function handleMessageDeleted(event) {
            document.querySelector(`.message[data-message-id="${event?.message_id}"]`)?.remove();
        }

        function handleChatCleared(event) {
            if (String(event?.conversation_id) === String(state.conversationId) && elements.messagesContainer) {
                elements.messagesContainer.innerHTML = '';
                showToast('Chat cleared', 'info');
            }
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
                const response = await fetch("{{ route('ping') }}", {
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
            // You can integrate with a toast library here
            console.log(`[${type.toUpperCase()}] ${message}`);
            
            // Simple native alert for now - replace with proper toast implementation
            if (type === 'error') {
                alert(`Error: ${message}`);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
            state.echo?.leave(`chat.${state.conversationId}`);
            
            clearTimeout(state.typingTimer);
            clearTimeout(state.readTimer);
            clearTimeout(state.retryTimer);
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', cleanup);

    })();
</script>