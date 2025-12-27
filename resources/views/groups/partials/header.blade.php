{{-- resources/views/groups/partials/header.blade.php --}}
@php
  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Str;

  $groupData = $groupData ?? [
    'name' => 'Group Chat',
    'initial' => 'G',
    'avatar' => null,
    'description' => null,
    'isPrivate' => false,
    'memberCount' => 0,
    'previewMembers' => collect(),
    'isOwner' => false,
    'userRole' => 'member'
  ];
@endphp

<header class="chat-header group-header p-3 border-bottom d-flex align-items-center" role="banner" data-context="group">
  {{-- Back Button (Mobile) --}}
  <button class="btn btn-sm btn-ghost d-md-none me-2" id="back-to-conversations" 
          aria-label="Back to conversations" title="Back to conversations">
    <i class="bi bi-arrow-left" aria-hidden="true"></i>
  </button>

  <div class="d-flex align-items-center flex-grow-1 min-width-0">
    {{-- Group Avatar --}}
    <div class="position-relative" style="width: 40px; height: 40px; margin-right: 12px;">
      @if(!empty($groupData['avatar']))
        <img src="{{ $groupData['avatar'] }}" alt="{{ $groupData['name'] }} {{ (($group ?? null)?->type ?? 'group') === 'channel' ? 'channel' : 'group' }} avatar"
             class="avatar avatar-img rounded-circle" loading="lazy" width="40" height="40" 
             style="object-fit: cover; position: relative; z-index: 1; width: 40px; height: 40px; display: block;"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <div class="avatar rounded-circle bg-brand text-white d-flex align-items-center justify-content-center" style="display: none; position: absolute; top: 0; left: 0; width: 40px; height: 40px; z-index: 0;">
          {{ $groupData['initial'] }}
        </div>
      @else
        <div class="avatar rounded-circle bg-brand text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
          {{ $groupData['initial'] }}
        </div>
      @endif
      
      {{-- Group Privacy Badge --}}
      @if($groupData['isPrivate'])
        <span class="position-absolute bottom-0 end-0 bg-dark rounded-circle border border-2 border-white d-flex align-items-center justify-content-center"
              style="width: 16px; height: 16px;" title="Private group" aria-label="Private group">
          <i class="bi bi-lock-fill text-white" style="font-size: 0.5rem;"></i>
        </span>
      @endif
    </div>

    {{-- Group Info --}}
    <div class="flex-grow-1 min-width-0">
      <h1 class="h5 mb-0 group-header-name text-truncate d-flex align-items-center gap-2" title="{{ $groupData['name'] }}">
        <span>{{ $groupData['name'] }}</span>
        @if(($group ?? null)?->type === 'channel')
          @if(($group ?? null)?->is_verified)
            <i class="bi bi-check-circle-fill text-primary" style="font-size: 1rem;" title="Verified Channel" aria-label="Verified Channel"></i>
          @endif
          <span class="badge" style="font-size: 0.65rem; background-color: var(--bg-accent); color: var(--text);">Channel</span>
        @elseif($groupData['isPrivate'])
          <i class="bi bi-lock-fill text-muted ms-1" title="Private group" aria-label="Private group"></i>
        @endif
      </h1>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <small class="muted text-truncate">
          @if(($group ?? null)?->type === 'channel')
            {{ $groupData['memberCount'] }} {{ $groupData['memberCount'] === 1 ? 'follower' : 'followers' }}
            @if($groupData['memberCount'] > 0 && $groupData['previewMembers']->count() > 0)
              • {{ $groupData['previewMembers']->pluck('name')->filter()->implode(', ') ?: $groupData['previewMembers']->pluck('phone')->implode(', ') }}
              @if($groupData['memberCount'] > 3)
                &nbsp;+{{ $groupData['memberCount'] - 3 }} more
              @endif
            @endif
          @else
            @if($groupData['memberCount'] > 0)
              {{ $groupData['previewMembers']->pluck('name')->filter()->implode(', ') ?: $groupData['previewMembers']->pluck('phone')->implode(', ') }}
              @if($groupData['memberCount'] > 3)
                &nbsp;+{{ $groupData['memberCount'] - 3 }} more
              @endif
            @else
              No members
            @endif
          @endif
        </small>
        <small class="muted typing-indicator" id="typing-indicator" style="display: none;" aria-live="polite">
          <span class="typing-dots">
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
          </span>
          <span class="group-typing-users"></span>
        </small>
      </div>
    </div>
  </div>

  {{-- Header Actions --}}
  <div class="d-flex align-items-center gap-2">
    {{-- Online Members --}}
    <div id="online-list" class="d-none d-md-flex align-items-center gap-2" aria-label="Online members"></div>
    
    {{-- Call Buttons (Groups only, not channels) --}}
    @if(($group ?? null) && ($group->type ?? 'group') !== 'channel')
      {{-- Voice Call Button --}}
      <button class="btn btn-sm btn-ghost" 
              id="group-voice-call-btn" 
              data-group-id="{{ $group->id }}"
              data-group-slug="{{ $group->slug }}"
              aria-label="{{ __('Voice call') }}" 
              title="{{ __('Voice call') }}">
        <i class="bi bi-telephone" aria-hidden="true"></i>
      </button>
      
      {{-- Video Call Button --}}
      <button class="btn btn-sm btn-ghost" 
              id="group-video-call-btn"
              data-group-id="{{ $group->id }}"
              data-group-slug="{{ $group->slug }}"
              aria-label="{{ __('Video call') }}" 
              title="{{ __('Video call') }}">
        <i class="bi bi-camera-video" aria-hidden="true"></i>
      </button>
    @endif
    
    {{-- Group Options Menu --}}
    <div class="dropdown group-options-dropdown">
      <button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown" 
              aria-expanded="false" aria-label="{{ (($group ?? null)?->type ?? 'group') === 'channel' ? 'Channel' : 'Group' }} options" title="{{ (($group ?? null)?->type ?? 'group') === 'channel' ? 'Channel' : 'Group' }} options">
        <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end" role="menu">
        <li role="none">
          <button class="dropdown-item d-flex align-items-center gap-2" 
                  data-bs-toggle="offcanvas" data-bs-target="#groupDetails" role="menuitem">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>{{ (($group ?? null)?->type ?? 'group') === 'channel' ? 'Channel' : 'Group' }} info</span>
          </button>
        </li>
        <li role="none">
          {{-- CHANGED: Use group-invite-modal instead of inviteModal --}}
          <button class="dropdown-item d-flex align-items-center gap-2" 
                  data-bs-toggle="modal" data-bs-target="#group-invite-modal" role="menuitem">
            <i class="bi bi-link-45deg" aria-hidden="true"></i>
            <span>Invite people</span>
          </button>
        </li>
        <li role="none">
          <button class="dropdown-item d-flex align-items-center gap-2" 
                  id="mute-group-btn" role="menuitem">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <span>Mute notifications</span>
          </button>
        </li>
        @if($groupData['isAdmin'] ?? ($groupData['isOwner'] || $groupData['userRole'] === 'admin'))
          <li role="none">
            <button class="dropdown-item d-flex align-items-center gap-2" 
                    data-bs-toggle="modal" data-bs-target="#editGroupModal" role="menuitem">
              <i class="bi bi-pencil" aria-hidden="true"></i>
              <span>Edit {{ ($group ?? null)?->type === 'channel' ? 'channel' : 'group' }}</span>
            </button>
          </li>
        @endif
        <li><hr class="dropdown-divider"></li>
        <li role="none">
          <button class="dropdown-item d-flex align-items-center gap-2 text-danger" 
                  data-bs-toggle="modal" data-bs-target="#leaveGroupModal" role="menuitem">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            <span>Leave {{ ($group ?? null)?->type === 'channel' ? 'channel' : 'group' }}</span>
          </button>
        </li>
      </ul>
    </div>
  </div>
