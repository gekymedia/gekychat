{{-- resources/views/chat/index.blade.php --}}
@extends('layouts.app')

@section('body_class', 'page-chat')

@section('title', 'GekyChat - Messages')
@section('description', 'Chat instantly with your contacts and groups using GekyChat real-time messaging.')
@section('keywords', 'chat, messaging, real-time, contacts, groups, secure')

@php
  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Str;

  // ---- Conversation Header Data ----
  $headerData = [
    'name' => 'Conversation',
    'initial' => 'C',
    'avatar' => null,
    'isGroup' => false,
    'online' => false
  ];

  if (isset($conversation)) {
      if ($conversation->is_group) {
          // Group conversation
          $headerData = [
            'name' => $conversation->name ?? 'Group Chat',
            'initial' => Str::upper(Str::substr($conversation->name ?? 'G', 0, 1)),
            'avatar' => $conversation->avatar_path ? Storage::url($conversation->avatar_path) : null,
            'isGroup' => true,
            'online' => false
          ];
      } else {
          // Direct conversation - find the other participant
          $me = auth()->id();
          
          // Ensure members are loaded efficiently
          if (!$conversation->relationLoaded('members')) {
              $conversation->load(['members:id,name,phone,avatar_path,last_seen']);
          }
          
          $otherUser = $conversation->members->firstWhere('id', '!=', $me);
          
          if ($otherUser) {
              $headerData = [
                'name' => $otherUser->name ?? $otherUser->phone ?? 'Unknown User',
                'initial' => Str::upper(Str::substr($otherUser->name ?? $otherUser->phone ?? 'U', 0, 1)),
                'avatar' => $otherUser->avatar_path ? Storage::url($otherUser->avatar_path) : null,
                'isGroup' => false,
                'online' => $otherUser->last_seen && $otherUser->last_seen->gt(now()->subMinutes(5))
              ];
          }
      }
  }

  // ---- Optimized Data Loading ----
  $sidebarData = [
    'conversations' => $conversations ?? collect(),
    'groups' => $groups ?? collect(),
    'botConversation' => $botConversation ?? null,
    'users' => $users ?? null
  ];

  // ---- Forward Data Preparation ----
  $forwardData = [
    'conversations' => $forwardDMs ?? [],
    'groups' => $forwardGroups ?? []
  ];

  // ---- Security Settings ----
  $securitySettings = [
    'isEncrypted' => old('is_encrypted', '0') === '1',
    'expiresIn' => old('expires_in', '')
  ];
@endphp

