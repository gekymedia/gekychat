{{-- resources/views/chat/shared/message.blade.php --}}
@php
  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Str;

  // === CORE MESSAGE DATA ===
  $isOwn = (int) $message->sender_id === (int) auth()->id();
  $messageId = $message->id;
  $isRead = $message->read_at;
  
  // === CONTEXT CONFIGURATION ===
  $isGroup = $isGroup ?? false;
  $showSenderNames = $showSenderNames ?? $isGroup;
  $context = $isGroup ? 'group' : 'direct';
  
  // === MESSAGE CONTENT FLAGS ===
  $hasAttachments = $message->attachments->isNotEmpty();
  $hasReactions = $message->reactions->isNotEmpty();
  $isForwarded = $message->forwarded_from_id ?? $message->is_forwarded ?? false;
  $hasReply = $message->reply_to_id && $message->replyTo;
  $isExpired = $message->expires_at ? $message->expires_at->isPast() : false;
  
  // === SENDER INFORMATION ===
  $senderName = $message->sender->name ?? $message->sender->phone ?? 'Unknown User';
  $body = $message->body ?? '';
  $isEncrypted = $message->is_encrypted ?? false;
  
  // === REPLY DATA (FIXED) ===
  $replyMessage = $hasReply ? $message->replyTo : null;
  $replySenderName = $replyMessage ? ($replyMessage->sender->name ?? $replyMessage->sender->phone ?? 'Unknown User') : null;
  $replyBody = $replyMessage ? ($replyMessage->body ?? '') : null;
  $replyHasAttachments = $replyMessage ? $replyMessage->attachments->isNotEmpty() : false;
  $replyIsOwn = $replyMessage ? ((int) $replyMessage->sender_id === (int) auth()->id()) : false;
  
  // === FIXED PERMISSIONS LOGIC ===
  if ($isGroup) {
    $isOwner = $isOwner ?? false;
    $userRole = $userRole ?? 'member';
    $canEdit = $isOwn;
    $canDelete = $isOwn || $isOwner || $userRole === 'admin';
  } else {
    $canEdit = $isOwn;
    $canDelete = $isOwn;
  }
  
  // === ACTION URLS ===
  if ($isGroup) {
    $deleteUrl = route('groups.messages.delete', ['group' => $group, 'message' => $message]);
    $editUrl = route('groups.messages.update', ['group' => $group, 'message' => $message]);
    $reactUrl = route('groups.messages.reactions', ['group' => $group, 'message' => $message]);
  } else {
    $deleteUrl = "/messages/{$messageId}";
    $editUrl = "/messages/{$messageId}";
    $reactUrl = "/messages/react";
  }
  
  // === SENDER ROLE STYLING (Groups only) ===
  $senderRoleClass = '';
  if ($isGroup && !$isOwn) {
    $senderRole = $message->sender->pivot->role ?? 'member';
    if ($senderRole === 'owner') {
      $senderRoleClass = 'owner';
    } elseif ($senderRole === 'admin') {
      $senderRoleClass = 'admin';
    }
  }
@endphp