</header>

{{-- Add this JavaScript to handle the group invite modal --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Ensure group invite modal opens with correct data
  const groupInviteModal = document.getElementById('group-invite-modal');
  if (groupInviteModal) {
    groupInviteModal.addEventListener('show.bs.modal', function() {
      // Update modal content with current group data
      const inviteLinkInput = this.querySelector('#group-invite-link-input');
      const memberCountElement = this.querySelector('#group-invite-member-count');
      const groupNameElement = this.querySelector('#group-invite-name');
      
      if (inviteLinkInput) {
        inviteLinkInput.value = window.location.href;
      }
      
      if (memberCountElement) {
        // Get member count from header or use data attribute
        const memberCount = document.querySelector('.group-header')?.dataset.memberCount || {{ $groupData['memberCount'] }};
        memberCountElement.textContent = memberCount + ' members';
      }
      
      if (groupNameElement) {
        groupNameElement.textContent = '{{ $groupData["name"] }}';
      }
    });
  }
});
</script>

<style>
.group-header {
  background: linear-gradient(135deg, var(--card) 0%, color-mix(in srgb, var(--group-accent) 5%, var(--card)) 100%);
  border-bottom: 2px solid var(--group-border);
  backdrop-filter: blur(10px);
  position: relative;
  z-index: 1030;
}

.group-header-name {
  font-weight: 600;
  letter-spacing: 0.2px;
  color: var(--text);
}

.typing-dots {
  display: inline-flex;
  gap: 2px;
  margin-left: 4px;
}

.typing-dots .dot {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: var(--wa-muted);
  animation: typing-bounce 1.4s infinite ease-in-out;
}

.typing-dots .dot:nth-child(1) { animation-delay: -0.32s; }
.typing-dots .dot:nth-child(2) { animation-delay: -0.16s; }
.typing-dots .dot:nth-child(3) { animation-delay: 0s; }

@keyframes typing-bounce {
  0%, 80%, 100% {
    transform: scale(0.8);
    opacity: 0.5;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}

.group-options-dropdown {
  position: relative;
  z-index: 1060;
}

.group-options-dropdown .dropdown-menu {
  z-index: 1060 !important;
}

@media (max-width: 768px) {
  .group-header {
    padding: 12px;
  }

  .group-options-dropdown .dropdown-menu {
    position: fixed;
    top: auto;
    bottom: 0;
    left: 0;
    right: 0;
    margin: 0;
    border-radius: 12px 12px 0 0;
    transform: none !important;
  }
}
</style>