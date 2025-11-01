@extends('layouts.app')

@section('content')
    {{-- Check if we're viewing a specific conversation or the general chat --}}
    @if (isset($conversation) && $conversation)
      {{-- NEW: Status Header --}}
        <div class="status-header-container border-bottom bg-card" id="status-header">
            {{-- Status header will be dynamically inserted by ChatCore --}}
        </div>
        {{-- Specific conversation view --}}
        @php
            $headerData = []; // Initialize empty array
            
            if (!$conversation->is_group) {
                $me = auth()->id();
                $otherUser = $conversation->members->firstWhere('id', '!=', $me);

                if ($otherUser) {
                    $headerData = [
                        'name' => $otherUser->name ?? ($otherUser->phone ?? 'Unknown User'),
                        'initial' => strtoupper(
                            substr($otherUser->name ?? ($otherUser->phone ?? 'U'), 0, 1)
                        ),
                        'avatar' => $otherUser->avatar_url ?? null,
                        'online' => $otherUser->is_online ?? false,
                        'lastSeen' => $otherUser->last_seen_at ?? null,
                        'userId' => $otherUser->id,
                        'phone' => $otherUser->phone ?? null,
                        'created_at' => $otherUser->created_at ?? null,
                    ];
                }
            }
        @endphp

        @include('chat.partials.header', ['headerData' => $headerData])

        {{-- Messages Container --}}
        <main class="messages-container">
            <div id="messages-container">
                @foreach ($conversation->messages as $message)
                    @php
                        $isOwnMessage = $message->sender_id === auth()->id();
                        $canEdit = $isOwnMessage;
                        $canDelete = $isOwnMessage;
                    @endphp

                    @include('chat.shared.message', [
                        'message' => $message,
                        'isGroup' => false,
                        'conversation' => $conversation,
                        'showSenderNames' => false,
                        'canEdit' => $canEdit,
                        'canDelete' => $canDelete,
                    ])
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
            quickRepliesUrl: @json(route('quick-replies.index')),
            statusUrl: @json(route('status.index')),
            messageContainer: '#messages-container',
            messageInput: '#message-input',
            messageForm: '#message-form',
            typingIndicator: '.typing-indicator',
            debug: @json(config('app.debug')),
        };

        // Initialize ChatCore for this conversation
        document.addEventListener('DOMContentLoaded', function() {
            if (window.ChatCore && window.__chatCoreConfig.conversationId) {
                window.chatInstance = new ChatCore(window.__chatCoreConfig);
                
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
            }
        });
    </script>
@endpush

    @else
        {{-- General chat index (no specific conversation selected) --}}
        <div class="d-flex flex-column align-items-center justify-content-center h-100 empty-chat-state" role="main">
            <div class="text-center p-4 max-w-400">
                <div class="avatar bg-card mb-4 mx-auto rounded-circle d-flex align-items-center justify-content-center empty-chat-icon">
                    <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                </div>
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