@unless($isExpired)
<div class="message mb-3 d-flex {{ $isOwn ? 'justify-content-end' : 'justify-content-start' }} position-relative"
     data-message-id="{{ $messageId }}"
     data-context="{{ $context }}"
     data-from-me="{{ $isOwn ? '1' : '0' }}"
     data-read="{{ $isRead ? '1' : '0' }}"
     data-sender-role="{{ $senderRoleClass }}"
     role="listitem"
     aria-label="Message from {{ $isOwn ? 'you' : $senderName }}">

  {{-- Message Bubble --}}
  <div class="message-bubble {{ $isOwn ? 'sent' : 'received' }} {{ $senderRoleClass }} position-relative">
    {{-- Sender Name (for groups and when configured) --}}
    @if(!$isOwn && $showSenderNames)
      <small class="sender-name {{ $senderRoleClass }}" aria-label="Sender: {{ $senderName }}">
        {{ $senderName }}
        @if($isGroup && $senderRoleClass)
          <span class="role-badge {{ $senderRoleClass }}" title="{{ ucfirst($senderRoleClass) }}">
            @if($senderRoleClass === 'owner')
              <i class="bi bi-star-fill" aria-hidden="true"></i>
            @elseif($senderRoleClass === 'admin')
              <i class="bi bi-shield-fill" aria-hidden="true"></i>
            @endif
          </span>
        @endif
      </small>
    @endif

    <div class="message-content">
      {{-- Reply Preview (FIXED VERSION) --}}
      @if($hasReply && $replyMessage)
        <div class="reply-preview mb-2 p-2 rounded border-start border-3 border-primary bg-light" 
             role="button"
             tabindex="0"
             data-reply-to="{{ $replyMessage->id }}"
             aria-label="Replying to message from {{ $replyIsOwn ? 'you' : $replySenderName }}: {{ Str::limit($replyBody, 100) }}">
          <div class="d-flex align-items-center mb-1">
            <i class="bi bi-reply-fill me-1 text-primary" aria-hidden="true"></i>
            <small class="fw-semibold text-primary">
              {{ $replyIsOwn ? 'You' : $replySenderName }}
            </small>
          </div>
          
          {{-- Reply Message Content --}}
          <div class="reply-content">
            @if($replyHasAttachments)
              <small class="text-muted">
                <i class="bi bi-paperclip me-1" aria-hidden="true"></i>
                {{ $replyMessage->attachments->count() }} attachment(s)
              </small>
            @elseif($replyBody)
              <small class="text-muted text-truncate d-block" style="max-width: 200px;">
                {{ Str::limit($replyBody, 80) }}
              </small>
            @else
              <small class="text-muted">
                <i class="bi bi-chat me-1" aria-hidden="true"></i>
                Message
              </small>
            @endif
          </div>
        </div>
      @endif

      {{-- Forwarded Header --}}
      @if($isForwarded)
        <div class="forwarded-header mb-1">
          <small class="muted">
            <i class="bi bi-forward-fill me-1" aria-hidden="true"></i>
            Forwarded
          </small>
        </div>
      @endif

      {{-- Message Text --}}
      @if(!empty(trim($body)) && (!$isEncrypted || $isOwn))
        <div class="message-text">
          @if($isEncrypted && !$isOwn)
            <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>
            <span>Encrypted message</span>
          @else
            {!! Str::of(e($body))->replaceMatches(
              '/(https?:\/\/[^\s]+)/', 
              fn($match) => '<a href="'.e($match[0]).'" target="_blank" class="linkify" rel="noopener noreferrer">'.e($match[0]).'</a>'
            ) !!}
          @endif
        </div>
      @endif

      {{-- Attachments --}}
      @if($hasAttachments)
        <div class="attachments-container mt-2">
          @foreach($message->attachments as $attachment)
            @include('chat.shared.attachment', [
              'attachment' => $attachment,
              'message' => $message,
              'isOwn' => $isOwn
            ])
          @endforeach
        </div>
      @endif
    </div>

    {{-- Message Footer --}}
    <div class="message-footer d-flex justify-content-between align-items-center mt-1">
      <small class="muted message-time">
        <time datetime="{{ $message->created_at->toIso8601String() }}">
          {{ $message->created_at->format('h:i A') }}
        </time>
      </small>
      
      {{-- Status Indicator (direct messages only) --}}
      @if($isOwn && !$isGroup)
        <div class="status-indicator" aria-label="Message status: {{ $message->status ?? 'sent' }}">
          @if($message->read_at)
            <i class="bi bi-check2-all text-primary" title="Read" data-bs-toggle="tooltip"></i>
          @elseif($message->delivered_at)
            <i class="bi bi-check2-all muted" title="Delivered" data-bs-toggle="tooltip"></i>
          @else
            <i class="bi bi-check2 muted" title="Sent" data-bs-toggle="tooltip"></i>
          @endif
        </div>
      @endif
    </div>

    {{-- Reactions --}}
    @if($hasReactions)
      <div class="reactions-container mt-1" aria-label="Reactions">
        @foreach($message->reactions as $reaction)
          @php
            $isOwnReaction = $reaction->user_id === auth()->id();
            $emoji = $reaction->emoji ?? $reaction->reaction ?? '👍';
            $userName = $reaction->user->name ?? 'User';
          @endphp
          <span class="badge bg-reaction rounded-pill me-1 {{ $isOwnReaction ? 'own-reaction' : '' }}" 
                title="{{ $userName }} reacted with {{ $emoji }}"
                data-bs-toggle="tooltip">
            {{ $emoji }}
          </span>
        @endforeach
      </div>
    @endif
  </div>

  {{-- Message Actions --}}
  @include('chat.shared.message_actions', [
    'messageId' => $messageId,
    'isOwn' => $isOwn,
    'isGroup' => $isGroup,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'body' => $body,
    'deleteUrl' => $deleteUrl,
    'editUrl' => $editUrl,
    'reactUrl' => $reactUrl,
    'group' => $group ?? null
  ])
</div>

{{-- JavaScript for reply functionality (INCLUDED IN EVERY MESSAGE) --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Handle reply preview click to scroll to original message
  const replyPreviews = document.querySelectorAll('.reply-preview');
  replyPreviews.forEach(preview => {
    preview.addEventListener('click', function() {
      const replyToId = this.getAttribute('data-reply-to');
      const originalMessage = document.querySelector(`[data-message-id="${replyToId}"]`);
      
      if (originalMessage) {
        originalMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Add highlight effect
        originalMessage.style.transition = 'all 0.5s ease';
        originalMessage.style.backgroundColor = 'var(--bs-warning-bg-subtle)';
        originalMessage.style.border = '2px solid var(--bs-warning-border-subtle)';
        
        setTimeout(() => {
          originalMessage.style.backgroundColor = '';
          originalMessage.style.border = '';
        }, 2000);
      }
    });

    // Add keyboard support
    preview.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.click();
      }
    });
  });
});
</script>

