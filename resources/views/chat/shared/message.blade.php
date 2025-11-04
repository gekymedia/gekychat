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

    // === SENDER INFORMATION ===
    $senderName = $message->sender->name ?? ($message->sender->phone ?? 'Unknown User');
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
    if (!empty(trim($body)) && (!$isEncrypted || $isOwn)) {
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

            // Apply markdown formatting
            $processedBody = \App\Helpers\MessageHelper::applyMarkdownFormatting($processedBody);
        }
    }

    // Link previews
    $linkPreviews = $message->link_previews ?? [];
@endphp

@unless ($isExpired)
    <div class="message mb-3 d-flex {{ $isOwn ? 'justify-content-end' : 'justify-content-start' }} position-relative"
        data-message-id="{{ $messageId }}" data-context="{{ $context }}" data-from-me="{{ $isOwn ? '1' : '0' }}"
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

                {{-- Message Text --}}
                @if (!empty(trim($body)) && (!$isEncrypted || $isOwn))
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

                {{-- Attachments --}}
                @if ($hasAttachments)
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

                {{-- Location Sharing --}}
                @if ($message->location_data)
                    @include('chat.shared.location_message', ['message' => $message])
                @endif

                {{-- Contact Sharing --}}
                @if ($message->contact_data)
                    @include('chat.shared.contact_message', ['message' => $message])
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
                    @endphp
                    <div class="status-indicator" aria-label="Message status: {{ $status }}">
                        @if ($status === 'read')
                            <i class="bi bi-check2-all text-primary" title="Read" data-bs-toggle="tooltip"></i>
                        @elseif ($status === 'delivered')
                            <i class="bi bi-check2-all muted" title="Delivered" data-bs-toggle="tooltip"></i>
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
                fetch('/api/start-chat-with-phone', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({
                            phone: phone
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.redirect_url;
                        } else {
                            alert('Failed to start chat: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error starting chat:', error);
                        alert('Failed to start chat');
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
            });

            // Fallback initialization if ChatCore not used
            if (!window.ChatCore) {
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('🎯 Initializing message interactions (ChatCore not detected)');
                    initializeMessageInteractions();
                });
            }

            // Export functions for global access (if needed in other components)
            window.messageUtils = {
                normalizePhoneNumber,
                handlePhoneClick,
                scrollToMessage,
                highlightMessage
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
        </style>
    @endpush
@endunless
