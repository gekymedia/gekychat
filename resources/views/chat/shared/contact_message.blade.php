@php
    $contactData = $message->contact_data ?? [];
    
    // Handle empty or null contact data
    if (empty($contactData)) {
        $contactData = [];
    }
    
    // Each message now contains a single contact (we send separate messages for multiple contacts)
    $displayName = $contactData['display_name'] ?? $contactData['phone'] ?? 'Unknown Contact';
    $phone = $contactData['phone'] ?? null;
    $email = $contactData['email'] ?? null;
    $contactUserId = $contactData['user_id'] ?? null;
    $sharedAt = $contactData['shared_at'] ?? $message->created_at;
    
    $isGekyChatUser = !empty($contactUserId);
    $currentUserId = auth()->id();
    $canChat = $isGekyChatUser && $contactUserId != $currentUserId;
    
    // Get user avatar if available
    $userAvatar = null;
    if ($contactUserId) {
        $contactUser = \App\Models\User::find($contactUserId);
        $userAvatar = $contactUser?->avatar_url ?? null;
    }
    
    $initial = strtoupper(substr($displayName, 0, 1));
@endphp

@if(!empty($contactData) && ($phone || !empty($contactData)))
<div class="contact-message mt-2">
    <div class="contact-card-wa rounded" role="article" aria-label="Shared contact">
        {{-- Contact Header --}}
        <div class="contact-header-wa p-3">
            <div class="d-flex align-items-center gap-3">
                {{-- Avatar --}}
                <div class="contact-avatar-wrapper position-relative">
                    @if($userAvatar)
                        <img src="{{ $userAvatar }}" 
                             class="contact-avatar-img rounded-circle" 
                             alt="{{ $displayName }}"
                             style="width: 50px; height: 50px; object-fit: cover;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    @endif
                    <div class="contact-avatar rounded-circle d-flex align-items-center justify-content-center {{ $userAvatar ? 'd-none' : '' }}" 
                         style="width: 50px; height: 50px; font-size: 1.25rem; font-weight: 600; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        {{ $initial }}
                    </div>
                </div>
                
                {{-- Contact Info --}}
                <div class="contact-info-wa flex-grow-1">
                    <h6 class="mb-1 fw-semibold contact-name">{{ $displayName }}</h6>
                    @if($isGekyChatUser)
                        <small class="text-muted d-block">
                            <i class="bi bi-check-circle-fill text-success me-1" style="font-size: 0.75rem;"></i>
                            GekyChat
                        </small>
                    @else
                        <small class="text-muted d-block">
                            <i class="bi bi-phone me-1" style="font-size: 0.75rem;"></i>
                            Phone Contact
                        </small>
                    @endif
                </div>
            </div>
        </div>
        
        {{-- Contact Details --}}
        <div class="contact-details-wa p-3 border-top">
            @if($phone)
                <div class="contact-field-wa d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-telephone-fill contact-icon" aria-hidden="true"></i>
                    <span class="flex-grow-1 contact-value">{{ $phone }}</span>
                    <button class="btn btn-sm btn-link p-0 text-muted" 
                            onclick="copyToClipboard('{{ $phone }}', 'Phone number')"
                            title="Copy phone number"
                            style="min-width: auto;">
                        <i class="bi bi-clipboard" aria-hidden="true"></i>
                    </button>
                </div>
            @endif
            
            @if($email)
                <div class="contact-field-wa d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-envelope-fill contact-icon" aria-hidden="true"></i>
                    <span class="flex-grow-1 contact-value">{{ $email }}</span>
                    <a href="mailto:{{ $email }}" class="btn btn-sm btn-link p-0 text-muted" title="Send email" style="min-width: auto;">
                        <i class="bi bi-send" aria-hidden="true"></i>
                    </a>
                    <button class="btn btn-sm btn-link p-0 text-muted" 
                            onclick="copyToClipboard('{{ $email }}', 'Email address')"
                            title="Copy email"
                            style="min-width: auto;">
                        <i class="bi bi-clipboard" aria-hidden="true"></i>
                    </button>
                </div>
            @endif
        </div>
        
        {{-- Chat Button (if GekyChat user) --}}
        @if($canChat)
            <div class="contact-footer-wa p-2 border-top">
                <button onclick="startChatWithContact('{{ $contactUserId }}', '{{ $phone }}')" 
                        class="btn btn-wa btn-sm w-100 d-flex align-items-center justify-content-center gap-2"
                        style="background: var(--wa-green); color: white; border: none;">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span>Chat</span>
                </button>
            </div>
        @endif
    </div>
