resources/views/chat/partials/message.blade.php
@php
  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Str;

  $isOwn = $message->is_own;
  $messageId = $message->id;
  $isRead = $message->read_at;
  $hasAttachments = $message->attachments->isNotEmpty();
  $hasReactions = $message->reactions->isNotEmpty();
  $isEncrypted = $message->is_encrypted && !$isOwn;
  $isForwarded = $message->is_forwarded;
  $hasReply = $message->reply_to;
@endphp

<div class="message mb-3 d-flex {{ $isOwn ? 'justify-content-end' : 'justify-content-start' }}"
     data-message-id="{{ $messageId }}"
     data-from-me="{{ $isOwn ? '1' : '0' }}"
     data-read="{{ $isRead ? '1' : '0' }}"
     role="listitem"
     aria-label="Message from {{ $isOwn ? 'you' : ($message->sender->name ?? 'Unknown') }}">

  {{-- Message Bubble --}}
  <div class="message-bubble {{ $isOwn ? 'sent' : 'received' }}">
    {{-- Sender Name (for received messages) --}}
    @unless($isOwn)
      <small class="sender-name" aria-label="Sender: {{ $message->sender->name ?? $message->sender->phone ?? 'Unknown' }}">
        {{ $message->sender->name ?? $message->sender->phone ?? 'Unknown' }}
      </small>
    @endunless

    <div class="message-content">
      {{-- Reply Preview --}}
      @if($hasReply)
        <div class="reply-preview" aria-label="Replying to message">
          <small>
            <i class="bi bi-reply-fill me-1" aria-hidden="true"></i>
            Replying to: {{ Str::limit($message->replyTo->display_body ?? $message->replyTo->body ?? '[message]', 100) }}
          </small>
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
      <div class="message-text">
        @if($isEncrypted)
          <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>
          <span>Encrypted message</span>
        @else
          {!! Str::of(e($message->display_body ?? $message->body ?? ''))
                ->replaceMatches('/(https?:\/\/[^\s]+)/', fn($match) => 
                    '<a href="'.e($match[0]).'" target="_blank" class="linkify" rel="noopener noreferrer">'.e($match[0]).'</a>'
                ) !!}
        @endif
      </div>

      {{-- Attachments --}}
      @if($hasAttachments)
        @foreach($message->attachments as $file)
          @php
            $ext = strtolower(pathinfo($file->file_path, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
            $isVideo = in_array($ext, ['mp4','mov','avi','webm']);
            $fileUrl = Storage::url($file->file_path);
            $fileSize = $file->file_size ? round($file->file_size / 1024) : null;
          @endphp
          
          @if($isImage)
            <div class="mt-2">
              <img src="{{ $fileUrl }}" alt="Shared image" class="img-fluid rounded media-img" 
                   loading="lazy" data-src="{{ $fileUrl }}" data-bs-toggle="modal" 
                   data-bs-target="#imageModal" data-image-src="{{ $fileUrl }}"
                   width="220" height="220">
            </div>
          @elseif($isVideo)
            <div class="mt-2">
              <video controls class="img-fluid rounded media-video" preload="metadata"
                     data-src="{{ $fileUrl }}" style="max-width: 220px;">
                <source src="{{ $fileUrl }}" type="video/{{ $ext }}">
                Your browser does not support the video tag.
              </video>
            </div>
          @else
            <div class="mt-2">
              <a href="{{ $fileUrl }}" target="_blank" class="d-inline-flex align-items-center doc-link" 
                 rel="noopener noreferrer" download="{{ $file->original_name }}">
                <i class="bi bi-file-earmark me-1" aria-hidden="true"></i>
                <span class="text-truncate" style="max-width: 200px;">
                  {{ $file->original_name }}
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
        <div class="status-indicator" aria-label="Message status: {{ $message->status }}">
          @if($message->status === 'read')
            <i class="bi bi-check2-all text-primary" title="Read" data-bs-toggle="tooltip"></i>
          @elseif($message->status === 'delivered')
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
                title="{{ $reaction->user->name }} reacted with {{ $reaction->reaction }}"
                data-bs-toggle="tooltip">
            {{ $reaction->reaction }}
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
      <li role="none">
        <button class="dropdown-item d-flex align-items-center gap-2 reply-btn" 
                data-message-id="{{ $messageId }}" role="menuitem">
          <i class="bi bi-reply" aria-hidden="true"></i>
          <span>Reply</span>
        </button>
      </li>
      <li role="none">
        <button class="dropdown-item d-flex align-items-center gap-2 forward-btn" 
                data-message-id="{{ $messageId }}" role="menuitem">
          <i class="bi bi-forward" aria-hidden="true"></i>
          <span>Forward</span>
        </button>
      </li>
      @if($isOwn)
        <li role="none">
          <button class="dropdown-item d-flex align-items-center gap-2 text-danger delete-btn" 
                  data-message-id="{{ $messageId }}" role="menuitem">
            <i class="bi bi-trash" aria-hidden="true"></i>
            <span>Delete</span>
          </button>
        </li>
      @endif
      <li><hr class="dropdown-divider"></li>
      <li role="none">
        <div class="d-flex px-3 py-1 reaction-buttons" role="group" aria-label="Quick reactions">
          <button class="btn btn-sm reaction-btn" data-reaction="👍" title="Like" aria-label="Like">
            👍
          </button>
          <button class="btn btn-sm reaction-btn ms-1" data-reaction="❤️" title="Love" aria-label="Love">
            ❤️
          </button>
          <button class="btn btn-sm reaction-btn ms-1" data-reaction="😂" title="Laugh" aria-label="Laugh">
            😂
          </button>
          <button class="btn btn-sm reaction-btn ms-1" data-reaction="😮" title="Wow" aria-label="Wow">
            😮
          </button>
        </div>
      </li>
    </ul>
  </div>
</div>