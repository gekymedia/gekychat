<script>
    (function() {
        'use strict';

        // ==== Configuration & Constants ====
        const CONFIG = {
            DEBOUNCE_DELAY: 300,
            SEARCH_LIMIT: 20,
            MAX_FILE_SIZE: 2 * 1024 * 1024, // 2MB
            ALLOWED_FILE_TYPES: ['image/jpeg', 'image/png', 'image/webp'],
            NOTIFICATION_TIMEOUT: 5000
        };

        // ==== State Management ====
        const state = {
            activeFilters: new Set(['all']),
            selectedUsers: new Set(),
            searchResults: [],
            notificationTimeout: null,
            currentPanel: null,
            notificationDismissed: false,
            notificationsEnabled: false,
            deniedBannerHidden: false,
            convBase: '/c/',
            groupBase: '/g/',
            userIds: []
        };

        // ==== DOM Elements Cache ====
        const elements = {};

        // ==== Initialization ====
        document.addEventListener('DOMContentLoaded', function() {
            initializeSidebar();
        });

        function initializeSidebar() {
            cacheElements();
            initializeState();
            setupEventListeners();
            initializeNotifications();
            setupPanelManagement();
            fixBaseUrls();
            setupRealTimeListeners(); // ✅ ADDED: Real-time integration
        }

        function fixBaseUrls() {
            if (state.convBase && state.convBase.includes('/user-')) {
                state.convBase = '/c/';
            }

            if (state.groupBase && !state.groupBase.endsWith('/')) {
                state.groupBase += '/';
            }
        }

        function cacheElements() {
            const selectors = {
                // Main containers
                sidebar: '#conversation-sidebar',
                conversationList: '#conversation-list',

                // Notification elements
                notificationPrompt: '#notification-prompt',
                enableNotifications: '#enable-notifications',
                dismissNotifications: '#dismiss-notifications',
                notifyDeniedInline: '#notify-denied-inline',
                dismissDeniedInline: '#dismiss-denied-inline',

                // Search elements
                chatSearch: '#chat-search',
                searchFilters: '#search-filters',
                searchResults: '#chat-search-results',

                // New Chat panel
                newChatPanel: '#sb-new-chat',
                newChatForm: '#sb-nc-form',
                newChatUserId: '#sb-nc-user-id',
                newChatList: '#sb-nc-list',
                newChatSearch: '#sb-nc-search',
                newChatCount: '#sb-nc-count',
                newChatStart: '#sb-nc-start',
                newChatPhoneInput: '#sb-nc-phone-input',
                newChatStartPhone: '#sb-nc-start-phone',

                // Create Group panel
                createGroupPanel: '#sb-create-group',
                createGroupForm: '#sb-gp-form',
                groupAvatarInput: '#sb-gp-avatar',
                groupAvatarPreview: '#sb-gp-avatar-preview',
                groupNameInput: '#sb-gp-name',
                groupDescInput: '#sb-gp-description',
                groupNameCounter: '#sb-gp-name-left',
                groupDescCounter: '#sb-gp-desc-left',
                groupFilter: '#sb-gp-filter',
                groupList: '#sb-gp-list',
                groupChips: '#sb-gp-chips',
                groupCount: '#sb-gp-count',
                groupSelectAll: '#sb-gp-select-all',
                groupClear: '#sb-gp-clear',
                groupCreate: '#sb-gp-create',

                // Buttons
                newChatBtn: '#new-chat-btn',
                newGroupBtn: '#new-group-btn'
            };

            Object.keys(selectors).forEach(key => {
                elements[key] = document.querySelector(selectors[key]);
            });

            // Cache base URLs
            state.convBase = elements.sidebar?.dataset?.convShowBase || '/c/';
            state.groupBase = elements.sidebar?.dataset?.groupShowBase || '/g/';
            state.userIds = JSON.parse(elements.sidebar?.dataset?.userIds || '[]');
        }

        function initializeState() {
            state.notificationDismissed = localStorage.getItem('notificationPromptDismissed') === 'true';
            state.notificationsEnabled = localStorage.getItem('notificationsEnabled') === 'true';
            state.deniedBannerHidden = localStorage.getItem('notificationDeniedBannerHidden') === 'true';
        }

        // ==== Real-time Event Integration ====
        function setupRealTimeListeners() {
            if (typeof Echo === 'undefined') {
                console.warn('Echo not available - real-time features disabled');
                return;
            }

            // Listen for new messages to update unread counts
            Echo.private(`user.${window.currentUserId}`)
                .listen('MessageSent', (e) => {
                    if (e.message.sender_id !== window.currentUserId) {
                        handleNewMessage(e.message);
                    }
                })
                .listen('MessageRead', (e) => {
                    handleMessagesRead(e.conversation_id, e.message_ids, e.reader_id);
                })
                .listen('MessageDeleted', (e) => {
                    handleMessageDeleted(e.message_id, e.deleted_by);
                })
                .listen('MessageStatusUpdated', (e) => {
                    handleMessageStatusUpdate(e);
                });

            // Listen for group updates
            Echo.join(`group.updates.${window.currentUserId}`)
                .listen('GroupUpdated', (e) => {
                    handleGroupUpdate(e);
                });

            console.log('✅ Real-time listeners initialized');
        }

        function handleNewMessage(message) {
            const isGroup = message.is_group || false;
            const targetId = isGroup ? message.group_id : message.conversation_id;
            const basePath = isGroup ? state.groupBase : state.convBase;
            
            // Update unread count for the conversation/group
            const conversationItem = document.querySelector(`[href*="${basePath}${targetId}"]`);
            
            if (conversationItem) {
                // Increment unread count
                const currentCount = parseInt(conversationItem.dataset.unread) || 0;
                const newCount = currentCount + 1;
                conversationItem.dataset.unread = newCount;

                // Update or create unread badge
                let badge = conversationItem.querySelector('.unread-badge');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'unread-badge rounded-pill';
                    badge.setAttribute('aria-label', `${newCount} unread messages`);
                    
                    const timeContainer = conversationItem.querySelector('.d-flex.justify-content-between');
                    if (timeContainer) {
                        timeContainer.appendChild(badge);
                    }
                }
                badge.textContent = newCount;

                // Add unread styling
                conversationItem.classList.add('unread', 'new-message');
                
                // Remove animation class after animation completes
                setTimeout(() => {
                    conversationItem.classList.remove('new-message');
                }, 600);

                // Update last message preview
                updateLastMessagePreview(conversationItem, message);
                
                // Move conversation to top
                moveConversationToTop(conversationItem);

                // Show notification toast
                if (!document.hasFocus()) {
                    showNewMessageNotification(message);
                }
            } else if (!isGroup) {
                // New conversation - might need to refresh sidebar
                showRealTimeToast(
                    `New message from ${message.sender?.name || 'Someone'}`,
                    'info',
                    {
                        actionText: 'View',
                        actionCallback: () => {
                            window.location.href = `/c/${message.conversation_id}`;
                        }
                    }
                );
            }
        }

        function handleMessagesRead(conversationId, messageIds, readerId) {
            if (readerId === window.currentUserId) {
                const conversationItem = document.querySelector(`[href*="/c/${conversationId}"]`);
                if (conversationItem) {
                    // Clear unread count
                    conversationItem.dataset.unread = '0';
                    const badge = conversationItem.querySelector('.unread-badge');
                    if (badge) badge.remove();
                    conversationItem.classList.remove('unread');
                }
            }
        }

        function handleMessageDeleted(messageId, deletedBy) {
            // If we're on the conversation where message was deleted, refresh the view
            const currentPath = window.location.pathname;
            if (currentPath.includes('/c/') || currentPath.includes('/g/')) {
                const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
                if (messageElement) {
                    messageElement.innerHTML = '<div class="text-muted fst-italic">Message deleted</div>';
                    messageElement.classList.add('text-muted', 'fst-italic');
                }
            }
        }

        function handleMessageStatusUpdate(event) {
            // Handle message status updates (delivered, read, etc.)
            if (event.status === 'read' && event.user_id !== window.currentUserId) {
                // Someone read your message - could update UI if needed
            }
        }

        function handleGroupUpdate(event) {
            const { group_id, update_type, changed_data, updated_by } = event;
            
            if (updated_by === window.currentUserId) return; // Don't show our own actions
            
            switch (update_type) {
                case 'member_added':
                    showRealTimeToast(`New member joined the group`, 'info');
                    break;
                case 'member_removed':
                    showRealTimeToast(`Member left the group`, 'warning');
                    break;
                case 'member_promoted':
                    showRealTimeToast(`Member promoted to admin`, 'success');
                    break;
                case 'info_updated':
                    showRealTimeToast(`Group info updated`, 'info');
                    // Update group header if we're viewing this group
                    if (window.location.pathname.includes(`/g/${group_id}`)) {
                        updateGroupHeader(changed_data);
                    }
                    break;
                case 'ownership_transferred':
                    showRealTimeToast(`Group ownership transferred`, 'warning');
                    break;
            }
        }

        function updateLastMessagePreview(conversationItem, message) {
            const messagePreview = conversationItem.querySelector('.text-muted');
            if (messagePreview) {
                let previewText = '';
                
                if (message.attachments && message.attachments.length > 0) {
                    previewText = '📎 Attachment';
                } else if (message.body) {
                    previewText = message.body.length > 50 
                        ? message.body.substring(0, 50) + '...' 
                        : message.body;
                } else {
                    previewText = 'New message';
                }
                
                messagePreview.textContent = previewText;
            }
            
            // Update timestamp
            const timeElement = conversationItem.querySelector('small.text-muted');
            if (timeElement) {
                timeElement.textContent = 'Just now';
            }
        }

        function moveConversationToTop(conversationItem) {
            const conversationList = document.getElementById('conversation-list');
            if (conversationList && conversationItem.parentNode === conversationList) {
                // Find the first non-header element to insert before
                const firstConversation = conversationList.querySelector('.conversation-item:not(.section-header)');
                if (firstConversation && firstConversation !== conversationItem) {
                    conversationList.insertBefore(conversationItem, firstConversation);
                }
            }
        }

        function updateGroupHeader(changedData) {
            // Update group name if changed
            if (changedData.new_data?.name) {
                const groupNameElement = document.querySelector('.group-header-name');
                if (groupNameElement) {
                    groupNameElement.textContent = changedData.new_data.name;
                }
            }
            
            // Update group description if changed
            if (changedData.new_data?.description) {
                const groupDescElement = document.querySelector('.group-description');
                if (groupDescElement) {
                    groupDescElement.textContent = changedData.new_data.description;
                }
            }
        }

        function showNewMessageNotification(message) {
            const conversationName = message.conversation?.name || message.group?.name || 'Unknown';
            const senderName = message.sender?.name || 'Someone';
            const isGroup = message.is_group || false;
            
            showRealTimeToast(
                `New message from ${senderName} ${isGroup ? `in ${conversationName}` : ''}`,
                'info',
                {
                    actionText: 'View',
                    actionCallback: () => {
                        const path = isGroup ? `/g/${message.group_id}` : `/c/${message.conversation_id}`;
                        window.location.href = path;
                    },
                    duration: 8000
                }
            );
        }

        // ==== Event Listeners Setup ====
        function setupEventListeners() {
            setupNotificationListeners();
            setupSearchListeners();
            setupNewChatListeners();
            setupCreateGroupListeners();
            setupPanelListeners();
        }

        // ==== Notification Management ====
        function initializeNotifications() {
            if (!('Notification' in window)) return;

            const { permission } = Notification;

            if (!state.notificationDismissed && !state.notificationsEnabled && permission === 'default') {
                showNotificationPrompt();
            }

            if (permission === 'denied' && !state.deniedBannerHidden) {
                showDeniedBanner();
            }
        }

        function setupNotificationListeners() {
            elements.enableNotifications?.addEventListener('click', handleEnableNotifications);
            elements.dismissNotifications?.addEventListener('click', handleDismissNotifications);
            elements.dismissDeniedInline?.addEventListener('click', handleDismissDeniedBanner);
        }

        async function handleEnableNotifications() {
            try {
                const permission = await Notification.requestPermission();

                if (permission === 'granted') {
                    localStorage.setItem('notificationsEnabled', 'true');
                    state.notificationsEnabled = true;
                    hideNotificationPrompt();
                    showToast('Notifications enabled', 'success');

                    try {
                        new Notification('GekyChat', {
                            body: 'You\'ll see alerts for new messages.',
                            icon: '/icons/icon-192x192.png'
                        });
                    } catch (e) {
                        // Silent fail for notification creation
                    }
                } else if (permission === 'denied') {
                    localStorage.setItem('notificationPromptDismissed', 'true');
                    state.notificationDismissed = true;
                    hideNotificationPrompt();
                    showToast('Permission denied', 'warning');
                    showDeniedBanner();
                }
            } catch (error) {
                console.error('Notification permission error:', error);
                showToast('Failed to enable notifications', 'error');
            }
        }

        function handleDismissNotifications() {
            localStorage.setItem('notificationPromptDismissed', 'true');
            state.notificationDismissed = true;
            hideNotificationPrompt();
        }

        function handleDismissDeniedBanner() {
            localStorage.setItem('notificationDeniedBannerHidden', 'true');
            state.deniedBannerHidden = true;
            hideDeniedBanner();
        }

        function showNotificationPrompt() {
            if (elements.notificationPrompt) {
                elements.notificationPrompt.style.display = 'block';
            }
        }

        function hideNotificationPrompt() {
            if (elements.notificationPrompt) {
                elements.notificationPrompt.style.display = 'none';
            }
        }

        function showDeniedBanner() {
            if (elements.notifyDeniedInline) {
                elements.notifyDeniedInline.classList.remove('d-none');
            }
        }

        function hideDeniedBanner() {
            if (elements.notifyDeniedInline) {
                elements.notifyDeniedInline.classList.add('d-none');
            }
        }

        // ==== Search Functionality ====
        function setupSearchListeners() {
            elements.chatSearch?.addEventListener('input', debounce(handleGlobalSearch, CONFIG.DEBOUNCE_DELAY));
            elements.searchFilters?.addEventListener('click', handleFilterClick);
            document.addEventListener('click', handleClickOutsideSearch);
            elements.searchResults?.addEventListener('click', handleSearchResultClick);
        }

        async function handleGlobalSearch(event) {
            const query = event.target.value.trim();

            if (!query) {
                await showRecentChats();
                return;
            }

            await performSearch(query);
        }

        async function showRecentChats() {
            try {
                const response = await apiCall('/api/search?q=&limit=10');
                const recentChats = response?.results?.recent_chats || [];

                if (recentChats.length > 0) {
                    renderSearchResults([{
                        type: 'recent_header',
                        title: 'Recent Chats',
                        items: recentChats
                    }]);
                    showSearchResults();
                } else {
                    hideSearchResults();
                }
            } catch (error) {
                console.error('Failed to load recent chats:', error);
                hideSearchResults();
            }
        }

        async function performSearch(query) {
            try {
                // First check local conversations and groups
                const localResults = performLocalSearch(query);
                if (localResults.length > 0) {
                    renderSearchResults(localResults, query);
                    showSearchResults();
                    return;
                }

                // Fall back to API search
                const params = new URLSearchParams({
                    q: query,
                    limit: CONFIG.SEARCH_LIMIT
                });

                if (state.activeFilters.size > 0 && !state.activeFilters.has('all')) {
                    params.append('filters', Array.from(state.activeFilters).join(','));
                }

                const response = await apiCall(`/api/search?${params.toString()}`);
                const results = response?.results || [];

                if (results.length > 0) {
                    renderSearchResults(results, query);
                    showSearchResults();
                } else {
                    const isPhoneQuery = isPhoneNumber(query);
                    if (isPhoneQuery) {
                        await handlePhoneNumberSearch(query);
                    } else {
                        renderNoResults(query);
                    }
                    showSearchResults();
                }
            } catch (error) {
                console.error('Search error:', error);
                
                // Fallback to local search only
                const localResults = performLocalSearch(query);
                if (localResults.length > 0) {
                    renderSearchResults(localResults, query);
                    showSearchResults();
                } else {
                    renderSearchError(error);
                    showSearchResults();
                }
            }
        }

        function performLocalSearch(query) {
            const results = [];
            const lowerQuery = query.toLowerCase();
            
            // Search conversations
            const conversationItems = document.querySelectorAll('.conversation-item');
            conversationItems.forEach(item => {
                const name = item.dataset.name || '';
                const phone = item.dataset.phone || '';
                const lastMessage = item.dataset.last || '';
                
                if (name.includes(lowerQuery) || phone.includes(lowerQuery) || lastMessage.includes(lowerQuery)) {
                    const href = item.getAttribute('href');
                    const isGroup = href.includes('/g/');
                    const id = href.split('/').pop();
                    
                    results.push({
                        type: isGroup ? 'group' : 'conversation',
                        id: id,
                        display_name: item.querySelector('.fw-semibold')?.textContent || 'Conversation',
                        phone: phone,
                        snippet: lastMessage,
                        avatar_url: item.querySelector('.avatar-img')?.src || null
                    });
                }
            });
            
            return results;
        }

        function isPhoneNumber(str) {
            const phoneRegex = /^[\+]?[0-9\s\-\(\)\.]{7,}$/;
            return phoneRegex.test(str.replace(/\s/g, ''));
        }

        async function handlePhoneNumberSearch(phone) {
            try {
                const contactCheck = await checkIfPhoneIsContact(phone);

                if (contactCheck.isContact) {
                    renderContactResult(contactCheck.contact, phone);
                } else {
                    renderNewContactOption(phone, contactCheck.isRegistered);
                }
            } catch (error) {
                console.error('Phone search error:', error);
                renderPhoneSearchError(phone, error);
            }
        }

        async function checkIfPhoneIsContact(phone) {
            try {
                const response = await apiCall(`/api/contacts?q=${encodeURIComponent(phone)}&limit=1`);

                if (response?.data?.length > 0) {
                    const contact = response.data[0];
                    return {
                        isContact: true,
                        contact: contact,
                        isRegistered: contact.is_registered
                    };
                }

                const resolveResponse = await apiCall('/api/contacts/resolve', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    body: JSON.stringify({
                        phones: [phone]
                    })
                });

                const userData = resolveResponse?.data?.[0];
                return {
                    isContact: false,
                    isRegistered: userData?.is_registered || false,
                    user: userData?.user || null
                };
            } catch (error) {
                console.error('Contact check error:', error);
                return {
                    isContact: false,
                    isRegistered: false,
                    error: error.message
                };
            }
        }

        function renderSearchResults(results, query) {
            if (!elements.searchResults) return;

            const html = results.map(item => {
                switch (item.type) {
                    case 'recent_header':
                        return renderRecentChats(item);
                    case 'contact':
                    case 'user':
                    case 'group':
                    case 'message':
                    case 'phone_suggestion':
                        return renderSearchItem(item);
                    default:
                        return '';
                }
            }).join('');

            elements.searchResults.innerHTML = html;
        }

        function renderContactResult(contact, phone) {
            if (!elements.searchResults) return;

            const badgeClass = contact.is_favorite ? 'bg-warning' : 'bg-success';
            const badgeText = contact.is_favorite ? 'Favorite' : 'Contact';

            elements.searchResults.innerHTML = `
                <div class="list-group-item">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center flex-grow-1 min-width-0">
                            <div class="avatar me-3 bg-avatar position-relative">
                                ${contact.avatar_url ? `
                                    <img src="${escapeHtml(contact.avatar_url)}" 
                                         class="rounded-circle w-100 h-100" 
                                         style="object-fit: cover;"
                                         onerror="this.style.display='none'"
                                         alt="">
                                ` : (contact.display_name?.charAt(0) || 'C')}
                                <span class="position-absolute bottom-0 end-0 ${badgeClass} rounded-circle border border-2 border-white"
                                      style="width: 12px; height: 12px;"></span>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="fw-semibold text-truncate">${escapeHtml(contact.display_name)}</div>
                                    <span class="badge ${badgeClass} rounded-pill">${badgeText}</span>
                                </div>
                                <div class="small text-muted text-truncate">${escapeHtml(contact.phone)}</div>
                                ${contact.note ? `
                                    <div class="small text-muted text-truncate">${escapeHtml(contact.note)}</div>
                                ` : ''}
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            ${contact.is_registered ? `
                                <button class="btn btn-wa btn-sm start-chat-contact" 
                                        data-contact-id="${contact.id}"
                                        data-user-id="${contact.user_id}">
                                    Message
                                </button>
                            ` : `
                                <button class="btn btn-outline-wa btn-sm invite-contact" 
                                        data-phone="${escapeHtml(contact.phone)}">
                                    Invite
                                </button>
                            `}
                            <button class="btn btn-outline-secondary btn-sm view-contact" 
                                    data-contact-id="${contact.id}">
                                View
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderNewContactOption(phone, isRegistered) {
            if (!elements.searchResults) return;

            elements.searchResults.innerHTML = `
                <div class="list-group-item">
                    <div class="text-center py-3">
                        <i class="bi bi-person-plus display-6 text-muted mb-3"></i>
                        <div class="mb-2">
                            <strong>New Contact</strong>
                        </div>
                        <div class="small text-muted mb-3">
                            ${escapeHtml(phone)} ${isRegistered ? 'is on GekyChat' : 'is not on GekyChat yet'}
                        </div>
                        <div class="d-flex gap-2 justify-content-center">
                            <button class="btn btn-wa btn-sm add-to-contacts" 
                                    data-phone="${escapeHtml(phone)}"
                                    data-registered="${isRegistered}">
                                <i class="bi bi-person-plus me-1"></i> Add to Contacts
                            </button>
                            ${isRegistered ? `
                                <button class="btn btn-outline-wa btn-sm start-chat-new" 
                                        data-phone="${escapeHtml(phone)}">
                                    Message
                                </button>
                            ` : `
                                <button class="btn btn-outline-wa btn-sm invite-new" 
                                        data-phone="${escapeHtml(phone)}">
                                    Invite
                                </button>
                            `}
                        </div>
                    </div>
                </div>
            `;
        }

        function renderNoResults(query) {
            if (!elements.searchResults) return;

            elements.searchResults.innerHTML = `
                <div class="list-group-item text-center text-muted py-4">
                    <i class="bi bi-search display-6 text-muted mb-3"></i>
                    <div class="mb-2">No results found for "${escapeHtml(query)}"</div>
                    ${isPhoneNumber(query) ? `
                        <div class="mt-3">
                            <button class="btn btn-outline-wa btn-sm add-to-contacts" 
                                    data-phone="${escapeHtml(query)}">
                                Add "${escapeHtml(query)}" to Contacts
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        function renderPhoneSearchError(phone, error) {
            if (!elements.searchResults) return;

            elements.searchResults.innerHTML = `
                <div class="list-group-item text-danger">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Search error</strong>
                    </div>
                    <small class="text-muted">Failed to search for ${escapeHtml(phone)}</small>
                    <div class="mt-2">
                        <button class="btn btn-outline-secondary btn-sm" onclick="window.sidebarApp.startChatWithPhone('${escapeHtml(phone)}')">
                            Try Starting Chat Anyway
                        </button>
                    </div>
                </div>
            `;
        }

        function renderRecentChats(section) {
            return `
                <div class="list-group-item small text-muted fw-semibold">${escapeHtml(section.title)}</div>
                ${section.items.map(chat => `
                    <a href="${state.convBase}${chat.conversation?.slug || chat.conversation?.id}" 
                       class="list-group-item list-group-item-action d-flex align-items-center search-result-item"
                       data-type="conversation" data-id="${chat.conversation?.id}">
                        <div class="avatar me-3 bg-avatar">
                            ${(chat.display_name || 'C').charAt(0)}
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="fw-semibold text-truncate">${escapeHtml(chat.display_name || 'Conversation')}</div>
                            <div class="small text-muted text-truncate">${escapeHtml(chat.last_message || 'No messages')}</div>
                        </div>
                        ${chat.unread_count > 0 ? `
                            <span class="unread-badge rounded-pill ms-2">${chat.unread_count}</span>
                        ` : ''}
                    </a>
                `).join('')}
            `;
        }

        function renderSearchItem(item) {
            const badges = {
                contact: {
                    class: 'bg-success',
                    text: 'Contact'
                },
                user: {
                    class: 'bg-primary',
                    text: 'User'
                },
                group: {
                    class: 'bg-warning',
                    text: 'Group'
                },
                message: {
                    class: 'bg-info',
                    text: 'Message'
                },
                phone_suggestion: {
                    class: 'bg-secondary',
                    text: 'New'
                }
            };

            const badge = badges[item.type] || {};
            const href = generateItemUrl(item);
            const actionText = getActionText(item.type);
            const clickHandler = getClickHandler(item);

            return `
                <div class="list-group-item list-group-item-action d-flex align-items-center justify-content-between search-result-item"
                     data-type="${item.type}" 
                     data-id="${item.id || ''}"
                     ${item.phone ? `data-phone="${escapeHtml(item.phone)}"` : ''}
                     ${clickHandler ? `onclick="${clickHandler}"` : `href="${href}"`}
                     style="cursor: pointer;">
                    <div class="d-flex align-items-center flex-grow-1 min-width-0">
                        <div class="avatar me-3 bg-avatar position-relative">
                            ${item.avatar_url ? `
                                <img src="${escapeHtml(item.avatar_url)}" 
                                     class="rounded-circle w-100 h-100" 
                                     style="object-fit: cover;"
                                     onerror="this.style.display='none'"
                                     alt="">
                                <span class="position-absolute bottom-0 end-0 ${badge.class} rounded-circle border border-2 border-white"
                                      style="width: 12px; height: 12px;"></span>
                            ` : (item.display_name?.charAt(0) || '?')}
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex align-items-center gap-2">
                                <div class="fw-semibold text-truncate">${escapeHtml(item.display_name || 'Item')}</div>
                                ${badge.text ? `
                                    <span class="badge ${badge.class} rounded-pill">${badge.text}</span>
                                ` : ''}
                            </div>
                            ${item.phone ? `
                                <div class="small text-muted text-truncate">${escapeHtml(item.phone)}</div>
                            ` : item.member_count ? `
                                <div class="small text-muted">${item.member_count} members</div>
                            ` : item.snippet ? `
                                <div class="small text-muted text-truncate">${escapeHtml(item.snippet)}</div>
                            ` : ''}
                        </div>
                    </div>
                    <span class="btn btn-outline-wa btn-sm">${actionText}</span>
                </div>
            `;
        }

        function generateItemUrl(item) {
            if (!item) return '#';

            switch (item.type) {
                case 'group':
                    return `${state.groupBase}${item.slug || item.id}`;
                case 'message':
                case 'conversation':
                    return `${state.convBase}${item.conversation_slug || item.slug || item.id}`;
                case 'contact':
                case 'user':
                    return '#';
                default:
                    return '#';
            }
        }

        function getActionText(type) {
            const actions = {
                contact: 'Message',
                user: 'Message',
                group: 'Open',
                message: 'View',
                phone_suggestion: 'Chat'
            };
            return actions[type] || 'Open';
        }

        function getClickHandler(item) {
            switch (item.type) {
                case 'contact':
                case 'user':
                    return `window.sidebarApp.handleUserClick('${item.id}', '${escapeHtml(item.phone || '')}', ${item.is_registered || false})`;
                case 'phone_suggestion':
                    return `window.sidebarApp.startChatWithPhone('${escapeHtml(item.phone)}')`;
                case 'message':
                    return `window.sidebarApp.handleMessageClick('${item.conversation_slug}', '${item.id}')`;
                default:
                    return null;
            }
        }

        function renderSearchError(error) {
            if (!elements.searchResults) return;

            elements.searchResults.innerHTML = `
                <div class="list-group-item text-danger">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Search temporarily unavailable</strong>
                    </div>
                    <small class="text-muted">${escapeHtml(error.message)}</small>
                    <div class="mt-2">
                        <button class="btn btn-outline-secondary btn-sm" onclick="window.location.reload()">
                            Retry
                        </button>
                    </div>
                </div>
            `;
        }

        function handleFilterClick(event) {
            const button = event.target.closest('.filter-btn');
            if (!button) return;

            const filter = button.dataset.filter;

            if (filter === 'all') {
                state.activeFilters.clear();
                state.activeFilters.add('all');
            } else {
                state.activeFilters.delete('all');
                if (state.activeFilters.has(filter)) {
                    state.activeFilters.delete(filter);
                } else {
                    state.activeFilters.add(filter);
                }

                if (state.activeFilters.size === 0) {
                    state.activeFilters.add('all');
                }
            }

            updateFilterButtons();

            const query = elements.chatSearch?.value.trim();
            if (query) {
                performSearch(query);
            }
        }

        function updateFilterButtons() {
            const buttons = elements.searchFilters?.querySelectorAll('.filter-btn') || [];
            buttons.forEach(button => {
                const filter = button.dataset.filter;
                const isActive = state.activeFilters.has(filter);

                button.classList.toggle('active', isActive);
                button.setAttribute('aria-pressed', isActive.toString());
            });
        }

        function handleClickOutsideSearch(event) {
            if (!elements.searchResults || !elements.chatSearch || !elements.searchFilters) return;

            const isClickInside = elements.searchResults.contains(event.target) ||
                elements.chatSearch.contains(event.target) ||
                elements.searchFilters.contains(event.target);

            if (!isClickInside) {
                hideSearchResults();
            }
        }

        function handleSearchResultClick(event) {
            const resultItem = event.target.closest('.search-result-item');
            const phoneButton = event.target.closest('.start-phone-chat');
            const contactButton = event.target.closest('.start-chat-contact');
            const inviteButton = event.target.closest('.invite-contact');
            const viewButton = event.target.closest('.view-contact');
            const addContactButton = event.target.closest('.add-to-contacts');
            const startChatNewButton = event.target.closest('.start-chat-new');
            const inviteNewButton = event.target.closest('.invite-new');

            if (phoneButton) {
                event.preventDefault();
                const phone = phoneButton.dataset.phone;
                startChatWithPhone(phone);
            } else if (contactButton) {
                event.preventDefault();
                const contactId = contactButton.dataset.contactId;
                const userId = contactButton.dataset.userId;
                startChatWithContact(contactId, userId);
            } else if (inviteButton) {
                event.preventDefault();
                const phone = inviteButton.dataset.phone;
                showInviteModal(phone);
            } else if (viewButton) {
                event.preventDefault();
                const contactId = viewButton.dataset.contactId;
                viewContactDetails(contactId);
            } else if (addContactButton) {
                event.preventDefault();
                const phone = addContactButton.dataset.phone;
                const isRegistered = addContactButton.dataset.registered === 'true';
                openAddContactModal(phone, isRegistered);
            } else if (startChatNewButton) {
                event.preventDefault();
                const phone = startChatNewButton.dataset.phone;
                startChatWithPhone(phone);
            } else if (inviteNewButton) {
                event.preventDefault();
                const phone = inviteNewButton.dataset.phone;
                showInviteModal(phone);
            } else if (resultItem?.dataset.type === 'phone_suggestion') {
                event.preventDefault();
                const phone = resultItem.dataset.phone;
                startChatWithPhone(phone);
            }
        }

        function showSearchResults() {
            if (elements.searchResults && elements.searchFilters) {
                elements.searchResults.classList.remove('d-none');
                elements.searchFilters.style.display = 'flex';
            }
        }

        function hideSearchResults() {
            if (elements.searchResults && elements.searchFilters) {
                elements.searchResults.classList.add('d-none');
                elements.searchFilters.style.display = 'none';
            }
        }

        // ==== Contact Management ====
        function openAddContactModal(phone, isRegistered) {
            const modalHtml = `
                <div class="modal fade" id="addContactModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add to Contacts</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addContactForm">
                                    <div class="mb-3">
                                        <label for="contactName" class="form-label">Name</label>
                                        <input type="text" class="form-control" id="contactName" 
                                               placeholder="Enter contact name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="contactPhone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="contactPhone" 
                                               value="${escapeHtml(phone)}" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label for="contactNote" class="form-label">Note (Optional)</label>
                                        <textarea class="form-control" id="contactNote" rows="2" 
                                                  placeholder="Add a note about this contact"></textarea>
                                    </div>
                                    ${isRegistered ? `
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> This person is on GekyChat
                                        </div>
                                    ` : `
                                        <div class="alert alert-warning">
                                            <i class="bi bi-exclamation-triangle"></i> This person is not on GekyChat yet
                                        </div>
                                    `}
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-wa" id="saveContactBtn">Save Contact</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const existingModal = document.getElementById('addContactModal');
            if (existingModal) existingModal.remove();

            document.body.insertAdjacentHTML('beforeend', modalHtml);

            const modal = new bootstrap.Modal(document.getElementById('addContactModal'));
            modal.show();

            document.getElementById('saveContactBtn').addEventListener('click', async () => {
                await saveNewContact(phone, modal);
            });
        }

        async function saveNewContact(phone, modal) {
            const name = document.getElementById('contactName').value.trim();
            const note = document.getElementById('contactNote').value.trim();

            if (!name) {
                showToast('Please enter a contact name', 'error');
                return;
            }

            try {
                const response = await apiCall('/api/contacts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    body: JSON.stringify({
                        display_name: name,
                        phone: phone,
                        note: note,
                        is_favorite: false
                    })
                });

                if (response.success) {
                    showToast('Contact saved successfully', 'success');
                    modal.hide();

                    const currentQuery = elements.chatSearch?.value;
                    if (currentQuery) {
                        performSearch(currentQuery);
                    }
                } else {
                    showToast(response.message || 'Failed to save contact', 'error');
                }
            } catch (error) {
                console.error('Save contact error:', error);
                showToast('Error saving contact', 'error');
            }
        }

        async function startChatWithContact(contactId, userId) {
            try {
                const response = await apiCall('/api/chat/start', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    body: JSON.stringify({
                        contact_id: contactId,
                        user_id: userId
                    })
                });

                if (response.success && response.redirect_url) {
                    window.location.href = response.redirect_url;
                } else {
                    showToast('Failed to start chat', 'error');
                }
            } catch (error) {
                console.error('Start chat with contact error:', error);
                showToast('Error starting chat', 'error');
            }
        }

        function viewContactDetails(contactId) {
            window.location.href = `/contacts/${contactId}`;
        }

        async function startChatWithPhone(phone) {
            try {
                const contactCheck = await checkIfPhoneIsContact(phone);

                if (contactCheck.isContact && contactCheck.contact.is_registered) {
                    await startChatWithContact(contactCheck.contact.id, contactCheck.contact.user_id);
                } else {
                    const response = await apiCall('/api/start-chat-with-phone', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                        },
                        body: JSON.stringify({
                            phone
                        })
                    });

                    if (response.success) {
                        if (response.not_registered) {
                            showInviteModal(phone, response.invite);
                        } else if (response.redirect_url) {
                            window.location.href = response.redirect_url;
                        }
                    } else {
                        showToast(response.message || 'Failed to start chat', 'error');
                    }
                }
            } catch (error) {
                console.error('Start chat error:', error);
                showToast('Error starting chat. Please try again.', 'error');
            }
        }

        function showInviteModal(phone, inviteData = null) {
            const modalHtml = `
                <div class="modal fade" id="inviteModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Invite to GekyChat</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <i class="bi bi-person-plus display-4 text-warning mb-3"></i>
                                <h6 class="mb-3">${escapeHtml(phone)} is not on GekyChat yet</h6>
                                <p class="text-muted mb-4">Invite them to join and start chatting!</p>
                                
                                ${inviteData ? `
                                    <div class="mb-3">
                                        <label class="form-label">Invite Link</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="inviteLink" 
                                                   value="${escapeHtml(inviteData.link)}" readonly>
                                            <button class="btn btn-outline-wa" type="button" id="copyInviteLink">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                    </div>
                                ` : ''}
                                
                                <div class="d-flex gap-2 justify-content-center">
                                    <button class="btn btn-outline-wa" id="shareInvite">
                                        <i class="bi bi-share me-1"></i> Share Invite
                                    </button>
                                    <button class="btn btn-wa" id="sendSmsInvite">
                                        <i class="bi bi-chat-text me-1"></i> Send SMS
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const existingModal = document.getElementById('inviteModal');
            if (existingModal) existingModal.remove();

            document.body.insertAdjacentHTML('beforeend', modalHtml);

            const modal = new bootstrap.Modal(document.getElementById('inviteModal'));
            modal.show();

            // Add event listeners for invite actions
            document.getElementById('copyInviteLink')?.addEventListener('click', copyInviteLink);
            document.getElementById('shareInvite')?.addEventListener('click', shareInvite);
            document.getElementById('sendSmsInvite')?.addEventListener('click', () => sendSmsInvite(phone));
        }

        function copyInviteLink() {
            const inviteLink = document.getElementById('inviteLink');
            if (inviteLink) {
                inviteLink.select();
                document.execCommand('copy');
                showToast('Invite link copied to clipboard', 'success');
            }
        }

        function shareInvite() {
            if (navigator.share) {
                navigator.share({
                    title: 'Join me on GekyChat',
                    text: 'Let\'s chat on GekyChat!',
                    url: document.getElementById('inviteLink')?.value || window.location.origin
                }).catch(() => {
                    copyInviteLink();
                });
            } else {
                copyInviteLink();
            }
        }

        function sendSmsInvite(phone) {
            const message = encodeURIComponent("Hey! Let's chat on GekyChat. Download it from: " + (document
                .getElementById('inviteLink')?.value || window.location.origin));
            window.open(`sms:${phone}?body=${message}`, '_blank');
        }

        // ==== New Chat Panel ====
        function setupNewChatListeners() {
            if (!elements.newChatPanel) return;

            elements.newChatList?.addEventListener('click', handleContactSelection);
            elements.newChatSearch?.addEventListener('input', debounce(filterContacts, CONFIG.DEBOUNCE_DELAY));
            elements.newChatStart?.addEventListener('click', handleStartChat);
            elements.newChatStartPhone?.addEventListener('click', handleStartPhoneChat);
            elements.newChatPanel.addEventListener('hidden.bs.collapse', resetNewChatPanel);
        }

        function handleContactSelection(event) {
            const row = event.target.closest('.sb-nc-row');
            if (!row) return;

            const activeRows = elements.newChatList?.querySelectorAll('.sb-nc-row.active') || [];
            activeRows.forEach(r => r.classList.remove('active'));

            row.classList.add('active');

            if (elements.newChatUserId) {
                elements.newChatUserId.value = row.dataset.id || '';
            }

            if (elements.newChatStart) {
                elements.newChatStart.disabled = !row.dataset.id;
            }
        }

        function filterContacts() {
            const query = elements.newChatSearch?.value.toLowerCase().trim() || '';
            let visibleCount = 0;

            const rows = elements.newChatList?.querySelectorAll('.sb-nc-row') || [];
            rows.forEach(row => {
                const name = row.dataset.name || '';
                const phone = row.dataset.phone || '';
                const isVisible = !query || name.includes(query) || phone.includes(query);

                row.style.display = isVisible ? 'flex' : 'none';
                if (isVisible) visibleCount++;
            });

            if (elements.newChatCount) {
                elements.newChatCount.textContent =
                    `${visibleCount} ${visibleCount === 1 ? 'contact' : 'contacts'}`;
            }
        }

        function handleStartChat() {
            if (elements.newChatUserId?.value && elements.newChatForm) {
                elements.newChatForm.submit();
            }
        }

        function handleStartPhoneChat() {
            const rawPhone = elements.newChatPhoneInput?.value.trim();
            if (!rawPhone) {
                showToast('Please enter a phone number', 'error');
                return;
            }

            const plus = rawPhone.startsWith('+') ? '+' : '';
            const digits = rawPhone.replace(/\D+/g, '');
            const formattedPhone = plus + digits;

            startChatWithPhone(formattedPhone);
        }

        function resetNewChatPanel() {
            if (elements.newChatForm) {
                elements.newChatForm.reset();
            }

            if (elements.newChatUserId) {
                elements.newChatUserId.value = '';
            }

            if (elements.newChatStart) {
                elements.newChatStart.disabled = true;
            }

            const activeRows = elements.newChatList?.querySelectorAll('.sb-nc-row.active') || [];
            activeRows.forEach(row => row.classList.remove('active'));

            if (elements.newChatSearch) {
                elements.newChatSearch.value = '';
                filterContacts();
            }
        }

        // ==== Create Group Panel ====
        function setupCreateGroupListeners() {
            if (!elements.createGroupPanel) return;

            elements.groupAvatarInput?.addEventListener('change', handleAvatarUpload);
            elements.groupNameInput?.addEventListener('input', () => updateCharCounter(elements.groupNameInput,
                elements.groupNameCounter, 64));
            elements.groupDescInput?.addEventListener('input', () => updateCharCounter(elements.groupDescInput,
                elements.groupDescCounter, 200));
            elements.groupFilter?.addEventListener('input', debounce(filterGroupMembers, CONFIG.DEBOUNCE_DELAY));
            elements.groupList?.addEventListener('change', handleMemberSelection);
            elements.groupSelectAll?.addEventListener('click', handleSelectAll);
            elements.groupClear?.addEventListener('click', handleClearSelection);
            elements.createGroupForm?.addEventListener('submit', handleGroupCreation);
            elements.createGroupPanel.addEventListener('hidden.bs.collapse', resetCreateGroupPanel);

            updateCharCounter(elements.groupNameInput, elements.groupNameCounter, 64);
            updateCharCounter(elements.groupDescInput, elements.groupDescCounter, 200);
        }

        function handleAvatarUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            if (!CONFIG.ALLOWED_FILE_TYPES.includes(file.type)) {
                showToast('Please upload a JPG, PNG, or WebP image', 'error');
                event.target.value = '';
                return;
            }

            if (file.size > CONFIG.MAX_FILE_SIZE) {
                showToast('Image size exceeds 2MB', 'error');
                event.target.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                if (elements.groupAvatarPreview) {
                    elements.groupAvatarPreview.src = e.target.result;
                }
            };
            reader.readAsDataURL(file);
        }

        function updateCharCounter(inputElement, counterElement, maxLength) {
            if (!inputElement || !counterElement) return;

            const currentLength = inputElement.value.length;
            const remaining = maxLength - currentLength;

            counterElement.textContent = `${remaining} characters left`;
            counterElement.classList.toggle('text-danger', remaining < 10);
            counterElement.classList.toggle('text-warning', remaining >= 10 && remaining < 20);
            counterElement.classList.toggle('text-muted', remaining >= 20);
        }

        function filterGroupMembers() {
            const query = elements.groupFilter?.value.toLowerCase().trim() || '';
            let visibleCount = 0;

            const rows = elements.groupList?.querySelectorAll('.sb-gp-row') || [];
            rows.forEach(row => {
                const name = row.dataset.name || '';
                const phone = row.dataset.phone || '';
                const isVisible = !query || name.includes(query) || phone.includes(query);

                row.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            if (elements.groupSelectAll) {
                elements.groupSelectAll.disabled = visibleCount === 0;
            }
        }

        function handleMemberSelection() {
            updateSelectedChips();
            updateMemberCount();
        }

        function updateSelectedChips() {
            if (!elements.groupChips) return;

            elements.groupChips.innerHTML = '';
            state.selectedUsers.clear();

            const checkedBoxes = elements.groupList?.querySelectorAll('.sb-gp-check:checked') || [];
            checkedBoxes.forEach(checkbox => {
                const row = checkbox.closest('.sb-gp-row');
                const name = row.querySelector('.fw-semibold')?.textContent?.trim() || 'User';
                const phone = row.querySelector('.small.text-muted')?.textContent?.trim() || '';
                const userId = checkbox.value;

                state.selectedUsers.add(userId);

                const chip = document.createElement('div');
                chip.className = 'chip';
                chip.innerHTML = `
                    <span>${escapeHtml(name)}</span>
                    ${phone ? `<small class="text-muted">${escapeHtml(phone)}</small>` : ''}
                    <button type="button" class="chip-remove" aria-label="Remove ${escapeHtml(name)}">
                        <i class="bi bi-x" aria-hidden="true"></i>
                    </button>
                `;

                const removeBtn = chip.querySelector('.chip-remove');
                removeBtn.addEventListener('click', () => {
                    checkbox.checked = false;
                    updateSelectedChips();
                    updateMemberCount();
                });

                elements.groupChips.appendChild(chip);
            });
        }

        function updateMemberCount() {
            if (elements.groupCount) {
                elements.groupCount.textContent = state.selectedUsers.size;
            }
        }

        function handleSelectAll() {
            const visibleRows = elements.groupList?.querySelectorAll('.sb-gp-row[style=""]') || [];
            visibleRows.forEach(row => {
                const checkbox = row.querySelector('.sb-gp-check');
                if (checkbox) {
                    checkbox.checked = true;
                }
            });

            updateSelectedChips();
            updateMemberCount();
        }

        function handleClearSelection() {
            const checkedBoxes = elements.groupList?.querySelectorAll('.sb-gp-check:checked') || [];
            checkedBoxes.forEach(checkbox => {
                checkbox.checked = false;
            });

            updateSelectedChips();
            updateMemberCount();
        }

        function handleGroupCreation(event) {
            if (state.selectedUsers.size === 0) {
                event.preventDefault();
                showToast('Please select at least one participant', 'error');
                return;
            }

            const groupName = elements.groupNameInput?.value.trim();
            if (!groupName) {
                event.preventDefault();
                showToast('Please enter a group name', 'error');
                return;
            }
        }

        function resetCreateGroupPanel() {
            if (elements.createGroupForm) {
                elements.createGroupForm.reset();
            }

            if (elements.groupAvatarPreview) {
                elements.groupAvatarPreview.src = "{{ asset('images/group-default.png') }}";
            }

            if (elements.groupFilter) {
                elements.groupFilter.value = '';
                filterGroupMembers();
            }

            updateSelectedChips();
            updateMemberCount();
            updateCharCounter(elements.groupNameInput, elements.groupNameCounter, 64);
            updateCharCounter(elements.groupDescInput, elements.groupDescCounter, 200);
        }

        // ==== Panel Management ====
        function setupPanelManagement() {
            setupPanelExclusivity();
            setupPanelListeners();
        }

        function setupPanelExclusivity() {
            if (!elements.newChatPanel || !elements.createGroupPanel) return;

            const chatCollapse = bootstrap.Collapse.getInstance(elements.newChatPanel) ||
                new bootstrap.Collapse(elements.newChatPanel, {
                    toggle: false
                });
            const groupCollapse = bootstrap.Collapse.getInstance(elements.createGroupPanel) ||
                new bootstrap.Collapse(elements.createGroupPanel, {
                    toggle: false
                });

            elements.newChatPanel.addEventListener('show.bs.collapse', () => {
                groupCollapse.hide();
                updatePanelButtons('chat');
                state.currentPanel = 'chat';
            });

            elements.createGroupPanel.addEventListener('show.bs.collapse', () => {
                chatCollapse.hide();
                updatePanelButtons('group');
                state.currentPanel = 'group';
            });

            elements.newChatPanel.addEventListener('hidden.bs.collapse', () => {
                if (state.currentPanel === 'chat') {
                    updatePanelButtons(null);
                    state.currentPanel = null;
                }
            });

            elements.createGroupPanel.addEventListener('hidden.bs.collapse', () => {
                if (state.currentPanel === 'group') {
                    updatePanelButtons(null);
                    state.currentPanel = null;
                }
            });
        }

        function updatePanelButtons(activePanel) {
            if (elements.newChatBtn && elements.newGroupBtn) {
                elements.newChatBtn.classList.remove('active');
                elements.newGroupBtn.classList.remove('active');
                elements.newChatBtn.classList.add('btn-wa');
                elements.newChatBtn.classList.remove('btn-outline-wa');
                elements.newGroupBtn.classList.add('btn-outline-wa');
                elements.newGroupBtn.classList.remove('btn-wa');

                if (activePanel === 'chat') {
                    elements.newChatBtn.classList.add('active');
                    elements.newChatBtn.classList.remove('btn-outline-wa');
                    elements.newChatBtn.classList.add('btn-wa');
                } else if (activePanel === 'group') {
                    elements.newGroupBtn.classList.add('active');
                    elements.newGroupBtn.classList.remove('btn-outline-wa');
                    elements.newGroupBtn.classList.add('btn-wa');
                }
            }
        }

        function setupPanelListeners() {
            if (window.innerWidth <= 768) {
                document.addEventListener('click', (event) => {
                    if (!state.currentPanel) return;

                    const isClickInsidePanel =
                        (elements.newChatPanel && elements.newChatPanel.contains(event.target)) ||
                        (elements.createGroupPanel && elements.createGroupPanel.contains(event.target)) ||
                        (elements.newChatBtn && elements.newChatBtn.contains(event.target)) ||
                        (elements.newGroupBtn && elements.newGroupBtn.contains(event.target));

                    if (!isClickInsidePanel) {
                        closeAllPanels();
                    }
                });
            }
        }

        function closeAllPanels() {
            const chatCollapse = bootstrap.Collapse.getInstance(elements.newChatPanel);
            const groupCollapse = bootstrap.Collapse.getInstance(elements.createGroupPanel);

            chatCollapse?.hide();
            groupCollapse?.hide();
            state.currentPanel = null;
            updatePanelButtons(null);
        }

        // ==== Utility Functions ====
        async function apiCall(url, options = {}) {
            const headers = {
                'Accept': 'application/json',
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
                    throw new Error(`HTTP ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return await response.json();
                }

                return await response.text();
            } catch (error) {
                console.error('API call failed:', error);
                throw error;
            }
        }

        function showToast(message, type = 'info') {
            const existingToasts = document.querySelectorAll('.sidebar-toast');
            existingToasts.forEach(toast => toast.remove());

            const toast = document.createElement('div');
            toast.className = `sidebar-toast position-fixed bottom-0 end-0 m-3 p-3 rounded border-0 shadow-lg ${
                type === 'success' ? 'bg-success' :
                type === 'error' ? 'bg-danger' :
                type === 'warning' ? 'bg-warning' : 'bg-info'
            }`;
            toast.style.zIndex = '1070';
            toast.style.maxWidth = '300px';
            toast.innerHTML = `
                <div class="d-flex align-items-center gap-2 text-white">
                    <i class="bi ${
                        type === 'success' ? 'bi-check-circle' :
                        type === 'error' ? 'bi-exclamation-circle' :
                        type === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle'
                    }"></i>
                    <span>${escapeHtml(message)}</span>
                </div>
            `;

            document.body.appendChild(toast);

            setTimeout(() => toast.style.opacity = '1', 100);
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';

            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, CONFIG.NOTIFICATION_TIMEOUT);
        }

        function showRealTimeToast(message, type = 'info', options = {}) {
            const {
                actionText,
                actionCallback,
                duration = CONFIG.NOTIFICATION_TIMEOUT
            } = options;

            const toast = document.createElement('div');
            toast.className = `sidebar-toast position-fixed bottom-0 end-0 m-3 p-3 rounded border-0 shadow-lg ${
                type === 'success' ? 'bg-success' :
                type === 'error' ? 'bg-danger' :
                type === 'warning' ? 'bg-warning' : 'bg-info'
            }`;
            toast.style.zIndex = '1070';
            toast.style.maxWidth = '350px';
            
            let toastContent = `
                <div class="d-flex align-items-center gap-2 text-white">
                    <i class="bi ${
                        type === 'success' ? 'bi-check-circle' :
                        type === 'error' ? 'bi-exclamation-circle' :
                        type === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle'
                    }"></i>
                    <span class="flex-grow-1">${escapeHtml(message)}</span>
            `;
            
            if (actionText && actionCallback) {
                toastContent += `
                    <button class="btn btn-sm btn-outline-light" onclick="(${actionCallback.toString()})()">
                        ${escapeHtml(actionText)}
                    </button>
                `;
            }
            
            toastContent += `</div>`;
            toast.innerHTML = toastContent;

            document.body.appendChild(toast);

            // Animation
            setTimeout(() => toast.style.opacity = '1', 100);
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';

            // Auto-remove
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, duration);

            return toast;
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
            if (state.notificationTimeout) {
                clearTimeout(state.notificationTimeout);
            }
        }

        window.addEventListener('beforeunload', cleanup);

        // ==== Global Exports ====
        window.sidebarApp = {
            startChatWithPhone,
            showToast,
            showRealTimeToast,
            cleanup,
            handleNewMessage,
            handleMessagesRead,
            handleMessageDeleted,
            
            handleUserClick: function(userId, phone, isRegistered) {
                if (isRegistered) {
                    this.startChatWithPhone(phone);
                } else {
                    this.showInviteModal(phone);
                }
            },
            
            handleMessageClick: function(conversationSlug, messageId) {
                if (conversationSlug) {
                    window.location.href = `${state.convBase}${conversationSlug}#message-${messageId}`;
                } else {
                    showToast('Conversation not found', 'error');
                }
            },
            
            showInviteModal: function(phone) {
                showInviteModal(phone);
            },
            
            refreshSidebar: function() {
                console.log('Refreshing sidebar data...');
                // Implement refresh logic as needed
            }
        };

    })();

    // Utility functions for phone validation
    function validatePhoneNumber(phone) {
        if (!phone) return false;

        const cleaned = phone.replace(/[^\d+]/g, '');

        const patterns = {
            international: /^\+\d{10,15}$/,
            local: /^\d{10,15}$/,
            withSpaces: /^[\d\s\-\(\)\.]{10,}$/
        };

        return patterns.international.test(cleaned) ||
            patterns.local.test(cleaned) ||
            patterns.withSpaces.test(phone);
    }

    function formatPhoneNumber(phone) {
        if (!phone) return '';

        const cleaned = phone.replace(/[^\d+]/g, '');

        if (cleaned.startsWith('+')) {
            return cleaned;
        }

        const countryCode = '+1';
        return countryCode + cleaned;
    }
</script>