@section('content')
<div class="container-fluid chat-container">
  {{-- Network Status Banner --}}
  <div class="network-banner" id="net-banner" style="display: none;" role="alert" aria-live="polite">
    <div class="container">
      <div class="d-flex align-items-center justify-content-center gap-2">
        <i class="bi bi-wifi-off"></i>
        <span>You are currently offline. Messages will be sent when connection is restored.</span>
        <span id="net-retry-in" class="ms-2"></span>
      </div>
    </div>
  </div>

  <div class="row h-100 g-0">
    {{-- SIDEBAR --}}
    @include('partials.chat_sidebar', $sidebarData)

    {{-- MAIN CHAT AREA --}}
    <div class="col-md-8 col-lg-9 d-flex flex-column" id="chat-area" aria-live="polite" aria-atomic="true">
      @if(isset($conversation))
        {{-- Chat Header --}}
        <header class="chat-header p-3 border-bottom d-flex align-items-center" role="banner">
          <button class="btn btn-sm btn-ghost d-md-none me-2" id="back-to-conversations" 
                  aria-label="Back to conversations" title="Back to conversations">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
          </button>

          <div class="d-flex align-items-center flex-grow-1 min-width-0">
            {{-- Avatar --}}
            <div class="position-relative">
              @if(!empty($headerData['avatar']))
                <img src="{{ $headerData['avatar'] }}" alt="{{ $headerData['name'] }} avatar"
                     class="avatar avatar-img me-3" loading="lazy" width="40" height="40"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="avatar me-3 rounded-circle bg-brand text-white" style="display: none;">
                  {{ $headerData['initial'] }}
                </div>
              @else
                <div class="avatar me-3 rounded-circle bg-brand text-white">
                  {{ $headerData['initial'] }}
                </div>
              @endif
              
              {{-- Online Status Indicator --}}
              @if($headerData['online'] && !$headerData['isGroup'])
                <span class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-2 border-white"
                      style="width: 12px; height: 12px;" title="Online"></span>
              @endif
            </div>

            {{-- Conversation Info --}}
            <div class="flex-grow-1 min-width-0">
              <h1 class="h5 mb-0 chat-header-name text-truncate" title="{{ $headerData['name'] }}">
                {{ $headerData['name'] }}
              </h1>
              <div class="d-flex align-items-center gap-2">
                <small class="muted typing-indicator" id="typing-indicator" style="display: none;" aria-live="polite">
                  <span class="typing-dots">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                  </span>
                  typing…
                </small>
                @if($headerData['online'] && !$headerData['isGroup'])
                  <small class="muted online-status" id="online-status">
                    <i class="bi bi-circle-fill text-success me-1" style="font-size: 0.5rem;"></i>
                    <span>Online</span>
                  </small>
                @endif
              </div>
            </div>
          </div>

          {{-- Header Actions --}}
          <div class="d-flex align-items-center gap-2">
            {{-- Online Users --}}
            <div id="online-list" class="d-none d-md-flex align-items-center gap-2" aria-label="Online users"></div>
            
            {{-- Options Menu --}}
            <div class="dropdown">
              <button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown" 
                      aria-expanded="false" aria-label="Chat options" title="Chat options">
                <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end" role="menu">
                <li role="none">
                  <a class="dropdown-item d-flex align-items-center gap-2" 
                     href="{{ route('profile.edit') }}" role="menuitem">
                    <i class="bi bi-person" aria-hidden="true"></i>
                    <span>View profile</span>
                  </a>
                </li>
                <li role="none">
                  <button class="dropdown-item d-flex align-items-center gap-2" 
                          id="mute-chat-btn" role="menuitem">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    <span>Mute notifications</span>
                  </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li role="none">
                  <button class="dropdown-item d-flex align-items-center gap-2 text-danger" 
                          id="clear-chat-btn" role="menuitem">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    <span>Clear chat</span>
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </header>

        {{-- Messages Container --}}
        <main class="messages-container flex-grow-1 position-relative" id="chat-box" role="main">
          {{-- Loading Indicator --}}
          <div id="messages-loader" class="messages-loader text-center py-4" style="display: none;" aria-live="polite">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading older messages...</span>
            </div>
          </div>

          {{-- Messages List --}}
          <div id="messages-container" role="log" aria-live="polite" aria-relevant="additions">
            @forelse($conversation->messages->where('is_expired', false) as $message)
              @include('chat.partials.message', ['message' => $message])
            @empty
              {{-- Empty Conversation State --}}
              <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center py-5">
                <div class="avatar bg-card mb-3 rounded-circle d-flex align-items-center justify-content-center empty-chat-icon">
                  <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                </div>
                <h2 class="h5 empty-chat-title mb-2">Start a conversation</h2>
                <p class="muted empty-chat-subtitle mb-4">Send your first message to get started</p>
              </div>
            @endforelse
          </div>

          {{-- Scroll Anchor for New Messages --}}
          <div id="scroll-anchor" tabindex="-1" aria-hidden="true"></div>
        </main>

        {{-- Reply Preview --}}
        <div class="reply-preview-container border-top" id="reply-preview" style="display: none;" aria-live="polite">
          <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center py-2">
              <div class="flex-grow-1 min-width-0">
                <small class="muted d-block">Replying to:</small>
                <div class="reply-preview-content text-truncate" aria-live="polite"></div>
              </div>
              <button class="btn btn-sm btn-outline-danger ms-3 flex-shrink-0" id="cancel-reply" 
                      aria-label="Cancel reply" title="Cancel reply">
                <i class="bi bi-x" aria-hidden="true"></i>
              </button>
            </div>
          </div>
        </div>

        {{-- Message Composer --}}
        <footer class="message-input-container border-top" role="form" aria-label="Send message">
          <div class="container-fluid">
            <form id="chat-form" action="{{ route('chat.send') }}" method="POST" 
                  enctype="multipart/form-data" novalidate>
              @csrf
              
              {{-- Hidden Fields --}}
              <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
              <input type="hidden" name="reply_to" id="reply-to" value="">
              <input type="hidden" name="is_encrypted" id="is-encrypted" value="{{ $securitySettings['isEncrypted'] ? '1' : '0' }}">
              <input type="hidden" name="expires_in" id="expires-in" value="{{ $securitySettings['expiresIn'] }}">

              <div class="input-group composer" id="drop-zone" role="group" aria-label="Message composer">
                {{-- Emoji Button --}}
                <button class="btn btn-ghost" type="button" id="emoji-btn" 
                        aria-label="Add emoji" title="Add emoji" data-bs-toggle="tooltip">
                  <i class="bi bi-emoji-smile" aria-hidden="true"></i>
                </button>

                {{-- Message Input --}}
                <input type="text" name="body" class="form-control message-input" 
                       placeholder="Type a message..." required id="message-input" 
                       autocomplete="off" maxlength="1000" aria-label="Message input"
                       aria-describedby="send-button">

                {{-- Security & Attachment Buttons --}}
                <div class="btn-group" role="group" aria-label="Message actions">
                  <button class="btn btn-ghost" type="button" id="security-btn" 
                          aria-label="Security options" title="Security options" data-bs-toggle="tooltip">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                  </button>
                  
                  <button class="btn btn-ghost dropdown-toggle" type="button" 
                          data-bs-toggle="dropdown" aria-expanded="false" 
                          aria-label="Attach files" title="Attach files">
                    <i class="bi bi-paperclip" aria-hidden="true"></i>
                  </button>
                  
                  <ul class="dropdown-menu dropdown-menu-end" role="menu">
                    <li role="none">
                      <label class="dropdown-item d-flex align-items-center gap-2 cursor-pointer" role="menuitem">
                        <i class="bi bi-image" aria-hidden="true"></i>
                        <span>Photo or Video</span>
                        <input type="file" name="attachments[]" accept="image/*,video/*" 
                               class="d-none" id="photo-upload" multiple>
                      </label>
                    </li>
                    <li role="none">
                      <label class="dropdown-item d-flex align-items-center gap-2 cursor-pointer" role="menuitem">
                        <i class="bi bi-file-earmark" aria-hidden="true"></i>
                        <span>Document</span>
                        <input type="file" name="attachments[]" class="d-none" 
                               id="doc-upload" multiple accept=".pdf,.doc,.docx,.txt">
                      </label>
                    </li>
                  </ul>
                </div>

                {{-- Send Button --}}
                <button class="btn btn-wa" type="submit" id="send-btn" 
                        aria-label="Send message" title="Send message">
                  <i class="bi bi-send" aria-hidden="true"></i>
                </button>
              </div>

              {{-- Upload Progress --}}
              <div id="upload-progress" class="progress mt-2" style="display: none;" 
                   role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <div class="progress-bar" style="width: 0%"></div>
              </div>

              {{-- Mobile Helper Text --}}
              <small class="muted d-block mt-2 d-md-none text-center">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                Use the sidebar to start new chats or groups
              </small>
            </form>
          </div>
        </footer>
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

