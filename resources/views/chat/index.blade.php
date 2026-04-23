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
            <div id="messages-container"
                 data-messages-panel-url="{{ $messagesPanelUrl ?? '' }}">
                @if(!empty($deferMessagesLoad) && $deferMessagesLoad)
                    <div class="text-center p-4 messages-initial-loader">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="ms-2 text-muted small">Loading messages…</span>
                    </div>
                @else
                    @include('chat.partials.messages_list', [
                        'conversation' => $conversation,
                        'messages' => $conversation->messages,
                    ])
                @endif
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
                @if(!empty($autoStartCallSessionId ?? null))
                window.__autoStartCall = { sessionId: @json($autoStartCallSessionId), type: @json($autoStartCallType ?? 'video') };
                @endif
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

                document.addEventListener('DOMContentLoaded', function() {
                    const messagesPanelEl = document.getElementById('messages-container');
                    const panelUrl = messagesPanelEl && messagesPanelEl.dataset.messagesPanelUrl
                        ? messagesPanelEl.dataset.messagesPanelUrl.trim()
                        : '';

                    function initChatShell() {
                        const ChatCoreClass = window.OfflineChatCore || window.ChatCore;

                        if (ChatCoreClass && window.__chatCoreConfig.conversationId) {
                            window.__chatCoreConfig.enableOffline = true;
                            window.__chatCoreConfig.loadFromCache = true;
                            window.__chatCoreConfig.autoSync = true;

                            window.chatInstance = new ChatCoreClass(window.__chatCoreConfig);

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

                            if (window.OfflineUI) {
                                window.offlineUI = new window.OfflineUI('.chat-header');
                                console.log('📴 OfflineUI initialized');
                            }

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

                            setTimeout(() => {
                                if (window.chatInstance && window.chatInstance.config.autoScroll) {
                                    window.chatInstance.scrollToBottom();
                                }
                            }, 300);
                        }

                        if (window.CallManager) {
                            window.callManager = new window.CallManager();
                            console.log('📞 CallManager initialized');
                        }

                        const messagesContainer = document.querySelector('.messages-container');
                        const messagesLoader = document.getElementById('messages-loader');
                        let isLoadingOlder = false;
                        let hasMoreMessages = typeof window.__messagesInitialHasMore === 'boolean'
                            ? window.__messagesInitialHasMore
                            : @json($hasMoreMessages ?? false);
                        let oldestMessageId = typeof window.__messagesInitialOldest !== 'undefined'
                            ? window.__messagesInitialOldest
                            : @json($conversation->messages->first()?->id ?? 0);
                        const panelBase = panelUrl || @json($messagesPanelUrl ?? '');

                        if (messagesContainer && messagesLoader && panelBase) {
                            messagesContainer.addEventListener('scroll', function() {
                                if (!isLoadingOlder && hasMoreMessages && messagesContainer.scrollTop < 200) {
                                    isLoadingOlder = true;
                                    messagesLoader.style.display = 'block';

                                    fetch(panelBase + '?before_id=' + encodeURIComponent(oldestMessageId), {
                                        headers: {
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                        },
                                        credentials: 'same-origin'
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        const messagesContainerEl = document.getElementById('messages-container');
                                        if (messagesContainerEl && data.html && data.html.trim()) {
                                            const scrollHeightBefore = messagesContainer.scrollHeight;
                                            messagesContainerEl.insertAdjacentHTML('afterbegin', data.html);
                                            const scrollHeightAfter = messagesContainer.scrollHeight;
                                            messagesContainer.scrollTop = scrollHeightAfter - scrollHeightBefore + messagesContainer.scrollTop;
                                        }
                                        oldestMessageId = data.oldest_message_id;
                                        hasMoreMessages = data.has_more;
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
                    }

                    if (panelUrl) {
                        fetch(panelUrl, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            credentials: 'same-origin'
                        })
                        .then(r => {
                            if (!r.ok) throw new Error('messages-panel');
                            return r.json();
                        })
                        .then(data => {
                            if (messagesPanelEl) {
                                messagesPanelEl.innerHTML = data.html || '';
                            }
                            window.__messagesInitialHasMore = !!data.has_more;
                            window.__messagesInitialOldest = data.oldest_message_id || 0;
                            initChatShell();
                        })
                        .catch(() => {
                            if (messagesPanelEl) {
                                messagesPanelEl.innerHTML = '<div class="text-center p-4 text-danger small">Could not load messages. <a href="' + location.href + '">Reload</a></div>';
                            }
                            window.__messagesInitialHasMore = false;
                            window.__messagesInitialOldest = 0;
                            initChatShell();
                        });
                    } else {
                        window.__messagesInitialHasMore = @json($hasMoreMessages ?? false);
                        window.__messagesInitialOldest = @json($conversation->messages->first()?->id ?? 0);
                        initChatShell();
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
        
        // Add Saved Messages first if it exists
        if (isset($savedMessagesConversation) && $savedMessagesConversation) {
            $conversationsData[] = [
                'id' => $savedMessagesConversation->id,
                'name' => 'Saved Messages',
                'phone' => '',
                'avatar' => '',
                'type' => 'conversation',
                'subtitle' => 'Save messages here',
                'is_saved_messages' => true,
            ];
        }
        
        foreach ($conversations ?? [] as $conversationItem) {
            // Skip saved messages in the loop since we added it above
            if ($conversationItem->is_saved_messages) {
                continue;
            }
            
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
            $memberCount = $group->members->count();
            $groupsData[] = [
                'id' => $group->id,
                'title' => $group->name,
                'name' => $group->name,
                'avatar' => $group->avatar_url ?? '',
                'type' => 'group',
                'subtitle' => $memberCount . ' ' . ($memberCount === 1 ? 'follower' : 'followers'),
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
