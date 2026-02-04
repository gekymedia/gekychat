@extends('layouts.app')

@section('content')
    @if (isset($group) && $group)
       {{-- NEW: Status Header --}}
        <div class="status-header-container border-bottom bg-card" id="status-header">
            {{-- Status header will be dynamically inserted by ChatCore --}}
        </div>

        {{-- Group Header --}}
        @php
            $userRole = $group->members->firstWhere('id', auth()->id())?->pivot?->role ?? 'member';
            $isOwner = $group->owner_id === auth()->id();
            $isAdmin = $isOwner || $userRole === 'admin' || auth()->user()->is_admin;
            
            $groupData = [
                'name' => $group->name ?? ($group->type === 'channel' ? 'Channel' : 'Group Chat'),
                'initial' => strtoupper(substr($group->name ?? ($group->type === 'channel' ? 'C' : 'G'), 0, 1)),
                'avatar' => $group->avatar_path ? \App\Helpers\UrlHelper::secureStorageUrl($group->avatar_path) : \App\Helpers\UrlHelper::secureAsset('images/group-default.png'),
                'description' => $group->description ?? null,
                'isPrivate' => $group->is_private ?? false,
                'memberCount' => $group->members->count() ?? 0,
                'previewMembers' => $group->members->take(3) ?? collect(),
                'isOwner' => $isOwner,
                'userRole' => $userRole,
                'isAdmin' => $isAdmin,
            ];
        @endphp

        @include('groups.partials.header', ['groupData' => $groupData, 'group' => $group])

        {{-- Messages Container --}}
        <main class="messages-container">
            <div id="messages-container">
                @foreach ($group->messages as $message)
                    @include('chat.shared.message', [
                        'message' => $message,
                        'isGroup' => true,
                        'group' => $group,
                        'isOwner' => $group->owner_id === auth()->id(),
                        'userRole' => $group->members->firstWhere('id', auth()->id())?->pivot?->role,
                        'showSenderNames' => true,
                    ])
                @endforeach
            </div>
        </main>
        @include('chat.shared.reply_preview', ['context' => 'group'])

        {{-- Message Composer -- Only show if user can send messages --}}
        @if ($canSendMessages ?? true)
            @include('chat.shared.message_composer', [
                'action' => route('groups.messages.store', $group),
                'conversationId' => $group->id,
                'placeholder' => "Message {$group->name}...",
                'context' => 'group',
                'group' => $group,
            ])
        @else
            {{-- Read-only message for non-admin channel members --}}
            <div class="message-input-container border-top bg-card position-sticky bottom-0 p-3 text-center">
                <div class="alert alert-info mb-0 d-flex align-items-center justify-content-center gap-2" role="alert">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span>Only admins and owners can send messages in this {{ isset($group) && $group->type === 'channel' ? 'channel' : 'group' }}.</span>
                </div>
            </div>
        @endif
    @else
        {{-- Empty Group State --}}
        <div class="d-flex flex-column align-items-center justify-content-center h-100 empty-chat-state" role="main">
            <div class="text-center p-4 max-w-400">
                <div
                    class="avatar bg-card mb-4 mx-auto rounded-circle d-flex align-items-center justify-content-center empty-chat-icon">
                    <i class="bi bi-people-fill" aria-hidden="true"></i>
                </div>
                <h1 class="h4 empty-chat-title mb-3">{{ isset($group) && $group->type === 'channel' ? 'Channel' : 'Group Chat' }}</h1>
                <p class="muted mb-4 empty-chat-subtitle">
                    Select a {{ isset($group) && $group->type === 'channel' ? 'channel' : 'group' }} from the sidebar or create a new one to start messaging
                </p>
            </div>
        </div>
    @endif

    {{-- Group Specific Modals --}}
    @include('groups.partials.group_management_modal')

    {{-- Shared Modals --}}
    @include('chat.shared.forward_modal', ['context' => 'group'])
    @include('chat.shared.image_modal')

    {{-- Forward Data --}}
    @if (isset($group) && $group)
        <script type="application/json" id="forward-datasets">
