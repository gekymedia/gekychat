{{-- resources/views/chat/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid chat-container">
  <div class="row h-100 g-0">
    {{-- Shared Sidebar --}}
    @include('partials.chat_sidebar')
    
    <div class="col-md-8 col-lg-9 d-flex flex-column" id="chat-area">
      @if(isset($conversation) && $conversation)
        {{-- Chat Header --}}
        @php
          // Header data for 1-1 chat - SINGLE DEFINITION
          $headerData = [
            'name' => 'Conversation',
            'initial' => 'C',
            'avatar' => null,
            'online' => false,
            'lastSeen' => null,
            'userId' => null
          ];

          if (!$conversation->is_group) {
            $me = auth()->id();
            $otherUser = $conversation->members->firstWhere('id', '!=', $me);
            
            if ($otherUser) {
              $headerData = [
                'name' => $otherUser->name ?? $otherUser->phone ?? 'Unknown User',
                'initial' => strtoupper(substr($otherUser->name ?? $otherUser->phone ?? 'U', 0, 1)),
                'avatar' => $otherUser->avatar_path ? Storage::url($otherUser->avatar_path) : null,
                'online' => $otherUser->last_seen && $otherUser->last_seen->gt(now()->subMinutes(5)),
                'lastSeen' => $otherUser->last_seen,
                'userId' => $otherUser->id
              ];
            }
          }
        @endphp

        @include('chat.partials.header', ['headerData' => $headerData])
        
        {{-- Messages Container --}}
        <main class="messages-container">
          <div id="messages-container">
            @foreach($conversation->messages as $message)
              @php
                $isOwnMessage = $message->sender_id === auth()->id();
                $canEdit = $isOwnMessage;
                $canDelete = $isOwnMessage;
              @endphp
              
              @include('chat.shared.message', [
                'message' => $message,
                'isGroup' => false,
                'showSenderNames' => false,
                'canEdit' => $canEdit,
                'canDelete' => $canDelete
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
            'expiresIn' => old('expires_in', '')
          ]
        ])
        
      @else
        {{-- Empty Chat State --}}
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
    </div>
  </div>
</div>

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
          'avatar' => $otherUser->avatar_url ?? '',
          'type' => 'conversation',
          'subtitle' => 'Direct chat'
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
          'subtitle' => $group->members->count() . ' members'
      ];
  }
@endphp
<script type="application/json" id="forward-datasets">
{
  "conversations": @json($conversationsData),
  "groups": @json($groupsData)
}
</script>

@if(isset($conversation) && $conversation)
  <script>
  class DirectChat extends ChatCore {
      constructor() {
          super({
              typingUrl: "{{ route('chat.typing') }}",
              historyUrl: "{{ route('chat.history', $conversation) }}"
          });
          
          this.conversationId = "{{ $conversation->id }}";
          this.setupMessageHandlers();
      }
      
      setupMessageHandlers() {
          // Prevent duplicate form submissions
          const messageForm = document.getElementById('chat-form');
          if (messageForm) {
              // Remove any existing listeners to prevent duplicates
              messageForm.replaceWith(messageForm.cloneNode(true));
              
              // Get the fresh form reference
              const freshForm = document.getElementById('chat-form');
              freshForm.addEventListener('submit', (e) => this.handleMessageSubmit(e));
          }
      }
      
      async handleMessageSubmit(event) {
          event.preventDefault();
          const form = event.target;
          
          // Disable submit button to prevent multiple submissions
          const submitBtn = form.querySelector('#send-btn');
          const originalHtml = submitBtn.innerHTML;
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
          
          try {
              const formData = new FormData(form);
              
              const response = await fetch(form.action, {
                  method: 'POST',
                  headers: {
                      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                      'Accept': 'application/json'
                  },
                  body: formData
              });
              
              if (response.ok) {
                  const result = await response.json();
                  if (result.success) {
                      // Clear form and reset
                      form.reset();
                      if (window.messageComposer) {
                          window.messageComposer.clearInput();
                      }
                      
                      // Show success feedback
                      this.showToast('Message sent', 'success');
                  } else {
                      throw new Error(result.message || 'Failed to send message');
                  }
              } else {
                  throw new Error(`HTTP ${response.status}`);
              }
          } catch (error) {
              console.error('Error sending message:', error);
              this.showToast('Failed to send message', 'error');
          } finally {
              // Re-enable submit button
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalHtml;
          }
      }
      
      setupRealtimeListeners() {
          if (typeof Echo !== 'undefined') {
              window.Echo.private(`chat.${this.conversationId}`)
                  .listen('MessageSent', (e) => this.handleIncomingMessage(e))
                  .listen('UserTyping', (e) => this.handleTypingEvent(e));
          }
      }
      
      handleIncomingMessage(event) {
          console.log('Incoming message:', event);
          // Add message to UI
          this.addMessageToUI(event.message);
      }
      
      addMessageToUI(message) {
          const messagesContainer = document.getElementById('messages-container');
          if (messagesContainer) {
              // Create and append new message element
              // You'll need to implement this based on your message structure
          }
      }
      
      showToast(message, type = 'info') {
          // Use existing toast system
          if (typeof showToast === 'function') {
              showToast(message, type);
          }
      }
  }

  document.addEventListener('DOMContentLoaded', () => {
      // Make sure we don't create multiple instances
      if (!window.chat) {
          window.chat = new DirectChat();
      }
  });
  </script>
@else
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const emptyChatBtn = document.getElementById('open-new-chat-empty');
    const newChatBtn = document.getElementById('open-new-chat');
    
    if (emptyChatBtn && newChatBtn) {
      emptyChatBtn.addEventListener('click', () => {
        newChatBtn.click();
      });
    }
  });
  </script>
@endif
@endsection