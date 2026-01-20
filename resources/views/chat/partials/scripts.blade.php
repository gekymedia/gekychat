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
        // Global function to join call from message (opens modal instead of new tab)
        window.joinCallFromMessage = function(callLink) {
            // Extract callId from the link (e.g., /calls/join/{callId})
            const callIdMatch = callLink.match(/\/calls\/join\/([^\/\?]+)/);
            if (!callIdMatch) {
                console.error('Invalid call link format');
                return;
            }
            const callId = callIdMatch[1];
            
            // Use fetch to join the call and get session info
            fetch(callLink, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to join call');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success' && data.session_id) {
                    // Set up the call manager to join the existing call
                    if (window.callManager) {
                        // Set current call info
                        window.callManager.currentCall = {
                            sessionId: data.session_id,
                            type: data.type || 'video'
                        };
                        window.callManager.callType = data.type || 'video';
                        window.callManager.isCaller = false;
                        
                        // Show call UI
                        const userName = document.querySelector('.chat-header-name')?.textContent || 'User';
                        const userAvatar = document.querySelector('.chat-header .avatar-img')?.src || null;
                        window.callManager.showCallUI(userName, userAvatar, 'joining');
                        
                        // Start WebRTC to join the call
                        window.callManager.initiateWebRTC();
                    } else {
                        // Fallback: redirect to call page
                        window.location.href = callLink;
                    }
                } else {
                    // Fallback: redirect to call page
                    window.location.href = callLink;
                }
            })
            .catch(error => {
                console.error('Error joining call:', error);
                // Fallback: redirect to call page
                window.location.href = callLink;
            });
        };
        
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
            
            // Initialize lazy loading for images
            lazyLoadImages();
            
            // Scroll to bottom when chat first loads
            setTimeout(() => {
                scrollToBottom({ force: true });
                // Re-run lazy loading after scroll
                lazyLoadImages();
            }, 100);
            
            // Prefill message if text parameter is provided (from /send/ links)
            @if(isset($prefillText) && !empty($prefillText))
            setTimeout(() => {
                if (elements.messageInput) {
                    elements.messageInput.value = @json($prefillText);
                    elements.messageInput.focus();
                    // Remove text parameter from URL to clean it up
                    const url = new URL(window.location);
                    url.searchParams.delete('text');
                    window.history.replaceState({}, '', url);
                }
            }, 300);
            @endif
            
            // Check for reply private context and show preview
            @if(isset($replyPrivateContext) && $replyPrivateContext)
            setTimeout(() => {
                const context = @json($replyPrivateContext);
                const messageText = context.group_message_body || '[Media/Attachment]';
                const senderName = context.group_message_sender || 'User';
                const previewText = `Replying privately to a message in ${context.group_name}: "${messageText.substring(0, 100)}${messageText.length > 100 ? '...' : ''}"`;
                
                if (window.showReplyPreview) {
                    window.showReplyPreview(previewText, senderName, null);
                }
            }, 500);
            @endif
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
            state.hasMore = {{ isset($hasMoreMessages) && $hasMoreMessages ? 'true' : 'false' }};
            state.currentPage = 1;
            
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
            document.getElementById('export-chat-btn')?.addEventListener('click', handleExportChat);
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

        // ==== Date Formatting Helper ====
        function formatChatDate(dateString) {
            if (!dateString) return '';
            
            const date = new Date(dateString);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            
            const messageDate = new Date(date);
            messageDate.setHours(0, 0, 0, 0);
            
            if (messageDate.getTime() === today.getTime()) {
                return 'Today';
            } else if (messageDate.getTime() === yesterday.getTime()) {
                return 'Yesterday';
            } else {
                // Format as "January 15, 2025"
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                return date.toLocaleDateString('en-US', options);
            }
        }
        
        function isDifferentDay(date1, date2) {
            if (!date1 || !date2) return true;
            
            const d1 = new Date(date1);
            const d2 = new Date(date2);
            
            d1.setHours(0, 0, 0, 0);
            d2.setHours(0, 0, 0, 0);
            
            return d1.getTime() !== d2.getTime();
        }
        
        function createDateDivider(dateString) {
            const divider = document.createElement('div');
            divider.className = 'date-divider text-center my-3';
            divider.setAttribute('data-date', dateString.split('T')[0]);
            divider.innerHTML = `
                <span class="date-divider-text bg-bg px-3 py-1 rounded-pill text-muted small fw-semibold">
                    ${formatChatDate(dateString)}
                </span>
            `;
            return divider;
        }

        // ==== Message Display & Management ====
        function appendMessage(messageData, isOwn = false) {
            if (!elements.messagesContainer) return;

            // Check if we need to add a date divider
            const lastMessage = elements.messagesContainer.querySelector('.message:last-child');
            if (lastMessage) {
                const lastMessageDate = lastMessage.getAttribute('data-message-date');
                if (isDifferentDay(lastMessageDate, messageData.created_at)) {
                    const dateDivider = createDateDivider(messageData.created_at);
                    elements.messagesContainer.appendChild(dateDivider);
                }
            } else {
                // First message, always show date divider
                const dateDivider = createDateDivider(messageData.created_at);
                elements.messagesContainer.appendChild(dateDivider);
            }

            const messageElement = createMessageElement(messageData, isOwn);
            elements.messagesContainer.appendChild(messageElement);
            addMessageEventListeners(messageElement);
            lazyLoadImages();
            initAudioPlayers(); // Initialize audio players for new messages
            
            // Observe for read tracking
            if (state.observer && !isOwn) {
                state.observer.observe(messageElement);
            }
        }

        function createMessageElement(message, isOwn = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message mb-3 d-flex ${isOwn ? 'justify-content-end' : 'justify-content-start'}`;
            messageDiv.dataset.messageId = message.id;
            messageDiv.dataset.messageDate = message.created_at || new Date().toISOString();
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
            const callMessage = message.call_data ? generateCallMessage(message.call_data, message.sender_id, isOwn) : '';
            const locationMessage = message.location_data ? generateLocationMessage(message.location_data) : '';
            const contactMessage = message.contact_data ? generateContactMessage(message.contact_data) : '';
            const footer = generateMessageFooter(message, isOwn);
            const reactions = generateReactions(message.reactions || []);

            return `
                ${senderName}
                <div class="message-content">
                    ${replyPreview}
                    ${forwardHeader}
                    <div class="message-text">${messageText}</div>
                    ${attachments}
                    ${callMessage}
                    ${locationMessage}
                    ${contactMessage}
                </div>
                ${footer}
                ${reactions}
            `;
        }
        
        function generateCallMessage(callData, senderId, isOwn) {
            if (!callData) return '';
            
            const callType = callData.type || 'voice';
            const callStatus = callData.status || 'ended';
            const callLink = callData.call_link || null;
            const isActive = ['calling', 'ongoing'].includes(callStatus);
            const isMissed = callData.missed || false;
            const duration = callData.duration;
            
            const callIcon = callType === 'video' ? '📹' : '📞';
            const callTypeText = callType === 'video' ? 'Video call' : 'Voice call';
            
            let statusIcon = '';
            if (isMissed) {
                statusIcon = '<i class="bi bi-x-circle-fill text-danger" style="font-size: 1.2rem;" title="Missed call"></i>';
            } else if (isActive) {
                statusIcon = '<i class="bi bi-circle-fill text-success" style="font-size: 0.8rem; animation: pulse 2s infinite;" title="Active call"></i>';
            } else if (isOwn) {
                statusIcon = '<i class="bi bi-check-circle-fill text-primary" style="font-size: 1.2rem;" title="Outgoing call"></i>';
            } else {
                statusIcon = '<i class="bi bi-arrow-down-circle-fill text-success" style="font-size: 1.2rem;" title="Incoming call"></i>';
            }
            
            let title = '';
            if (isMissed) {
                title = `Missed ${callTypeText}`;
            } else if (isActive) {
                title = `${callTypeText} - Join now`;
            } else {
                title = callTypeText;
            }
            
            // Make the entire call card clickable if there's a call link
            const cardClickable = callLink ? `onclick="event.preventDefault(); joinCallFromMessage('${escapeHtml(callLink)}');" style="cursor: pointer;"` : '';
            const cardClass = callLink ? 'call-card-clickable' : '';
            
            let joinButton = '';
            if (isActive && callLink) {
                joinButton = `
                    <div class="mt-2">
                        <a href="${escapeHtml(callLink)}" 
                           class="btn btn-sm btn-primary d-inline-flex align-items-center gap-2 join-call-link" 
                           onclick="event.preventDefault(); joinCallFromMessage('${escapeHtml(callLink)}'); return false;">
                            <i class="bi bi-telephone-fill"></i>
                            <span>Join Call</span>
                        </a>
                    </div>
                `;
            } else if (callLink && !isActive) {
                // For ended calls, show a link to view call details
                joinButton = `
                    <div class="mt-2">
                        <a href="${escapeHtml(callLink)}" 
                           class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2 join-call-link" 
                           onclick="event.preventDefault(); joinCallFromMessage('${escapeHtml(callLink)}'); return false;">
                            <i class="bi bi-info-circle"></i>
                            <span>View Call</span>
                        </a>
                    </div>
                `;
            }
            
            return `
                <div class="call-message mt-2">
                    <div class="call-card rounded border bg-light d-flex align-items-center p-3 ${cardClass}" ${cardClickable}>
                        <div class="call-icon me-3">
                            <i class="bi ${callType === 'video' ? 'bi-camera-video-fill' : 'bi-telephone-fill'} text-primary" style="font-size: 1.5rem;"></i>
                        </div>
                        <div class="call-details flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark mb-1">${title}</div>
                                    ${duration ? `<small class="text-muted">${duration < 60 ? duration + 's' : Math.floor(duration / 60) + ':' + String(duration % 60).padStart(2, '0')}</small>` : ''}
                                    ${joinButton}
                                </div>
                                <div class="call-status-icon">${statusIcon}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function generateLocationMessage(locationData) {
            if (!locationData || !locationData.latitude || !locationData.longitude) return '';
            const mapUrl = `https://www.google.com/maps?q=${locationData.latitude},${locationData.longitude}`;
            return `
                <div class="location-message mt-2">
                    <a href="${mapUrl}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-geo-alt-fill me-1"></i>
                        View Location
                    </a>
                </div>
            `;
        }
        
        function generateContactMessage(contactData) {
            if (!contactData) return '';
            return `
                <div class="contact-message mt-2">
                    <div class="contact-card p-2 border rounded">
                        <strong>${escapeHtml(contactData.name || 'Contact')}</strong>
                        ${contactData.phone ? `<div><small>${escapeHtml(contactData.phone)}</small></div>` : ''}
                    </div>
                </div>
            `;
        }

        function generateReplyPreview(replyTo) {
            if (!replyTo) return '';
            
            const repliedText = replyTo.display_body || replyTo.body || '';
            const senderName = replyTo.sender?.name || replyTo.sender?.phone || 'Someone';
            const previewText = repliedText.length > 80 ? repliedText.slice(0, 80) + '…' : repliedText;
            
            const escapedSenderName = escapeHtml(senderName);
            const escapedPreviewText = escapeHtml(previewText);
            const ariaLabel = `Replying to message from ${senderName}: ${previewText}`;
            
            return `
                <div class="reply-preview mb-2 p-2 rounded border-start border-3 border-primary bg-light" role="button"
                    tabindex="0" data-reply-to="${replyTo.id || ''}"
                    aria-label="${ariaLabel.replace(/"/g, '&quot;')}">
                    <div class="d-flex align-items-center mb-1">
                        <i class="bi bi-reply-fill me-1 text-primary" aria-hidden="true"></i>
                        <small class="fw-semibold text-primary">${escapedSenderName}</small>
                    </div>
                    <div class="reply-content">
                        <small class="text-muted text-truncate d-block" style="max-width: 200px;">
                            ${escapedPreviewText}
                        </small>
                    </div>
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
            let escapedText = escapeHtml(rawText);
            
            // Process group reference links (for reply privately messages)
            const metadata = message.metadata || {};
            if (metadata.group_reference && metadata.group_reference.group_slug) {
                const groupName = escapeHtml(metadata.group_reference.group_name);
                const groupSlug = escapeHtml(metadata.group_reference.group_slug);
                const groupUrl = `/g/${groupSlug}`;
                
                // Replace "in {group_name}:" with clickable link
                const groupNameRegex = new RegExp('in (' + escapeRegex(groupName) + '):', 'g');
                escapedText = escapedText.replace(groupNameRegex, 
                    `in <a href="${groupUrl}" class="group-reference-link text-primary fw-semibold" title="Go to group">${groupName}</a>:`
                );
            }
            
            // Convert URLs to clickable links
            return escapedText.replace(
                /(https?:\/\/[^\s]+)/g, 
                '<a class="linkify" target="_blank" rel="noopener noreferrer" href="$1">$1</a>'
            );
        }
        
        function escapeRegex(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function generateAttachments(attachments) {
            if (!attachments.length) return '';

            return attachments.map(file => {
                const ext = (file.file_path?.split('.').pop() || '').toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
                const isVideo = ['mp4', 'mov', 'avi', 'mkv', 'flv', 'webm'].includes(ext);
                const isAudio = ['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a', 'webm'].includes(ext);
                const fileUrl = state.storageUrl + file.file_path;
                const fileName = escapeHtml(file.original_name || 'file');
                const audioId = file.id || Date.now() + Math.random();

                if (isImage) {
                    return `
                        <img class="img-fluid rounded media-img" alt="Shared image" loading="lazy" 
                             data-src="${fileUrl}" data-bs-toggle="modal" data-bs-target="#imageModal" 
                             data-image-src="${fileUrl}">
                    `;
                } else if (isVideo) {
                    return `
                        <div class="mt-2">
                            <video controls class="img-fluid rounded" style="max-width: 300px; max-height: 300px;">
                                <source src="${fileUrl}" type="video/${ext}">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    `;
                } else if (isAudio) {
                    // WhatsApp-style audio player
                    return `
                        <div class="attachment-item mt-2" data-file-type="${ext}" data-file-name="${fileName}">
                            <div class="audio-attachment-wa" data-audio-url="${fileUrl}" data-audio-id="${audioId}">
                                <div class="audio-player-container">
                                    <button class="audio-play-btn" type="button" aria-label="Play audio">
                                        <i class="bi bi-play-fill play-icon"></i>
                                        <i class="bi bi-pause-fill pause-icon d-none"></i>
                                    </button>
                                    <div class="audio-controls">
                                        <div class="audio-waveform-container">
                                            <canvas class="audio-waveform-canvas" width="200" height="40"></canvas>
                                            <div class="audio-progress-overlay"></div>
                                        </div>
                                        <div class="audio-time">
                                            <span class="audio-current-time">0:00</span>
                                            <span class="audio-separator">/</span>
                                            <span class="audio-duration">--:--</span>
                                        </div>
                                    </div>
                                    <audio class="audio-element" preload="metadata" src="${fileUrl}">
                                        <source src="${fileUrl}" type="audio/${ext}">
                                        Your browser does not support the audio element.
                                    </audio>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    return `
                        <div class="mt-2">
                            <a href="${fileUrl}" target="_blank" class="d-inline-flex align-items-center doc-link" 
                               rel="noopener noreferrer">
                                <i class="bi bi-file-earmark me-1"></i> 
                                ${fileName}
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
                                        data-message-id="${message.id}"
                                        data-delete-for="me">
                                    <i class="bi bi-trash"></i>Delete for me
                                </button>
                            </li>
                            ${message.created_at && (new Date() - new Date(message.created_at)) < 3600000 ? `
                                <li>
                                    <button class="dropdown-item d-flex align-items-center gap-2 text-danger delete-for-everyone-btn" 
                                            data-message-id="${message.id}"
                                            data-delete-for="everyone">
                                        <i class="bi bi-trash-fill"></i>Delete for everyone
                                    </button>
                                </li>
                            ` : ''}
                        ` : ''}
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <div class="d-flex px-3 py-1 reaction-buttons">
                                <button class="btn btn-sm reaction-btn" data-reaction="👍">👍</button>
                                <button class="btn btn-sm reaction-btn ms-1" data-reaction="❤️">❤️</button>
                                <button class="btn btn-sm reaction-btn ms-1" data-reaction="😂">😂</button>
                                <button class="btn btn-sm reaction-btn ms-1" data-reaction="😮">😮</button>
                                <button class="btn btn-sm btn-outline-secondary ms-1 more-reactions-btn" data-message-id="${message.id}" title="More reactions">
                                    <i class="bi bi-plus-circle"></i>
                                </button>
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
            
            // Delete for everyone functionality
            const deleteForEveryoneBtn = messageElement.querySelector('.delete-for-everyone-btn');
            deleteForEveryoneBtn?.addEventListener('click', handleDelete);

            // Reactions
            const reactionBtns = messageElement.querySelectorAll('.reaction-btn');
            reactionBtns.forEach(btn => {
                btn.addEventListener('click', handleReaction);
            });

            // More reactions button (emoji picker)
            const moreReactionsBtn = messageElement.querySelector('.more-reactions-btn');
            if (moreReactionsBtn) {
                moreReactionsBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const messageId = e.currentTarget.dataset.messageId;
                    showEmojiPickerForReaction(messageId);
                });
            }

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
            const senderName = messageEl?.querySelector('.sender-name')?.textContent || null;
            
            // Set the reply input field
            const replyInput = document.getElementById('reply-to-id') || document.getElementById('reply-to');
            if (replyInput) {
                replyInput.value = messageId;
                console.log('Set reply_to input value to:', messageId);
            }
            
            // Use the global showReplyPreview function if available, otherwise use local one
            if (window.showReplyPreview) {
                window.showReplyPreview(messageText, senderName, messageId);
            } else {
                showReplyPreview(messageText, senderName, messageId);
            }
            elements.messageInput?.focus();
        }

        function showReplyPreview(text, senderName = null, messageId = null) {
            if (!elements.replyPreview) return;
            
            const previewContent = elements.replyPreview.querySelector('.reply-preview-content');
            if (previewContent) {
                previewContent.textContent = text.length > 60 ? text.slice(0, 60) + '…' : text;
            }
            
            // Set the reply input field if messageId is provided
            if (messageId) {
                const replyInput = document.getElementById('reply-to-id') || document.getElementById('reply-to');
                if (replyInput) {
                    replyInput.value = messageId;
                    console.log('Set reply_to input value to:', messageId);
                }
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
            const deleteFor = event.currentTarget.dataset.deleteFor || 'me';
            
            let confirmMessage = 'Are you sure you want to delete this message?';
            if (deleteFor === 'everyone') {
                confirmMessage = 'Are you sure you want to delete this message for everyone? This action cannot be undone and the message will be removed from all participants\' devices.';
            }
            
            if (!confirm(confirmMessage)) return;

            try {
                const url = `/api/v1/messages/${messageId}?delete_for=${deleteFor}`;
                const response = await apiCall(url, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' }
                });
                
                const messageElement = document.querySelector(`.message[data-message-id="${messageId}"]`);
                if (messageElement) {
                    if (deleteFor === 'everyone') {
                        // Show deleted message indicator
                        messageElement.innerHTML = '<div class="text-muted fst-italic">This message was deleted</div>';
                        messageElement.classList.add('text-muted', 'fst-italic', 'deleted-message');
                    } else {
                        // Remove from view for "delete for me"
                        messageElement.remove();
                    }
                }
                
                const successMessage = deleteFor === 'everyone' 
                    ? 'Message deleted for everyone' 
                    : 'Message deleted';
                showToast(successMessage, 'success');
            } catch (error) {
                console.error('Delete message error:', error);
                const errorMessage = error.response?.data?.message || 'Failed to delete message';
                showToast(errorMessage, 'error');
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
            div.className = 'avatar-placeholder avatar-md';
            div.style.marginRight = '12px';
            
            // Get initials
            const initials = getInitials(name);
            div.textContent = initials;
            
            // Set background gradient based on name hash (using getAvatarColor function)
            div.style.background = getAvatarColor(name);
            div.style.color = 'white';
            
            return div;
        }
        
        function getInitials(name) {
            if (!name || name.trim() === '') return '?';
            const parts = name.trim().split(' ');
            if (parts.length >= 2) {
                return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
            } else if (parts.length === 1 && parts[0].length >= 2) {
                return parts[0].substring(0, 2).toUpperCase();
            } else if (parts.length === 1 && parts[0].length === 1) {
                return parts[0][0].toUpperCase();
            }
            return '?';
        }
        
        function getAvatarColor(name) {
            // Gradient pairs: [light, dark] for 3D effect similar to Telegram
            const gradientPairs = [
                ['#EF5350', '#C62828'], // Red
                ['#42A5F5', '#1565C0'], // Blue
                ['#66BB6A', '#2E7D32'], // Green
                ['#FFA726', '#E65100'], // Orange
                ['#AB47BC', '#6A1B9A'], // Purple
                ['#EC407A', '#AD1457'], // Pink
                ['#5C6BC0', '#283593'], // Indigo
                ['#26A69A', '#00695C'], // Teal
                ['#29B6F6', '#0277BD'], // Light Blue
                ['#9CCC65', '#558B2F'], // Light Green
                ['#FFCA28', '#F57F17'], // Yellow
                ['#FF7043', '#D84315'], // Deep Orange
                ['#8D6E63', '#5D4037'], // Brown
                ['#78909C', '#455A64'], // Blue Grey
                ['#7E57C2', '#4527A0'], // Deep Purple
                ['#00ACC1', '#00838F']  // Cyan
            ];
            if (!name || name.trim() === '') {
                const [light, dark] = gradientPairs[0];
                return `linear-gradient(135deg, ${light} 0%, ${dark} 100%)`;
            }
            const hash = name.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
            const colorIndex = Math.abs(hash) % gradientPairs.length;
            const [light, dark] = gradientPairs[colorIndex];
            return `linear-gradient(135deg, ${light} 0%, ${dark} 100%)`;
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

            // Add scroll event listener for detecting scroll to top
            let scrollTimeout;
            elements.chatBox.addEventListener('scroll', () => {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    // Load more messages when scrolled near top (within 300px)
                    if (elements.chatBox.scrollTop < 300 && state.hasMore && !state.isLoading) {
                        loadMoreMessages();
                    }
                }, 100); // Debounce scroll events
            });

            state.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        
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

            // Store current scroll position before loading
            const scrollHeightBefore = elements.chatBox.scrollHeight;
            const scrollTopBefore = elements.chatBox.scrollTop;

            try {
                const nextPage = state.currentPage + 1;
                const response = await apiCall(`/c/${state.conversationId}/history?page=${nextPage}`);
                
                const messages = response?.messages?.data || [];
                
                if (messages.length === 0) {
                    state.hasMore = false;
                    showLoader(false);
                    return;
                }

                const fragment = document.createDocumentFragment();
                messages.forEach(message => {
                    const element = createMessageElement(message, message.sender_id === state.currentUserId);
                    fragment.appendChild(element); // Append in order (oldest to newest)
                });

                // Prepend fragment to container
                elements.messagesContainer.insertBefore(fragment, elements.messagesContainer.firstChild);

                // Restore scroll position (maintain scroll position)
                const scrollHeightAfter = elements.chatBox.scrollHeight;
                const heightDifference = scrollHeightAfter - scrollHeightBefore;
                elements.chatBox.scrollTop = scrollTopBefore + heightDifference;

                state.currentPage = nextPage;
                state.hasMore = !!response?.messages?.next_page_url;

                // Observe new messages
                fragment.childNodes.forEach(node => {
                    if (node.nodeType === 1 && state.observer) {
                        state.observer.observe(node);
                    }
                });

            } catch (error) {
                console.error('Load more messages error:', error);
                showToast('Failed to load older messages', 'error');
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
            // Use consistent channel name: conversation.{id} (matches mobile/desktop)
            const channel = state.echo.private(`conversation.${state.conversationId}`);

            channel
                .listen('MessageSent', (event) => handleIncomingMessage(event))
                .listen('UserTyping', (event) => handleUserTyping(event))
                .listen('UserRecording', (event) => handleUserRecording(event))
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

            // Merge top-level reply_to into message object if it exists
            const messageData = { ...event.message };
            if (event.reply_to && !messageData.reply_to) {
                messageData.reply_to = event.reply_to;
            }

            // Handle encrypted messages
            if (messageData.is_encrypted) {
                messageData.display_body = '[Encrypted message]';
            }

            appendMessage(messageData, false);
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
                // Use consistent timeout: 3 seconds (matches mobile/desktop)
                window.typingHideTimeout = setTimeout(() => {
                    indicator.style.display = 'none';
                }, 3000);
            }
        }

        function handleUserRecording(event) {
            if (event?.user_id === state.currentUserId) return;

            const recordingIndicator = document.getElementById('recording-indicator');
            const typingIndicator = document.getElementById('typing-indicator');
            
            if (recordingIndicator) {
                if (event?.is_recording) {
                    recordingIndicator.style.display = 'block';
                    // Hide typing indicator when recording
                    if (typingIndicator) {
                        typingIndicator.style.display = 'none';
                    }
                } else {
                    recordingIndicator.style.display = 'none';
                }
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

        // Global audio player initialization
        function initAudioPlayers() {
            document.querySelectorAll('.audio-attachment-wa:not([data-initialized="true"])').forEach(container => {
                // Mark as initialized immediately to prevent duplicate initialization
                container.dataset.initialized = 'true';
                
                const audioElement = container.querySelector('.audio-element');
                const playBtn = container.querySelector('.audio-play-btn');
                const playIcon = container.querySelector('.play-icon');
                const pauseIcon = container.querySelector('.pause-icon');
                const canvas = container.querySelector('.audio-waveform-canvas');
                const progressOverlay = container.querySelector('.audio-progress-overlay');
                const currentTimeEl = container.querySelector('.audio-current-time');
                const durationEl = container.querySelector('.audio-duration');
                const waveformContainer = container.querySelector('.audio-waveform-container');
                
                if (!audioElement || !playBtn || !canvas) {
                    console.warn('Audio player elements not found in container:', container, {
                        hasAudio: !!audioElement,
                        hasPlayBtn: !!playBtn,
                        hasCanvas: !!canvas
                    });
                    return;
                }
                
                // Ensure audio element has a source
                const audioUrl = container.dataset.audioUrl;
                if (audioUrl) {
                    // Set src directly on audio element (more reliable)
                    if (!audioElement.src || audioElement.src !== audioUrl) {
                        audioElement.src = audioUrl;
                    }
                    // Also ensure source element exists
                    let audioSource = audioElement.querySelector('source');
                    if (!audioSource) {
                        audioSource = document.createElement('source');
                        audioSource.src = audioUrl;
                        const ext = audioUrl.split('.').pop().toLowerCase();
                        audioSource.type = 'audio/' + ext;
                        audioElement.appendChild(audioSource);
                    } else if (!audioSource.src || audioSource.src !== audioUrl) {
                        audioSource.src = audioUrl;
                    }
                }
                
                let animationFrame = null;
                let waveformData = null;
                let isPlaying = false;
                
                // Format time helper
                function formatTime(seconds) {
                    if (!isFinite(seconds) || isNaN(seconds) || seconds < 0) return '0:00';
                    const mins = Math.floor(seconds / 60);
                    const secs = Math.floor(seconds % 60);
                    return `${mins}:${secs.toString().padStart(2, '0')}`;
                }
                
                // Generate waveform from audio
                function generateWaveform() {
                    if (waveformData) {
                        drawWaveform(waveformData);
                        return;
                    }
                    
                    const ctx = canvas.getContext('2d');
                    const width = canvas.width;
                    const height = canvas.height;
                    const barCount = 50;
                    const bars = [];
                    for (let i = 0; i < barCount; i++) {
                        bars.push(Math.random() * 0.6 + 0.2);
                    }
                    waveformData = bars;
                    drawWaveform(bars);
                }
                
                // Draw waveform
                function drawWaveform(bars, progress = 0) {
                    const ctx = canvas.getContext('2d');
                    const width = canvas.width;
                    const height = canvas.height;
                    const barCount = bars.length;
                    const barWidth = width / barCount;
                    const centerY = height / 2;
                    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    
                    ctx.fillStyle = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)';
                    ctx.fillRect(0, 0, width, height);
                    
                    bars.forEach((barHeight, i) => {
                        const x = i * barWidth;
                        const barH = barHeight * height * 0.6;
                        const isPlayed = (i / barCount) < progress;
                        ctx.fillStyle = isPlayed ? 'rgb(37, 211, 102)' : (isDark ? 'rgba(255, 255, 255, 0.3)' : 'rgba(0, 0, 0, 0.2)');
                        ctx.fillRect(x, centerY - barH / 2, Math.max(1, barWidth - 1), barH);
                    });
                }
                
                // Update progress
                function updateProgress() {
                    if (!audioElement.duration || !isFinite(audioElement.duration) || audioElement.duration <= 0) return;
                    const progress = audioElement.currentTime / audioElement.duration;
                    if (progressOverlay) progressOverlay.style.width = (progress * 100) + '%';
                    if (waveformData) drawWaveform(waveformData, progress);
                    if (currentTimeEl) currentTimeEl.textContent = formatTime(audioElement.currentTime);
                }
                
                // Play/Pause toggle
                const playButtonClickHandler = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    // Ensure audio has a source before playing
                    if (!audioElement.src) {
                        const audioUrl = container.dataset.audioUrl;
                        if (audioUrl) {
                            audioElement.src = audioUrl;
                        } else {
                            console.error('No audio URL available');
                            alert('Audio source not available');
                            return false;
                        }
                    }
                    
                    if (isPlaying) {
                        audioElement.pause();
                        if (playIcon) playIcon.classList.remove('d-none');
                        if (pauseIcon) pauseIcon.classList.add('d-none');
                        isPlaying = false;
                        if (animationFrame) {
                            cancelAnimationFrame(animationFrame);
                            animationFrame = null;
                        }
                    } else {
                        // Play audio - handle promise properly
                        try {
                            const playPromise = audioElement.play();
                            if (playPromise !== undefined) {
                                playPromise
                                    .then(() => {
                                        // Audio started playing
                                        if (playIcon) playIcon.classList.add('d-none');
                                        if (pauseIcon) pauseIcon.classList.remove('d-none');
                                        isPlaying = true;
                                        const update = () => {
                                            if (isPlaying) {
                                                updateProgress();
                                                animationFrame = requestAnimationFrame(update);
                                            }
                                        };
                                        update();
                                    })
                                    .catch(err => {
                                        console.error('Error playing audio:', err);
                                        isPlaying = false;
                                        // Reset UI on error
                                        if (playIcon) playIcon.classList.remove('d-none');
                                        if (pauseIcon) pauseIcon.classList.add('d-none');
                                        alert('Failed to play audio: ' + (err.message || 'Unknown error'));
                                    });
                            } else {
                                // Fallback for older browsers
                                if (playIcon) playIcon.classList.add('d-none');
                                if (pauseIcon) pauseIcon.classList.remove('d-none');
                                isPlaying = true;
                                const update = () => {
                                    if (isPlaying) {
                                        updateProgress();
                                        animationFrame = requestAnimationFrame(update);
                                    }
                                };
                                update();
                            }
                        } catch (err) {
                            console.error('Exception playing audio:', err);
                            alert('Failed to play audio: ' + (err.message || 'Unknown error'));
                        }
                    }
                    return false;
                };
                
                // Attach with capture phase to ensure it fires first
                playBtn.addEventListener('click', playButtonClickHandler, true);
                
                // Also add mousedown as backup in case click is blocked
                playBtn.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }, true);
                
                // Function to update duration
                function updateDuration() {
                    if (audioElement.duration && isFinite(audioElement.duration) && audioElement.duration > 0) {
                        if (durationEl) {
                            durationEl.textContent = formatTime(audioElement.duration);
                        }
                        generateWaveform();
                    } else {
                        if (durationEl) {
                            durationEl.textContent = '--:--';
                        }
                    }
                }
                
                // Audio event listeners
                audioElement.addEventListener('loadedmetadata', function() {
                    updateDuration();
                });
                
                audioElement.addEventListener('loadeddata', function() {
                    updateDuration();
                });
                
                audioElement.addEventListener('canplay', function() {
                    updateDuration();
                });
                
                audioElement.addEventListener('timeupdate', updateProgress);
                
                audioElement.addEventListener('ended', function() {
                    if (playIcon) playIcon.classList.remove('d-none');
                    if (pauseIcon) pauseIcon.classList.add('d-none');
                    isPlaying = false;
                    audioElement.currentTime = 0;
                    updateProgress();
                    if (animationFrame) {
                        cancelAnimationFrame(animationFrame);
                        animationFrame = null;
                    }
                });
                
                audioElement.addEventListener('error', function(e) {
                    console.error('Audio loading error:', e);
                    if (playBtn) {
                        playBtn.disabled = true;
                        playBtn.style.opacity = '0.5';
                    }
                    if (durationEl) {
                        durationEl.textContent = 'Error';
                    }
                });
                
                // Waveform click to seek
                if (waveformContainer) {
                    waveformContainer.addEventListener('click', function(e) {
                        if (!audioElement.duration || !isFinite(audioElement.duration) || audioElement.duration <= 0) return;
                        const rect = this.getBoundingClientRect();
                        const clickX = e.clientX - rect.left;
                        const progress = clickX / rect.width;
                        audioElement.currentTime = progress * audioElement.duration;
                        updateProgress();
                    });
                }
                
                // Initialize waveform immediately
                generateWaveform();
                
                // Load metadata without calling load() which resets the element
                try {
                    // Check if metadata is already available
                    if (audioElement.readyState >= 1 && audioElement.duration && isFinite(audioElement.duration) && audioElement.duration > 0) {
                        // Metadata is available, update duration immediately
                        updateDuration();
                    } else {
                        // Wait for metadata to load
                        const checkDuration = () => {
                            if (audioElement.duration && isFinite(audioElement.duration) && audioElement.duration > 0) {
                                updateDuration();
                            }
                        };
                        
                        // Try checking after delays
                        setTimeout(checkDuration, 300);
                        setTimeout(checkDuration, 1000);
                    }
                } catch (error) {
                    console.warn('Error initializing audio metadata:', error);
                }
            });
        }
        
        // Initialize audio players on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAudioPlayers);
        } else {
            initAudioPlayers();
        }
        
        // Also initialize for dynamically added messages (with debounce to prevent duplicate calls)
        let audioInitTimeout = null;
        const audioObserver = new MutationObserver(function(mutations) {
            // Debounce to prevent multiple rapid calls
            if (audioInitTimeout) {
                clearTimeout(audioInitTimeout);
            }
            audioInitTimeout = setTimeout(() => {
                const newAudioPlayers = document.querySelectorAll('.audio-attachment-wa:not([data-initialized="true"])');
                if (newAudioPlayers.length > 0) {
                    initAudioPlayers();
                }
            }, 100);
        });
        
        audioObserver.observe(document.body, {
            childList: true,
            subtree: true
        });

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

        // ==== Custom Emoji Reactions ====
        let currentReactionMessageId = null;

        function showEmojiPickerForReaction(messageId) {
            currentReactionMessageId = messageId;
            const emojiPicker = document.getElementById('emoji-picker-container');
            if (emojiPicker) {
                emojiPicker.style.display = 'flex';
                // Set up emoji click handler for reactions
                setupEmojiPickerForReactions();
            }
        }

        function setupEmojiPickerForReactions() {
            const emojiButtons = document.querySelectorAll('#emoji-picker-container .emoji-btn');
            emojiButtons.forEach(btn => {
                btn.removeEventListener('click', handleEmojiReactionClick);
                btn.addEventListener('click', handleEmojiReactionClick);
            });
        }

        async function handleEmojiReactionClick(event) {
            const emoji = event.currentTarget.textContent.trim();
            if (currentReactionMessageId && emoji) {
                await sendReaction(currentReactionMessageId, emoji);
                // Close emoji picker
                const emojiPicker = document.getElementById('emoji-picker-container');
                if (emojiPicker) {
                    emojiPicker.style.display = 'none';
                }
                currentReactionMessageId = null;
            }
        }

        async function sendReaction(messageId, emoji) {
            try {
                const response = await fetch(`/messages/${messageId}/react`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ emoji })
                });

                if (!response.ok) {
                    throw new Error('Failed to send reaction');
                }

                const data = await response.json();
                // Reaction will be updated via real-time event
            } catch (error) {
                console.error('Error sending reaction:', error);
                showToast('Failed to send reaction', 'error');
            }
        }

        // ==== Export Chat ====
        async function handleExportChat() {
            const conversationId = state.conversationId;
            const groupId = state.groupId;
            
            if (!conversationId && !groupId) {
                alert('No conversation selected');
                return;
            }

            const confirmed = confirm('Export this conversation? This will download all messages as a file.');
            if (!confirmed) return;

            try {
                const url = conversationId 
                    ? `/api/v1/conversations/${conversationId}/export`
                    : `/api/v1/groups/${groupId}/export`;
                
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Export failed');
                }

                // Get filename from Content-Disposition header or generate one
                const contentDisposition = response.headers.get('Content-Disposition');
                let filename = 'chat_export.txt';
                if (contentDisposition) {
                    const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(contentDisposition);
                    if (matches != null && matches[1]) {
                        filename = matches[1].replace(/['"]/g, '');
                    }
                }

                // Download the file
                const blob = await response.blob();
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(downloadUrl);
                document.body.removeChild(a);

                showToast('Chat exported successfully', 'success');
            } catch (error) {
                console.error('Export error:', error);
                alert('Failed to export chat: ' + error.message);
            }
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