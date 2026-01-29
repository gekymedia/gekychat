@extends('layouts.app')

@section('content')
    {{-- Check if we're viewing a specific conversation or the general chat --}}
    @if (isset($conversation) && $conversation)
        {{-- NEW: Status Header --}}
        <div class="status-header-container border-bottom bg-card" id="status-header">
            {{-- Status header will be dynamically inserted by ChatCore --}}
        </div>
        {{-- Specific conversation view --}}
        {{-- Header data is passed from controller --}}
        @if(!isset($headerData))
            @php
                $headerData = []; // Fallback if not set by controller
            @endphp
        @endif

        @include('chat.partials.header', ['headerData' => $headerData])

        {{-- Messages Container --}}
        <main class="messages-container">
            <div id="messages-loader" class="text-center p-3" style="display: none;">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading older messages...</span>
                </div>
                <span class="ms-2 text-muted small">Loading older messages...</span>
            </div>
            <div id="messages-container">
                @php
                    $previousDate = null;
                @endphp
                @foreach ($conversation->messages as $message)
                    @php
                        $isOwnMessage = $message->sender_id === auth()->id();
                        $canEdit = $isOwnMessage;
                        $canDelete = $isOwnMessage;
                        $currentDate = $message->created_at->startOfDay();
                        
                        // Check if we need to show a date divider
                        $showDateDivider = $previousDate === null || !$currentDate->isSameDay($previousDate);
                    @endphp

                    @if($showDateDivider)
                        <div class="date-divider text-center my-3" data-date="{{ $message->created_at->format('Y-m-d') }}">
                            <span class="date-divider-text bg-bg px-3 py-1 rounded-pill text-muted small fw-semibold">
                                {{ \App\Helpers\DateHelper::formatChatDate($message->created_at) }}
                            </span>
                        </div>
                    @endif

                    @include('chat.shared.message', [
                        'message' => $message,
                        'isGroup' => false,
                        'conversation' => $conversation,
                        'showSenderNames' => false,
                        'canEdit' => $canEdit,
                        'canDelete' => $canDelete,
                    ])
                    
                    @php
                        $previousDate = $currentDate;
                    @endphp
                @endforeach
            </div>
        </main>

        @include('chat.shared.reply_preview', ['context' => 'direct'])

        {{-- Message Composer --}}
        @include('chat.shared.message_composer', [
            'action' => route('chat.send'),
            'conversationId' => $conversation->id,
            'placeholder' => 'Type a message...',
            'context' => 'direct',
            'securitySettings' => [
                'isEncrypted' => old('is_encrypted', '0') === '1',
                'expiresIn' => old('expires_in', ''),
            ],
        ])

        {{-- Scripts for specific conversation - UPDATED FOR CHATCORE --}}
        @push('scripts')
            <script>
                window.__chatCoreConfig = {
                    conversationId: @json($conversation->id),
                    groupId: null,
                    userId: @json(auth()->id()),
                    typingUrl: @json(route('chat.typing')),
                    messageUrl: @json(route('chat.send')),
                    reactionUrl: @json(route('messages.react')),
                    // NEW: Add quick replies and status URLs
                    // Use the API endpoint for quick replies instead of the HTML route
                    quickRepliesUrl: @json(route('api.quick-replies')),
                    statusUrl: @json(route('status.index')),
                    messageContainer: '#messages-container',
                    messageInput: '#message-input',
                    messageForm: '#message-form',
                    typingIndicator: '.typing-indicator',
                    debug: @json(config('app.debug')),
                };

                // Initialize OfflineChatCore for this conversation (offline-first)
                document.addEventListener('DOMContentLoaded', function() {
                    // Use OfflineChatCore if available, fallback to ChatCore
                    const ChatCoreClass = window.OfflineChatCore || window.ChatCore;
                    
                    if (ChatCoreClass && window.__chatCoreConfig.conversationId) {
                        // Enable offline functionality
                        window.__chatCoreConfig.enableOffline = true;
                        window.__chatCoreConfig.loadFromCache = true;
                        window.__chatCoreConfig.autoSync = true;
                        
                        window.chatInstance = new ChatCoreClass(window.__chatCoreConfig);

                        // Optional: Add custom event handlers for new features
                        window.chatInstance
                            .onMessage(function(message) {
                                console.log('💌 New message via ChatCore:', message);
                            })
                            .onTyping(function(typingData) {
                                console.log('⌨️ Typing event:', typingData);
                            })
                            .onQuickRepliesLoaded(function(data) {
                                console.log('💬 Quick replies loaded:', data);
                            })
                            .onStatusesLoaded(function(data) {
                                console.log('📱 Statuses loaded:', data);
                            })
                            .onError(function(error) {
                                console.error('🔴 ChatCore error:', error);
                            });

                        console.log('🎯 ChatCore initialized for conversation:', window.__chatCoreConfig.conversationId);
                        
                        // Initialize OfflineUI if available
                        if (window.OfflineUI) {
                            window.offlineUI = new window.OfflineUI('.chat-header');
                            console.log('📴 OfflineUI initialized');
                        }
                        
                        // Listen to sync events
                        document.addEventListener('forceSync', async () => {
                            if (window.offlineUI && window.chatInstance?.forceSync) {
                                try {
                                    window.offlineUI.showSyncProgress();
                                    await window.chatInstance.forceSync();
                                    window.offlineUI.hideSyncProgress();
                                    window.offlineUI.showToast('Messages synced successfully', 'success');
                                } catch (error) {
                                    window.offlineUI.hideSyncProgress();
                                    window.offlineUI.showToast('Sync failed. Please try again.', 'error');
                                }
                            }
                        });
                        
                        // Ensure scroll to bottom after initialization
                        setTimeout(() => {
                            if (window.chatInstance && window.chatInstance.config.autoScroll) {
                                window.chatInstance.scrollToBottom();
                            }
                        }, 300);
                    }
                    
                    // Initialize CallManager
                    if (window.CallManager) {
                        window.callManager = new window.CallManager();
                        console.log('📞 CallManager initialized');
                    }
                    
                    // Lazy loading: Load older messages when scrolling to top
                    const messagesContainer = document.querySelector('.messages-container');
                    const messagesLoader = document.getElementById('messages-loader');
                    let isLoadingOlder = false;
                    let hasMoreMessages = @json($hasMoreMessages ?? false);
                    let oldestMessageId = @json($conversation->messages->first()?->id ?? 0);
                    
                    if (messagesContainer && messagesLoader) {
                        messagesContainer.addEventListener('scroll', function() {
                            // Load more when scrolled near the top (within 200px)
                            if (!isLoadingOlder && hasMoreMessages && messagesContainer.scrollTop < 200) {
                                isLoadingOlder = true;
                                messagesLoader.style.display = 'block';
                                
                                fetch(`{{ route('chat.history', $conversation->slug) }}?before_id=${oldestMessageId}`, {
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                    },
                                    credentials: 'same-origin'
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.data && data.data.length > 0) {
                                        const messagesContainerEl = document.getElementById('messages-container');
                                        const scrollHeightBefore = messagesContainer.scrollHeight;
                                        
                                        // Helper function for date formatting
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
                                                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                                                return date.toLocaleDateString('en-US', options);
                                            }
                                        }
                                        
                                        // Prepend older messages to the container
                                        let previousMessageDate = null;
                                        data.data.forEach((message, index) => {
                                            const messageDate = message.created_at ? new Date(message.created_at).toISOString().split('T')[0] : null;
                                            
                                            // Check if we need a date divider
                                            if (index === 0 || (previousMessageDate && messageDate !== previousMessageDate)) {
                                                const dateDivider = document.createElement('div');
                                                dateDivider.className = 'date-divider text-center my-3';
                                                dateDivider.setAttribute('data-date', messageDate || '');
                                                const formattedDate = formatChatDate(message.created_at || new Date().toISOString());
                                                dateDivider.innerHTML = `
                                                    <span class="date-divider-text bg-bg px-3 py-1 rounded-pill text-muted small fw-semibold">
                                                        ${formattedDate}
                                                    </span>
                                                `;
                                                messagesContainerEl.insertBefore(dateDivider, messagesContainerEl.firstChild);
                                            }
                                            
                                            // Render message HTML (simplified - you may need to use your message template)
                                            const messageEl = document.createElement('div');
                                            messageEl.setAttribute('data-message-id', message.id);
                                            messageEl.setAttribute('data-message-date', message.created_at || new Date().toISOString());
                                            messageEl.innerHTML = `<!-- Message will be rendered by your message template -->`;
                                            messagesContainerEl.insertBefore(messageEl, messagesContainerEl.firstChild);
                                            
                                            previousMessageDate = messageDate;
                                        });
                                        
                                        // Update oldest message ID and hasMore flag
                                        oldestMessageId = data.oldest_message_id;
                                        hasMoreMessages = data.has_more;
                                        
                                        // Maintain scroll position after prepending
                                        const scrollHeightAfter = messagesContainer.scrollHeight;
                                        messagesContainer.scrollTop = scrollHeightAfter - scrollHeightBefore + messagesContainer.scrollTop;
                                    } else {
                                        hasMoreMessages = false;
                                    }
                                    
                                    messagesLoader.style.display = 'none';
                                    isLoadingOlder = false;
                                })
                                .catch(error => {
                                    console.error('Error loading older messages:', error);
                                    messagesLoader.style.display = 'none';
                                    isLoadingOlder = false;
                                });
                            }
                        });
                    }
                });
            </script>
        @endpush
    @else
        {{-- General chat index (no specific conversation selected) --}}
        <div class="d-flex flex-column align-items-center justify-content-center h-100 empty-chat-state" role="main">
            <div class="text-center p-4 max-w-400">
                <img src="/icons/gekychat-logo-gold-32.png" alt="GekyChat Logo" class="empty-chat-logo" />
                <h1 class="h4 empty-chat-title mb-3">Welcome to GekyChat</h1>
                <p class="muted mb-4 empty-chat-subtitle">
                    Select a conversation from the sidebar or start a new one to begin messaging
                </p>
            </div>
        </div>
    @endif

    {{-- Shared Modals (available in both views) --}}
    @include('chat.shared.forward_modal', ['context' => 'direct'])
    @include('chat.shared.image_modal')

    {{-- Forward Data --}}
    @php
        $conversationsData = [];
        foreach ($conversations ?? [] as $conversationItem) {
            $otherUser = $conversationItem->members->where('id', '!=', auth()->id())->first();
            $conversationsData[] = [
                'id' => $conversationItem->id,
                'name' => $otherUser->name ?? 'Unknown',
                'phone' => $otherUser->phone ?? '',
                'avatar' => $otherUser->avatar_url ?? '',
                'type' => 'conversation',
                'subtitle' => 'Direct chat',
            ];
        }

        $groupsData = [];
        foreach ($groups ?? [] as $group) {
            $groupsData[] = [
                'id' => $group->id,
                'title' => $group->name,
                'name' => $group->name,
                'avatar' => $group->avatar_url ?? '',
                'type' => 'group',
                'subtitle' => $group->members->count() . ' members',
            ];
        }
    @endphp

    <script type="application/json" id="forward-datasets">
    {
        "conversations": @json($conversationsData),
        "groups": @json($groupsData)
    }
    </script>
@endsection