<style>
.message {
  position: relative;
}

.message-bubble {
  max-width: 70%;
  padding: 8px 12px;
  border-radius: 18px;
  position: relative;
  word-wrap: break-word;
}

.message-bubble.sent {
  background: var(--bubble-sent-bg);
  color: var(--bubble-sent-text);
  border-bottom-right-radius: 4px;
}

.message-bubble.received {
  background: var(--bubble-recv-bg);
  color: var(--bubble-recv-text);
  border-bottom-left-radius: 4px;
}

/* Reply Preview Styling */
.reply-preview {
  cursor: pointer;
  transition: all 0.2s ease;
  background: var(--bs-light-bg-subtle) !important;
  border-left-color: var(--bs-primary) !important;
}

.reply-preview:hover {
  background: var(--bs-primary-bg-subtle) !important;
  transform: translateX(2px);
}

.reply-preview:focus {
  outline: 2px solid var(--bs-primary);
  outline-offset: 2px;
}

.reply-content {
  max-width: 100%;
  overflow: hidden;
}

.message-bubble.sent .reply-preview {
  background: var(--bs-primary-bg-subtle) !important;
  border-left-color: var(--bs-primary) !important;
}

.message-bubble.received .reply-preview {
  background: var(--bs-light-bg-subtle) !important;
  border-left-color: var(--bs-secondary) !important;
}

/* Message Actions Styling */
.message-actions {
  z-index: 10;
  bottom: -10px;
  opacity: 0;
  visibility: hidden;
  transition: all 0.2s ease;
}

.message-bubble.sent .message-actions {
  right: -10px;
  left: auto;
}

.message-bubble.received .message-actions {
  left: -10px;
  right: auto;
}

/* Show actions on message hover */
.message:hover .message-actions {
  opacity: 1;
  visibility: visible;
}

.action-buttons {
  backdrop-filter: blur(10px);
  background: rgba(255, 255, 255, 0.95) !important;
  border: 1px solid var(--border);
}

[data-theme="dark"] .action-buttons {
  background: rgba(30, 30, 30, 0.95) !important;
  border-color: #444 !important;
}

.action-buttons .btn {
  padding: 4px 8px;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.action-buttons .btn:hover {
  transform: scale(1.1);
}

.attachments-container {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.attachment-item {
  border-radius: 12px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid var(--border);
}

.attachment-item img,
.attachment-item video {
  max-width: 100%;
  height: auto;
  display: block;
}

.attachment-preview {
  position: relative;
  cursor: pointer;
}

.attachment-preview img,
.attachment-preview video {
  max-width: 300px;
  max-height: 300px;
  border-radius: 8px;
}

.attachment-info {
  padding: 8px;
  background: rgba(0, 0, 0, 0.7);
  color: white;
  font-size: 0.875rem;
}

.file-attachment {
  padding: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.file-icon {
  font-size: 1.5rem;
}

.file-info {
  flex: 1;
  min-width: 0;
}

.file-name {
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.file-size {
  font-size: 0.75rem;
  opacity: 0.8;
}

.download-btn {
  background: var(--wa-green);
  color: white;
  border: none;
  border-radius: 6px;
  padding: 4px 8px;
  font-size: 0.75rem;
  cursor: pointer;
}

/* Image modal styling */
.modal-content {
  background: var(--card);
  border: 1px solid var(--border);
}

.modal-header {
  border-bottom: 1px solid var(--border);
}

.modal-footer {
  border-top: 1px solid var(--border);
}

.modal-image {
  max-width: 100%;
  max-height: 70vh;
  object-fit: contain;
}

/* Responsive design */
@media (max-width: 768px) {
  .message-bubble {
    max-width: 85%;
  }
  
  .attachment-preview img,
  .attachment-preview video {
    max-width: 250px;
    max-height: 250px;
  }
  
  .message-actions {
    position: relative !important;
    bottom: auto;
    right: auto;
    left: auto;
    margin-top: 8px;
    justify-content: center;
    opacity: 1;
    visibility: visible;
  }
  
  .action-buttons {
    background: rgba(255, 255, 255, 0.98) !important;
  }
}

@media (max-width: 576px) {
  .message-bubble {
    max-width: 90%;
  }
  
  .attachment-preview img,
  .attachment-preview video {
    max-width: 200px;
    max-height: 200px;
  }
}
</style>
@endunless