{{-- sidebar_scripts --}}
<script>
    console.log('🚀 sidebar_scripts.blade.php LOADED');
    (function() {
        'use strict';
        console.log('🚀 sidebar_scripts IIFE executing');

        // ==== Configuration & Constants ====
        const CONFIG = {
            DEBOUNCE_DELAY: 300,
            SEARCH_LIMIT: 20,
            MAX_FILE_SIZE: 2 * 1024 * 1024,
            ALLOWED_FILE_TYPES: ['image/jpeg', 'image/png', 'image/webp'],
            NOTIFICATION_TIMEOUT: 5000,
            REAL_TIME_EVENTS: {
                MESSAGE_SENT: 'MessageSent',
                MESSAGE_READ: 'MessageRead',
                MESSAGE_DELETED: 'MessageDeleted',
                MESSAGE_STATUS_UPDATED: 'MessageStatusUpdated',
                GROUP_UPDATED: 'GroupUpdated',
                STATUS_CREATED: 'status.created'
            }
        };

        // ==== State Management ====
        const state = {
            activeFilters: new Set(['all']), // Deprecated - using single selection mode now
            selectedUsers: new Set(),
            searchResults: [],
            notificationTimeout: null,
            currentPanel: null,
            notificationDismissed: false,
            notificationsEnabled: false,
            deniedBannerHidden: false,
            convBase: '/c/',
            groupBase: '/g/',
            userIds: [],
            currentUserId: window.currentUserId || {{ auth()->id() ?? 'null' }},
            realTimeListeners: {},
            searchState: {
                currentQuery: '',
                isLoading: false
            }
        };

        // ==== DOM Elements Cache ====
        const elements = {};

        // ==== Avatar Helper Functions ====
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
        
        function getInitials(name) {
            if (!name || name.trim() === '') return '?';
            const parts = name.trim().split(' ').filter(p => p.length > 0);
            if (parts.length >= 2) {
                return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
            } else if (parts.length === 1 && parts[0].length >= 2) {
                return parts[0].substring(0, 2).toUpperCase();
            } else if (parts.length === 1 && parts[0].length === 1) {
                return parts[0][0].toUpperCase();
            }
            return '?';
        }
        
        // ==== Core Initialization ====
        // Use multiple initialization strategies to ensure scripts run
        function initializeSidebar() {
            try {
                // Cache elements first
                cacheElements();

                // Verify critical elements exist before proceeding
                if (!elements.sidebar) {
                    console.warn('⚠️ Sidebar element not found, retrying...');
                    setTimeout(initializeSidebar, 100);
                    return;
                }

                initializeState();
                setupEventListeners();
                initializeNotifications();
                setupPanelManagement();
                fixBaseUrls();
                setupRealTimeListeners();
                initializeUnreadCountSystem();
                initializeAutoReadTracking();
                setupPhoneValidation();
                setupGroupTypeHandlers();

                console.log('✅ Sidebar initialized successfully');
            } catch (error) {
                console.error('❌ Sidebar initialization failed:', error);
                // Retry once after a short delay
                setTimeout(() => {
                    try {
                        cacheElements();
                        setupEventListeners();
                        console.log('✅ Sidebar re-initialized after error');
                    } catch (retryError) {
                        console.error('❌ Sidebar re-initialization failed:', retryError);
                    }
                }, 500);
            }
        }

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeSidebar);
        } else {
            // DOM already loaded, initialize immediately
            initializeSidebar();
        }

        // Also try after a short delay as fallback
        setTimeout(initializeSidebar, 100);

        function cacheElements() {
            // Use getElementById for better performance and null safety
            const getEl = (id) => document.getElementById(id);
            const getEls = (selector) => document.querySelectorAll(selector);

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
                searchFiltersContainer: '#search-filters-container',
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
                newGroupBtn: '#new-group-btn',

                // Unread count elements
                totalUnreadCount: '#total-unread-count',

                // Invite modal elements
                inviteModal: '#inviteModal',
                invitePhoneHint: '#invitePhoneHint',
                inviteSmsBtn: '#inviteSmsBtn',
                inviteShareBtn: '#inviteShareBtn',
                inviteLinkInput: '#inviteLinkInput',
                inviteCopyBtn: '#inviteCopyBtn'
            };

            Object.keys(selectors).forEach(key => {
                const selector = selectors[key];
                // Use getElementById for IDs (more efficient), querySelector for others
                if (selector.startsWith('#')) {
                    elements[key] = document.getElementById(selector.substring(1));
                } else {
                    elements[key] = document.querySelector(selector);
                }
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

        function fixBaseUrls() {
            if (state.convBase && state.convBase.includes('/user-')) {
                state.convBase = '/c/';
            }
            if (state.groupBase && !state.groupBase.endsWith('/')) {
                state.groupBase += '/';
            }
        }

        // ==== Account Switcher Functions ====
        // Define setupAccountSwitcherListeners before it's used
        // Note: showAccountSwitcherModal is defined later in this IIFE
        function setupAccountSwitcherListeners() {
            const accountSwitcherBtn = document.querySelector('.account-switcher-btn');
            if (accountSwitcherBtn) {
                accountSwitcherBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Call the function directly (it's hoisted in this scope)
                    if (typeof showAccountSwitcherModal === 'function') {
                        showAccountSwitcherModal();
                    } else if (typeof window.showAccountSwitcherModal === 'function') {
                        window.showAccountSwitcherModal();
                    } else {
                        console.error('showAccountSwitcherModal is not defined');
                    }
                });
            }
        }

        // ==== Event Listeners Setup ====
        function setupEventListeners() {
            setupNotificationListeners();
            setupSearchListeners();
            setupNewChatListeners();
            setupCreateGroupListeners();
            setupNewDropdownListeners();
            setupPanelListeners();
            setupInviteModalListeners();
            setupConversationClickHandlers();
            setupAccountSwitcherListeners();
            setupMenuSidebarFilters();
            ensureModalsAboveChat();
        }

        // Setup menu sidebar filter buttons (Chat, Broadcast)
        function setupMenuSidebarFilters() {
            // Handle Chat filter button
            const chatFilterBtn = document.querySelector('.chat-filter-btn');
            if (chatFilterBtn) {
                chatFilterBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Navigate to chat index if not already there
                    if (!window.location.pathname.includes('/c') && !window.location.pathname.includes('/g')) {
                        window.location.href = '{{ route("chat.index") }}';
                        // Wait for navigation, then trigger filter
                        setTimeout(() => {
                            triggerFilter('chat');
                        }, 100);
                    } else {
                        triggerFilter('chat');
                    }
                    // Update active state
                    updateMenuSidebarActiveState(this);
                });
            }

            // Handle Broadcast filter button
            const broadcastFilterBtn = document.querySelector('.broadcast-filter-btn');
            if (broadcastFilterBtn) {
                broadcastFilterBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Navigate to chat index if not already there
                    if (!window.location.pathname.includes('/c') && !window.location.pathname.includes('/g')) {
                        window.location.href = '{{ route("chat.index") }}';
                        // Wait for navigation, then trigger filter
                        setTimeout(() => {
                            triggerFilter('broadcast');
                        }, 100);
                    } else {
                        triggerFilter('broadcast');
                    }
                    // Update active state
                    updateMenuSidebarActiveState(this);
                });
            }
        }

        // Trigger filter programmatically
        function triggerFilter(filterName) {
            // Create a mock button element for the filter
            const mockButton = document.createElement('button');
            mockButton.setAttribute('data-filter', filterName);
            mockButton.classList.add('filter-btn');
            
            // Create a synthetic event
            const event = {
                target: mockButton,
                preventDefault: () => {},
                stopPropagation: () => {}
            };
            
            // Call handleFilterClick with the mock event
            handleFilterClick(event);
        }

        // Update active state of menu sidebar buttons
        function updateMenuSidebarActiveState(activeButton) {
            // Remove active from all menu sidebar filter buttons
            document.querySelectorAll('.chat-filter-btn, .broadcast-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            // Add active to clicked button
            if (activeButton) {
                activeButton.classList.add('active');
            }
        }

        // Setup conversation click handlers to update active state
        function setupConversationClickHandlers() {
            // Use event delegation to handle clicks on conversation items
            const conversationList = elements.conversationList || document.querySelector('.conversation-list');
            if (!conversationList) return;

            conversationList.addEventListener('click', function(e) {
                // Find the conversation item (could be the link itself or a child)
                const conversationItem = e.target.closest('.conversation-item');
                if (!conversationItem) return;

                // Remove active class from all conversation items
                const allItems = conversationList.querySelectorAll('.conversation-item');
                allItems.forEach(item => {
                    item.classList.remove('active');
                });

                // Add active class to clicked item
                conversationItem.classList.add('active');

                console.log('Conversation clicked, active state updated:', conversationItem.dataset
                    .conversationId || conversationItem.dataset.groupId);
            });

            // Also update active state based on current URL on page load
            updateActiveConversationFromUrl();
        }

        // Update active conversation based on current URL
        function updateActiveConversationFromUrl() {
            const currentPath = window.location.pathname;
            const conversationList = elements.conversationList || document.querySelector('.conversation-list');
            if (!conversationList) return;

            // Remove active from all items first
            const allItems = conversationList.querySelectorAll('.conversation-item');
            allItems.forEach(item => {
                item.classList.remove('active');
            });

            // Find and activate the item matching current URL
            // Match by checking if the href matches the current path
            allItems.forEach(item => {
                const href = item.getAttribute('href');
                if (!href) return;

                // Normalize paths for comparison
                const hrefPath = new URL(href, window.location.origin).pathname;
                const currentPathNormalized = currentPath.endsWith('/') ? currentPath.slice(0, -1) :
                    currentPath;
                const hrefPathNormalized = hrefPath.endsWith('/') ? hrefPath.slice(0, -1) : hrefPath;

                // Check if paths match (exact match or current path contains the href path)
                if (currentPathNormalized === hrefPathNormalized || currentPathNormalized.startsWith(
                        hrefPathNormalized + '/')) {
                    item.classList.add('active');
                    console.log('Active conversation updated from URL:', href);
                    return; // Found match, exit early
                }
            });
        }

        // Also update on popstate (back/forward navigation)
        window.addEventListener('popstate', function() {
            setTimeout(updateActiveConversationFromUrl, 100);
        });

        // Ensure all modals appear above chat area
        function ensureModalsAboveChat() {
            // Move modals to body if they're inside sidebar (Bootstrap best practice)
            const sidebar = document.getElementById('conversation-sidebar');
            if (!sidebar) return;

            const modals = sidebar.querySelectorAll('.modal');
            modals.forEach(modal => {
                // Check if modal is still inside sidebar
                if (sidebar.contains(modal)) {
                    // Move to body to avoid stacking context issues
                    document.body.appendChild(modal);
                    console.log('Moved modal to body:', modal.id);
                }
            });

            // Also ensure modals have proper z-index when shown
            document.addEventListener('show.bs.modal', function(e) {
                const modal = e.target;
                if (modal && modal.classList.contains('modal')) {
                    // Ensure modal is in body (not inside sidebar/chat area)
                    const sidebar = document.getElementById('conversation-sidebar');
                    const chatArea = document.getElementById('chat-area');
                    if ((sidebar && sidebar.contains(modal)) || (chatArea && chatArea.contains(modal))) {
                        document.body.appendChild(modal);
                    }

                    modal.style.zIndex = '9999';
                    modal.style.position = 'fixed';
                    modal.style.top = '0';
                    modal.style.left = '0';
                    modal.style.width = '100%';
                    modal.style.height = '100%';

                    const dialog = modal.querySelector('.modal-dialog');
                    if (dialog) {
                        dialog.style.zIndex = '10000';
                    }
                    const content = modal.querySelector('.modal-content');
                    if (content) {
                        content.style.zIndex = '10001';
                    }
                }
            });

            // Fix ALL backdrop z-index when modal is shown - ensure they're all behind modals
            document.addEventListener('shown.bs.modal', function(e) {
                setTimeout(() => {
                    // Get ALL backdrops and ensure they're all behind modals
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach((backdrop, index) => {
                        backdrop.style.zIndex = '9998';
                        backdrop.style.position = 'fixed';
                        backdrop.style.top = '0';
                        backdrop.style.left = '0';
                        backdrop.style.width = '100%';
                        backdrop.style.height = '100%';
                    });

                    // Ensure the modal that was just shown is on top
                    const modal = e.target;
                    if (modal && modal.classList.contains('modal')) {
                        // Ensure modal is in body
                        const sidebar = document.getElementById('conversation-sidebar');
                        const chatArea = document.getElementById('chat-area');
                        if ((sidebar && sidebar.contains(modal)) || (chatArea && chatArea.contains(
                                modal))) {
                            document.body.appendChild(modal);
                        }

                        // Count how many modals are currently shown
                        const shownModals = document.querySelectorAll('.modal.show');
                        const modalIndex = Array.from(shownModals).indexOf(modal);
                        const baseZIndex = 9999;
                        const modalZIndex = baseZIndex + (modalIndex * 10);

                        modal.style.zIndex = modalZIndex.toString();
                        modal.style.position = 'fixed';
                        modal.style.top = '0';
                        modal.style.left = '0';
                        modal.style.width = '100%';
                        modal.style.height = '100%';

                        const dialog = modal.querySelector('.modal-dialog');
                        if (dialog) {
                            dialog.style.zIndex = (modalZIndex + 1).toString();
                        }
                        const content = modal.querySelector('.modal-content');
                        if (content) {
                            content.style.zIndex = (modalZIndex + 2).toString();
                        }
                    }
                }, 10);
            });

            // Clean up backdrops when modal is hidden
            document.addEventListener('hidden.bs.modal', function(e) {
                setTimeout(() => {
                    // Remove any orphaned backdrops
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    const shownModals = document.querySelectorAll('.modal.show');

                    // If no modals are shown, remove all backdrops
                    if (shownModals.length === 0) {
                        backdrops.forEach(backdrop => backdrop.remove());
                    } else {
                        // Ensure remaining backdrops have correct z-index
                        backdrops.forEach(backdrop => {
                            backdrop.style.zIndex = '1050';
                        });
                    }
                }, 10);
            });
        }

        // ==== Unread Count Management ====
        function initializeUnreadCountSystem() {
            updateTotalUnreadCount();

            // Set up periodic updates as fallback
            setInterval(() => {
                updateTotalUnreadCount();
            }, 30000);
        }

        function initializeAutoReadTracking() {
            // Auto-mark as read when conversation/group is viewed
            const conversationLinks = document.querySelectorAll('.conversation-item[data-conversation-id]');
            conversationLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const conversationId = this.getAttribute('data-conversation-id');
                    markConversationAsRead(conversationId);
                });
            });

            const groupLinks = document.querySelectorAll('.conversation-item[data-group-id]');
            groupLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const groupId = this.getAttribute('data-group-id');
                    markGroupAsRead(groupId);
                });
            });

            // Also track URL changes for direct navigation
            let currentUrl = window.location.href;
            setInterval(() => {
                if (window.location.href !== currentUrl) {
                    currentUrl = window.location.href;
                    handleUrlChangeForReadStatus(currentUrl);
                }
            }, 1000);
        }

        function handleUrlChangeForReadStatus(url) {
            // Check if we're viewing a conversation
            const conversationMatch = url.match(/\/c\/([^\/]+)/);
            if (conversationMatch) {
                const conversationItem = document.querySelector(`[href*="/c/${conversationMatch[1]}"]`);
                if (conversationItem) {
                    const conversationId = conversationItem.getAttribute('data-conversation-id');
                    markConversationAsRead(conversationId);
                }
            }

            // Check if we're viewing a group
            const groupMatch = url.match(/\/g\/([^\/]+)/);
            if (groupMatch) {
                const groupItem = document.querySelector(`[href*="/g/${groupMatch[1]}"]`);
                if (groupItem) {
                    const groupId = groupItem.getAttribute('data-group-id');
                    markGroupAsRead(groupId);
                }
            }
        }

        // Make updateTotalUnreadCount globally accessible
        window.updateTotalUnreadCount = function() {
            const conversationItems = document.querySelectorAll('.conversation-item');
            let totalCount = 0;

            conversationItems.forEach(item => {
                const unread = parseInt(item.dataset.unread) || 0;
                totalCount += unread;
            });

            // Gracefully handle cases where elements.totalUnreadCount may be undefined
            let totalUnreadEl = null;
            try {
                totalUnreadEl = elements.totalUnreadCount;
            } catch (e) {
                // ignore
            }
            if (!totalUnreadEl) {
                totalUnreadEl = document.getElementById('total-unread-count');
            }
            if (totalUnreadEl) {
                if (totalCount > 0) {
                    totalUnreadEl.textContent = totalCount > 99 ? '99+' : totalCount;
                    totalUnreadEl.style.display = 'flex';
                } else {
                    totalUnreadEl.style.display = 'none';
                }
            }

            updateBrowserTitle(totalCount);
        };

        function updateBrowserTitle(count) {
            const baseTitle = '{{ config('app.name', 'GekyChat') }}';
            if (count > 0) {
                document.title = `(${count}) ${baseTitle}`;
            } else {
                document.title = baseTitle;
            }
        }

        async function markConversationAsRead(conversationId) {
            try {
                await apiCall('{{ route('chat.read') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        conversation_id: conversationId
                    })
                });

                // Update UI immediately
                const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
                if (conversationItem) {
                    conversationItem.dataset.unread = '0';
                    updateUnreadBadge(conversationItem, 0);
                    updateTotalUnreadCount();
                }
            } catch (error) {
                console.error('Error marking conversation as read:', error);
            }
        }

        async function markGroupAsRead(groupId) {
            try {
                await apiCall(`/g/${groupId}/read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                // Update UI immediately
                const groupItem = document.querySelector(`[data-group-id="${groupId}"]`);
                if (groupItem) {
                    groupItem.dataset.unread = '0';
                    updateUnreadBadge(groupItem, 0);
                    updateTotalUnreadCount();
                }
            } catch (error) {
                console.error('Error marking group as read:', error);
            }
        }

        function updateUnreadBadge(item, count) {
            let badge = item.querySelector('.unread-badge');

            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'unread-badge rounded-pill';
                    badge.setAttribute('aria-label', `${count} unread messages`);

                    const timeContainer = item.querySelector('.d-flex.justify-content-between');
                    if (timeContainer) {
                        timeContainer.appendChild(badge);
                    }
                }
                badge.textContent = count > 99 ? '99+' : count;
                item.classList.add('unread');

                // Add animation for new messages
                item.classList.add('new-message');
                setTimeout(() => {
                    item.classList.remove('new-message');
                }, 2000);
            } else {
                if (badge) {
                    badge.remove();
                }
                item.classList.remove('unread');
                item.classList.remove('new-message');
            }
        }

        // ==== Real-time Integration ====
        function setupRealTimeListeners() {
            if (typeof Echo === 'undefined') {
                console.warn('Echo not available - real-time features disabled');
                return;
            }

            try {
                // Listen for user-specific events
                state.realTimeListeners.user = Echo.private(`user.${state.currentUserId}`)
                    .listen(CONFIG.REAL_TIME_EVENTS.MESSAGE_SENT, (e) => {
                        if (e.message.sender_id !== state.currentUserId) {
                            handleNewMessage(e.message);
                        }
                    })
                    .listen(CONFIG.REAL_TIME_EVENTS.MESSAGE_READ, (e) => {
                        handleMessagesRead(e.conversation_id, e.message_ids, e.reader_id);
                    })
                    .listen(CONFIG.REAL_TIME_EVENTS.MESSAGE_DELETED, (e) => {
                        handleMessageDeleted(e.message_id, e.deleted_by);
                    })
                    .listen(CONFIG.REAL_TIME_EVENTS.MESSAGE_STATUS_UPDATED, (e) => {
                        handleMessageStatusUpdate(e);
                    });

                // Listen for group updates on user channel (for when user is added)
                if (state.realTimeListeners.user) {
                    state.realTimeListeners.user.listen(CONFIG.REAL_TIME_EVENTS.GROUP_UPDATED, (e) => {
                        handleGroupUpdate(e);
                    });
                }

                // Listen for group updates on group channel
                state.realTimeListeners.group = Echo.join(`group.updates.${state.currentUserId}`)
                    .listen(CONFIG.REAL_TIME_EVENTS.GROUP_UPDATED, (e) => {
                        handleGroupUpdate(e);
                    });

                // Listen for status updates
                state.realTimeListeners.status = Echo.channel('status.updates')
                    .listen(CONFIG.REAL_TIME_EVENTS.STATUS_CREATED, (e) => {
                        handleStatusCreated(e.status);
                    });

                console.log('✅ Real-time listeners initialized');
            } catch (error) {
                console.error('❌ Real-time listener setup failed:', error);
            }
        }

        function handleNewMessage(message) {
            const isGroup = message.is_group || false;
            const targetId = isGroup ? message.group_id : message.conversation_id;
            const basePath = isGroup ? state.groupBase : state.convBase;

            // Update unread count
            const conversationItem = document.querySelector(`[href*="${basePath}${targetId}"]`);

            if (conversationItem) {
                // Check if this conversation is currently active (user is viewing it)
                const isCurrentlyActive = conversationItem.classList.contains('active');

                const currentCount = parseInt(conversationItem.dataset.unread) || 0;
                const newCount = currentCount + 1;
                conversationItem.dataset.unread = newCount;

                updateUnreadBadge(conversationItem, newCount);
                updateTotalUnreadCount();
                updateLastMessagePreview(conversationItem, message);

                // Only move to top if the conversation is NOT currently active
                // This prevents auto-selecting conversations with unread messages
                if (!isCurrentlyActive) {
                    moveConversationToTop(conversationItem);
                }

                // Show notification if tab is not focused
                if (!document.hasFocus() && state.notificationsEnabled) {
                    showNewMessageNotification(message);
                }
            } else if (!isGroup) {
                // New conversation - might need to refresh or show notification
                showRealTimeToast(
                    `New message from ${message.sender?.name || 'Someone'}`,
                    'info', {
                        actionText: 'View',
                        actionCallback: () => {
                            window.location.href = `/c/${message.conversation_id}`;
                        }
                    }
                );
            }
        }

        function handleMessagesRead(conversationId, messageIds, readerId) {
            if (readerId === state.currentUserId) {
                // Try to find by data attribute first (more reliable)
                let conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);

                // Fallback to href selector if data attribute not found
                if (!conversationItem) {
                    conversationItem = document.querySelector(`[href*="/c/${conversationId}"]`);
                }

                if (conversationItem) {
                    conversationItem.dataset.unread = '0';
                    updateUnreadBadge(conversationItem, 0);
                    updateTotalUnreadCount();
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
            if (event.status === 'read' && event.user_id !== state.currentUserId) {
                // Someone read your message - could update UI if needed
                console.log('Message read by user:', event.user_id);
            }
        }

        async function handleGroupUpdate(event) {
            const {
                group_id,
                update_type,
                changed_data,
                updated_by,
                group
            } = event;

            // Handle when user is added to a group
            if (update_type === 'members_added' && changed_data?.member_ids) {
                const currentUserId = state.currentUserId;
                const wasAdded = Array.isArray(changed_data.member_ids) ?
                    changed_data.member_ids.includes(parseInt(currentUserId)) :
                    parseInt(changed_data.member_ids) === parseInt(currentUserId);

                if (wasAdded && updated_by !== currentUserId) {
                    // User was added to the group - refresh sidebar and show notification
                    const addedByName = changed_data.added_by?.name || 'Someone';
                    const groupName = group?.name || 'a group';
                    showToast(`You were added to "${groupName}" by ${addedByName}`, 'success');

                    // Reload sidebar to show new group
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                    return;
                }
            }

            if (updated_by === state.currentUserId) return; // Don't show our own actions

            const messages = {
                'members_added': 'New member(s) joined the group',
                'member_removed': 'Member left the group',
                'member_promoted': 'Member promoted to admin',
                'info_updated': 'Group info updated',
                'ownership_transferred': 'Group ownership transferred'
            };

            if (messages[update_type]) {
                showRealTimeToast(messages[update_type], 'info');
            }

            // Update group header if we're viewing this group
            if (window.location.pathname.includes(`/g/${group_id}`)) {
                updateGroupHeader(changed_data);
            }
        }

        function handleStatusCreated(statusData) {
            // Don't show our own status (it's already there)
            if (statusData.user_id === state.currentUserId) {
                return;
            }

            // Check if status already exists in the sidebar
            const statusCarousel = document.querySelector('.status-carousel > div');
            if (!statusCarousel) return;

            const existingStatus = statusCarousel.querySelector(`[data-status-id="${statusData.id}"]`);
            if (existingStatus) {
                return; // Status already exists
            }

            // Get user data from status
            const user = statusData.user || {};
            const userId = statusData.user_id;

            // Get display name from contacts (preferred) or fall back to system name
            let userName = user.name || user.phone || 'User';
            if (window.contactDisplayNames && window.contactDisplayNames[userId]) {
                userName = window.contactDisplayNames[userId];
            } else {
                // Fallback: try to get display name from existing status items (if user already has a status)
                const existingStatusFromUser = statusCarousel.querySelector(`[data-user-id="${userId}"]`);
                if (existingStatusFromUser) {
                    // Get the name from the existing status item's text
                    const nameElement = existingStatusFromUser.closest('.status-item')?.querySelector('small');
                    if (nameElement) {
                        userName = nameElement.textContent.trim();
                    }
                }
            }

            const initial = user.initial || userName.charAt(0).toUpperCase();
            // Use Storage::url format (same as server-side)
            const avatarUrl = user.avatar_path ? `/storage/${user.avatar_path}` : null;
            const isUnread = true; // New status is always unread
            const borderColor = isUnread ? 'var(--geky-green, #10B981)' : '#ddd';
            const borderWidth = isUnread ? 3 : 2.5;

            // Escape HTML to prevent XSS
            const escapeHtml = (text) => {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            };

            // Create status item HTML
            const statusItem = document.createElement('div');
            statusItem.className = 'status-item text-center';
            statusItem.style.cssText = 'min-width: 60px; cursor: pointer;';
            statusItem.innerHTML = `
            <button class="btn p-0 border-0 status-view-btn w-100 h-100" 
                    data-status-id="${statusData.id}"
                    data-user-id="${userId}"
                    aria-label="View status from ${escapeHtml(userName)}"
                    style="background: none; border: none; padding: 0; cursor: pointer;">
                <div class="position-relative mx-auto mb-1" style="width: 56px; height: 56px;">
                    ${avatarUrl ? `
                        <img src="${escapeHtml(avatarUrl)}" 
                             class="rounded-circle status-avatar" 
                             style="width: 56px; height: 56px; object-fit: cover; border: ${borderWidth}px solid ${borderColor}; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.2s ease;"
                             alt="${escapeHtml(userName)}"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                             onmouseover="this.style.transform='scale(1.05)'"
                             onmouseout="this.style.transform='scale(1)'">
                        <div class="avatar-placeholder avatar-lg status-avatar unread d-none" 
                             style="border: ${borderWidth}px solid ${borderColor}; cursor: pointer; transition: transform 0.2s ease; background: ${getAvatarColor(userName)}; color: white;"
                             onmouseover="this.style.transform='scale(1.05)'"
                             onmouseout="this.style.transform='scale(1)'">
                            ${escapeHtml(initial)}
                        </div>
                    ` : `
                        <div class="avatar-placeholder avatar-lg status-avatar unread" 
                             style="border: ${borderWidth}px solid ${borderColor}; cursor: pointer; transition: transform 0.2s ease; background: ${getAvatarColor(userName)}; color: white;"
                             onmouseover="this.style.transform='scale(1.05)'"
                             onmouseout="this.style.transform='scale(1)'">
                            ${escapeHtml(initial)}
                        </div>
                    `}
                </div>
            </button>
            <small class="text-muted d-block text-truncate mt-1" style="font-size: 0.7rem; max-width: 60px; font-weight: 500;">
                ${escapeHtml(userName)}
            </small>
        `;

            // Insert after "My Status" button (first child) but before other statuses
            // Find the "My Status" button container
            const myStatusContainer = statusCarousel.querySelector('.status-item:first-child');
            if (myStatusContainer && myStatusContainer.nextSibling) {
                statusCarousel.insertBefore(statusItem, myStatusContainer.nextSibling);
            } else {
                // If no other statuses, just append
                statusCarousel.appendChild(statusItem);
            }

            // Add smooth animation
            statusItem.style.opacity = '0';
            statusItem.style.transform = 'scale(0.8)';
            setTimeout(() => {
                statusItem.style.transition = 'all 0.3s ease';
                statusItem.style.opacity = '1';
                statusItem.style.transform = 'scale(1)';
            }, 10);
        }

        function updateLastMessagePreview(conversationItem, message) {
            const messagePreview = conversationItem.querySelector('.text-muted');
            if (messagePreview) {
                let previewText = '';

                if (message.attachments && message.attachments.length > 0) {
                    previewText = '📎 Attachment';
                } else if (message.body) {
                    previewText = message.body.length > 50 ?
                        message.body.substring(0, 50) + '...' :
                        message.body;
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

        // ==== Notification Management ====
        function setupNotificationListeners() {
            elements.enableNotifications?.addEventListener('click', handleEnableNotifications);
            elements.dismissNotifications?.addEventListener('click', handleDismissNotifications);
            elements.dismissDeniedInline?.addEventListener('click', handleDismissDeniedBanner);
        }

        function initializeNotifications() {
            if (!('Notification' in window)) return;

            const {
                permission
            } = Notification;

            if (!state.notificationDismissed && !state.notificationsEnabled && permission === 'default') {
                showNotificationPrompt();
            }

            if (permission === 'denied' && !state.deniedBannerHidden) {
                showDeniedBanner();
            }
        }

        async function handleEnableNotifications() {
            try {
                const permission = await Notification.requestPermission();

                if (permission === 'granted') {
                    localStorage.setItem('notificationsEnabled', 'true');
                    state.notificationsEnabled = true;
                    hideNotificationPrompt();
                    showToast('Notifications enabled', 'success');

                    // Show sample notification
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
            // Ensure search input element exists
            if (!elements.chatSearch) {
                console.warn('Search input not found, retrying...');
                setTimeout(setupSearchListeners, 200);
                return;
            }

            // Setup search input listener
            elements.chatSearch.addEventListener('input', debounce(handleGlobalSearch, CONFIG.DEBOUNCE_DELAY));
            console.log('Search input listener attached');

            // Setup filter click handler - use event delegation for better reliability
            // Try multiple approaches to ensure the listener is attached
            const attachFilterHandler = () => {
                // Try to find search filters element
                if (!elements.searchFilters) {
                    elements.searchFilters = document.getElementById('search-filters');
                }

                if (elements.searchFilters) {
                    // Use event delegation - attach once to parent, check target on click
                    if (!elements.searchFilters.hasAttribute('data-filter-handler-attached')) {
                        elements.searchFilters.addEventListener('click', handleFilterClick);
                        elements.searchFilters.setAttribute('data-filter-handler-attached', 'true');
                        console.log('Filter handler attached to searchFilters element');
                    }
                } else {
                    // Fallback: attach to container if filters element not found
                    const container = document.getElementById('search-filters-container');
                    if (container) {
                        if (!container.hasAttribute('data-filter-handler-attached')) {
                            container.addEventListener('click', handleFilterClick);
                            container.setAttribute('data-filter-handler-attached', 'true');
                            console.log('Filter handler attached to search-filters-container');
                        }
                    } else {
                        console.warn('Could not find search filters container');
                    }
                }
            };

            // Try immediately
            attachFilterHandler();

            // Also try after a short delay in case elements aren't ready yet
            setTimeout(attachFilterHandler, 100);
            setTimeout(attachFilterHandler, 500);

            // Setup click outside handler
            document.addEventListener('click', handleClickOutsideSearch);

            // Setup search results click handler
            if (elements.searchResults) {
                elements.searchResults.addEventListener('click', handleSearchResultClick);
            }
        }

        async function handleGlobalSearch(event) {
            const query = event.target.value.trim();
            state.searchState.currentQuery = query;

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
            if (state.searchState.isLoading) return;

            state.searchState.isLoading = true;

            try {
                // Check if we're on world feed page - if so, search world feed posts
                const currentPath = window.location.pathname;
                const isWorldFeedPage = currentPath === '/world-feed' || currentPath.startsWith('/world-feed');
                
                if (isWorldFeedPage) {
                    // Search world feed posts
                    const params = new URLSearchParams({
                        q: query,
                        page: 1
                    });
                    
                    try {
                        const response = await apiCall(`/world-feed/posts?${params.toString()}`);
                        const posts = response?.data || [];
                        
                        if (posts.length > 0) {
                            // Render world feed search results
                            renderWorldFeedSearchResults(posts, query);
                            showSearchResults();
                        } else {
                            renderNoResults(query);
                            showSearchResults();
                        }
                    } catch (error) {
                        console.error('World feed search failed:', error);
                        renderSearchError(error);
                        showSearchResults();
                    } finally {
                        state.searchState.isLoading = false;
                    }
                    return;
                }
                
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

                // Get current active filter from UI instead of state
                const activeFilterButton = elements.searchFilters?.querySelector('.filter-btn.active');
                const activeFilter = activeFilterButton?.dataset.filter;
                if (activeFilter && activeFilter !== 'all') {
                    params.append('filters', activeFilter);
                }

                let response;
                try {
                    // Use /api/search web route (authenticated via session middleware)
                    response = await apiCall(`/api/search?${params.toString()}`);
                } catch (error) {
                    // If /api/search fails, try /api/v1/search as fallback
                    console.warn('Web search failed, trying API route:', error);
                    try {
                        response = await apiCall(`/api/v1/search?${params.toString()}`);
                    } catch (fallbackError) {
                        throw error; // Throw original error
                    }
                }

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
                const localResults = performLocalSearch(query);
                if (localResults.length > 0) {
                    renderSearchResults(localResults, query);
                    showSearchResults();
                } else {
                    renderSearchError(error);
                    showSearchResults();
                }
            } finally {
                state.searchState.isLoading = false;
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

                if (name.includes(lowerQuery) || phone.includes(lowerQuery) || lastMessage.includes(
                        lowerQuery)) {
                    const href = item.getAttribute('href');
                    const isGroup = href.includes('/g/');
                    const id = href.split('/').pop();

                    results.push({
                        type: isGroup ? 'group' : 'conversation',
                        id: id,
                        display_name: item.querySelector('.fw-semibold')?.textContent ||
                            'Conversation',
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
                        'X-CSRF-TOKEN': getCsrfToken()
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

        function renderWorldFeedSearchResults(posts, query) {
            if (!elements.searchResults) return;

            const html = posts.map(post => {
                const creator = post.creator || {};
                const creatorName = creator.name || 'Unknown';
                const creatorAvatar = creator.avatar_url || creator.avatar_path || null;
                const caption = post.caption || '';
                const mediaUrl = post.media_url || '';
                const isVideo = post.type === 'video';
                const postId = post.id;
                const baseUrl = window.location.origin;
                const fullMediaUrl = mediaUrl.startsWith('http') ? mediaUrl : `${baseUrl}/storage/${mediaUrl}`;
                
                return `
                    <div class="list-group-item list-group-item-action d-flex align-items-center search-result-item"
                         data-type="world_post" 
                         data-id="${postId}"
                         onclick="window.location.href='/world-feed#post-${postId}'"
                         style="cursor: pointer;">
                        <div class="d-flex align-items-center flex-grow-1 min-width-0">
                            <div class="position-relative" style="margin-right: 12px;">
                                <img src="${escapeHtml(fullMediaUrl)}" 
                                     class="rounded" 
                                     style="width: 60px; height: 60px; object-fit: cover;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                     alt="">
                                <div class="d-flex align-items-center justify-content-center rounded bg-secondary" 
                                     style="display: none; width: 60px; height: 60px;">
                                    <i class="bi bi-${isVideo ? 'play-circle' : 'image'}" style="font-size: 24px;"></i>
                                </div>
                                ${isVideo ? `
                                    <span class="position-absolute top-50 start-50 translate-middle text-white" style="font-size: 20px;">
                                        <i class="bi bi-play-circle-fill"></i>
                                    </span>
                                ` : ''}
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <div class="fw-semibold text-truncate">${escapeHtml(creatorName)}</div>
                                ${caption ? `
                                    <div class="small text-muted text-truncate" style="max-height: 2.4em; overflow: hidden;">${escapeHtml(caption)}</div>
                                ` : ''}
                            </div>
                        </div>
                        <span class="btn btn-outline-wa btn-sm">View</span>
                    </div>
                `;
            }).join('');

            elements.searchResults.innerHTML = `
                <div class="list-group-item small text-muted fw-semibold">World Feed Posts</div>
                ${html}
            `;
        }

        function renderRecentChats(section) {
            return `
            <div class="list-group-item small text-muted fw-semibold">${escapeHtml(section.title)}</div>
            ${section.items.map(chat => `
                <a href="${state.convBase}${chat.conversation?.slug || chat.conversation?.id}" 
                   class="list-group-item list-group-item-action d-flex align-items-center search-result-item"
                   data-type="conversation" data-id="${chat.conversation?.id}">
                    <div class="avatar-placeholder avatar-md" style="margin-right: 12px;">
                        ${(chat.display_name || 'C').charAt(0).toUpperCase()}
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
                    <div class="position-relative" style="margin-right: 12px;">
                        ${item.avatar_url ? `
                            <img src="${escapeHtml(item.avatar_url)}" 
                                 class="rounded-circle" 
                                 style="width: 40px; height: 40px; object-fit: cover;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                 alt="">
                            <div class="avatar-placeholder avatar-md" 
                                 style="display: none; background: ${getAvatarColor(item.display_name || item.name || 'User')}; color: white;">${getInitials(item.display_name || item.name || 'User')}</div>
                            <span class="position-absolute bottom-0 end-0 ${badge.class} rounded-circle border border-2 border-white"
                                  style="width: 12px; height: 12px;"></span>
                        ` : `
                            <div class="avatar-placeholder avatar-md" 
                                 style="background: ${getAvatarColor(item.display_name || item.name || 'User')}; color: white;">${getInitials(item.display_name || item.name || 'User')}</div>
                        `}
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

        function renderContactResult(contact, phone) {
            if (!elements.searchResults) return;

            const badgeClass = contact.is_favorite ? 'bg-warning' : 'bg-success';
            const badgeText = contact.is_favorite ? 'Favorite' : 'Contact';

            elements.searchResults.innerHTML = `
            <div class="list-group-item">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center flex-grow-1 min-width-0">
                        <div class="position-relative" style="margin-right: 12px;">
                            ${contact.avatar_url ? `
                                <img src="${escapeHtml(contact.avatar_url)}" 
                                     class="rounded-circle" 
                                     style="width: 40px; height: 40px; object-fit: cover;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                     alt="">
                                <div class="avatar-placeholder avatar-md" 
                                     style="display: none; background: ${getAvatarColor(contact.display_name || 'Contact')}; color: white;">${getInitials(contact.display_name || 'Contact')}</div>
                                <span class="position-absolute bottom-0 end-0 ${badgeClass} rounded-circle border border-2 border-white"
                                      style="width: 12px; height: 12px;"></span>
                            ` : `
                                <div class="avatar-placeholder avatar-md">${(contact.display_name?.charAt(0) || 'C').toUpperCase()}</div>
                            `}
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

        function handleFilterClick(event) {
            // Find the filter button - check if clicked element is the button or inside it
            let button = event.target;
            if (!button.classList.contains('filter-btn')) {
                button = event.target.closest('.filter-btn');
            }

            if (!button) {
                // Check if clicking on icon inside button
                const iconParent = event.target.closest('i')?.parentElement;
                if (iconParent && iconParent.classList.contains('filter-btn')) {
                    button = iconParent;
                } else {
                    // Not a filter button click, allow default behavior
                    return;
                }
            }

            // Only prevent default for filter buttons
            event.preventDefault();
            event.stopPropagation();

            const filter = button.getAttribute('data-filter') || button.dataset.filter;
            if (!filter) {
                console.warn('Filter button clicked but no filter attribute found:', button);
                return;
            }

            console.log('Filter clicked:', filter); // Debug log

            const isCurrentlyActive = button.classList.contains('active');

            // Get all filter buttons
            const filterButtons = elements.searchFilters?.querySelectorAll('.filter-btn') || [];

            // If clicking the same active button (except "all"), deselect it and show "All"
            if (isCurrentlyActive && filter !== 'all') {
                // Deselect all buttons
                filterButtons.forEach(b => {
                    b.classList.remove('active');
                    b.setAttribute('aria-pressed', 'false');
                });
                // Select "All" button
                const allButton = Array.from(filterButtons).find(b => b.dataset.filter === 'all');
                if (allButton) {
                    allButton.classList.add('active');
                    allButton.setAttribute('aria-pressed', 'true');
                }
                // Show all items
                const conversationList = document.getElementById('conversation-list');
                const allItems = conversationList ? conversationList.querySelectorAll('.conversation-item') : document.querySelectorAll('.conversation-item');
                allItems.forEach(item => {
                    item.style.display = '';
                    item.style.visibility = '';
                    item.classList.remove('filtered-out');
                });

                // Also filter search results if there's an active search
                const query = elements.chatSearch?.value.trim();
                if (query) {
                    performSearch(query);
                }
                return;
            }

            // Single selection mode - only one filter can be active at a time
            filterButtons.forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-pressed', 'false');
            });
            button.classList.add('active');
            button.setAttribute('aria-pressed', 'true');

            // Filter sidebar conversation items
            const conversationList = document.getElementById('conversation-list');
            const allItems = conversationList ? conversationList.querySelectorAll('.conversation-item') : document.querySelectorAll('.conversation-item');
            console.log('Filtering', allItems.length, 'conversation items with filter:', filter);

            let visibleCount = 0;
            allItems.forEach(item => {
                let show = true;

                // Check if item is a group/channel (has data-group-id) or personal chat (has data-conversation-id but no data-group-id)
                const hasGroupId = item.hasAttribute('data-group-id') || item.dataset.groupId;
                const hasConversationId = item.hasAttribute('data-conversation-id') || item.dataset
                    .conversationId;
                const isPersonalChat = hasConversationId && !hasGroupId;
                const groupType = item.dataset.groupType || item.getAttribute('data-group-type') || '';

                if (filter && filter.startsWith('label-')) {
                    const labelId = filter.split('-')[1];
                    const labelsStr = item.dataset.labels || item.getAttribute('data-labels') || '';
                    const labels = labelsStr.split(',').filter(Boolean);
                    show = labels.includes(labelId);
                } else if (filter === 'unread') {
                    const unreadStr = item.dataset.unread || item.getAttribute('data-unread') || '0';
                    const unread = parseInt(unreadStr);
                    show = unread > 0;
                } else if (filter === 'groups') {
                    // Show only private groups (non-channel) when filtering groups
                    show = hasGroupId && groupType !== 'channel';
                } else if (filter === 'channels') {
                    // Show only channels
                    show = groupType === 'channel';
                } else if (filter === 'personal' || filter === 'chats') {
                    // Show only personal chats (conversations without group-id)
                    show = isPersonalChat;
                } else if (filter === 'archived') {
                    // Show only archived conversations
                    const isArchived = item.dataset.archived === 'true' || item.hasAttribute('data-archived');
                    show = isArchived;
                } else if (filter === 'broadcast') {
                    // Broadcast filter - navigate to broadcast lists page but keep sidebar
                    // Since broadcast lists aren't conversations, navigate to broadcast page
                    if (visibleCount === 0 && allItems.length > 0) {
                        // All items are hidden, navigate to broadcast lists
                        setTimeout(() => {
                            window.location.href = '/broadcast-lists';
                        }, 100);
                        return;
                    }
                    show = false; // Hide all conversations for broadcast filter
                } else if (filter === 'chat') {
                    // Chat filter - show only personal chats (conversations without group-id)
                    show = isPersonalChat;
                } else if (filter === 'all') {
                    // Show everything (excluding archived by default)
                    const isArchived = item.dataset.archived === 'true' || item.hasAttribute('data-archived');
                    show = !isArchived; // Exclude archived from "all" view
                } else {
                    // Unknown filter, show all
                    show = true;
                }

                if (show) {
                    visibleCount++;
                    item.style.display = '';
                    item.style.visibility = '';
                    item.classList.remove('filtered-out');
                } else {
                    item.style.display = 'none';
                    item.style.visibility = 'hidden';
                    item.classList.add('filtered-out');
                }
            });

            console.log('Filter result: showing', visibleCount, 'of', allItems.length, 'items');

            // Also filter search results if there's an active search
            const query = elements.chatSearch?.value.trim();
            if (query) {
                performSearch(query);
            }
        }

        function updateFilterButtons() {
            // Button state is now managed directly in handleFilterClick
            // This function is kept for backward compatibility but may not be needed
            const buttons = elements.searchFilters?.querySelectorAll('.filter-btn') || [];
            const activeButton = elements.searchFilters?.querySelector('.filter-btn.active');
            if (activeButton) {
                buttons.forEach(button => {
                    const isActive = button === activeButton;
                    button.classList.toggle('active', isActive);
                    button.setAttribute('aria-pressed', isActive.toString());
                });
            }
        }

        function handleClickOutsideSearch(event) {
            if (!elements.searchResults || !elements.chatSearch || !elements.searchFiltersContainer) return;

            const isClickInside = elements.searchResults.contains(event.target) ||
                elements.chatSearch.contains(event.target) ||
                elements.searchFiltersContainer.contains(event.target);

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
            if (elements.searchResults && elements.searchFiltersContainer) {
                elements.searchResults.classList.remove('d-none');
                elements.searchFiltersContainer.style.display = 'block';
            }
        }

        function hideSearchResults() {
            if (elements.searchResults && elements.searchFiltersContainer) {
                elements.searchResults.classList.add('d-none');
                elements.searchFiltersContainer.style.display = 'none';
            }
        }

        // ==== Contact Management ====
        async function startChatWithContact(contactId, userId) {
            try {
                const response = await apiCall('/api/chat/start', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
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
                            'X-CSRF-TOKEN': getCsrfToken()
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
                        'X-CSRF-TOKEN': getCsrfToken()
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

        async function handleStartPhoneChat() {
            const rawPhone = elements.newChatPhoneInput?.value.trim();
            if (!rawPhone) {
                showToast('Please enter a phone number', 'error');
                return;
            }

            const plus = rawPhone.startsWith('+') ? '+' : '';
            const digits = rawPhone.replace(/\D+/g, '');
            const formattedPhone = plus + digits;

            await startChatWithPhone(formattedPhone);
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

        // Setup New Dropdown Button Listeners
        function setupNewDropdownListeners() {
            // New Contact button
            const newContactBtn = document.getElementById('new-contact-btn');
            if (newContactBtn) {
                newContactBtn.addEventListener('click', function() {
                    // Close other panels first
                    const groupPanel = document.getElementById('sb-create-group');
                    if (groupPanel) {
                        const bsCollapse = bootstrap.Collapse.getInstance(groupPanel);
                        if (bsCollapse) bsCollapse.hide();
                    }
                });
            }

            // New Group button
            const newGroupBtn = document.getElementById('new-group-btn-dropdown');
            if (newGroupBtn) {
                newGroupBtn.addEventListener('click', function() {
                    // Close new chat panel if open
                    const newChatPanel = document.getElementById('sb-new-chat');
                    if (newChatPanel) {
                        const bsCollapse = bootstrap.Collapse.getInstance(newChatPanel);
                        if (bsCollapse) bsCollapse.hide();
                    }
                    // Set type to group and open group panel
                    const groupRadio = document.getElementById('sb-gp-group');
                    if (groupRadio) {
                        groupRadio.checked = true;
                        groupRadio.dispatchEvent(new Event('change'));
                    }
                    // Open the group panel
                    const groupPanel = document.getElementById('sb-create-group');
                    if (groupPanel) {
                        const bsCollapse = bootstrap.Collapse.getInstance(groupPanel) || new bootstrap.Collapse(groupPanel);
                        bsCollapse.show();
                    }
                });
            }

            // New Channel button
            const newChannelBtn = document.getElementById('new-channel-btn-dropdown');
            if (newChannelBtn) {
                newChannelBtn.addEventListener('click', function() {
                    // Close new chat panel if open
                    const newChatPanel = document.getElementById('sb-new-chat');
                    if (newChatPanel) {
                        const bsCollapse = bootstrap.Collapse.getInstance(newChatPanel);
                        if (bsCollapse) bsCollapse.hide();
                    }
                    // Set type to channel and open group panel
                    const channelRadio = document.getElementById('sb-gp-channel');
                    if (channelRadio) {
                        channelRadio.checked = true;
                        channelRadio.dispatchEvent(new Event('change'));
                    }
                    // Open the group panel
                    const groupPanel = document.getElementById('sb-create-group');
                    if (groupPanel) {
                        const bsCollapse = bootstrap.Collapse.getInstance(groupPanel) || new bootstrap.Collapse(groupPanel);
                        bsCollapse.show();
                    }
                });
            }

            // New Broadcast button
            const newBroadcastBtn = document.getElementById('new-broadcast-btn-dropdown');
            if (newBroadcastBtn) {
                newBroadcastBtn.addEventListener('click', function() {
                    // Close any open panels
                    const newChatPanel = document.getElementById('sb-new-chat');
                    const groupPanel = document.getElementById('sb-create-group');
                    if (newChatPanel) {
                        const bsCollapse = bootstrap.Collapse.getInstance(newChatPanel);
                        if (bsCollapse) bsCollapse.hide();
                    }
                    if (groupPanel) {
                        const bsCollapse = bootstrap.Collapse.getInstance(groupPanel);
                        if (bsCollapse) bsCollapse.hide();
                    }
                    // Open broadcast modal
                    const broadcastModal = document.getElementById('create-broadcast-modal');
                    if (broadcastModal) {
                        // Ensure modal is in body (not inside sidebar) to avoid stacking context issues
                        const sidebar = document.getElementById('conversation-sidebar');
                        if (sidebar && sidebar.contains(broadcastModal)) {
                            document.body.appendChild(broadcastModal);
                        }
                        
                        // Clean up any existing backdrops first
                        const existingBackdrops = document.querySelectorAll('.modal-backdrop');
                        existingBackdrops.forEach(backdrop => backdrop.remove());
                        
                        // Get existing modal instance or create a new one
                        let modal = bootstrap.Modal.getInstance(broadcastModal);
                        if (!modal) {
                            modal = new bootstrap.Modal(broadcastModal, {
                                backdrop: true,
                                keyboard: true,
                                focus: true
                            });
                        }
                        
                        // Set z-index before showing
                        broadcastModal.style.zIndex = '1050';
                        
                        modal.show();
                        
                        // Fix backdrop z-index after modal is shown
                        broadcastModal.addEventListener('shown.bs.modal', function fixBackdrop() {
                            setTimeout(() => {
                                const backdrops = document.querySelectorAll('.modal-backdrop');
                                backdrops.forEach(backdrop => {
                                    backdrop.style.zIndex = '1040';
                                });
                                broadcastModal.style.zIndex = '1050';
                                const modalDialog = broadcastModal.querySelector('.modal-dialog');
                                if (modalDialog) {
                                    modalDialog.style.zIndex = '1051';
                                }
                            }, 10);
                            broadcastModal.removeEventListener('shown.bs.modal', fixBackdrop);
                        }, { once: true });
                        
                        // Load contacts for modal
                        if (typeof window.loadContactsForModal === 'function') {
                            window.loadContactsForModal();
                        }
                    } else {
                        // If modal doesn't exist on this page, navigate to broadcast page
                        window.location.href = '/broadcast-lists';
                    }
                });
            }
        }

        function setupGroupTypeHandlers() {
            const typeRadios = document.querySelectorAll('input[name="type"]');
            const isPrivateField = document.getElementById('sb-gp-is-private');
            const createButton = document.getElementById('sb-gp-create');
            const createButtonText = document.getElementById('sb-gp-create-text');

            function updateButtonText() {
                const checkedType = document.querySelector('input[name="type"]:checked');
                if (checkedType && createButtonText) {
                    if (checkedType.value === 'channel') {
                        createButtonText.textContent = 'Create Channel';
                        if (createButton.querySelector('i')) {
                            createButton.querySelector('i').className = 'bi bi-broadcast me-1';
                        }
                    } else {
                        createButtonText.textContent = 'Create Group';
                        if (createButton.querySelector('i')) {
                            createButton.querySelector('i').className = 'bi bi-people-fill me-1';
                        }
                    }
                }
            }

            if (typeRadios.length && isPrivateField) {
                typeRadios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        isPrivateField.value = this.getAttribute('data-is-private');
                        updateButtonText();
                    });
                });

                // Initialize the hidden field with the correct value
                const checkedType = document.querySelector('input[name="type"]:checked');
                updateButtonText();
                if (checkedType) {
                    isPrivateField.value = checkedType.getAttribute('data-is-private');
                }
            }
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
                elements.groupAvatarPreview.src =
                    "{{ \App\Helpers\UrlHelper::secureAsset('images/group-default.png') }}";
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

        // ==== Invite Modal ====
        function setupInviteModalListeners() {
            // Copy invite link
            elements.inviteCopyBtn?.addEventListener('click', copyInviteLink);

            // Share invite
            elements.inviteShareBtn?.addEventListener('click', shareInvite);
        }

        function showInviteModal(phone, inviteData = null) {
            if (!elements.inviteModal) return;

            // Fill modal data
            if (elements.invitePhoneHint) {
                elements.invitePhoneHint.textContent = `Phone: ${phone}`;
            }

            if (elements.inviteLinkInput && inviteData?.link) {
                elements.inviteLinkInput.value = inviteData.link;
            }

            // Setup SMS button
            if (elements.inviteSmsBtn) {
                const smsText = inviteData?.sms_text ||
                    `Join me on {{ config('app.name', 'GekyChat') }}: ${inviteData?.link || window.location.origin}`;
                const smsTarget = phone.replace(/[^\d+]/g, '');
                elements.inviteSmsBtn.href = `sms:${smsTarget}?&body=${encodeURIComponent(smsText)}`;
            }

            // Setup share button
            if (elements.inviteShareBtn) {
                if (navigator.share) {
                    elements.inviteShareBtn.style.display = 'block';
                    elements.inviteShareBtn.onclick = async () => {
                        try {
                            await navigator.share({
                                title: 'Join me on GekyChat',
                                text: inviteData?.share_text || `Let's chat on GekyChat!`,
                                url: inviteData?.link || window.location.origin
                            });
                        } catch (e) {
                            // User cancelled share
                        }
                    };
                } else {
                    elements.inviteShareBtn.style.display = 'none';
                }
            }

            // Show modal
            const modal = new bootstrap.Modal(elements.inviteModal);
            modal.show();
        }

        function copyInviteLink() {
            if (elements.inviteLinkInput) {
                elements.inviteLinkInput.select();
                document.execCommand('copy');
                showToast('Invite link copied to clipboard', 'success');
            }
        }

        function shareInvite() {
            if (navigator.share) {
                navigator.share({
                    title: 'Join me on GekyChat',
                    text: 'Let\'s chat on GekyChat!',
                    url: elements.inviteLinkInput?.value || window.location.origin
                }).catch(() => {
                    copyInviteLink();
                });
            } else {
                copyInviteLink();
            }
        }

        // ==== Phone Validation ====
        function setupPhoneValidation() {
            const startByPhoneBtn = document.getElementById('sb-nc-start-phone');
            const phoneInput = document.getElementById('sb-nc-phone-input');

            if (startByPhoneBtn && phoneInput) {
                function validatePhone(v) {
                    return (v || '').replace(/[^\d+]/g, '').length >= 10;
                }

                function syncPhoneButton() {
                    startByPhoneBtn.disabled = !validatePhone(phoneInput.value.trim());
                }

                phoneInput.addEventListener('input', syncPhoneButton);
                syncPhoneButton();
            }
        }

        // ==== Utility Functions ====
        async function apiCall(url, options = {}) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
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

        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        function showToast(message, type = 'info', duration = CONFIG.NOTIFICATION_TIMEOUT) {
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
            }, duration);
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

        function showNewMessageNotification(message) {
            const conversationName = message.conversation?.name || message.group?.name || 'Unknown';
            const senderName = message.sender?.name || 'Someone';
            const isGroup = message.is_group || false;

            showRealTimeToast(
                `New message from ${senderName} ${isGroup ? `in ${conversationName}` : ''}`,
                'info', {
                    actionText: 'View',
                    actionCallback: () => {
                        const path = isGroup ? `/g/${message.group_id}` : `/c/${message.conversation_id}`;
                        window.location.href = path;
                    },
                    duration: 8000
                }
            );
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

        // ==== Account Switcher Functions (Inside Main IIFE) ====
        // Get or create device ID for web
        function getOrCreateDeviceId() {
            let deviceId = localStorage.getItem('web_device_id');
            if (!deviceId) {
                // Generate a unique device ID for web
                deviceId = 'web_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem('web_device_id', deviceId);
            }
            return deviceId;
        }

        async function showAccountSwitcherModal() {
            try {
                const deviceId = getOrCreateDeviceId();
                const response = await fetch(`{{ route('settings.auth.accounts') }}?device_id=${deviceId}&device_type=web`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin'
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to load accounts');
                }

                const accounts = data.data || [];
                
                if (accounts.length <= 1) {
                    // Logout first, then redirect to login page to add another account
                    // Create a form and submit it for logout (Laravel requires POST with CSRF)
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '/logout';
                    
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = csrfToken;
                    form.appendChild(csrfInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                    return;
                }

                // Create modal HTML
                const modalHtml = `
                    <div class="modal fade" id="accountSwitcherModal" tabindex="-1" aria-labelledby="accountSwitcherModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="accountSwitcherModalLabel">Switch Account</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="list-group">
                                        ${accounts.map(account => {
                                            const user = account.user || {};
                                            const userName = user.name || user.phone || 'Account';
                                            const isActive = account.is_active === true;
                                            return `
                                                <div class="list-group-item ${isActive ? 'active' : ''} account-item" 
                                                     data-account-id="${account.id}"
                                                     style="cursor: ${isActive ? 'default' : 'pointer'};">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <div class="avatar-placeholder avatar-sm" 
                                                                 style="background: ${getAvatarColor(userName)}; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                                                ${getInitials(userName)}
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-0">${escapeHtml(userName)}</h6>
                                                                ${account.account_label ? `<small class="text-muted">${escapeHtml(account.account_label)}</small>` : ''}
                                                            </div>
                                                        </div>
                                                        ${isActive ? '<span class="badge bg-success">Active</span>' : ''}
                                                        ${!isActive ? `<button class="btn btn-sm btn-outline-danger remove-account-btn" data-account-id="${account.id}" onclick="event.stopPropagation(); window.removeAccountFromWeb(${account.id})">
                                                            <i class="bi bi-trash"></i>
                                                        </button>` : ''}
                                                    </div>
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal if any
                const existingModal = document.getElementById('accountSwitcherModal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Add modal to body
                document.body.insertAdjacentHTML('beforeend', modalHtml);

                // Setup click handlers
                const accountItems = document.querySelectorAll('.account-item:not(.active)');
                accountItems.forEach(item => {
                    item.addEventListener('click', function() {
                        const accountId = parseInt(this.dataset.accountId);
                        switchAccountOnWeb(accountId);
                    });
                });

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('accountSwitcherModal'));
                modal.show();
            } catch (error) {
                console.error('Error loading accounts:', error);
                alert('Failed to load accounts: ' + error.message);
            }
        }

        async function switchAccountOnWeb(accountId) {
            try {
                const deviceId = getOrCreateDeviceId();
                const response = await fetch('{{ route('settings.auth.switch-account') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        account_id: accountId,
                        device_id: deviceId,
                        device_type: 'web'
                    })
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to switch account');
                }

                // Store new token (if provided, though web uses session-based auth)
                if (data.token) {
                    // For web, we might need to refresh the page to use the new session
                    // Or handle token storage if using API tokens
                }

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('accountSwitcherModal'));
                if (modal) {
                    modal.hide();
                }

                // Reload page to refresh with new account
                window.location.reload();
            } catch (error) {
                console.error('Error switching account:', error);
                alert('Failed to switch account: ' + error.message);
            }
        }

        async function removeAccountFromWeb(accountId) {
            if (!confirm('Are you sure you want to remove this account? You will need to log in again to use it.')) {
                return;
            }

            try {
                const deviceId = getOrCreateDeviceId();
                const response = await fetch(`/settings/auth/accounts/${accountId}?device_id=${deviceId}&device_type=web`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin'
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to remove account');
                }

                // Close modal and reload
                const modal = bootstrap.Modal.getInstance(document.getElementById('accountSwitcherModal'));
                if (modal) {
                    modal.hide();
                }

                // Refresh accounts list or reload page
                showAccountSwitcherModal();
            } catch (error) {
                console.error('Error removing account:', error);
                alert('Failed to remove account: ' + error.message);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Expose account switcher functions globally
        window.showAccountSwitcherModal = showAccountSwitcherModal;
        window.switchAccountOnWeb = switchAccountOnWeb;
        window.removeAccountFromWeb = removeAccountFromWeb;

        // ==== Global Exports ====
        window.sidebarApp = {
            startChatWithPhone,
            showToast,
            showRealTimeToast,
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
                window.location.reload();
            },

            // Debug methods
            getState: () => state,
            getElements: () => elements
        };

        // ==== Cleanup ====
        function cleanup() {
            if (state.notificationTimeout) {
                clearTimeout(state.notificationTimeout);
            }

            // Clean up real-time listeners
            Object.values(state.realTimeListeners).forEach(listener => {
                if (listener && typeof listener.stop === 'function') {
                    listener.stop();
                }
            });
        }

        window.addEventListener('beforeunload', cleanup);

    })();

// Status Creation Functionality
(function() {
    let statusModal = null;
    let statusModalElement = null;
    
    // Define form elements at IIFE level so they're accessible throughout
    const statusForm = document.getElementById('status-form');
    const statusContent = document.getElementById('status-content');
    const charCounter = document.querySelector('.char-counter');
    const mediaUploadGroup = document.getElementById('media-upload-group');
    const textContentGroup = document.getElementById('text-content-group');
    const textStylingGroup = document.getElementById('text-styling-group');
    const textPreviewGroup = document.getElementById('text-preview-group');
    const mediaDropzone = document.getElementById('media-dropzone');
    const statusMedia = document.getElementById('status-media');
    const mediaPreview = document.getElementById('media-preview');
    const mediaPreviewContainer = document.getElementById('media-preview-container');
    const removeMediaBtn = document.getElementById('remove-media');
    const backgroundColor = document.getElementById('background-color');
    const textColor = document.getElementById('text-color');
    const textPreview = document.getElementById('text-preview');
    const previewText = document.getElementById('preview-text');
    const postStatusBtn = document.getElementById('post-status-btn');
    
    function initStatusCreation() {
        statusModalElement = document.getElementById('statusCreatorModal');
        if (!statusModalElement) {
            console.warn('Status creator modal not found, will retry');
            setTimeout(initStatusCreation, 200);
            return;
        }
        
        // Ensure modal is in body (not inside sidebar) to avoid stacking context issues
        const sidebar = document.getElementById('conversation-sidebar');
        if (sidebar && sidebar.contains(statusModalElement)) {
            document.body.appendChild(statusModalElement);
            console.log('Moved statusCreatorModal to body');
        }
        
        // Set high z-index immediately
        statusModalElement.style.zIndex = '9999';
        statusModalElement.style.position = 'fixed';

        // Check if Bootstrap is available
        if (typeof bootstrap === 'undefined') {
            console.warn('Bootstrap not available yet, will retry');
            setTimeout(initStatusCreation, 200);
            return;
        }

        // Initialize Bootstrap modal
        try {
            statusModal = new bootstrap.Modal(statusModalElement, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
            console.log('Status modal initialized');
        } catch (error) {
            console.error('Failed to initialize status modal:', error);
            return;
        }

        // Ensure modal can be opened via data attributes
        // Handle clicks on status add button - use event delegation
        // Only attach once to avoid duplicate handlers
        if (!window.statusModalClickHandlerAttached) {
            document.addEventListener('click', function(e) {
                const statusAddBtn = e.target.closest(
                    '[data-bs-target="#statusCreatorModal"], .status-add-btn-new');
                if (statusAddBtn && statusModalElement) {
                    e.preventDefault();
                    e.stopPropagation();
                        try {
                            if (statusModal) {
                                statusModal.show();
                            } else if (typeof bootstrap !== 'undefined') {
                                // Fallback: create new modal instance
                                const bsModal = new bootstrap.Modal(statusModalElement);
                                bsModal.show();
                            } else {
                                console.error('Bootstrap not available to show modal');
                            }
                        } catch (error) {
                            console.error('Error opening status modal:', error);
                        // Last resort: manually show modal
                        statusModalElement.style.zIndex = '9999';
                        statusModalElement.style.position = 'fixed';
                        statusModalElement.classList.add('show');
                        statusModalElement.style.display = 'block';
                        document.body.classList.add('modal-open');
                        const backdrop = document.createElement('div');
                        backdrop.className = 'modal-backdrop fade show';
                        backdrop.style.zIndex = '9998';
                        backdrop.style.position = 'fixed';
                        backdrop.style.top = '0';
                        backdrop.style.left = '0';
                        backdrop.style.width = '100%';
                        backdrop.style.height = '100%';
                        document.body.appendChild(backdrop);
                    }
                    return false;
                }
            }, true);
            window.statusModalClickHandlerAttached = true;
            console.log('Status modal click handler attached');
        }

        // Toast notification function
        function showToast(message, type = 'success') {
                // Create toast element
                const toast = document.createElement('div');
                toast.className =
                    `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0 position-fixed top-0 end-0 m-3`;
                toast.style.zIndex = '9999';
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');

                toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}-fill me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

                document.body.appendChild(toast);

                // Initialize and show toast
                const bsToast = new bootstrap.Toast(toast, {
                    autohide: true,
                    delay: 3000
                });
                bsToast.show();

                // Remove from DOM after hiding
                toast.addEventListener('hidden.bs.toast', function() {
                    toast.remove();
                });
        }

        // Debug: Check if elements are found
        if (!mediaDropzone) {
            console.warn('Media dropzone not found');
        }
        if (!statusMedia) {
            console.warn('Status media input not found');
        }
        // Check if all required elements exist
        if (!statusForm || !statusContent || !postStatusBtn) {
            console.warn('Status form elements not found');
            return;
        }

            // Event delegation for remove single media buttons
            if (mediaPreviewContainer) {
                mediaPreviewContainer.addEventListener('click', function(e) {
                    const removeBtn = e.target.closest('.remove-single-media');
                    if (removeBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        const removeIndex = parseInt(removeBtn.getAttribute('data-index'));
                        removeSingleMedia(removeIndex);
                    }
                });
            }

            // Note: Status modal can still be opened from the status carousel add button
            // which has data-bs-toggle="modal" data-bs-target="#statusCreatorModal"

            // Also setup when modal is shown (in case opened by other means)
            if (statusModalElement) {
                // Fix z-index when modal is shown - ensure backdrop is behind modal
                statusModalElement.addEventListener('show.bs.modal', function() {
                    // Ensure modal is in body (not inside sidebar)
                    const sidebar = document.getElementById('conversation-sidebar');
                    if (sidebar && sidebar.contains(this)) {
                        document.body.appendChild(this);
                    }

                    // Ensure modal has very high z-index before showing
                    this.style.zIndex = '9999';
                    this.style.position = 'fixed';
                    this.style.top = '0';
                    this.style.left = '0';
                    this.style.width = '100%';
                    this.style.height = '100%';

                    // Ensure modal dialog has very high z-index
                    const modalDialog = this.querySelector('.modal-dialog');
                    if (modalDialog) {
                        modalDialog.style.zIndex = '10000';
                    }
                    const modalContent = this.querySelector('.modal-content');
                    if (modalContent) {
                        modalContent.style.zIndex = '10001';
                    }
                });

                statusModalElement.addEventListener('shown.bs.modal', function() {
                    // Fix ALL backdrop z-index after Bootstrap creates it
                    setTimeout(function() {
                        // Get ALL backdrops and ensure they're all behind modals
                        const backdrops = document.querySelectorAll('.modal-backdrop');
                        backdrops.forEach((backdrop) => {
                            backdrop.style.zIndex = '1050';
                            backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
                            backdrop.style.opacity = '1';
                            backdrop.style.position = 'fixed';
                            backdrop.style.top = '0';
                            backdrop.style.left = '0';
                            backdrop.style.width = '100%';
                            backdrop.style.height = '100%';
                        });

                        // Count how many modals are currently shown to determine z-index
                        const shownModals = document.querySelectorAll('.modal.show');
                        const modalIndex = Array.from(shownModals).indexOf(statusModalElement);
                        const baseZIndex = 1060;
                        const modalZIndex = baseZIndex + (modalIndex * 10);

                        statusModalElement.style.zIndex = modalZIndex.toString();
                        const modalDialog = statusModalElement.querySelector('.modal-dialog');
                        if (modalDialog) {
                            modalDialog.style.zIndex = (modalZIndex + 1).toString();
                        }
                        const modalContent = statusModalElement.querySelector('.modal-content');
                        if (modalContent) {
                            modalContent.style.zIndex = (modalZIndex + 2).toString();
                        }
                    }, 10);

                    setTimeout(function() {
                        setupMediaUpload();
                    }, 100);
                });
            }
        } // Close initStatusCreation() function

        // Update character counter
        if (statusContent && charCounter && previewText) {
            statusContent.addEventListener('input', function() {
                const length = this.value.length;
                charCounter.textContent = `${length}/500`;

                if (length > 450) {
                    charCounter.classList.add('warning');
                } else {
                    charCounter.classList.remove('warning');
                }

                // Update preview
                previewText.textContent = this.value || 'Your status will appear here';
            });
        }

        // Track selected media files
        let selectedMediaFiles = [];

        // Handle media upload - setup when elements exist
        function setupMediaUpload() {
            const mediaDropzoneEl = document.getElementById('media-dropzone');
            const statusMediaEl = document.getElementById('status-media');

            if (!mediaDropzoneEl || !statusMediaEl) {
                console.log('Media upload elements not found');
                return false;
            }

            // Remove d-none class and ensure the file input is accessible even when visually hidden
            statusMediaEl.classList.remove('d-none');
            statusMediaEl.style.pointerEvents = 'auto';
            statusMediaEl.style.position = 'absolute';
            statusMediaEl.style.opacity = '0';
            statusMediaEl.style.width = '1px';
            statusMediaEl.style.height = '1px';
            statusMediaEl.style.overflow = 'hidden';
            statusMediaEl.style.zIndex = '-1';
            statusMediaEl.style.display = 'block'; // Override d-none

            // Remove old event listeners by cloning (clean slate)
            const mediaDropzoneClone = mediaDropzoneEl.cloneNode(true);
            mediaDropzoneEl.parentNode.replaceChild(mediaDropzoneClone, mediaDropzoneEl);

            // Get the file input from the cloned dropzone (it's inside it)
            const fileInput = mediaDropzoneClone.querySelector('#status-media');
            if (!fileInput) {
                console.error('File input not found in cloned dropzone');
                return false;
            }

            // Ensure file input is properly configured
            fileInput.classList.remove('d-none');
            fileInput.style.pointerEvents = 'auto';
            fileInput.style.position = 'absolute';
            fileInput.style.opacity = '0';
            fileInput.style.width = '1px';
            fileInput.style.height = '1px';
            fileInput.style.overflow = 'hidden';
            fileInput.style.zIndex = '9999';
            fileInput.style.display = 'block';

            // Setup click handler on dropzone
            mediaDropzoneClone.addEventListener('click', function(e) {
                // Don't prevent default if clicking directly on the file input
                if (e.target === fileInput || e.target.closest('input[type="file"]')) {
                    return; // Let the native file input handle it
                }

                e.preventDefault();
                e.stopPropagation();

                // Trigger file input click immediately
                try {
                    fileInput.click();
                } catch (error) {
                    console.error('Error triggering file input:', error);
                    // Fallback: create and dispatch click event
                    const clickEvent = new MouseEvent('click', {
                        view: window,
                        bubbles: true,
                        cancelable: true,
                        buttons: 1
                    });
                    fileInput.dispatchEvent(clickEvent);
                }
            });

            // Drag and drop functionality
            mediaDropzoneClone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            mediaDropzoneClone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            mediaDropzoneClone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                handleMediaFiles(e.dataTransfer.files);
            });

            // Handle file selection - attach to the file input from cloned dropzone
            fileInput.addEventListener('change', function(e) {
                console.log('=== FILE INPUT CHANGE EVENT ===');
                console.log('Event:', e);
                console.log('Files:', e.target.files);
                console.log('File count:', e.target.files ? e.target.files.length : 0);
                if (e.target.files && e.target.files.length > 0) {
                    console.log('Calling handleMediaFiles with', e.target.files.length, 'files');
                    handleMediaFiles(e.target.files);
                } else {
                    console.log('No files selected');
                }
            });

            console.log('Media upload setup complete');
            return true;
        }

        // Try to setup media upload immediately if elements exist
        if (mediaDropzone && statusMedia) {
            setupMediaUpload();
        }

        function handleMediaFiles(files) {
            if (!files || files.length === 0) return;

            const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm'];
            const maxSize = 10 * 1024 * 1024; // 10MB

            // Clear previous selections
            selectedMediaFiles = [];
            const previewContainer = document.getElementById('media-preview-container');
            if (previewContainer) previewContainer.innerHTML = '';

            // Process all files
            Array.from(files).forEach((file, index) => {
                if (!validTypes.includes(file.type)) {
                    alert(
                        `File "${file.name}" is not a valid image (JPG, PNG, WebP, GIF) or video (MP4, WebM) file.`
                        );
                    return;
                }

                if (file.size > maxSize) {
                    alert(`File "${file.name}" is too large. Maximum size is 10MB.`);
                    return;
                }

                selectedMediaFiles.push(file);
                displayMediaPreview(file, previewContainer, index);
            });

            // Show/hide styling and preview based on whether media is selected
            if (selectedMediaFiles.length > 0) {
                // If multiple media, hide main text field and styling options
                if (selectedMediaFiles.length > 1) {
                    if (textContentGroup) textContentGroup.classList.add('d-none');
                    if (textStylingGroup) textStylingGroup.classList.add('d-none');
                    if (textPreviewGroup) textPreviewGroup.classList.add('d-none');
                } else {
                    // Single media: show main text field as caption
                    if (textContentGroup) textContentGroup.classList.remove('d-none');
                    if (textStylingGroup) textStylingGroup.classList.add('d-none');
                    if (textPreviewGroup) textPreviewGroup.classList.add('d-none');
                    if (textContentGroup) {
                        const label = textContentGroup.querySelector('label');
                        if (label) label.textContent = "Add a caption (optional)";
                    }
                }
                if (mediaPreview) mediaPreview.classList.remove('d-none');
            } else {
                if (textContentGroup) textContentGroup.classList.remove('d-none');
                if (textStylingGroup) textStylingGroup.classList.remove('d-none');
                if (textPreviewGroup) textPreviewGroup.classList.remove('d-none');
                if (textContentGroup) {
                    const label = textContentGroup.querySelector('label');
                    if (label) label.textContent = "What's on your mind?";
                }
                if (mediaPreview) mediaPreview.classList.add('d-none');
            }
        }

        function displayMediaPreview(file, container, index) {
            const previewItem = document.createElement('div');
            previewItem.className = 'media-preview-item mb-3 p-3 border rounded';
            previewItem.setAttribute('data-media-index', index);

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewItem.innerHTML = `
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <img src="${e.target.result}" class="rounded shadow" 
                             style="max-height: 150px; max-width: 150px; object-fit: contain;">
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto remove-single-media" data-index="${index}">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    ${selectedMediaFiles.length > 1 ? `
                        <div class="mt-2">
                            <label class="form-label small mb-1">Caption (optional)</label>
                            <textarea class="form-control form-control-sm media-caption" 
                                      rows="2" 
                                      placeholder="Add a caption for this image..."
                                      data-media-index="${index}"></textarea>
                        </div>
                    ` : ''}
                `;
                    container.appendChild(previewItem);
                };
                reader.readAsDataURL(file);
            } else if (file.type.startsWith('video/')) {
                const url = URL.createObjectURL(file);
                previewItem.innerHTML = `
                <div class="d-flex align-items-start gap-2 mb-2">
                    <video src="${url}" class="rounded shadow" controls 
                           style="max-height: 150px; max-width: 150px; object-fit: contain;"></video>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto remove-single-media" data-index="${index}">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                ${selectedMediaFiles.length > 1 ? `
                    <div class="mt-2">
                        <label class="form-label small mb-1">Caption (optional)</label>
                        <textarea class="form-control form-control-sm media-caption" 
                                  rows="2" 
                                  placeholder="Add a caption for this video..."
                                  data-media-index="${index}"></textarea>
                    </div>
                ` : ''}
            `;
                container.appendChild(previewItem);
            }
        }

        function removeSingleMedia(index) {
            // Remove from array
            selectedMediaFiles.splice(index, 1);

            // Rebuild preview with updated indices
            const previewContainer = document.getElementById('media-preview-container');
            if (previewContainer) previewContainer.innerHTML = '';

            selectedMediaFiles.forEach((file, newIndex) => {
                displayMediaPreview(file, previewContainer, newIndex);
            });

            // Update file input (this is tricky - we need to create a new FileList)
            // For now, just clear it and user needs to re-select
            statusMedia.value = '';

            // Update UI visibility based on remaining files
            if (selectedMediaFiles.length === 0) {
                if (textContentGroup) textContentGroup.classList.remove('d-none');
                if (textStylingGroup) textStylingGroup.classList.remove('d-none');
                if (textPreviewGroup) textPreviewGroup.classList.remove('d-none');
                if (textContentGroup) {
                    const label = textContentGroup.querySelector('label');
                    if (label) label.textContent = "What's on your mind?";
                }
                if (mediaPreview) mediaPreview.classList.add('d-none');
            } else if (selectedMediaFiles.length === 1) {
                // Single media: show main text field as caption
                if (textContentGroup) textContentGroup.classList.remove('d-none');
                if (textStylingGroup) textStylingGroup.classList.add('d-none');
                if (textPreviewGroup) textPreviewGroup.classList.add('d-none');
                if (textContentGroup) {
                    const label = textContentGroup.querySelector('label');
                    if (label) label.textContent = "Add a caption (optional)";
                }
            }
        }

        // Remove all media
        if (removeMediaBtn) {
            removeMediaBtn.addEventListener('click', function() {
                selectedMediaFiles = [];
                if (statusMedia) statusMedia.value = '';
                if (mediaPreview) mediaPreview.classList.add('d-none');
                const previewContainer = document.getElementById('media-preview-container');
                if (previewContainer) previewContainer.innerHTML = '';

                // Show styling options again
                if (textContentGroup) textContentGroup.classList.remove('d-none');
                if (textStylingGroup) textStylingGroup.classList.remove('d-none');
                if (textPreviewGroup) textPreviewGroup.classList.remove('d-none');
                if (textContentGroup) {
                    const label = textContentGroup.querySelector('label');
                    if (label) label.textContent = "What's on your mind?";
                }
            });
        }

        // Update text preview styling
        let updatePreview = function() {
            if (textPreview && backgroundColor && textColor) {
                textPreview.style.background = backgroundColor.value;
                textPreview.style.color = textColor.value;
            }
        };

        if (backgroundColor && textColor && textPreview) {
            backgroundColor.addEventListener('input', updatePreview);
            textColor.addEventListener('input', updatePreview);
            // Initialize preview
            updatePreview();
        }

        // Post status - use both click and prevent form submission
        console.log('Setting up post status button handler...');
        console.log('postStatusBtn element:', postStatusBtn);
        console.log('postStatusBtnHandlerAttached flag:', window.postStatusBtnHandlerAttached);

        if (!window.postStatusBtnHandlerAttached && postStatusBtn) {
            console.log('Attaching post status button click handler');
            postStatusBtn.addEventListener('click', async function(e) {
                console.log('=== POST STATUS BUTTON CLICKED ===');
                console.log('Event:', e);
                console.log('Button element:', this);
                console.log('Button disabled:', this.disabled);

                e.preventDefault();
                e.stopPropagation();

                const hasMedia = selectedMediaFiles.length > 0;
                const hasText = statusContent.value.trim();
                const isMultipleMedia = selectedMediaFiles.length > 1;

                console.log('Has media:', hasMedia, 'Media files:', selectedMediaFiles.length);
                console.log('Has text:', hasText, 'Text:', statusContent.value);
                console.log('Is multiple media:', isMultipleMedia);

                // Validation: Must have either text or media (or both)
                if (!hasMedia && !hasText) {
                    console.log('Validation failed: No media or text');
                    alert('Please enter some text or upload a media file for your status.');
                    return;
                }

                // Show loading state
                const originalText = postStatusBtn.innerHTML;
                postStatusBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Posting...';
                postStatusBtn.disabled = true;
                console.log('Button state updated to loading');

                try {
                    if (isMultipleMedia) {
                        console.log('Posting multiple statuses...');
                        // Handle multiple media: create one status per media file
                        await postMultipleStatuses();
                    } else {
                        console.log('Posting single status...');
                        // Handle single media or text-only: create single status
                        await postSingleStatus();
                    }
                    console.log('Status posted successfully');
                } catch (error) {
                    console.error('Status creation error:', error);
                    alert('Failed to post status: ' + error.message);
                } finally {
                    // Reset button state
                    postStatusBtn.innerHTML = originalText;
                    postStatusBtn.disabled = false;
                    console.log('Button state reset');
                }
            }, true); // Use capture phase to catch event early

            // Also prevent form submission if form is submitted
            if (statusForm) {
                console.log('Attaching form submit handler');
                statusForm.addEventListener('submit', function(e) {
                    console.log('=== FORM SUBMIT EVENT ===');
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Triggering button click from form submit');
                    postStatusBtn.click();
                    return false;
                });
            }

            window.postStatusBtnHandlerAttached = true;
            console.log('Post status button handler attached successfully');
        } else {
            console.log('Post status button handler NOT attached.');
            console.log('Already attached:', window.postStatusBtnHandlerAttached);
            console.log('Button exists:', !!postStatusBtn);
        }

        async function postSingleStatus() {
            const formData = new FormData(statusForm);

            // Determine type based on whether media is uploaded
            let type = 'text';
            const hasMedia = selectedMediaFiles.length > 0;

            if (hasMedia) {
                const firstFile = selectedMediaFiles[0];
                if (firstFile.type.startsWith('image/')) {
                    type = 'image';
                } else if (firstFile.type.startsWith('video/')) {
                    type = 'video';
                }

                // Add the file to formData
                formData.append('media[]', firstFile);
            }

            // Add text content (from main field or single caption)
            if (statusContent.value.trim()) {
                formData.append('content', statusContent.value.trim());
            }

            // Add type to form data
            formData.append('type', type);

            // Add duration (fixed at 24 hours)
            formData.append('duration', '86400');

            const response = await fetch('{{ route('status.store') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                handlePostSuccess();
            } else {
                throw new Error(result.message || 'Failed to post status');
            }
        }

        async function postMultipleStatuses() {
            // Post each media file as a separate status
            const promises = selectedMediaFiles.map(async (file, index) => {
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('media[]', file);

                // Get caption for this specific media item
                const captionField = document.querySelector(
                    `.media-caption[data-media-index="${index}"]`);
                const caption = captionField ? captionField.value.trim() : '';

                if (caption) {
                    formData.append('content', caption);
                }

                // Determine type
                const type = file.type.startsWith('image/') ? 'image' :
                    file.type.startsWith('video/') ? 'video' : 'text';
                formData.append('type', type);
                formData.append('duration', '86400');

                const response = await fetch('{{ route('status.store') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.message || `Failed to post status ${index + 1}`);
                }

                return result;
            });

            await Promise.all(promises);
            handlePostSuccess();
        }

        function handlePostSuccess() {
            // Success
            if (statusModal) statusModal.hide();
            if (statusForm) statusForm.reset();
            selectedMediaFiles = [];
            if (mediaPreview) mediaPreview.classList.add('d-none');
            const previewContainer = document.getElementById('media-preview-container');
            if (previewContainer) previewContainer.innerHTML = '';
            if (previewText) previewText.textContent = 'Your status will appear here';
            if (charCounter) charCounter.textContent = '0/500';
            if (textStylingGroup) textStylingGroup.classList.remove('d-none');
            if (textPreviewGroup) textPreviewGroup.classList.remove('d-none');
            if (textContentGroup) {
                const label = textContentGroup.querySelector('label');
                if (label) label.textContent = "What's on your mind?";
            }

            // Show success notification
            if (window.sidebarApp && window.sidebarApp.showToast) {
                window.sidebarApp.showToast('Status posted successfully!', 'success');
            } else {
                alert('Status posted successfully!');
            }

            // Reload statuses if ChatCore is available
            if (window.chatCore) {
                window.chatCore.loadStatuses();
            } else {
                // Reload page to show new status
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }

        // // Toast notification function
        // function showToast(message, type = 'info') {
        //     const toast = document.createElement('div');
        //     toast.className = `toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3`;
        //     toast.style.zIndex = '1060';
        //     toast.innerHTML = `
        //         <div class="d-flex">
        //             <div class="toast-body">${message}</div>
        //             <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        //         </div>
        //     `;
        //     document.body.appendChild(toast);

        //     const bsToast = new bootstrap.Toast(toast);
        //     bsToast.show();

        //     toast.addEventListener('hidden.bs.toast', () => {
        //         toast.remove();
        //     });
        // }

        // Initialize preview
        if (typeof updatePreview === 'function') {
            updatePreview();
        }

        // Initialize status creation
        // Wait for Bootstrap to be available
        function waitForBootstrapAndInit() {
            if (typeof bootstrap === 'undefined') {
                setTimeout(waitForBootstrapAndInit, 100);
                return;
            }
            initStatusCreation();
        }
        
        // Initialize when Bootstrap is ready
        if (typeof bootstrap !== 'undefined') {
            // Bootstrap already loaded
            initStatusCreation();
        } else {
            // Wait for Bootstrap to load
            waitForBootstrapAndInit();
        }
        
        // Also initialize when DOM is ready (in case modal is added dynamically)
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof bootstrap !== 'undefined') {
                    initStatusCreation();
                } else {
                    waitForBootstrapAndInit();
                }
            });
        }
        
        // Retry initialization after delays to catch late-loading elements
        setTimeout(function() {
            if (typeof bootstrap !== 'undefined') {
                initStatusCreation();
            }
        }, 500);
        setTimeout(function() {
            if (typeof bootstrap !== 'undefined') {
                initStatusCreation();
            }
        }, 1000);
    })();


    // Status Viewer Functionality
    (function() {
        let statusViewerModal = null;
        let currentStatuses = [];
        let currentIndex = 0;
        let progressInterval = null;
        let currentUserData = {};
        let currentUserId = window.currentUserId || {{ auth()->id() ? auth()->id() : 'null' }};

        // Helper functions
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }

        function formatTimeAgo(dateString) {
            if (!dateString) return 'just now';
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            return `${diffDays}d ago`;
        }

        function renderStatusContent(status) {
            if (status.type === 'text') {
                const bgColor = status.background_color || '#000';
                const textColor = status.text_color || '#fff';
                const fontSize = status.font_size ? `${status.font_size}px` : '24px';

                return `
                <div class="text-status d-flex align-items-center justify-content-center" style="
                    background: ${bgColor};
                    color: ${textColor};
                    font-size: ${fontSize};
                    padding: 40px;
                    border-radius: 16px;
                    max-width: 90%;
                    min-height: 60vh;
                    word-wrap: break-word;
                    text-align: center;
                ">
                    ${escapeHtml(status.text || status.content || '')}
                </div>
            `;
            } else if (status.type === 'image') {
                if (status.media_url) {
                    let html = `<div class="d-flex flex-column align-items-center justify-content-center" style="max-width: 100%;">
                        <img src="${escapeHtml(status.media_url)}" class="media-status" style="max-width: 100%; max-height: 70vh; object-fit: contain; border-radius: 8px;">`;
                    // Add caption below image if present
                    if (status.text || status.content) {
                        html += `<div class="status-caption text-white mt-3 p-3" style="background: rgba(0,0,0,0.6); border-radius: 8px; max-width: 90%; text-align: center; word-wrap: break-word;">
                            ${escapeHtml(status.text || status.content)}
                        </div>`;
                    }
                    html += '</div>';
                    return html;
                }
                return '<div class="text-muted">Image not available</div>';
            } else if (status.type === 'video') {
                if (status.media_url) {
                    let html = `<div class="d-flex flex-column align-items-center justify-content-center" style="max-width: 100%;">
                        <video src="${escapeHtml(status.media_url)}" class="media-status" style="max-width: 100%; max-height: 70vh; border-radius: 8px;" controls autoplay muted></video>`;
                    // Add caption below video if present
                    if (status.text || status.content) {
                        html += `<div class="status-caption text-white mt-3 p-3" style="background: rgba(0,0,0,0.6); border-radius: 8px; max-width: 90%; text-align: center; word-wrap: break-word;">
                            ${escapeHtml(status.text || status.content)}
                        </div>`;
                    }
                    html += '</div>';
                    return html;
                }
                return '<div class="text-muted">Video not available</div>';
            }
            return '<div class="text-muted">Unsupported status type</div>';
        }

        function showStatusViewer() {
            console.log('=== showStatusViewer CALLED ===');
            console.log('statusViewerModal:', statusViewerModal);

            if (!statusViewerModal) {
                console.error('Cannot show status viewer: modal not initialized');
                return;
            }

            // Ensure modal is in body (not inside sidebar/chat area)
            const sidebar = document.getElementById('conversation-sidebar');
            const chatArea = document.getElementById('chat-area');
            if ((sidebar && sidebar.contains(statusViewerModal)) || (chatArea && chatArea.contains(
                    statusViewerModal))) {
                console.log('Moving status viewer modal to body');
                document.body.appendChild(statusViewerModal);
            }

            // Set high z-index and show
            statusViewerModal.style.zIndex = '9999';
            statusViewerModal.style.position = 'fixed';
            statusViewerModal.style.top = '0';
            statusViewerModal.style.left = '0';
            statusViewerModal.style.width = '100%';
            statusViewerModal.style.height = '100%';
            statusViewerModal.style.display = 'block';
            statusViewerModal.style.visibility = 'visible';
            statusViewerModal.style.opacity = '1';
            document.body.style.overflow = 'hidden';
            statusViewerModal.offsetHeight; // Force reflow

            console.log('Status viewer displayed');
            console.log('Modal computed styles:', window.getComputedStyle(statusViewerModal));
        }

        window.closeStatusViewer = function() {
            if (!statusViewerModal) return;
            statusViewerModal.style.display = 'none';
            document.body.style.overflow = '';
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
            currentStatuses = [];
            currentIndex = 0;
        };

        window.nextStatus = function() {
            if (currentIndex < currentStatuses.length - 1) {
                currentIndex++;
                renderStatusViewer();
                startProgressBar();
            } else {
                closeStatusViewer();
            }
        };

        window.prevStatus = function() {
            if (currentIndex > 0) {
                currentIndex--;
                renderStatusViewer();
                startProgressBar();
            }
        };

        window.handleStatusViewerClick = function(e) {
            const rect = e.currentTarget.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const width = rect.width;
            if (clickX < width / 2) {
                prevStatus();
            } else {
                nextStatus();
            }
        };

        function startProgressBar() {
            if (progressInterval) {
                clearInterval(progressInterval);
            }
            if (!statusViewerModal) return;
            const progressBar = statusViewerModal.querySelector('.progress-bar.active .progress-fill');
            if (!progressBar) return;
            const duration = 5000;
            progressBar.style.width = '0%';
            progressBar.style.transition = `width ${duration}ms linear`;
            setTimeout(() => {
                progressBar.style.width = '100%';
            }, 10);
            progressInterval = setTimeout(() => {
                nextStatus();
            }, duration);
        }

        // PHASE 1: Stealth viewing state
        let stealthModeEnabled = false;

        async function markStatusAsViewed(statusId) {
            try {
                await fetch(`/status/${statusId}/view`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                            '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        stealth: stealthModeEnabled
                    })
                });
            } catch (error) {
                console.error('Error marking status as viewed:', error);
            }
        }

        // PHASE 1: Toggle stealth viewing mode
        window.toggleStealthMode = function() {
            stealthModeEnabled = !stealthModeEnabled;
            
            // Update button appearance
            const stealthBtn = document.querySelector('.stealth-mode-btn');
            if (stealthBtn) {
                const icon = stealthBtn.querySelector('i');
                if (stealthModeEnabled) {
                    icon.className = 'bi bi-eye-slash-fill';
                    stealthBtn.style.color = '#FFC107'; // Amber color
                    stealthBtn.title = 'Stealth mode: ON (viewing hidden)';
                } else {
                    icon.className = 'bi bi-eye-fill';
                    stealthBtn.style.color = 'white';
                    stealthBtn.title = 'Stealth mode: OFF';
                }
            }
            
            // Show toast notification
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.style.cssText = 'position: fixed; top: 80px; left: 50%; transform: translateX(-50%); background: rgba(0, 0, 0, 0.8); color: white; padding: 12px 24px; border-radius: 8px; z-index: 10000;';
            toast.textContent = stealthModeEnabled 
                ? 'Stealth mode enabled - viewing won\'t be visible'
                : 'Stealth mode disabled';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        }

        function renderStatusViewer() {
            if (!statusViewerModal || currentStatuses.length === 0 || currentIndex >= currentStatuses.length) {
                closeStatusViewer();
                return;
            }
            const status = currentStatuses[currentIndex];
            const isOwnStatus = currentUserData.id == currentUserId;
            const viewCount = status.view_count || 0;

            statusViewerModal.innerHTML = `
            <div class="status-viewer-content">
                <div class="status-viewer-header">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm text-white p-0 border-0" onclick="closeStatusViewer()" style="background: none;">
                            <i class="bi bi-arrow-left" style="font-size: 1.2rem;"></i>
                        </button>
                        <div class="d-flex align-items-center gap-2">
                            ${currentUserData.avatar_url ? 
                                `<img src="${escapeHtml(currentUserData.avatar_url)}" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">` :
                                `<div class="rounded-circle bg-light text-dark d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: bold;">${escapeHtml((currentUserData.name || 'U')[0].toUpperCase())}</div>`
                            }
                            <div>
                                <div class="text-white fw-semibold">${escapeHtml(currentUserData.name || 'User')}</div>
                                <small class="text-white-50">${formatTimeAgo(status.created_at)}</small>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sm text-white p-0 border-0" onclick="closeStatusViewer()" style="background: none;">
                        <i class="bi bi-x-lg" style="font-size: 1.2rem;"></i>
                    </button>
                </div>
                <div class="status-progress" style="position: absolute; top: 0; left: 0; right: 0; display: flex; gap: 4px; padding: 0 12px; height: 3px;">
                    ${currentStatuses.map((s, idx) => `
                        <div class="progress-bar ${idx < currentIndex ? 'viewed' : ''} ${idx === currentIndex ? 'active' : ''}" style="flex: 1; height: 3px; background: rgba(255, 255, 255, 0.3); border-radius: 2px; overflow: hidden;">
                            <div class="progress-fill" style="height: 100%; background: white; width: ${idx < currentIndex ? '100%' : '0%'};"></div>
                        </div>
                    `).join('')}
                </div>
                <div class="status-viewer-body" onclick="handleStatusViewerClick(event)">
                    <div class="status-content">
                        ${renderStatusContent(status)}
                    </div>
                    <div class="status-actions" style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); display: flex; gap: 20px; z-index: 10;">
                        ${!isOwnStatus ? 
                            `<button class="btn btn-sm text-white border-0 stealth-mode-btn" onclick="event.stopPropagation(); toggleStealthMode();" style="background: rgba(0, 0, 0, 0.3); border-radius: 20px; padding: 8px 16px;" title="Stealth mode: OFF">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                            <button class="btn btn-sm text-white border-0" onclick="event.stopPropagation(); showStatusComments(${status.id});" style="background: rgba(0, 0, 0, 0.3); border-radius: 20px; padding: 8px 16px;">
                                <i class="bi bi-reply"></i> Reply
                            </button>` : ''
                        }
                        ${isOwnStatus ? 
                            `<div class="status-view-count" ${viewCount > 0 ? `onclick="event.stopPropagation(); showStatusViewers(${status.id});" title="Click to see who viewed this status"` : 'title="No views yet"'} style="${viewCount === 0 ? 'opacity: 0.6; cursor: default;' : ''} background: rgba(0, 0, 0, 0.3); border-radius: 20px; padding: 8px 16px; cursor: pointer;">
                                <i class="bi bi-eye-fill"></i>
                                <span>${viewCount} ${viewCount === 1 ? 'view' : 'views'}</span>
                            </div>` : ''
                        }
                    </div>
                </div>
                ${currentIndex < currentStatuses.length - 1 ? 
                    '<div class="status-nav-right" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); cursor: pointer; color: white; font-size: 2rem; z-index: 10;" onclick="nextStatus()"><i class="bi bi-chevron-right"></i></div>' : ''
                }
                ${currentIndex > 0 ? 
                    '<div class="status-nav-left" style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); cursor: pointer; color: white; font-size: 2rem; z-index: 10;" onclick="prevStatus()"><i class="bi bi-chevron-left"></i></div>' : ''
                }
            </div>
        `;
            if (!status.viewed) {
                markStatusAsViewed(status.id);
            }
        }

        // Show status viewers modal
        window.showStatusViewers = async function(statusId) {
            try {
                const response = await fetch(`/status/${statusId}/viewers`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch viewers');
                }

                const data = await response.json();
                const viewers = data.viewers || [];

                // Create or update viewers modal
                let viewersModal = document.getElementById('status-viewers-modal');
                if (!viewersModal) {
                    viewersModal = document.createElement('div');
                    viewersModal.id = 'status-viewers-modal';
                    viewersModal.className = 'status-viewers-modal';
                    document.body.appendChild(viewersModal);
                }

                // Build viewers list HTML
                let viewersHtml = `
                    <div class="status-viewers-content">
                        <div class="status-viewers-header">
                            <h5 class="mb-0">Status Views</h5>
                            <button type="button" class="btn-close" onclick="closeStatusViewersModal()"></button>
                        </div>
                        <div class="status-viewers-body">
                            ${viewers.length === 0 ? 
                                '<div class="text-center text-muted py-4">No views yet</div>' :
                                viewers.map(viewer => `
                                    <div class="status-viewer-item">
                                        <div class="d-flex align-items-center gap-3">
                                            ${viewer.user.avatar_url ? 
                                                `<img src="${escapeHtml(viewer.user.avatar_url)}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">` :
                                                `<div class="rounded-circle bg-light text-dark d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: bold;">${escapeHtml((viewer.user.name || 'U')[0].toUpperCase())}</div>`
                                            }
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold">${escapeHtml(viewer.user.name || 'Unknown User')}</div>
                                                <small class="text-muted">${escapeHtml(viewer.time_ago || '')}</small>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')
                            }
                        </div>
                    </div>
                `;

                viewersModal.innerHTML = viewersHtml;
                viewersModal.style.display = 'flex';
                viewersModal.classList.add('show');
                
                // Close on backdrop click
                viewersModal.addEventListener('click', function(e) {
                    if (e.target === viewersModal) {
                        closeStatusViewersModal();
                    }
                });

                // Close on Escape key
                const escapeHandler = function(e) {
                    if (e.key === 'Escape') {
                        closeStatusViewersModal();
                        document.removeEventListener('keydown', escapeHandler);
                    }
                };
                document.addEventListener('keydown', escapeHandler);

            } catch (error) {
                console.error('Error fetching status viewers:', error);
                alert('Failed to load viewers');
            }
        };

        window.closeStatusViewersModal = function() {
            const viewersModal = document.getElementById('status-viewers-modal');
            if (viewersModal) {
                viewersModal.style.display = 'none';
            }
        };

        async function openStatusViewer(userId, initialStatusId = null) {
            console.log('=== openStatusViewer CALLED ===');
            console.log('User ID:', userId, 'Status ID:', initialStatusId);
            console.log('Current statusViewerModal:', statusViewerModal);

            if (!userId) {
                console.error('No user ID provided to openStatusViewer');
                return;
            }

            // If modal not found, try to initialize it
            if (!statusViewerModal) {
                console.log('statusViewerModal not found, trying to find it...');
                statusViewerModal = document.getElementById('status-viewer-modal');
                console.log('Found statusViewerModal:', statusViewerModal);
                if (!statusViewerModal) {
                    console.error('Status viewer modal not found in DOM, cannot open');
                    alert('Status viewer is not ready. Please refresh the page.');
                    return;
                }
                // Ensure modal is in body
                const sidebar = document.getElementById('conversation-sidebar');
                const chatArea = document.getElementById('chat-area');
                if ((sidebar && sidebar.contains(statusViewerModal)) || (chatArea && chatArea.contains(
                        statusViewerModal))) {
                    console.log('Moving status viewer modal to body');
                    document.body.appendChild(statusViewerModal);
                }
                statusViewerModal.style.zIndex = '9999';
                statusViewerModal.style.position = 'fixed';
                console.log('Status viewer modal initialized');
            }
            try {
                const routeUrl = `{{ route('status.user', ':id') }}`.replace(':id', userId);
                console.log('Fetching statuses from:', routeUrl);
                const response = await fetch(routeUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin'
                });
                if (!response.ok) {
                    throw new Error(`Failed to fetch statuses: ${response.status}`);
                }
                const data = await response.json();
                console.log('Status data received:', data);
                currentStatuses = data.updates || [];
                currentUserData = {
                    name: data.user_name,
                    avatar_url: data.user_avatar,
                    id: data.user_id
                };
                console.log('Current statuses:', currentStatuses.length);
                console.log('Current user data:', currentUserData);
                if (currentStatuses.length === 0) {
                    alert('No active statuses found');
                    return;
                }
                if (initialStatusId) {
                    currentIndex = currentStatuses.findIndex(s => s.id == initialStatusId);
                    if (currentIndex === -1) currentIndex = 0;
                } else {
                    currentIndex = 0;
                }
                console.log('Rendering status viewer with', currentStatuses.length, 'statuses');
                renderStatusViewer();
                console.log('Showing status viewer');
                showStatusViewer();
                console.log('Starting progress bar');
                startProgressBar();
            } catch (error) {
                console.error('Error opening status viewer:', error);
                alert('Failed to load status: ' + error.message);
            }
        }

        // Handle status view button clicks - attach after initialization
        function attachStatusClickHandlers() {
            // Only attach once to avoid duplicate listeners
            if (window.statusClickHandlerAttached) {
                console.log('Status click handler already attached, skipping');
                return;
            }

            console.log('Attaching status click handler to document');
            document.addEventListener('click', function(e) {
                // Check if clicked element or parent is status-view-btn
                let statusBtn = e.target.closest('.status-view-btn');

                // If clicking on status item or its children, find the button
                if (!statusBtn) {
                    const statusItem = e.target.closest('.status-item');
                    if (statusItem) {
                        statusBtn = statusItem.querySelector('.status-view-btn');
                    }
                }

                // Also check if clicking on status avatar/image directly
                if (!statusBtn && (e.target.closest('.status-avatar') || e.target.classList.contains(
                        'status-avatar'))) {
                    const statusItem = e.target.closest('.status-item');
                    if (statusItem) {
                        statusBtn = statusItem.querySelector('.status-view-btn');
                    }
                }

                if (!statusBtn) {
                    return; // Not a status click, let event bubble normally
                }

                console.log('=== STATUS VIEW BUTTON CLICKED ===');
                console.log('Event:', e);
                console.log('Status button:', statusBtn);
                console.log('Target element:', e.target);
                console.log('Button dataset:', statusBtn.dataset);

                // Prevent default and stop propagation to avoid conflicts
                e.preventDefault();
                e.stopPropagation();

                const userId = statusBtn.dataset.userId || statusBtn.getAttribute('data-user-id');
                const statusId = statusBtn.dataset.statusId || statusBtn.getAttribute('data-status-id');

                console.log('User ID:', userId, 'Status ID:', statusId);

                if (userId) {
                    console.log('Opening status viewer for user:', userId);
                    openStatusViewer(userId, statusId);
                } else {
                    console.warn('No user ID found on status button');
                }
                return false;
            }, true); // Use capture phase to catch events early

            window.statusClickHandlerAttached = true;
            console.log('Status click handler attached successfully');
        }

        // Initialize status viewer modal reference and attach handlers
        function initStatusViewer() {
            statusViewerModal = document.getElementById('status-viewer-modal');
            if (!statusViewerModal) {
                console.warn('Status viewer modal not found, will retry');
                setTimeout(initStatusViewer, 200);
                return;
            }

            // Ensure status viewer modal is in body and has proper z-index
            const sidebar = document.getElementById('conversation-sidebar');
            const chatArea = document.getElementById('chat-area');
            if ((sidebar && sidebar.contains(statusViewerModal)) || (chatArea && chatArea.contains(
                    statusViewerModal))) {
                document.body.appendChild(statusViewerModal);
                console.log('Moved status-viewer-modal to body');
            }

            // Set high z-index
            statusViewerModal.style.zIndex = '9999';
            statusViewerModal.style.position = 'fixed';

            // Attach click handlers for status items (only once)
            if (!window.statusViewerHandlersAttached) {
                attachStatusClickHandlers();
                window.statusViewerHandlersAttached = true;

                // Close on Escape key
                document.addEventListener('keydown', function(e) {
                    if (!statusViewerModal) return;
                    if (e.key === 'Escape' && statusViewerModal.style.display === 'block') {
                        closeStatusViewer();
                    } else if (e.key === 'ArrowLeft' && statusViewerModal.style.display === 'block') {
                        prevStatus();
                    } else if (e.key === 'ArrowRight' && statusViewerModal.style.display === 'block') {
                        nextStatus();
                    }
                });
            }
        }

        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initStatusViewer);
        } else {
            initStatusViewer();
        }

        // Retry initialization after a delay to catch late-loading elements
        setTimeout(initStatusViewer, 500);
        setTimeout(initStatusViewer, 1000);
    })();

    // Conversation Context Menu
    (function() {
        const contextMenu = document.getElementById('conversation-context-menu');
        if (!contextMenu) return;

        let currentConversationId = null;
        let currentConversationItem = null;
        let isPinned = false;
        let currentLabels = [];

        // Prevent default context menu on conversation items
        document.addEventListener('contextmenu', function(e) {
            const conversationItem = e.target.closest('.conversation-item');
            if (conversationItem) {
                e.preventDefault();
                showContextMenu(e, conversationItem);
            } else {
                hideContextMenu();
            }
        });

        // Hide context menu on click outside
        document.addEventListener('click', function(e) {
            if (!contextMenu.contains(e.target)) {
                hideContextMenu();
            }
        });

        // Hide context menu on scroll
        document.addEventListener('scroll', hideContextMenu, true);

        function showContextMenu(event, item) {
            currentConversationItem = item;
            const convId = item.dataset.conversationId || item.getAttribute('data-conversation-id');
            const groupId = item.dataset.groupId || item.getAttribute('data-group-id');

            // Show menu for both conversations and groups
            if (!convId && !groupId) {
                return;
            }

            // Extract slug from href (format: /c/{slug} or /g/{slug})
            const href = item.getAttribute('href');
            let slug = null;
            let isGroup = false;
            
            if (href) {
                const convMatch = href.match(/\/c\/([^\/]+)/);
                const groupMatch = href.match(/\/g\/([^\/]+)/);
                if (convMatch) {
                    slug = convMatch[1];
                    // For conversations, store the ID (needed for markAsRead API call)
                    // but also store slug for pin/unpin operations
                    currentConversationId = convId; // Use ID for API calls
                    currentConversationItem.dataset.conversationSlug = slug; // Store slug for pin/unpin
                } else if (groupMatch) {
                    slug = groupMatch[1];
                    currentConversationId = slug; // For groups, use slug (route model binding)
                    isGroup = true;
                    currentConversationItem.dataset.isGroup = 'true';
                }
            }
            
            // Fallback to ID if slug not found
            if (!slug) {
                currentConversationId = convId || groupId;
            }

            // Get current state
            const unreadCount = parseInt(item.dataset.unread || item.getAttribute('data-unread') || '0');
            const labelsStr = item.dataset.labels || item.getAttribute('data-labels') || '';
            currentLabels = labelsStr.split(',').filter(Boolean);

            // Check if pinned (you may need to add data-pinned attribute to items)
            isPinned = item.classList.contains('pinned') || false;
            
            // Check if archived
            const isArchived = item.dataset.archived === 'true' || item.hasAttribute('data-archived') || false;

            // Update menu items visibility
            const pinItem = contextMenu.querySelector('[data-action="pin"]');
            const unpinItem = contextMenu.querySelector('[data-action="unpin"]');
            const markReadItem = contextMenu.querySelector('[data-action="mark-read"]');
            const markUnreadItem = contextMenu.querySelector('[data-action="mark-unread"]');
            const archiveItem = contextMenu.querySelector('[data-action="archive"]');
            const unarchiveItem = contextMenu.querySelector('[data-action="unarchive"]');
            const isGroupItem = isGroup || (currentConversationItem && currentConversationItem.dataset.isGroup === 'true');

            // Pin/Unpin only for conversations (not groups/channels)
            if (pinItem && unpinItem) {
                if (isGroupItem) {
                    pinItem.style.display = 'none';
                    unpinItem.style.display = 'none';
                } else {
                    if (isPinned) {
                        pinItem.style.display = 'none';
                        unpinItem.style.display = 'flex';
                    } else {
                        pinItem.style.display = 'flex';
                        unpinItem.style.display = 'none';
                    }
                }
            }

            // Mark read/unread - mark-as-unread only for conversations (not groups)
            if (markReadItem && markUnreadItem) {
                if (unreadCount > 0) {
                    markReadItem.style.display = 'flex';
                    markUnreadItem.style.display = 'none'; // Always hide when there are unread messages
                } else {
                    markReadItem.style.display = 'none';
                    // Only show mark-as-unread for conversations, not groups
                    markUnreadItem.style.display = isGroupItem ? 'none' : 'flex';
                }
            }
            
            // Archive/Unarchive - only for conversations (not groups)
            if (archiveItem && unarchiveItem) {
                if (isGroupItem) {
                    archiveItem.style.display = 'none';
                    unarchiveItem.style.display = 'none';
                } else {
                    if (isArchived) {
                        archiveItem.style.display = 'none';
                        unarchiveItem.style.display = 'flex';
                    } else {
                        archiveItem.style.display = 'flex';
                        unarchiveItem.style.display = 'none';
                    }
                }
            }

            // Position menu
            const x = event.clientX;
            const y = event.clientY;
            contextMenu.style.left = x + 'px';
            contextMenu.style.top = y + 'px';
            contextMenu.style.display = 'block';

            // Adjust if menu goes off screen
            setTimeout(() => {
                const rect = contextMenu.getBoundingClientRect();
                if (rect.right > window.innerWidth) {
                    contextMenu.style.left = (x - rect.width) + 'px';
                }
                if (rect.bottom > window.innerHeight) {
                    contextMenu.style.top = (y - rect.height) + 'px';
                }
            }, 0);
        }

        function hideContextMenu() {
            contextMenu.style.display = 'none';
            const submenu = contextMenu.querySelector('#label-submenu');
            if (submenu) {
                submenu.style.display = 'none';
            }
            currentConversationId = null;
            currentConversationItem = null;
            // Clear group flag
            if (currentConversationItem) {
                delete currentConversationItem.dataset.isGroup;
            }
        }

        // Handle menu item clicks
        contextMenu.addEventListener('click', function(e) {
            const menuItem = e.target.closest('.context-menu-item');
            if (!menuItem) return;

            const action = menuItem.dataset.action;
            if (!action || !currentConversationId) return;

            e.stopPropagation();

            switch (action) {
                case 'pin':
                    // Use slug for pin/unpin (route model binding)
                    const pinSlug = currentConversationItem.dataset.conversationSlug || currentConversationId;
                    pinConversation(pinSlug);
                    break;
                case 'unpin':
                    // Use slug for pin/unpin (route model binding)
                    const unpinSlug = currentConversationItem.dataset.conversationSlug || currentConversationId;
                    pinConversation(unpinSlug);
                    break;
                case 'mark-read':
                    // For conversations use ID, for groups use slug
                    markAsRead(currentConversationId);
                    break;
                case 'mark-unread':
                    // Use slug for mark-as-unread (route model binding)
                    const unreadSlug = currentConversationItem.dataset.conversationSlug || currentConversationId;
                    markAsUnread(unreadSlug);
                    break;
                case 'archive':
                    archiveConversation(currentConversationId);
                    break;
                case 'unarchive':
                    unarchiveConversation(currentConversationId);
                    break;
                case 'add-label':
                    showLabelSubmenu(menuItem);
                    break;
                case 'remove-label':
                    showLabelSubmenu(menuItem, true);
                    break;
            }
        });

        // Handle label submenu clicks
        contextMenu.addEventListener('click', function(e) {
            const submenuItem = e.target.closest('.context-submenu-item');
            if (!submenuItem) return;

            const labelId = submenuItem.dataset.labelId;
            const isRemove = submenuItem.dataset.remove === 'true';

            if (labelId && currentConversationId) {
                if (isRemove) {
                    removeLabelFromConversation(currentConversationId, labelId);
                } else {
                    addLabelToConversation(currentConversationId, labelId);
                }
            }
        });

        function showLabelSubmenu(menuItem, showRemove = false) {
            const submenu = contextMenu.querySelector('#label-submenu');
            if (!submenu) return;

            // Toggle submenu
            if (submenu.style.display === 'block') {
                submenu.style.display = 'none';
                return;
            }

            // Load labels
            fetch('{{ route('labels.index') }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin'
                })
                .then(res => res.json())
                .then(data => {
                    const labels = data.data || data.labels || [];
                    submenu.innerHTML = '';

                    if (labels.length === 0) {
                        submenu.innerHTML =
                            '<div class="context-submenu-item" style="color: var(--text-muted); cursor: default;">No labels. Create one first.</div>';
                    } else {
                        labels.forEach(label => {
                            const isAttached = currentLabels.includes(String(label.id));
                            if ((showRemove && isAttached) || (!showRemove && !isAttached)) {
                                const item = document.createElement('div');
                                item.className = 'context-submenu-item';
                                item.dataset.labelId = label.id;
                                item.dataset.remove = showRemove ? 'true' : 'false';
                                item.innerHTML =
                                    `<i class="bi ${isAttached ? 'bi-tag-fill' : 'bi-tag'}"></i><span>${escapeHtml(label.name)}</span>`;
                                submenu.appendChild(item);
                            }
                        });
                    }

                    // Position submenu
                    const rect = menuItem.getBoundingClientRect();
                    submenu.style.left = '100%';
                    submenu.style.top = '0';
                    submenu.style.marginLeft = '4px';
                    submenu.style.display = 'block';
                })
                .catch(err => {
                    console.error('Error loading labels:', err);
                });
        }

        async function pinConversation(conversationSlug) {
            try {
                const response = await fetch(`/conversation/${conversationSlug}/pin`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                            '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                hideContextMenu();
                
                // Update the conversation item UI to reflect pin status
                const conversationItem = currentConversationItem;
                if (conversationItem) {
                    if (data.pinned) {
                        conversationItem.classList.add('pinned');
                    } else {
                        conversationItem.classList.remove('pinned');
                    }
                }
                
                // Show success message
                if (window.sidebarApp && window.sidebarApp.showToast) {
                    window.sidebarApp.showToast(
                        data.pinned ? 'Conversation pinned' : 'Conversation unpinned',
                        'success'
                    );
                }
                
                // Wait a moment for the toast to show, then reload
                // Use window.location.reload() which is more reliable than setting href
                setTimeout(() => {
                    window.location.reload();
                }, 500);
                
            } catch (error) {
                console.error('Error pinning conversation:', error);
                alert(error.message || 'Failed to pin conversation');
            }
        }

        async function archiveConversation(conversationId) {
            try {
                // Use web route with session auth instead of API route
                const response = await fetch(`/api/conversations/${conversationId}/archive`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                }

                hideContextMenu();
                
                // Update the conversation item UI
                const conversationItem = currentConversationItem;
                if (conversationItem) {
                    conversationItem.dataset.archived = 'true';
                    conversationItem.style.display = 'none'; // Hide from main list
                }
                
                // Show success message
                if (window.sidebarApp && window.sidebarApp.showToast) {
                    window.sidebarApp.showToast('Conversation archived', 'success');
                }
                
                // Reload to refresh the list
                setTimeout(() => {
                    window.location.reload();
                }, 500);
                
            } catch (error) {
                console.error('Error archiving conversation:', error);
                alert(error.message || 'Failed to archive conversation');
            }
        }

        async function unarchiveConversation(conversationId) {
            try {
                // Use web route with session auth instead of API route
                const response = await fetch(`/api/conversations/${conversationId}/archive`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    // Handle authentication errors
                    if (response.status === 401) {
                        throw new Error('Unauthenticated. Please refresh the page and try again.');
                    }
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                }

                hideContextMenu();
                
                // Update the conversation item UI
                const conversationItem = currentConversationItem;
                if (conversationItem) {
                    conversationItem.dataset.archived = 'false';
                    delete conversationItem.dataset.archived;
                }
                
                // Show success message
                if (window.sidebarApp && window.sidebarApp.showToast) {
                    window.sidebarApp.showToast('Conversation unarchived', 'success');
                }
                
                // Reload to refresh the list
                setTimeout(() => {
                    window.location.reload();
                }, 500);
                
            } catch (error) {
                console.error('Error unarchiving conversation:', error);
                alert(error.message || 'Failed to unarchive conversation');
            }
        }

        async function markAsRead(itemId) {
            try {
                const isGroup = currentConversationItem && currentConversationItem.dataset.isGroup === 'true';
                let url, body;
                
                if (isGroup) {
                    // For groups: POST /g/{group}/read
                    url = `/g/${itemId}/read`;
                    body = null;
                } else {
                    // For conversations: POST /c/read with conversation_id in body
                    url = `/c/read`;
                    body = JSON.stringify({
                        conversation_id: itemId
                    });
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                            '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: body
                });

                if (response.ok) {
                    hideContextMenu();
                    // Update UI
                    if (currentConversationItem) {
                        currentConversationItem.classList.remove('unread');
                        const unreadBadge = currentConversationItem.querySelector('.unread-badge');
                        if (unreadBadge) unreadBadge.remove();
                        currentConversationItem.setAttribute('data-unread', '0');
                    }
                    // Update total unread count
                    if (window.updateTotalUnreadCount) {
                        window.updateTotalUnreadCount();
                    }
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                }
            } catch (error) {
                console.error('Error marking as read:', error);
                alert(error.message || 'Failed to mark as read');
            }
        }

        async function markAsUnread(conversationId) {
            try {
                const response = await fetch(`/conversation/${conversationId}/mark-unread`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                            '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                const data = await response.json();
                if (response.ok) {
                    hideContextMenu();
                    // Reload to show updated unread status
                    location.reload();
                } else {
                    alert(data.message || 'Failed to mark as unread');
                }
            } catch (error) {
                console.error('Error marking as unread:', error);
                alert('Failed to mark as unread');
            }
        }

        async function addLabelToConversation(conversationId, labelId) {
            try {
                const response = await fetch(`/labels/${labelId}/attach/${conversationId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                            '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                const data = await response.json();
                if (response.ok) {
                    hideContextMenu();
                    // Update labels attribute
                    if (currentConversationItem) {
                        const currentLabelsStr = currentConversationItem.getAttribute('data-labels') || '';
                        const labels = currentLabelsStr.split(',').filter(Boolean);
                        if (!labels.includes(String(labelId))) {
                            labels.push(String(labelId));
                            currentConversationItem.setAttribute('data-labels', labels.join(','));
                        }
                    }
                } else {
                    alert(data.message || 'Failed to add label');
                }
            } catch (error) {
                console.error('Error adding label:', error);
                alert('Failed to add label');
            }
        }

        async function removeLabelFromConversation(conversationId, labelId) {
            try {
                const response = await fetch(`/labels/${labelId}/detach/${conversationId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                            '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                const data = await response.json();
                if (response.ok) {
                    hideContextMenu();
                    // Update labels attribute
                    if (currentConversationItem) {
                        const currentLabelsStr = currentConversationItem.getAttribute('data-labels') || '';
                        const labels = currentLabelsStr.split(',').filter(Boolean).filter(id => id !== String(
                            labelId));
                        currentConversationItem.setAttribute('data-labels', labels.join(','));
                    }
                } else {
                    alert(data.message || 'Failed to remove label');
                }
            } catch (error) {
                console.error('Error removing label:', error);
                alert('Failed to remove label');
            }
        }

        // Account switcher functions are now defined in the main IIFE above
        // Use global functions from window
        window.removeAccountFromWeb = window.removeAccountFromWeb || function(accountId) {
            if (typeof window.removeAccountFromWeb === 'function') {
                return window.removeAccountFromWeb(accountId);
            }
        };

        // ==== Broadcast Modal Functions ====
        // Make these functions globally available
        window.loadContactsForModal = function() {
            fetch('/api/contacts', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                const container = document.getElementById('recipients-list');
                if (!container) return;
                
                // Handle paginated response (with data.data) or direct array response
                const contactsArray = data.data || (Array.isArray(data) ? data : []);
                
                if (contactsArray.length > 0) {
                    // Filter to only show contacts that are registered on GekyChat
                    const registeredContacts = contactsArray.filter(c => c.is_registered === true && (c.user_id || c.contact_user_id));
                    if (registeredContacts.length > 0) {
                        container.innerHTML = registeredContacts.map(contact => {
                            const userId = contact.user_id || contact.contact_user_id;
                            return `
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="${userId}" id="contact-${contact.id}" name="recipients[]">
                                <label class="form-check-label" for="contact-${contact.id}">
                                    ${escapeHtml(contact.display_name || contact.user_name || contact.phone || 'Unknown')}
                                </label>
                            </div>
                        `;
                        }).join('');
                    } else {
                        container.innerHTML = '<p class="text-muted small mb-0">No contacts registered on GekyChat available</p>';
                    }
                } else {
                    container.innerHTML = '<p class="text-muted small mb-0">No contacts available</p>';
                }
            })
            .catch(error => {
                console.error('Error loading contacts:', error);
                const container = document.getElementById('recipients-list');
                if (container) {
                    container.innerHTML = '<p class="text-danger small">Failed to load contacts</p>';
                }
            });
        };

        window.createBroadcastList = function() {
            const name = document.getElementById('broadcast-name')?.value.trim();
            const description = document.getElementById('broadcast-description')?.value.trim();
            const checkboxes = document.querySelectorAll('#recipients-list input[type="checkbox"]:checked');
            const recipientIds = Array.from(checkboxes).map(cb => parseInt(cb.value)).filter(id => !isNaN(id));

            if (!name) {
                alert('Please enter a name');
                return;
            }

            if (recipientIds.length === 0) {
                alert('Please select at least one recipient');
                return;
            }

            const btn = document.getElementById('save-broadcast-btn');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Creating...';
            }

            fetch('/broadcast-lists', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: name,
                    description: description || null,
                    recipients: recipientIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.data) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('create-broadcast-modal'));
                    if (modal) modal.hide();
                    const form = document.getElementById('create-broadcast-form');
                    if (form) form.reset();
                    // Reload page or refresh broadcast lists if on broadcast page
                    if (typeof loadBroadcastLists === 'function') {
                        loadBroadcastLists();
                    } else {
                        // If not on broadcast page, reload to show new broadcast in sidebar
                        window.location.reload();
                    }
                    if (typeof showToast === 'function') {
                        showToast('Broadcast list created successfully', 'success');
                    } else {
                        alert('Broadcast list created successfully');
                    }
                } else {
                    throw new Error(data.message || 'Failed to create broadcast list');
                }
            })
            .catch(error => {
                console.error('Error creating broadcast list:', error);
                alert('Failed to create broadcast list: ' + error.message);
            })
            .finally(() => {
                const btn = document.getElementById('save-broadcast-btn');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Create';
                }
            });
        };

        // Setup broadcast modal handlers when DOM is ready
        function setupBroadcastModal() {
            const saveBtn = document.getElementById('save-broadcast-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    if (typeof window.createBroadcastList === 'function') {
                        window.createBroadcastList();
                    }
                });
            }

            // Load contacts when modal is shown
            const broadcastModal = document.getElementById('create-broadcast-modal');
            if (broadcastModal) {
                broadcastModal.addEventListener('show.bs.modal', function() {
                    if (typeof window.loadContactsForModal === 'function') {
                        window.loadContactsForModal();
                    }
                });
            }
        }

        // Initialize broadcast modal setup
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupBroadcastModal);
        } else {
            setupBroadcastModal();
        }
    })();
</script>
