{{-- resources/views/groups/partials/message.blade.php --}}
@php
  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Str;

  $isOwn = (int) $message->sender_id === (int) auth()->id();
  $messageId = $message->id;
  $isRead = $message->read_at;
  $hasAttachments = $message->attachments->isNotEmpty();
  $hasReactions = $message->reactions->isNotEmpty();
  $isForwarded = $message->forwarded_from_id;
  $hasReply = $message->reply_to_id && $message->replyTo;
  $isExpired = $message->expires_at ? $message->expires_at->isPast() : false;
  $canEdit = $isOwn || $isOwner || $userRole === 'admin';
  $canDelete = $isOwn || $isOwner || $userRole === 'admin';
  
  $senderName = $message->sender->name ?? $message->sender->phone ?? 'Unknown User';
  $body = $message->body ?? '';

  // Reply data
  $replyMessage = $hasReply ? $message->replyTo : null;
  $replySenderName = $replyMessage ? ($replyMessage->sender->name ?? $replyMessage->sender->phone ?? 'Unknown User') : null;
  $replyBody = $replyMessage ? ($replyMessage->body ?? '') : null;
  $replyHasAttachments = $replyMessage ? $replyMessage->attachments->isNotEmpty() : false;
  $replyIsOwn = $replyMessage ? ((int) $replyMessage->sender_id === (int) auth()->id()) : false;
@endphp