</div>

<script>
function copyToClipboard(text, type) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            if (typeof showToast === 'function') {
                showToast(`${type} copied to clipboard`, 'success');
            } else {
                alert(`${type} copied to clipboard`);
            }
        }).catch(() => {
            if (typeof showToast === 'function') {
                showToast(`Failed to copy ${type.toLowerCase()}`, 'error');
            }
        });
    } else {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            if (typeof showToast === 'function') {
                showToast(`${type} copied to clipboard`, 'success');
            } else {
                alert(`${type} copied to clipboard`);
            }
        } catch (err) {
            if (typeof showToast === 'function') {
                showToast(`Failed to copy ${type.toLowerCase()}`, 'error');
            }
        }
        document.body.removeChild(textarea);
    }
}

function startChatWithContact(userId, phone) {
    // Try to use existing startChatWithPhone function if available
    if (typeof window.sidebarApp !== 'undefined' && typeof window.sidebarApp.startChatWithPhone === 'function') {
        window.sidebarApp.startChatWithPhone(phone);
        return;
    }
    
    if (typeof startChatWithPhone === 'function') {
        startChatWithPhone(phone);
        return;
    }
    
    // Fallback: use API to start chat
    fetch('/start-chat-with-phone', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ phone: phone })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect to the conversation
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
            } else if (data.conversation && data.conversation.slug) {
                window.location.href = `/c/chat-${data.conversation.slug}`;
            } else if (data.conversation_id) {
                window.location.href = `/c/chat-${data.conversation_slug || data.conversation_id}`;
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
</script>

<style>
.contact-card-wa {
    max-width: 320px;
    background: var(--card);
    border: 1px solid var(--border);
    transition: all 0.2s ease;
    overflow: hidden;
}

.contact-card-wa:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.contact-header-wa {
    background: var(--card);
}

.contact-name {
    color: var(--text);
    font-size: 1rem;
}

.contact-details-wa {
    background: var(--card);
}

.contact-field-wa {
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
}

.contact-field-wa:last-child {
    border-bottom: none;
}

.contact-icon {
    color: var(--wa-green);
    font-size: 1rem;
    width: 20px;
    text-align: center;
}

.contact-value {
    color: var(--text);
    font-size: 0.9rem;
}

.contact-footer-wa {
    background: var(--card);
}

.contact-avatar-wrapper {
    flex-shrink: 0;
}

.contact-avatar-img {
    border: 2px solid var(--border);
}

[data-theme="dark"] .contact-card-wa {
    background: var(--card);
    border-color: var(--border);
}

[data-theme="dark"] .contact-header-wa,
[data-theme="dark"] .contact-details-wa,
[data-theme="dark"] .contact-footer-wa {
    background: var(--card);
}

[data-theme="dark"] .contact-name {
    color: var(--text);
}

[data-theme="dark"] .contact-value {
    color: var(--text-muted);
}

/* WhatsApp green button */
.btn-wa {
    background: var(--wa-green) !important;
    color: white !important;
    border: none !important;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-wa:hover {
    background: #2dbd8a !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(37, 211, 102, 0.3);
}

.btn-wa:active {
    transform: translateY(0);
}
</style>
@endif