@php
  $conversationsData = [];
  if (isset($conversations)) {
      foreach ($conversations as $conversation) {
          if ($conversation && $conversation->members) {
              $otherUser = $conversation->members->where('id', '!=', auth()->id())->first();
              $conversationsData[] = [
                  'id' => $conversation->id ?? 0,
                  'name' => $otherUser->name ?? 'Unknown',
                  'phone' => $otherUser->phone ?? '',
                  'avatar' => $otherUser->avatar_url ?? '',
                  'type' => 'conversation',
                  'subtitle' => 'Direct chat'
              ];
          }
      }
  }

  $groupsData = [];
  if (isset($group) && isset($groups)) {
      foreach ($groups as $groupItem) {
          if ($groupItem && $groupItem->id !== $group->id) {
              $groupsData[] = [
                  'id' => $groupItem->id ?? 0,
                  'title' => $groupItem->name ?? 'Group',
                  'name' => $groupItem->name ?? 'Group',
                  'avatar' => ($groupItem->avatar_path ?? null) ? \App\Helpers\UrlHelper::secureStorageUrl($groupItem->avatar_path) : \App\Helpers\UrlHelper::secureAsset('images/group-default.png'),
                  'type' => 'group',
                  'subtitle' => ($groupItem->members->count() ?? 0) . ' members'
              ];
          }
      }
  }
@endphp
{
  "conversations": @json($conversationsData),
  "groups": @json($groupsData)
}
</script>

    @if(isset($group) && $group)
   @push('scripts')
    <script>
        window.__chatCoreConfig = {
            conversationId: null,
            groupId: @json($group->id),
            userId: @json(auth()->id()),
            typingUrl: @json(route('groups.typing', $group)),
            messageUrl: @json(route('groups.messages.store', $group)),
            // NEW: Add quick replies and status URLs
            // Use API endpoint for quick replies instead of HTML route
            quickRepliesUrl: @json(route('api.quick-replies')),
            statusUrl: @json(route('status.index')),
            messageContainer: '#messages-container',
            messageInput: '#message-input',
            messageForm: '#message-form',
            typingIndicator: '.typing-indicator',
            debug: @json(config('app.debug')),
        };

        // ✅ INITIALIZE OFFLINECHATCORE FOR GROUP (offline-first)
        document.addEventListener('DOMContentLoaded', function() {
            // Use OfflineChatCore if available, fallback to ChatCore
            const ChatCoreClass = window.OfflineChatCore || window.ChatCore;
            
            if (ChatCoreClass && window.__chatCoreConfig.groupId) {
                // Enable offline functionality
                window.__chatCoreConfig.enableOffline = true;
                window.__chatCoreConfig.loadFromCache = true;
                window.__chatCoreConfig.autoSync = true;
                
                window.chatInstance = new ChatCoreClass(window.__chatCoreConfig);
                
                // Optional: Add custom event handlers
                window.chatInstance
                    .onMessage(function(message) {
                        console.log('💌 New group message via ChatCore:', message);
                    })
                    .onTyping(function(typingData) {
                        console.log('⌨️ Group typing event:', typingData);
                    })
                    .onPresence(function(type, data) {
                        console.log('👥 Group presence:', type, data);
                    })
                    .onQuickRepliesLoaded(function(data) {
                        console.log('💬 Quick replies loaded for group:', data);
                    })
                    .onStatusesLoaded(function(data) {
                        console.log('📱 Statuses loaded for group:', data);
                    })
                    .onError(function(error) {
                        console.error('🔴 ChatCore error:', error);
                    });
                    
                console.log('🎯 ChatCore initialized for group:', window.__chatCoreConfig.groupId);
            }

            // Initialize CallManager for group calls
            if (window.CallManager) {
                window.callManager = new window.CallManager();
                console.log('📞 CallManager initialized for group');
            }
        });
    </script>
@endpush
@endif

    @endif
@endsection