{{-- Emoji Picker --}}
<div id="emoji-picker-wrap" class="emoji-wrap" aria-live="polite" style="display: none;">
  <emoji-picker id="emoji-picker" class="shadow-lg"></emoji-picker>
</div>

{{-- Security Modal --}}
<div id="security-modal" class="modal fade" tabindex="-1" aria-labelledby="securityModalLabel" 
     aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="securityModalLabel">Security Options</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" 
                aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="form-check form-switch mb-4">
          <input class="form-check-input" type="checkbox" id="encrypt-toggle" 
                 {{ $securitySettings['isEncrypted'] ? 'checked' : '' }}>
          <label class="form-check-label fw-medium" for="encrypt-toggle">
            <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>
            End-to-end encryption
          </label>
          <div class="form-text text-muted small">
            Messages are encrypted and can only be read by you and the recipient
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">
            <i class="bi bi-clock me-2" aria-hidden="true"></i>
            Self-destruct timer
          </label>
          <select class="form-select" id="expiration-select" aria-describedby="expirationHelp">
            <option value="0" {{ !$securitySettings['expiresIn'] ? 'selected' : '' }}>No expiration</option>
            <option value="1" {{ $securitySettings['expiresIn'] == '1' ? 'selected' : '' }}>1 hour</option>
            <option value="24" {{ $securitySettings['expiresIn'] == '24' ? 'selected' : '' }}>1 day</option>
            <option value="168" {{ $securitySettings['expiresIn'] == '168' ? 'selected' : '' }}>1 week</option>
          </select>
          <div id="expirationHelp" class="form-text text-muted small">
            Messages will automatically delete after the selected time period
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="apply-security">Apply Settings</button>
      </div>
    </div>
  </div>
