{{-- resources/views/chat/shared/message.blade.php --}}
@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    // === CORE MESSAGE DATA ===
    $isOwn = (int) $message->sender_id === (int) auth()->id();
    $messageId = $message->id;
    $isRead = $message->is_read ?? false;


    // === CONTEXT CONFIGURATION ===
    $isGroup = $isGroup ?? false;
    $showSenderNames = $showSenderNames ?? $isGroup;
    $context = $isGroup ? 'group' : 'direct';

    // === MESSAGE CONTENT FLAGS ===
    $hasAttachments = $message->attachments->isNotEmpty();
    $hasReactions = $message->reactions->isNotEmpty();
    $isForwarded = $message->forwarded_from_id ?? ($message->is_forwarded ?? false);
    $hasReply = $message->reply_to && $message->replyTo;
    $isExpired = $message->expires_at ? $message->expires_at->isPast() : false;
    $isViewOnce = (bool)($message->is_view_once ?? false);
    $viewOnceOpened = (bool)($message->viewed_at !== null);

    // === SENDER INFORMATION ===
    // For channels: Hide admin sender name and show channel name instead
    $isChannel = isset($group) && $group->type === 'channel';
    $senderIsAdmin = false;
    if ($isChannel && $isGroup && isset($group)) {
        $senderIsAdmin = $group->isAdmin($message->sender_id);
    }
    
    // Use channel name if it's a channel and sender is admin
    $senderName = ($isChannel && $senderIsAdmin && !$isOwn) 
        ? ($group->name ?? 'Channel')
        : ($message->sender->name ?? ($message->sender->phone ?? 'Unknown User'));
    $body = $message->body ?? '';
    $isEncrypted = $message->is_encrypted ?? false;

    // === REPLY DATA (FIXED) ===
    $replyMessage = $hasReply ? $message->replyTo : null;
    $replySenderName = $replyMessage
        ? $replyMessage->sender->name ?? ($replyMessage->sender->phone ?? 'Unknown User')
        : null;
    $replyBody = $replyMessage ? $replyMessage->body ?? '' : null;
    $replyHasAttachments = $replyMessage ? $replyMessage->attachments->isNotEmpty() : false;
    $replyIsOwn = $replyMessage ? (int) $replyMessage->sender_id === (int) auth()->id() : false;

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
        $reactUrl = '/messages/react';
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

    // === PROCESS MESSAGE CONTENT ===
    $processedBody = '';
    $isGifMessage = false;
    
    // Check if message is a GIF URL
    $trimmedBody = trim($body);
    if (preg_match('/^https?:\/\/[^\s]+\.(gif|webp)(\?[^\s]*)?$/i', $trimmedBody) ||
        preg_match('/^https?:\/\/(media\d*\.giphy\.com|i\.giphy\.com)/i', $trimmedBody)) {
        $isGifMessage = true;
        $processedBody = '<img src="' . e($trimmedBody) . '" class="img-fluid rounded gif-message" alt="GIF" style="max-width: 300px; max-height: 300px; cursor: pointer;" onclick="window.open(\'' . e($trimmedBody) . '\', \'_blank\')">';
    }
    
    if (!$isGifMessage && !empty(trim($body)) && (!$isEncrypted || $isOwn)) {
        if ($isEncrypted && !$isOwn) {
            $processedBody = '<i class="bi bi-lock-fill me-1" aria-hidden="true"></i><span>Encrypted message</span>';
        } else {
            // Process URLs, emails, and phone numbers
            $processedBody = preg_replace_callback(
                '/(https?:\/\/[^\s]+)/',
                function ($match) {
                    return '<a href="' .
                        e($match[0]) .
                        '" target="_blank" class="linkify" rel="noopener noreferrer">' .
                        e($match[0]) .
                        '</a>';
                },
                e($body),
            );

            $processedBody = preg_replace_callback(
                '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/',
                function ($match) {
                    return '<a href="mailto:' . e($match[0]) . '" class="email-link">' . e($match[0]) . '</a>';
                },
                $processedBody,
            );

            $processedBody = preg_replace_callback(
                '/(?:\+?233|0)?([1-9]\d{8})/',
                function ($match) {
                    $fullMatch = $match[0];
                    $cleanNumber = $match[1] ?? $match[0];
                    $normalizedPhone = \App\Helpers\MessageHelper::normalizePhoneNumber($fullMatch);
                    return '<a href="#" class="phone-link" data-phone="' .
                        e($normalizedPhone) .
                        '" onclick="handlePhoneClick(\'' .
                        e($normalizedPhone) .
                        '\'); return false;">' .
                        e($fullMatch) .
                        '</a>';
                },
                $processedBody,
            );

            // Process group reference links (for reply privately messages)
            $metadata = $message->metadata ?? [];
            if (isset($metadata['group_reference']) && !empty($metadata['group_reference']['group_slug'])) {
                $groupName = e($metadata['group_reference']['group_name']);
                $groupSlug = e($metadata['group_reference']['group_slug']);
                $groupUrl = route('groups.show', $groupSlug);
                
                // Replace "in {group_name}:" with clickable link
                $processedBody = preg_replace_callback(
                    '/in (' . preg_quote($groupName, '/') . '):/',
                    function ($match) use ($groupName, $groupUrl) {
                        return 'in <a href="' . $groupUrl . '" class="group-reference-link text-primary fw-semibold" title="Go to group">' . $groupName . '</a>:';
                    },
                    $processedBody,
                );
            }

            // Apply markdown formatting
            $processedBody = \App\Helpers\MessageHelper::applyMarkdownFormatting($processedBody);
        }
    }

    // Link previews
    $linkPreviews = $message->link_previews ?? [];
