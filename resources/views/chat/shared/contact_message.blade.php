@php
    $contactData = $message->contact_data ?? [];
    $displayName = $contactData['display_name'] ?? null;
    $phone = $contactData['phone'] ?? null;
    $email = $contactData['email'] ?? null;
    $contactUserId = $contactData['user_id'] ?? null;
    $sharedAt = $contactData['shared_at'] ?? $message->created_at;
    
    $isGekyChatUser = !empty($contactUserId);
    $currentUserId = auth()->id();
@endphp

@if($phone)
<div class="contact-message mt-2">
    <div class="contact-card rounded border bg-light" role="article" aria-label="Shared contact">
        <div class="contact-header p-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
                <div class="contact-avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                     style="width: 50px; height: 50px; font-size: 1.25rem; font-weight: 600;">
                    {{ substr($displayName ?? $phone, 0, 1) }}
                </div>
                <div class="contact-info flex-grow-1">
                    <h6 class="mb-1 fw-semibold text-dark">{{ $displayName ?? $phone }}</h6>
                    @if($isGekyChatUser)
                        <span class="badge bg-success rounded-pill">
                            <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>
                            GekyChat User
                        </span>
                    @else
                        <span class="badge bg-secondary rounded-pill">
                            <i class="bi bi-phone me-1" aria-hidden="true"></i>
                            Phone Contact
                        </span>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="contact-details p-3">
            @if($phone)
                <div class="contact-field d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-telephone-fill text-primary" aria-hidden="true"></i>
                    <span class="flex-grow-1">{{ $phone }}</span>
                    <div class="contact-actions">
                        <button class="btn btn-sm btn-outline-primary me-1" 
                                onclick="startChatWithPhone('{{ $phone }}')"
                                title="Start chat">
                            <i class="bi bi-chat" aria-hidden="true"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" 
                                onclick="copyToClipboard('{{ $phone }}', 'Phone number')"
                                title="Copy phone number">
                            <i class="bi bi-clipboard" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            @endif
            
            @if($email)
                <div class="contact-field d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-envelope-fill text-primary" aria-hidden="true"></i>
                    <span class="flex-grow-1">{{ $email }}</span>
                    <div class="contact-actions">
                        <a href="mailto:{{ $email }}" class="btn btn-sm btn-outline-primary me-1" title="Send email">
                            <i class="bi bi-send" aria-hidden="true"></i>
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" 
                                onclick="copyToClipboard('{{ $email }}', 'Email address')"
                                title="Copy email">
                            <i class="bi bi-clipboard" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            @endif
        </div>
        
        <div class="contact-footer p-2 border-top bg-light rounded-bottom">
            <small class="text-muted">
                <i class="bi bi-clock me-1" aria-hidden="true"></i>
                Shared {{ $sharedAt instanceof \Carbon\Carbon ? $sharedAt->diffForHumans() : \Carbon\Carbon::parse($sharedAt)->diffForHumans() }}
            </small>
            
            @if($isGekyChatUser && $contactUserId != $currentUserId)
                <button class="btn btn-sm btn-success ms-2" 
                        onclick="addGekyChatContact('{{ $contactUserId }}')">
                    <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Add to Contacts
                </button>
            @endif
        </div>
    </div>
</div>

<script>
function copyToClipboard(text, type) {
    navigator.clipboard.writeText(text).then(() => {
        showToast(`${type} copied to clipboard`);
    }).catch(() => {
        showToast(`Failed to copy ${type.toLowerCase()}`);
    });
}

function addGekyChatContact(userId) {
    // Implement adding user to contacts
    fetch('/contacts', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({ user_id: userId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Contact added successfully');
        } else {
            showToast(data.message || 'Failed to add contact');
        }
    })
    .catch(error => {
        console.error('Error adding contact:', error);
        showToast('Failed to add contact');
    });
}
</script>

<style>
.contact-card {
    transition: all 0.3s ease;
    max-width: 320px;
    border: 1px solid var(--border-color, #dee2e6) !important;
}

.contact-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.contact-avatar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.contact-field {
    padding: 8px;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.5);
}

.contact-actions .btn {
    padding: 4px 8px;
    border-radius: 4px;
}

[data-theme="dark"] .contact-card {
    background: var(--bs-dark-bg-subtle) !important;
    border-color: #444 !important;
}

[data-theme="dark"] .contact-header h6 {
    color: var(--bs-light);
}

[data-theme="dark"] .contact-field {
    background: rgba(255, 255, 255, 0.1);
}
</style>
@endif