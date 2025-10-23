@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    // Unified message data structure
    $isOwn = (int) $message->sender_id === (int) auth()->id();
    $messageId = $message->id;
    $hasAttachments = $message->attachments->isNotEmpty();
    $hasReactions = $message->reactions->isNotEmpty();
    $isForwarded = $message->forwarded_from_id ?? $message->is_forwarded ?? false;
    $hasReply = $message->reply_to && $message->replyTo;
    $isExpired = $message->expires_at ? $message->expires_at->isPast() : false;
    
    // Context-aware variables
    $senderName = $message->sender->name ?? $message->sender->phone ?? 'Unknown User';
    $body = $message->body ?? '';
    $isEncrypted = $message->is_encrypted ?? false;
    
    // Permissions (context-dependent)
    $canEdit = $isOwn || ($isGroup ?? false ? ($isOwner ?? false || $userRole === 'admin') : true);
    $canDelete = $isOwn || ($isGroup ?? false ? ($isOwner ?? false || $userRole === 'admin') : true);
    
    // Action URLs (context-dependent)
    $deleteUrl = $isGroup ?? false ? 
        route('groups.messages.delete', ['group' => $group, 'message' => $message]) : 
        "/messages/{$messageId}";
    
    $editUrl = $isGroup ?? false ? 
        route('groups.messages.update', ['group' => $group, 'message' => $message]) : 
        "/messages/{$messageId}";
    
    $reactUrl = $isGroup ?? false ? 
        route('groups.messages.reactions', ['group' => $group, 'message' => $message]) : 
        "/messages/react";
@endphp

@unless($isExpired)
<div class="message mb-3 d-flex {{ $isOwn ? 'justify-content-end' : 'justify-content-start' }}"
     data-message-id="{{ $messageId }}"
     data-from-me="{{ $isOwn ? '1' : '0' }}"
     data-read="{{ $message->read_at ? '1' : '0' }}"
     role="listitem"
     aria-label="Message from {{ $isOwn ? 'you' : $senderName }}">

    {{-- Message Bubble --}}
    <div class="message-bubble {{ $isOwn ? 'sent' : 'received' }}">
        {{-- Sender Name (for group messages or when configured) --}}
        @if((!$isOwn && ($showSenderNames ?? false)) || (!$isOwn && ($isGroup ?? false)))
            <small class="sender-name {{ $message->sender->pivot->role ?? '' }}" 
                   aria-label="Sender: {{ $senderName }}">
                {{ $senderName }}
            </small>
        @endif

        <div class="message-content">
            {{-- Reply Preview --}}
            @if($hasReply)
                <div class="reply-preview" aria-label="Replying to message">
                    <small>
                        <i class="bi bi-reply-fill me-1" aria-hidden="true"></i>
                        Replying to: {{ Str::limit($message->replyTo->body ?? '[message]', 80) }}
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

            {{-- Attachments (shared logic) --}}
            @if($hasAttachments)
                @foreach($message->attachments as $file)
                    @include('chat.partials.attachment', ['file' => $file])
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
            @if($isOwn && !($isGroup ?? false))
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

    {{-- Message Actions (shared component) --}}
    @include('chat.partials.message_actions', [
        'messageId' => $messageId,
        'isOwn' => $isOwn,
        'canEdit' => $canEdit,
        'canDelete' => $canDelete,
        'body' => $body,
        'deleteUrl' => $deleteUrl,
        'editUrl' => $editUrl,
        'reactUrl' => $reactUrl,
        'isGroup' => $isGroup ?? false
    ])
</div>
@endunless