{{-- WhatsApp-style peer info card for DM chats --}}
@php
    use App\Support\PhoneCountry;

    $peerUserId = $headerData['userId'] ?? null;
    $isDm = !empty($peerUserId) && empty($headerData['isGroup']) && empty($headerData['is_group']);
    $peerName = $headerData['name'] ?? __('Unknown');
    $peerPhone = $headerData['phone'] ?? null;
    $peerAvatar = $headerData['avatar'] ?? null;
    $peerUsername = $headerData['username'] ?? null;
    $isContact = false;
    if ($isDm) {
        try {
            $isContact = (bool) auth()->user()?->isContact($peerUserId);
        } catch (\Throwable $e) {
            $isContact = false;
        }
    }
    $country = PhoneCountry::fromPhone($peerPhone);
    $originCountry = $country ?: __('unknown region');
    $contactLabel = $isContact ? __('In your contacts') : __('Not a contact');
@endphp

@if($isDm)
<div id="chat-peer-info-card"
     class="chat-peer-info-card"
     data-user-id="{{ $peerUserId }}"
     data-is-contact="{{ $isContact ? '1' : '0' }}"
     data-phone="{{ e($peerPhone ?? '') }}"
     data-name="{{ e($peerName) }}">
    <div class="chat-peer-info-inner">
        <div class="chat-peer-info-avatar">
            @if($peerAvatar)
                <img src="{{ $peerAvatar }}" alt="{{ $peerName }}">
            @else
                <div class="chat-peer-info-avatar-fallback">
                    {{ strtoupper(substr($peerName, 0, 1)) }}
                </div>
            @endif
        </div>

        @if(!empty($peerUsername))
            <div class="chat-peer-info-handle">{{ '@' . ltrim($peerUsername, '@') }}</div>
            <div class="chat-peer-info-pushname">~{{ $peerName }}</div>
        @else
            <div class="chat-peer-info-handle">{{ $peerName }}</div>
        @endif

        <div class="chat-peer-info-meta" id="chat-peer-info-meta">
            {{ __('Phone number from') }}
            <strong>{{ $originCountry }}</strong>
            <span class="dot">·</span>
            <span id="chat-peer-contact-label">{{ $contactLabel }}</span>
            <span class="dot">·</span>
            <span id="chat-peer-groups-label">{{ __('No common groups') }}</span>
        </div>

        <button type="button" class="chat-peer-info-safety" id="chat-peer-safety-btn">
            <i class="bi bi-info-circle"></i> {{ __('Safety tools') }}
        </button>

        @unless($isContact)
            <button type="button" class="chat-peer-info-add" id="chat-peer-add-contact-btn">
                <i class="bi bi-person-plus"></i> {{ __('Add to contacts') }}
            </button>
        @endunless

        <button type="button" class="chat-peer-info-block" id="chat-peer-block-btn">
            <i class="bi bi-slash-circle"></i> {{ __('Block') }}
        </button>
    </div>
</div>

<style>
.chat-peer-info-card {
    margin: 12px 16px 8px;
    flex: 0 0 auto;
    position: static !important;
    z-index: 1;
}
.chat-peer-info-inner {
    background: var(--bs-tertiary-bg, #1f2c34);
    border-radius: 18px;
    padding: 22px 20px 16px;
    text-align: center;
}
[data-bs-theme="light"] .chat-peer-info-inner,
body:not(.dark-mode) .chat-peer-info-inner {
    background: #f0f2f5;
}
.chat-peer-info-avatar {
    width: 80px;
    height: 80px;
    margin: 0 auto 14px;
    border-radius: 50%;
    overflow: hidden;
}
.chat-peer-info-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.chat-peer-info-avatar-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #1565c0;
    color: #fff;
    font-size: 1.75rem;
    font-weight: 600;
}
.chat-peer-info-handle {
    font-size: 1.35rem;
    font-weight: 700;
    color: inherit;
}
.chat-peer-info-pushname {
    color: #8696a0;
    margin-top: 2px;
}
.chat-peer-info-meta {
    margin-top: 12px;
    color: #8696a0;
    font-size: 0.9rem;
    line-height: 1.45;
}
.chat-peer-info-meta .dot {
    margin: 0 0.35rem;
}
.chat-peer-info-safety {
    margin-top: 14px;
    border: 0;
    background: transparent;
    color: #00a884;
    font-weight: 600;
}
.chat-peer-info-add,
.chat-peer-info-block {
    display: block;
    width: 100%;
    margin-top: 8px;
    border-radius: 999px;
    padding: 0.65rem 1rem;
    font-weight: 700;
    border: 0;
}
.chat-peer-info-add {
    background: transparent;
    color: #00a884;
    border: 1px solid rgba(0, 168, 132, 0.45) !important;
}
.chat-peer-info-block {
    background: #3a1f24;
    color: #ff6b6b;
}
[data-bs-theme="light"] .chat-peer-info-block,
body:not(.dark-mode) .chat-peer-info-block {
    background: #fdecee;
    color: #d32f2f;
}
</style>

<script>
(function () {
    const card = document.getElementById('chat-peer-info-card');
    if (!card) return;
    const userId = card.dataset.userId;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function loadCommonGroups() {
        if (!userId) return;
        try {
            const res = await fetch(`/api/v1/users/${userId}/common-groups`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const json = await res.json();
            const groups = Array.isArray(json.data) ? json.data : [];
            const label = document.getElementById('chat-peer-groups-label');
            if (!label) return;
            if (groups.length === 0) {
                label.textContent = @json(__('No common groups'));
                return;
            }
            if (groups.length === 1) {
                const name = (groups[0] && groups[0].name) ? String(groups[0].name) : '';
                label.textContent = name
                    ? (@json(__('1 group in common')) + ': ' + name)
                    : @json(__('1 group in common'));
                return;
            }
            label.textContent = `${groups.length} ${@json(__('groups in common'))}`;
        } catch (e) {}
    }

    document.getElementById('chat-peer-safety-btn')?.addEventListener('click', function () {
        alert(@json(__('Only chat with people you trust. You can block this person to stop messages and calls from them.')));
    });

    document.getElementById('chat-peer-block-btn')?.addEventListener('click', function () {
        const blockBtn = document.getElementById('block-user-btn');
        if (blockBtn) {
            blockBtn.click();
            return;
        }
        if (!confirm(@json(__('Block this person?')))) return;
        fetch('/blocks', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ blocked_user_id: Number(userId) }),
        }).then(r => {
            if (r.ok) {
                card.remove();
            }
        });
    });

    document.getElementById('chat-peer-add-contact-btn')?.addEventListener('click', function () {
        const addBtn = document.getElementById('add-contact-btn') || document.getElementById('save-contact-btn');
        if (addBtn) {
            addBtn.click();
            return;
        }
        // Fallback: open profile modal if present
        const profileBtn = document.querySelector('[data-bs-target="#userProfileModal"]');
        profileBtn?.click();
    });

    loadCommonGroups();
})();
</script>
@endif