@unless($isExpired)
  <div class="message mb-3 d-flex {{ $isOwn ? 'justify-content-end' : 'justify-content-start' }}"
       data-message-id="{{ $messageId }}"
       data-from-me="{{ $isOwn ? '1' : '0' }}"
       data-read="{{ $isRead ? '1' : '0' }}"
       role="listitem"
       aria-label="Message from {{ $isOwn ? 'you' : $senderName }}">

    {{-- Message Bubble --}}
    <div class="message-bubble {{ $isOwn ? 'sent' : 'received' }}">
      {{-- Sender Name (for received messages) --}}
      @unless($isOwn)
        <small class="sender-name" aria-label="Sender: {{ $senderName }}">
          {{ $senderName }}
        </small>
      @endunless

      <div class="message-content">
        {{-- Reply Preview --}}
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
        @if($body)
          <div class="message-text">
            {!! Str::of(e($body))->replaceMatches(
                '/(https?:\/\/[^\s]+)/', 
                fn($match) => '<a href="'.e($match[0]).'" target="_blank" class="linkify" rel="noopener noreferrer">'.e($match[0]).'</a>'
            ) !!}
          </div>
        @endif

        {{-- Attachments --}}
        @if($hasAttachments)
          @foreach($message->attachments as $file)
            @php
              $url = method_exists($file, 'getUrlAttribute') ? $file->url : Storage::url($file->file_path);
              $ext = strtolower(pathinfo($file->file_path, PATHINFO_EXTENSION));
              $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
              $isVideo = in_array($ext, ['mp4','mov','avi','webm']);
              $fileName = $file->original_name ?? basename($file->file_path);
              $fileSize = $file->file_size ? round($file->file_size / 1024) : null;
            @endphp
            
            @if($isImage)
              <div class="mt-2">
                <img data-src="{{ $url }}" alt="Shared image" class="img-fluid rounded media-img" 
                     loading="lazy" data-bs-toggle="modal" data-bs-target="#imageModal" 
                     data-image-src="{{ $url }}" width="220" height="220">
              </div>
            @elseif($isVideo)
              <div class="mt-2">
                <video controls class="img-fluid rounded media-video" preload="metadata"
                       data-src="{{ $url }}" style="max-width: 220px;">
                  <source src="{{ $url }}" type="video/{{ $ext }}">
                  Your browser does not support the video tag.
                </video>
              </div>
            @else
              <div class="mt-2">
                <a href="{{ $url }}" target="_blank" class="d-inline-flex align-items-center doc-link" 
                   rel="noopener noreferrer" download="{{ $fileName }}">
                  <i class="bi bi-file-earmark me-1" aria-hidden="true"></i>
                  <span class="text-truncate" style="max-width: 200px;">
                    {{ $fileName }}
                  </span>
                  @if($fileSize)
                    <small class="text-muted ms-2">({{ $fileSize }} KB)</small>
                  @endif
                </a>
              </div>
            @endif
          @endforeach
        @endif
      </div>

      {{-- Message Footer --}}
      <div class="message-footer d-flex justify-content-between align-items-center mt-1">
        <small class="muted message-time">
          <time datetime="{{ $message->created_at->toIso8601String() }}">
            {{ $message->created_at->format('h:i A') }}
          </time>
        </small>
        
        {{-- Status Indicator (own messages only) --}}
        @if($isOwn)
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
            <span class="badge bg-reaction rounded-pill me-1" 
                  title="{{ $reaction->user->name ?? 'User' }} reacted with {{ $reaction->emoji ?? $reaction->reaction ?? '👍' }}"
                  data-bs-toggle="tooltip">
              {{ $reaction->emoji ?? $reaction->reaction ?? '👍' }}
            </span>
          @endforeach
        </div>
      @endif
    </div>

    {{-- Message Actions --}}
    <div class="message-actions dropdown">
      <button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown" 
              aria-expanded="false" aria-label="Message actions" title="Message actions">
        <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
      </button>
      <ul class="dropdown-menu" role="menu">
        {{-- Reply --}}
        <li role="none">
          <button class="dropdown-item d-flex align-items-center gap-2 reply-btn" 
                  data-message-id="{{ $messageId }}" role="menuitem">
            <i class="bi bi-reply" aria-hidden="true"></i>
            <span>Reply</span>
          </button>
        </li>

        {{-- Forward --}}
        <li role="none">
          <button class="dropdown-item d-flex align-items-center gap-2 forward-btn" 
                  data-message-id="{{ $messageId }}" role="menuitem">
            <i class="bi bi-forward" aria-hidden="true"></i>
            <span>Forward</span>
          </button>
        </li>

        {{-- Edit (if permitted) --}}
        @if($canEdit)
          <li role="none">
            <button class="dropdown-item d-flex align-items-center gap-2 edit-btn"
                    data-message-id="{{ $messageId }}"
                    data-body="{{ e($body) }}"
                    data-edit-url="{{ route('groups.messages.update', ['group' => $group, 'message' => $message]) }}"
                    role="menuitem">
              <i class="bi bi-pencil" aria-hidden="true"></i>
              <span>Edit</span>
            </button>
          </li>
        @endif

        {{-- Delete (if permitted) --}}
        @if($canDelete)
          <li role="none">
            <form method="POST" action="{{ route('groups.messages.delete', [$group, $message]) }}" 
                  class="d-inline delete-form" onsubmit="return confirm('Are you sure you want to delete this message?')">
              @csrf @method('DELETE')
              <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger" role="menuitem">
                <i class="bi bi-trash" aria-hidden="true"></i>
                <span>Delete</span>
              </button>
            </form>
          </li>
        @endif

        <li><hr class="dropdown-divider"></li>
        
        {{-- Quick Reactions --}}
        <li role="none">
          <div class="d-flex px-3 py-1 reaction-buttons" role="group" aria-label="Quick reactions">
            @php 
              $reactUrl = route('groups.messages.reactions', ['group' => $group, 'message' => $message]);
            @endphp
            <button class="btn btn-sm reaction-btn" data-reaction="👍" data-react-url="{{ $reactUrl }}" 
                    title="Like" aria-label="Like">👍</button>
            <button class="btn btn-sm reaction-btn ms-1" data-reaction="❤️" data-react-url="{{ $reactUrl }}" 
                    title="Love" aria-label="Love">❤️</button>
            <button class="btn btn-sm reaction-btn ms-1" data-reaction="😂" data-react-url="{{ $reactUrl }}" 
                    title="Laugh" aria-label="Laugh">😂</button>
            <button class="btn btn-sm reaction-btn ms-1" data-reaction="😮" data-react-url="{{ $reactUrl }}" 
                    title="Wow" aria-label="Wow">😮</button>
          </div>
        </li>
      </ul>
    </div>
  </div>

  {{-- JavaScript for reply functionality --}}
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
  </style>
@endunless