{{-- resources/views/groups/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid chat-container">
  <div class="row h-100 g-0">
    {{-- Shared Sidebar --}}
    @include('partials.chat_sidebar')
    
    <div class="col-md-8 col-lg-9 d-flex flex-column" id="chat-area">
      @if(isset($group) && $group)
        {{-- Group Header --}}
        @php
          $groupData = [
            'name' => $group->name ?? 'Group Chat',
            'initial' => strtoupper(substr($group->name ?? 'G', 0, 1)),
            'avatar' => $group->avatar_path ? Storage::url($group->avatar_path) : asset('images/group-default.png'),
            'description' => $group->description ?? null,
            'isPrivate' => $group->is_private ?? false,
            'memberCount' => $group->members->count() ?? 0,
            'previewMembers' => $group->members->take(3) ?? collect(),
            'isOwner' => $group->owner_id === auth()->id(),
            'userRole' => $group->members->firstWhere('id', auth()->id())?->pivot?->role ?? 'member'
          ];
        @endphp

        @include('groups.partials.header', ['groupData' => $groupData])
        
        {{-- Messages Container --}}
        <main class="messages-container">
          <div id="messages-container">
            @foreach($group->messages as $message)
              @include('chat.shared.message', [
                'message' => $message,
                'isGroup' => true,
                'group' => $group,
                'isOwner' => $group->owner_id === auth()->id(),
                'userRole' => $group->members->firstWhere('id', auth()->id())?->pivot?->role,
                'showSenderNames' => true
              ])
            @endforeach
          </div>
        </main>
        @include('chat.shared.reply_preview', ['context' => 'direct'])
        
        {{-- ONLY ONE MESSAGE COMPOSER --}}
        @include('chat.shared.message_composer', [
          'action' => route('groups.messages.store', $group),
          'conversationId' => $group->id,
          'placeholder' => "Message {$group->name}...",
          'context' => 'group',
          'group' => $group
        ])
      @else
        {{-- Empty Group State --}}
        <div class="d-flex flex-column align-items-center justify-content-center h-100 empty-chat-state" role="main">
          <div class="text-center p-4 max-w-400">
            <div class="avatar bg-card mb-4 mx-auto rounded-circle d-flex align-items-center justify-content-center empty-chat-icon">
              <i class="bi bi-people-fill" aria-hidden="true"></i>
            </div>
            <h1 class="h4 empty-chat-title mb-3">Group Chat</h1>
            <p class="muted mb-4 empty-chat-subtitle">
              Select a group from the sidebar or create a new one to start group messaging
            </p>
          </div>
        </div>
      @endif
    </div>
  </div>
</div>

{{-- Group Specific Modals --}}
@include('groups.partials.group_management_modal')

{{-- Shared Modals --}}
@include('chat.shared.forward_modal', ['context' => 'group'])
@include('chat.shared.image_modal')

{{-- Forward Data --}}
@if(isset($group) && $group)
<script type="application/json" id="forward-datasets">
@php
  $conversationsData = [];
  foreach ($conversations ?? [] as $conversation) {
      $otherUser = $conversation->members->where('id', '!=', auth()->id())->first();
      $conversationsData[] = [
          'id' => $conversation->id,
          'name' => $otherUser->name ?? 'Unknown',
          'phone' => $otherUser->phone ?? '',
          'avatar' => $otherUser->avatar_url ?? '',
          'type' => 'conversation',
          'subtitle' => 'Direct chat'
      ];
  }

  $groupsData = [];
  foreach ($groups ?? [] as $groupItem) {
      if ($groupItem->id !== $group->id) {
          $groupsData[] = [
              'id' => $groupItem->id,
              'title' => $groupItem->name,
              'name' => $groupItem->name,
              'avatar' => $groupItem->avatar_path ? Storage::url($groupItem->avatar_path) : asset('images/group-default.png'),
              'type' => 'group',
              'subtitle' => $groupItem->members->count() . ' members'
          ];
      }
  }
@endphp
{
  "conversations": @json($conversationsData),
  "groups": @json($groupsData)
}
</script>

  <script>
  class GroupChat extends ChatCore {
      constructor() {
          super({
              typingUrl: "{{ route('groups.typing', $group) }}",
              historyUrl: "{{ route('groups.messages.history', $group) }}"
          });
          
          this.groupId = "{{ $group->id }}";
          this.groupName = "{{ $group->name }}";
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
          super.setupRealtimeListeners();
          
          if (typeof Echo !== 'undefined') {
              window.Echo.private(`group.${this.groupId}`)
                  .listen('GroupMessageSent', (e) => this.handleIncomingMessage(e))
                  .listen('GroupTyping', (e) => this.handleTypingEvent(e))
                  .listen('GroupMemberAdded', (e) => this.handleMemberUpdate(e))
                  .listen('GroupMemberRemoved', (e) => this.handleMemberUpdate(e));
          }
      }
      
      handleMemberUpdate(event) {
          console.log('Group member updated:', event);
          this.showToast(`Group membership updated`, 'info');
      }
      
      showToast(message, type = 'info') {
          if (typeof showToast === 'function') {
              showToast(message, type);
          }
      }
  }

  document.addEventListener('DOMContentLoaded', () => {
      // Make sure we don't create multiple instances
      if (!window.groupChat) {
          window.groupChat = new GroupChat();
      }
  });
  </script>
@endif
@endsection