@endphp

@unless ($isExpired)
    <div class="message mb-3 d-flex {{ $isOwn ? 'justify-content-end' : 'justify-content-start' }} position-relative"
        data-message-id="{{ $messageId }}" data-message-date="{{ $message->created_at->toIso8601String() }}" data-context="{{ $context }}" data-from-me="{{ $isOwn ? '1' : '0' }}"
        data-read="{{ $isRead ? '1' : '0' }}" data-sender-role="{{ $senderRoleClass }}" role="listitem"
        aria-label="Message from {{ $isOwn ? 'you' : $senderName }}">

        {{-- Message Bubble --}}
        <div class="message-bubble {{ $isOwn ? 'sent' : 'received' }} {{ $senderRoleClass }} position-relative">
            {{-- Sender Name (for groups and when configured) --}}
            @if (!$isOwn && $showSenderNames)
                <small class="sender-name {{ $senderRoleClass }}" aria-label="Sender: {{ $senderName }}">
                    {{ $senderName }}
                    @if ($isGroup && $senderRoleClass)
                        <span class="role-badge {{ $senderRoleClass }}" title="{{ ucfirst($senderRoleClass) }}">
                            @if ($senderRoleClass === 'owner')
                                <i class="bi bi-star-fill" aria-hidden="true"></i>
                            @elseif($senderRoleClass === 'admin')
                                <i class="bi bi-shield-fill" aria-hidden="true"></i>
                            @endif
                        </span>
                    @endif
                </small>
            @endif

            <div class="message-content">
                {{-- Reply Preview --}}
                @if ($hasReply && $replyMessage)
                    <div class="reply-preview mb-2 p-2 rounded border-start border-3 border-primary bg-light" role="button"
                        tabindex="0" data-reply-to="{{ $replyMessage->id }}"
                        aria-label="Replying to message from {{ $replyIsOwn ? 'you' : $replySenderName }}: {{ Str::limit($replyBody, 100) }}">
                        <div class="d-flex align-items-center mb-1">
                            <i class="bi bi-reply-fill me-1 text-primary" aria-hidden="true"></i>
                            <small class="fw-semibold text-primary">
                                {{ $replyIsOwn ? 'You' : $replySenderName }}
                            </small>
                        </div>

                        {{-- Reply Message Content --}}
                        <div class="reply-content">
                            @if ($replyHasAttachments)
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
                @if ($isForwarded)
                    <div class="forwarded-header mb-1">
                        <small class="muted">
                            <i class="bi bi-forward-fill me-1" aria-hidden="true"></i>
                            Forwarded
                        </small>
                    </div>
                @endif

                {{-- Message Text (hide if location, contact, call, poll, or view_once) --}}
                @if (!empty(trim($body)) && (!$isEncrypted || $isOwn) && !$message->location_data && !$message->contact_data && !$message->call_data && (($message->type ?? '') !== 'poll') && !$isViewOnce)
                    <div class="message-text">
                        {!! $processedBody !!}
                    </div>
                @endif

                {{-- Link Previews --}}
                @if (count($linkPreviews) > 0)
                    <div class="link-previews-container mt-2">
                        @foreach ($linkPreviews as $preview)
                            <div class="link-preview-card rounded border bg-light" role="article"
                                aria-label="Link preview for {{ $preview['title'] ?? $preview['url'] }}">

                                @if ($preview['image'] ?? false)
                                    <div class="link-preview-image position-relative">
                                        <img src="{{ $preview['image'] }}"
                                            alt="{{ $preview['title'] ?? 'Preview image' }}" class="img-fluid rounded-top"
                                            loading="lazy" onerror="this.style.display='none'">
                                        <div class="image-overlay"></div>
                                    </div>
                                @endif

                                <div class="link-preview-content p-2 position-relative">
                                    @if ($preview['site_name'] ?? false)
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-globe me-1"></i>
                                            {{ $preview['site_name'] }}
                                        </small>
                                    @endif

                                    @if ($preview['title'] ?? false)
                                        <h6 class="mb-1 fw-semibold text-dark">{{ Str::limit($preview['title'], 70) }}</h6>
                                    @endif

                                    @if ($preview['description'] ?? false)
                                        <p class="mb-1 text-muted small lh-sm">
                                            {{ Str::limit($preview['description'], 120) }}</p>
                                    @endif

                                    <small class="text-primary">
                                        {{ parse_url($preview['url'], PHP_URL_HOST) }}
                                    </small>

                                    <a href="{{ $preview['url'] }}" target="_blank" rel="noopener noreferrer"
                                        class="stretched-link"
                                        aria-label="Visit website: {{ $preview['title'] ?? $preview['url'] }}">
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Attachments (Hide inline for view_once; handle via modal or custom UI) --}}
                @if ($hasAttachments && !$isViewOnce)
                    <div class="attachments-container mt-2">
                        @foreach ($message->attachments as $attachment)
                            @include('chat.shared.attachment', [
                                'attachment' => $attachment,
                                'message' => $message,
                                'isOwn' => $isOwn,
                            ])
                        @endforeach
                    </div>
                @endif
                
                {{-- View Once Message UI --}}
                @if ($isViewOnce)
                    <div class="view-once-container my-1 p-2 rounded border view-once-btn {{ $viewOnceOpened && !$isOwn ? 'opened' : '' }}" 
                         data-message-id="{{ $messageId }}" 
                         data-is-own="{{ $isOwn ? 'true' : 'false' }}"
                         role="button" tabindex="0">
                        <div class="d-flex align-items-center gap-2">
                            <div class="view-once-icon-wrapper rounded-circle bg-wa-green bg-opacity-10 p-2 text-wa-green d-flex justify-content-center align-items-center" style="width: 36px; height: 36px;">
                                @if($viewOnceOpened && !$isOwn)
                                    <i class="bi bi-envelope-open" style="font-size: 1.1rem;"></i>
                                @elseif($hasAttachments)
                                    @php
                                        $isVideo = false;
                                        $mimeTypeLower = strtolower($message->attachments->first()->mime_type ?? '');
                                        if (str_contains($mimeTypeLower, 'video/')) {
                                            $isVideo = true;
                                        }
                                    @endphp
                                    <i class="bi {{ $isVideo ? 'bi-play-circle' : 'bi-image' }}" style="font-size: 1.1rem;"></i>
                                    <span class="position-absolute translate-middle badge rounded-pill bg-wa-green text-white" style="font-size: 0.5rem; top: 25%; left: 75%;">1</span>
                                @else
                                    <span class="view-once-text-icon fw-bold" style="font-size: 0.9rem;">1</span>
                                @endif
                            </div>
                            <div class="view-once-info">
                                <span class="d-block fw-semibold" style="line-height: 1.2;">
                                    @if($hasAttachments)
                                        {{ $isVideo ? 'Video' : 'Photo' }}
                                    @else
                                        View once message
                                    @endif
                                </span>
                                <small class="text-muted" style="font-size: 0.75rem;">
                                    {{ $viewOnceOpened && !$isOwn ? 'Opened' : 'Click to view' }}
                                </small>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Location Sharing --}}
                @if ($message->location_data)
                    @include('chat.shared.location_message', ['message' => $message])
                @endif

                {{-- Contact Sharing --}}
                @if ($message->contact_data)
                    @include('chat.shared.contact_message', ['message' => $message])
                @endif

                {{-- Call Message --}}
                @if ($message->call_data)
                    @include('chat.shared.call_message', ['message' => $message])
                @endif

                {{-- Poll --}}
                @if (($message->type ?? '') === 'poll')
                    @include('chat.shared.poll_message', ['message' => $message, 'isGroup' => $isGroup])
                @endif
            </div>

            {{-- Message Footer --}}
            <div class="message-footer d-flex justify-content-between align-items-center mt-1">
                <small class="muted message-time">
                    <time datetime="{{ $message->created_at->toIso8601String() }}">
                        {{ $message->created_at->format('h:i A') }}
                    </time>
                </small>

                @if ($isOwn && !$isGroup)
                    @php
                        $status = $message->status ?? 'sent';
                        $clientUuid = $message->client_uuid ?? null;
                    @endphp
                    <div class="status-indicator" 
                         aria-label="Message status: {{ $status }}"
                         @if($clientUuid) data-client-uuid="{{ $clientUuid }}" @endif
                         data-message-id="{{ $messageId }}">
                        @if ($status === 'read')
                            <i class="bi bi-check2-all text-primary" title="Read" data-bs-toggle="tooltip"></i>
                        @elseif ($status === 'delivered')
                            <i class="bi bi-check2-all muted" title="Delivered" data-bs-toggle="tooltip"></i>
                        @elseif ($status === 'sent')
                            <i class="bi bi-check2 muted" title="Sent" data-bs-toggle="tooltip"></i>
                        @elseif ($status === 'pending')
                            <i class="bi bi-clock text-muted" title="Pending" data-bs-toggle="tooltip"></i>
                        @elseif ($status === 'sending')
                            <i class="bi bi-arrow-up-circle text-muted" title="Sending..." data-bs-toggle="tooltip"></i>
                        @elseif ($status === 'failed')
                            <i class="bi bi-x-circle text-danger" title="Failed to send" data-bs-toggle="tooltip"></i>
                        @else
                            <i class="bi bi-check2 muted" title="Sent" data-bs-toggle="tooltip"></i>
                        @endif
                    </div>
                @endif

            </div>

            {{-- Reactions --}}
            @if ($hasReactions)
                <div class="reactions-container mt-1" aria-label="Reactions">
                    @foreach ($message->reactions as $reaction)
                        @php
                            $isOwnReaction = $reaction->user_id === auth()->id();
                            $emoji = $reaction->emoji ?? ($reaction->reaction ?? '👍');
                            $userName = $reaction->user->name ?? 'User';
                        @endphp
                        <span class="badge bg-reaction rounded-pill me-1 {{ $isOwnReaction ? 'own-reaction' : '' }}"
                            title="{{ $userName }} reacted with {{ $emoji }}" data-bs-toggle="tooltip">
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
            'group' => $group ?? null,
        ])
    </div>

    @push('scripts')
        <script>
            // Phone number handling functions (keep these as utilities)
            function normalizePhoneNumber(phone) {
                let cleaned = phone.replace(/[^\d+]/g, '');

                if (cleaned.startsWith('233')) {
                    return '+' + cleaned;
                } else if (cleaned.startsWith('0')) {
                    return '+233' + cleaned.substring(1);
                } else if (cleaned.length === 9 && !cleaned.startsWith('0')) {
                    return '+233' + cleaned;
                }

                return cleaned;
            }

            function handlePhoneClick(phone) {
                showPhoneActionMenu(phone);
            }

            function showPhoneActionMenu(phone) {
                // Remove existing menu
                const existingMenu = document.getElementById('phone-action-menu');
                if (existingMenu) {
                    existingMenu.remove();
                }

                // Create new menu
                const menu = document.createElement('div');
                menu.id = 'phone-action-menu';
                menu.className = 'dropdown-menu show';
                menu.style.position = 'absolute';
                menu.style.zIndex = '9999';
                menu.innerHTML = `
    <button class="dropdown-item" onclick="startChatWithPhone('${phone}')">
      <i class="bi bi-chat me-2"></i>Chat with ${phone}
    </button>
    <button class="dropdown-item" onclick="inviteToGekyChat('${phone}')">
      <i class="bi bi-person-plus me-2"></i>Invite to GekyChat
    </button>
    <button class="dropdown-item" onclick="copyPhoneNumber('${phone}')">
      <i class="bi bi-clipboard me-2"></i>Copy number
    </button>
  `;

                document.body.appendChild(menu);

                // Position near click (simplified - you might want to improve this)
                menu.style.top = '50%';
                menu.style.left = '50%';
                menu.style.transform = 'translate(-50%, -50%)';

                // Close menu when clicking outside
                setTimeout(() => {
                    document.addEventListener('click', function closeMenu() {
                        menu.remove();
                        document.removeEventListener('click', closeMenu);
                    });
                }, 100);
            }

            function startChatWithPhone(phone) {
                // Try to use existing startChatWithPhone function from sidebar if available
                if (typeof window.sidebarApp !== 'undefined' && typeof window.sidebarApp.startChatWithPhone === 'function') {
                    window.sidebarApp.startChatWithPhone(phone);
                    return;
                }
                
                // Fallback: use the route directly
                fetch('/start-chat-with-phone', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            phone: phone
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(err => {
                                throw new Error(err.message || 'Failed to start chat');
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            if (data.not_registered) {
                                // Show invite modal if user not registered
                                if (typeof showInviteModal === 'function') {
                                    showInviteModal(phone, data.invite);
                                } else {
                                    alert('This user is not registered on GekyChat. Invite them to join!');
                                }
                            } else if (data.redirect_url) {
                                window.location.href = data.redirect_url;
                            } else if (data.conversation && data.conversation.slug) {
                                window.location.href = `/c/chat-${data.conversation.slug}`;
                            } else {
                                throw new Error('Conversation URL not found');
                            }
                        } else {
                            throw new Error(data.message || 'Failed to start chat');
                        }
                    })
                    .catch(error => {
                        console.error('Error starting chat:', error);
                        if (typeof showToast === 'function') {
                            showToast(error.message || 'Failed to start chat', 'error');
                        } else {
                            alert(error.message || 'Failed to start chat');
                        }
                    });
            }

            function inviteToGekyChat(phone) {
                const message = `Join me on GekyChat! Download the app to start chatting.`;

                if (/iPhone|iPad|iPod|Android/i.test(navigator.userAgent)) {
                    window.open(`whatsapp://send?text=${encodeURIComponent(message)}&phone=${phone}`);
                } else {
                    navigator.clipboard.writeText(message).then(() => {
                        showToast('Invitation message copied to clipboard!');
                    }).catch(() => {
                        showToast('Please copy the invitation manually: ' + message);
                    });
                }
            }

            function copyPhoneNumber(phone) {
                navigator.clipboard.writeText(phone).then(() => {
                    showToast('Phone number copied to clipboard');
                }).catch(() => {
                    showToast('Failed to copy phone number');
                });
            }

            function showToast(message) {
                // Simple toast implementation
                const toast = document.createElement('div');
                toast.className = 'alert alert-success position-fixed';
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; padding: 12px 16px;';
                toast.textContent = message;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.remove();
                }, 2000);
            }

            // ChatCore-integrated event handlers
            function initializeMessageInteractions() {
                // Handle reply preview click to scroll to original message
                document.addEventListener('click', function(e) {
                    const replyPreview = e.target.closest('.reply-preview');
                    if (replyPreview) {
                        const replyToId = replyPreview.getAttribute('data-reply-to');
                        scrollToMessage(replyToId);
                        return;
                    }

                    // Handle link preview clicks
                    const linkPreview = e.target.closest('.link-preview-card');
                    if (linkPreview && !e.target.closest('.btn')) {
                        const link = linkPreview.querySelector('a.stretched-link');
                        if (link) {
                            window.open(link.href, '_blank', 'noopener,noreferrer');
                        }
                        return;
                    }
                });

                // Add keyboard support for reply previews
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        const activeElement = document.activeElement;
                        const replyPreview = activeElement.closest('.reply-preview');
                        if (replyPreview) {
                            e.preventDefault();
                            const replyToId = replyPreview.getAttribute('data-reply-to');
                            scrollToMessage(replyToId);
                        }

                        const linkPreview = activeElement.closest('.link-preview-card');
                        if (linkPreview) {
                            e.preventDefault();
                            const link = linkPreview.querySelector('a.stretched-link');
                            if (link) {
                                window.open(link.href, '_blank', 'noopener,noreferrer');
                            }
                        }
                    }
                });

                // Initialize lazy loading for images
                initializeLazyLoading();
            }

            function scrollToMessage(messageId) {
                const originalMessage = document.querySelector(`[data-message-id="${messageId}"]`);

                if (originalMessage) {
                    // Use ChatCore's scroll method if available, otherwise fallback
                    if (window.chatInstance && typeof window.chatInstance.scrollToMessage === 'function') {
                        window.chatInstance.scrollToMessage(messageId);
                    } else {
                        // Fallback scrolling
                        originalMessage.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }

                    // Add highlight effect
                    highlightMessage(originalMessage);
                }
            }

            function highlightMessage(messageElement) {
                messageElement.style.transition = 'all 0.5s ease';
                messageElement.style.backgroundColor = 'var(--bs-warning-bg-subtle)';
                messageElement.style.border = '2px solid var(--bs-warning-border-subtle)';

                setTimeout(() => {
                    messageElement.style.backgroundColor = '';
                    messageElement.style.border = '';
                }, 2000);
            }
            
            // View Once Logic
            function initializeViewOnceHandlers() {
                document.querySelectorAll('.view-once-btn:not(.initialized)').forEach(btn => {
                    btn.classList.add('initialized');
                    
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const messageId = this.dataset.messageId;
                        const isOwn = this.dataset.isOwn === 'true';
                        
                        if (this.classList.contains('opened') && !isOwn) {
                            showToast('This message has already been viewed', 'info');
                            return;
                        }
                        
                        openViewOnceMessage(messageId, this);
                    });
                });
            }
            
            function openViewOnceMessage(messageId, btnElement) {
                // Determine if we need to call API or just show modal
                // Usually we'd fetch the decrypted/unblurred content via an API endpoint that records the view
                // For now, let's assume `window.openViewOnceModal` is defined globally or we dispatch an event
                
                // Show loading state
                const originalContent = btnElement.innerHTML;
                const iconWrapper = btnElement.querySelector('.view-once-icon-wrapper');
                if (iconWrapper) {
                    iconWrapper.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                }
                
                fetch(`/api/v1/messages/${messageId}/view-once`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        if (response.status === 403 || response.status === 410 || response.status === 404) {
                            throw new Error('This message is no longer available.');
                        }
                        throw new Error('Failed to open message');
                    }
                    return response.json();
                })
                .then(data => {
                    // Update UI to 'opened' state immediately if not own message
                    if (btnElement.dataset.isOwn !== 'true') {
                        btnElement.classList.add('opened');
                        btnElement.innerHTML = `
                            <div class="d-flex align-items-center gap-2 text-muted">
                                <div class="view-once-icon-wrapper rounded-circle bg-secondary bg-opacity-10 p-2 d-flex justify-content-center align-items-center" style="width: 36px; height: 36px;">
                                    <i class="bi bi-envelope-open" style="font-size: 1.1rem;"></i>
                                </div>
                                <div class="view-once-info">
                                    <span class="d-block fw-semibold" style="line-height: 1.2;">Opened</span>
                                </div>
                            </div>
                        `;
                    } else {
                        btnElement.innerHTML = originalContent;
                    }
                    
                    // Display the content in a secure fullscreen modal
                    showViewOnceContent(data);
                })
                .catch(err => {
                    console.error('View once error:', err);
                    showToast(err.message || 'Error opening message', 'error');
                    btnElement.innerHTML = originalContent;
                });
            }
            
            function showViewOnceContent(data) {
                // If a global handler exists, let it handle the UI
                if (typeof window.showGlobalViewOnceModal === 'function') {
                    window.showGlobalViewOnceModal(data);
                    return;
                }
                
                // Fallback implementation: create a temporary full-screen overlay
                const overlay = document.createElement('div');
                overlay.className = 'view-once-fullscreen-overlay d-flex flex-column justify-content-center align-items-center position-fixed w-100 h-100 top-0 start-0 bg-black text-white z-3';
                overlay.style.zIndex = '99999';
                
                // Block screenshots/recording (best effort)
                overlay.style.userSelect = 'none';
                
                // Close button
                const closeBtn = document.createElement('button');
                closeBtn.className = 'btn btn-link text-white position-absolute top-0 start-0 m-3 z-3';
                closeBtn.innerHTML = '<i class="bi bi-x-lg fs-4"></i>';
                closeBtn.onclick = () => overlay.remove();
                
                // Content area
                const contentArea = document.createElement('div');
                contentArea.className = 'view-once-content-area text-center position-relative w-100 h-100 d-flex flex-column justify-content-center align-items-center p-4';
                
                if (data.type === 'image' || data.attachment_url?.match(/\.(jpeg|jpg|gif|png|webp)$/i)) {
                    contentArea.innerHTML = `<img src="${data.attachment_url}" class="img-fluid" style="max-height: 80vh; max-width: 100%; object-fit: contain;">`;
                } else if (data.type === 'video' || data.attachment_url?.match(/\.(mp4|webm|ogg)$/i)) {
                    contentArea.innerHTML = `<video src="${data.attachment_url}" autoplay controls class="img-fluid" style="max-height: 80vh; max-width: 100%;"></video>`;
                } else if (data.body) {
                    contentArea.innerHTML = `<div class="fs-3">${data.body.replace(/\n/g, '<br>')}</div>`;
                } else {
                    contentArea.innerHTML = `<div><i class="bi bi-file-earmark fs-1 text-muted mb-3 d-block"></i><p>Unsupported view once format</p></div>`;
                }
                
                // View once warning
                const warning = document.createElement('div');
                warning.className = 'position-absolute bottom-0 mb-4 bg-dark bg-opacity-75 rounded-pill px-3 py-2 text-white small';
                warning.innerHTML = '<i class="bi bi-shield-lock me-1"></i> <span class="fw-bold">1</span> View Once Message';
                
                overlay.appendChild(closeBtn);
                overlay.appendChild(contentArea);
                overlay.appendChild(warning);
                document.body.appendChild(overlay);
                
                // Block print screen attempt
                document.addEventListener('keyup', (e) => {
                    if (e.key === 'PrintScreen') {
                        overlay.remove();
                        showToast('Screenshots are disabled for view once messages', 'error');
                    }
                });
            }

            function initializeLazyLoading() {
                if ('IntersectionObserver' in window) {
                    const imageObserver = new IntersectionObserver((entries, observer) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                const img = entry.target;
                                if (img.dataset.src) {
                                    img.src = img.dataset.src;
                                    img.classList.remove('lazy');
                                }
                                imageObserver.unobserve(img);
                            }
                        });
                    });

                    document.querySelectorAll('.link-preview-image img[data-src]').forEach(img => {
                        imageObserver.observe(img);
                    });
                }
            }

            // Initialize when ChatCore is ready or DOM is loaded
            document.addEventListener('chatcore:initialized', function() {
                console.log('🎯 ChatCore ready - initializing message interactions');
                initializeMessageInteractions();
                initializeViewOnceHandlers();
            });

            // Fallback initialization if ChatCore not used
            if (!window.ChatCore) {
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('🎯 Initializing message interactions (ChatCore not detected)');
                    initializeMessageInteractions();
                    initializeViewOnceHandlers();
                });
            }

            // Export functions for global access (if needed in other components)
            window.messageUtils = {
                normalizePhoneNumber,
                handlePhoneClick,
                scrollToMessage,
                highlightMessage,
                initializeViewOnceHandlers
            };
        </script>
    @endpush
    @push('styles')
        <style>
            /* Lazy loading styles */
            .link-preview-image img[data-src] {
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .link-preview-image img:not([data-src]) {
                opacity: 1;
            }

            /* Ensure phone links are clickable */
            .phone-link {
                cursor: pointer;
            }
            
            /* View Once styles */
            .view-once-btn {
                background-color: var(--card);
                transition: all 0.2s ease;
                min-width: 180px;
                cursor: pointer;
            }
            
            .view-once-btn:hover:not(.opened) {
                background-color: color-mix(in srgb, var(--wa-green) 5%, var(--card));
                border-color: color-mix(in srgb, var(--wa-green) 30%, var(--border)) !important;
            }
            
            .view-once-btn.opened {
                opacity: 0.7;
                cursor: default;
                border-style: dashed;
            }
            
            .view-once-btn.opened .view-once-icon-wrapper {
                background-color: color-mix(in srgb, var(--bs-secondary) 15%, transparent) !important;
                color: var(--bs-secondary) !important;
            }
            
            .bg-wa-green {
                background-color: var(--wa-green, #25d366);
            }
            
            .text-wa-green {
                color: var(--wa-green, #25d366);
            }
        </style>
    @endpush
@endunless
