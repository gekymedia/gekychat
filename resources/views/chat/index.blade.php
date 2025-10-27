{{-- resources/views/chat/index.blade.php --}}
@extends('layouts.app')

@section('content')
    @if (isset($conversation) && $conversation)
        {{-- Chat Header --}}
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
                        'avatar' => $otherUser->avatar_url ?? null, // ✅ FIXED: Use avatar_url
                        'online' => $otherUser->is_online ?? false, // ✅ FIXED: Use is_online
                        'lastSeen' => $otherUser->last_seen_at ?? null, // ✅ FIXED: Use last_seen_at
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
        
        {{-- ONLY ONE MESSAGE COMPOSER --}}
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
    @else
        {{-- Empty Chat State --}}
        <div class="d-flex flex-column align-items-center justify-content-center h-100 empty-chat-state"
            role="main">
            <div class="text-center p-4 max-w-400">
                <div
                    class="avatar bg-card mb-4 mx-auto rounded-circle d-flex align-items-center justify-content-center empty-chat-icon">
                    <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                </div>
                <h1 class="h4 empty-chat-title mb-3">Welcome to GekyChat</h1>
                <p class="muted mb-4 empty-chat-subtitle">
                    Select a conversation from the sidebar or start a new one to begin messaging
                </p>
            </div>
        </div>
    @endif

    {{-- Shared Modals --}}
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
                'avatar' => $otherUser->avatar_url ?? '', // ✅ FIXED: Use avatar_url
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
                'avatar' => $group->avatar_url ?? '', // ✅ FIXED: Use avatar_url
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

    @if (isset($conversation) && $conversation)
        @push('scripts')
            <script>
                window.__chatCoreConfig = {
                    conversationId: @json($conversation->id),
                    groupId: null,
                    userId: @json(auth()->id()),

                    // routes
                    typingUrl: @json(route('chat.typing')), // POST /c/typing
                    messageUrl: @json(route('chat.send')), // POST /c/send
                    reactionUrl: @json(route('messages.react')), // POST /messages/react

                    // selectors already used by your layout/partials
                    messageContainer: '.messages-container',
                    messageInput: '#message-input',
                    messageForm: '#message-form',
                    typingIndicator: '.typing-indicator',

                    // If your typing STOP endpoint doesn't support DELETE:
                    // typingStopMethod: 'POST',

                    // debug: true,
                };
            </script>
        @endpush
    @endif
@endsection