</div>

{{-- Image Modal --}}
<div id="imageModal" class="modal fade" tabindex="-1" aria-labelledby="imageModalLabel" 
     aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-transparent border-0">
      <div class="modal-header border-0 position-absolute top-0 end-0 z-1">
        <button type="button" class="btn btn-ghost text-white" data-bs-dismiss="modal" 
                aria-label="Close image">
          <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
      </div>
      <div class="modal-body text-center p-0">
        <img id="modalImage" src="" alt="Enlarged view of shared image" 
             class="img-fluid rounded shadow-lg" loading="lazy">
      </div>
    </div>
  </div>
</div>

{{-- Forward Modal --}}
<div id="forward-modal" class="modal fade" tabindex="-1" aria-labelledby="forwardModalLabel" 
     aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content rounded-3 border-0 shadow-lg">
      <div class="modal-header border-0 pb-0">
        <h2 class="modal-title h5 fw-bold">Forward to</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" 
                aria-label="Close"></button>
      </div>
      
      <div class="modal-body p-0">
        {{-- Search --}}
        <div class="p-3 border-bottom">
          <div class="input-group input-group-lg rounded-pill bg-light">
            <span class="input-group-text border-0 bg-light text-muted">
              <i class="bi bi-search" aria-hidden="true"></i>
            </span>
            <input type="text" class="form-control border-0 bg-light" id="forward-search" 
                   placeholder="Search conversations..." aria-label="Search conversations">
          </div>
        </div>

        {{-- Lists --}}
        <div class="forward-list-container">
          {{-- Recent Chats --}}
          <div class="forward-section">
            <div class="px-3 py-2 text-muted small fw-bold bg-light">RECENT CHATS</div>
            <div id="forward-recent-list" class="list-group list-group-flush" role="listbox" 
                 aria-label="Recent chats"></div>
          </div>

          {{-- Contacts --}}
          <div class="forward-section">
            <div class="px-3 py-2 text-muted small fw-bold bg-light">CONTACTS</div>
            <div id="forward-contacts-list" class="list-group list-group-flush" role="listbox" 
                 aria-label="Contacts"></div>
          </div>

          {{-- Groups --}}
          <div class="forward-section">
            <div class="px-3 py-2 text-muted small fw-bold bg-light">GROUPS</div>
            <div id="forward-groups-list" class="list-group list-group-flush" role="listbox" 
                 aria-label="Groups"></div>
          </div>
        </div>
      </div>

      <div class="modal-footer border-0">
        <button type="button" class="btn btn-lg rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-lg rounded-pill px-4" id="forward-confirm" disabled>
          <span id="forward-count">0</span>
          <span class="visually-hidden">Forward to</span>
          Forward
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Hidden Data Stores --}}
<script id="forward-datasets" type="application/json">
@json($forwardData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG)
</script>

{{-- Notification Sound --}}
<audio id="notification-sound" preload="metadata" aria-hidden="true">
  <source src="{{ asset('sounds/notification.mp3') }}" type="audio/mpeg">
  <source src="{{ asset('sounds/notification.ogg') }}" type="audio/ogg">
</audio>

{{-- Empty State Button Handler --}}
@unless(isset($conversation))
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
@endunless

{{-- Component Styles --}}
@include('chat.partials.styles')

{{-- Component Scripts --}}
@include('chat.partials.scripts')

@endsection