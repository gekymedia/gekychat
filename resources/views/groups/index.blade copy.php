{{-- resources/views/groups/index.blade.php --}}
@extends('layouts.app')

@section('body_class', 'page-chat')

@section('title', $group->name . ' - GekyChat Group')
@section('description', 'Group chat: ' . ($group->description ?? 'Join the conversation'))
@section('keywords', 'group chat, messaging, collaboration, team chat')

@php
  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Str;

  // Group header data
  $groupData = [
    'name' => $group->name ?? 'Group Chat',
    'initial' => Str::upper(Str::substr($group->name ?? 'G', 0, 1)),
    'avatar' => $group->avatar_path ? Storage::url($group->avatar_path) : null,
    'description' => $group->description ?? null,
    'isPrivate' => $group->is_private ?? false,
    'memberCount' => $group->members->count() ?? 0,
    'previewMembers' => $group->members->take(3) ?? collect(),
    'isOwner' => $group->owner_id === auth()->id(),
    'userRole' => $group->members->firstWhere('id', auth()->id())?->pivot?->role ?? 'member'
  ];

  // Forward data preparation
  $forwardData = [
    'conversations' => collect($conversations ?? [])->map(function($c) {
      $me = auth()->id();
      $other = $c->user_one_id == $me ? $c->userTwo : $c->userOne;
      return [
        'id' => $c->id,
        'name' => $other?->name ?: $other?->phone ?: 'Unknown User',
        'avatar' => $other?->avatar_path ? Storage::url($other->avatar_path) : null
      ];
    })->values()->toArray(),
    
    'groups' => collect($groups ?? [])->filter(fn($g) => (int)$g->id !== (int)$group->id)->map(function($g) {
      return [
        'id' => $g->id,
        'name' => $g->name,
        'avatar' => $g->avatar_path ? Storage::url($g->avatar_path) : null
      ];
    })->values()->toArray()
  ];

  // Sidebar data
  $sidebarData = [
    'conversations' => $conversations ?? collect(),
    'groups' => $groups ?? collect(),
    'botConversation' => $botConversation ?? null,
    'users' => $users ?? collect()
  ];
@endphp

@section('content')
<div class="container-fluid chat-container">
  {{-- Network Status Banner --}}
  <div class="network-banner" id="net-banner" style="display: none;" role="alert" aria-live="polite">
    <div class="container">
      <div class="d-flex align-items-center justify-content-center gap-2 py-2">
        <i class="bi bi-wifi-off" aria-hidden="true"></i>
        <span>You are currently offline. Messages will be sent when connection is restored.</span>
        <span id="net-retry-in" class="ms-2"></span>
      </div>
    </div>
  </div>

  <div class="row h-100 g-0">
    {{-- SIDEBAR --}}
    @include('partials.chat_sidebar', $sidebarData)

    {{-- GROUP CHAT AREA --}}
    <div class="col-md-8 col-lg-9 d-flex flex-column" id="chat-area" aria-live="polite" aria-atomic="true">
      {{-- Group Header --}}
      <header class="chat-header p-3 border-bottom d-flex align-items-center" role="banner">
        <button class="btn btn-sm btn-ghost d-md-none me-2" id="back-to-conversations" 
                aria-label="Back to conversations" title="Back to conversations">
          <i class="bi bi-arrow-left" aria-hidden="true"></i>
        </button>

        <div class="d-flex align-items-center flex-grow-1 min-width-0">
          {{-- Group Avatar --}}
          <div class="position-relative">
            @if(!empty($groupData['avatar']))
              <img src="{{ $groupData['avatar'] }}" alt="{{ $groupData['name'] }} group avatar"
                   class="avatar avatar-img me-3" loading="lazy" width="40" height="40"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
              <div class="avatar me-3 rounded-circle bg-brand text-white" style="display: none;">
                {{ $groupData['initial'] }}
              </div>
            @else
              <div class="avatar me-3 rounded-circle bg-brand text-white">
                {{ $groupData['initial'] }}
              </div>
            @endif
          </div>

          {{-- Group Info --}}
          <div class="flex-grow-1 min-width-0">
            <h1 class="h5 mb-0 chat-header-name text-truncate" title="{{ $groupData['name'] }}">
              {{ $groupData['name'] }}
              @if($groupData['isPrivate'])
                <i class="bi bi-lock-fill text-muted ms-1" title="Private group" aria-label="Private group"></i>
              @endif
            </h1>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <small class="muted text-truncate">
                @if($groupData['memberCount'] > 0)
                  {{ $groupData['previewMembers']->pluck('name')->filter()->implode(', ') ?: $groupData['previewMembers']->pluck('phone')->implode(', ') }}
                  @if($groupData['memberCount'] > 3)
                    &nbsp;+{{ $groupData['memberCount'] - 3 }} more
                  @endif
                @else
                  No members
                @endif
              </small>
              <small class="muted typing-indicator" id="typing-indicator" style="display: none;" aria-live="polite">
                <span class="typing-dots">
                  <span class="dot"></span>
                  <span class="dot"></span>
                  <span class="dot"></span>
                </span>
                typing…
              </small>
            </div>
          </div>
        </div>

        {{-- Header Actions --}}
        <div class="d-flex align-items-center gap-2">
          {{-- Online Members --}}
          <div id="online-list" class="d-none d-md-flex align-items-center gap-2" aria-label="Online members"></div>
          
          {{-- Group Options Menu --}}
          <div class="dropdown">
            <button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown" 
                    aria-expanded="false" aria-label="Group options" title="Group options">
              <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" role="menu">
              <li role="none">
                <button class="dropdown-item d-flex align-items-center gap-2" 
                        data-bs-toggle="offcanvas" data-bs-target="#groupDetails" role="menuitem">
                  <i class="bi bi-info-circle" aria-hidden="true"></i>
                  <span>Group info</span>
                </button>
              </li>
              <li role="none">
                <button class="dropdown-item d-flex align-items-center gap-2" 
                        id="mute-group-btn" role="menuitem">
                  <i class="bi bi-bell" aria-hidden="true"></i>
                  <span>Mute notifications</span>
                </button>
              </li>
              @if($groupData['isOwner'] || $groupData['userRole'] === 'admin')
                <li role="none">
                  <button class="dropdown-item d-flex align-items-center gap-2" 
                          data-bs-toggle="modal" data-bs-target="#editGroupModal" role="menuitem">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                    <span>Edit group</span>
                  </button>
                </li>
              @endif
              <li><hr class="dropdown-divider"></li>
              <li role="none">
                <form method="POST" action="{{ route('groups.leave', $group) }}" 
                      onsubmit="return confirm('Are you sure you want to leave this group?')" class="d-inline">
                  @csrf
                  <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger" role="menuitem">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                    <span>Leave group</span>
                  </button>
                </form>
              </li>
            </ul>
          </div>
        </div>
      </header>

      {{-- Messages Container --}}
      <main class="messages-container flex-grow-1 position-relative" id="chat-box" role="main">
        {{-- Scroll Sentinel for Infinite Loading --}}
        <div id="top-sentinel" aria-hidden="true" style="height: 1px;"></div>

        {{-- Loading Indicator --}}
        <div id="messages-loader" class="messages-loader text-center py-4" style="display: none;" aria-live="polite">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading older messages...</span>
          </div>
        </div>

        {{-- Messages List --}}
        <div id="messages-container" role="log" aria-live="polite" aria-relevant="additions">
          @forelse(($messages ?? $group->messages ?? collect())->where('is_expired', false) as $message)
            @include('groups.partials.message', [
              'message' => $message,
              'group' => $group,
              'userRole' => $groupData['userRole'],
              'isOwner' => $groupData['isOwner']
            ])
          @empty
            {{-- Empty Group State --}}
            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center py-5">
              <div class="avatar bg-card mb-4 rounded-circle d-flex align-items-center justify-content-center empty-chat-icon">
                <i class="bi bi-people" aria-hidden="true"></i>
              </div>
              <h2 class="h5 empty-chat-title mb-3">Welcome to {{ $groupData['name'] }}</h2>
              <p class="muted empty-chat-subtitle mb-4">
                @if($groupData['description'])
                  {{ $groupData['description'] }}
                @else
                  Send the first message to start the conversation
                @endif
              </p>
            </div>
          @endforelse
        </div>

        {{-- Scroll Anchor for New Messages --}}
        <div id="scroll-anchor" tabindex="-1" aria-hidden="true"></div>
      </main>

      {{-- Reply Preview --}}
      <div class="reply-preview-container border-top" id="reply-preview" style="display: none;" aria-live="polite">
        <div class="container-fluid">
          <div class="d-flex justify-content-between align-items-center py-2">
            <div class="flex-grow-1 min-width-0">
              <small class="muted d-block">Replying to:</small>
              <div class="reply-preview-content text-truncate" aria-live="polite"></div>
            </div>
            <button class="btn btn-sm btn-outline-danger ms-3 flex-shrink-0" id="cancel-reply" 
                    aria-label="Cancel reply" title="Cancel reply">
              <i class="bi bi-x" aria-hidden="true"></i>
            </button>
          </div>
        </div>
      </div>

      {{-- Message Composer --}}
      <footer class="message-input-container border-top" role="form" aria-label="Send message to group">
        <div class="container-fluid">
          <form id="chat-form" action="{{ route('groups.messages.store', $group) }}" method="POST" 
                enctype="multipart/form-data" novalidate>
            @csrf
            
            {{-- Hidden Fields --}}
            <input type="hidden" name="reply_to_id" id="reply-to-id" value="">
            <input type="hidden" name="forward_from_id" id="forward-from-id" value="">

            <div class="input-group composer" id="drop-zone" role="group" aria-label="Message composer">
              {{-- Emoji Button --}}
              <button class="btn btn-ghost" type="button" id="emoji-btn" 
                      aria-label="Add emoji" title="Add emoji" data-bs-toggle="tooltip">
                <i class="bi bi-emoji-smile" aria-hidden="true"></i>
              </button>

              {{-- Message Input --}}
              <input type="text" name="body" class="form-control message-input" 
                     placeholder="Message {{ $groupData['name'] }}..." required id="message-input" 
                     autocomplete="off" maxlength="1000" aria-label="Message input"
                     aria-describedby="send-button">

              {{-- Attachment Button --}}
              <div class="btn-group" role="group" aria-label="Message actions">
                <button class="btn btn-ghost dropdown-toggle" type="button" 
                        data-bs-toggle="dropdown" aria-expanded="false" 
                        aria-label="Attach files" title="Attach files">
                  <i class="bi bi-paperclip" aria-hidden="true"></i>
                </button>
                
                <ul class="dropdown-menu dropdown-menu-end" role="menu">
                  <li role="none">
                    <label class="dropdown-item d-flex align-items-center gap-2 cursor-pointer" role="menuitem">
                      <i class="bi bi-image" aria-hidden="true"></i>
                      <span>Photo or Video</span>
                      <input type="file" name="attachments[]" accept="image/*,video/*" 
                             class="d-none" id="photo-upload" multiple>
                    </label>
                  </li>
                  <li role="none">
                    <label class="dropdown-item d-flex align-items-center gap-2 cursor-pointer" role="menuitem">
                      <i class="bi bi-file-earmark" aria-hidden="true"></i>
                      <span>Document</span>
                      <input type="file" name="attachments[]" class="d-none" 
                             id="doc-upload" multiple accept=".pdf,.doc,.docx,.txt,.zip">
                    </label>
                  </li>
                </ul>
              </div>

              {{-- Send Button --}}
              <button class="btn btn-wa" type="submit" id="send-btn" 
                      aria-label="Send message" title="Send message">
                <i class="bi bi-send" aria-hidden="true"></i>
              </button>
            </div>

            {{-- Upload Progress --}}
            <div id="upload-progress" class="progress mt-2" style="display: none;" 
                 role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
              <div class="progress-bar" style="width: 0%"></div>
            </div>

            {{-- Mobile Helper Text --}}
            <small class="muted d-block mt-2 d-md-none text-center">
              <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
              Use the sidebar to switch between conversations and groups
            </small>
          </form>
        </div>
      </footer>
    </div>
  </div>
</div>

{{-- Group Details Offcanvas --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="groupDetails" aria-labelledby="groupDetailsLabel">
  <div class="offcanvas-header border-bottom">
    <h2 class="offcanvas-title h5" id="groupDetailsLabel">Group Information</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    {{-- Group Header in Offcanvas --}}
    <div class="d-flex align-items-center mb-4">
      @if($groupData['avatar'])
        <img src="{{ $groupData['avatar'] }}" class="rounded-circle me-3" width="64" height="64" 
             style="object-fit: cover" alt="{{ $groupData['name'] }} group avatar"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <div class="rounded-circle bg-brand text-white d-flex align-items-center justify-content-center me-3" 
             style="width:64px;height:64px;display:none;">
          {{ $groupData['initial'] }}
        </div>
      @else
        <div class="rounded-circle bg-brand text-white d-flex align-items-center justify-content-center me-3" 
             style="width:64px;height:64px;">
          {{ $groupData['initial'] }}
        </div>
      @endif
      <div class="min-width-0">
        <strong class="d-block text-truncate">{{ $groupData['name'] }}</strong>
        <small class="muted">
          {{ $groupData['memberCount'] }} {{ Str::plural('member', $groupData['memberCount']) }}
          @if($groupData['isPrivate'])
            • <i class="bi bi-lock-fill" title="Private group"></i> Private
          @endif
        </small>
      </div>
    </div>

    {{-- Group Description --}}
    @if(!empty($groupData['description']))
      <div class="mb-4">
        <h3 class="h6 mb-2">Description</h3>
        <p class="mb-0 text-break" style="white-space: pre-wrap; line-height: 1.5;">
          {{ $groupData['description'] }}
        </p>
      </div>
    @endif

    {{-- Invite Link --}}
    <div class="mb-4">
      <h3 class="h6 mb-2">Invite Link</h3>
      <div class="d-grid gap-2">
        <button class="btn btn-outline-primary" id="copy-invite">
          <i class="bi bi-link-45deg me-2" aria-hidden="true"></i>
          Copy invite link
        </button>
        <small class="text-muted text-center">
          Share this link to invite others to the group
        </small>
      </div>
    </div>

    {{-- Members List --}}
    <div>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 mb-0">Members</h3>
        <span class="badge bg-secondary">{{ $groupData['memberCount'] }}</span>
      </div>
      
      <div class="d-flex flex-column gap-3">
        @forelse($group->members as $member)
          <div class="d-flex align-items-center gap-3">
            {{-- Member Avatar --}}
            @if($member->avatar_path)
              <img src="{{ Storage::url($member->avatar_path) }}" class="rounded-circle" 
                   width="40" height="40" alt="{{ $member->name ?? $member->phone }} avatar"
                   style="object-fit: cover">
            @else
              <div class="rounded-circle bg-avatar text-white d-flex align-items-center justify-content-center" 
                   style="width:40px;height:40px;">
                <small>{{ Str::upper(Str::substr($member->name ?? $member->phone ?? 'U', 0, 1)) }}</small>
              </div>
            @endif

            {{-- Member Info --}}
            <div class="flex-grow-1 min-width-0">
              <div class="d-flex align-items-center gap-2">
                <strong class="text-truncate d-block">
                  {{ $member->name ?? $member->phone ?? 'Unknown User' }}
                </strong>
                @if($group->owner_id === $member->id)
                  <span class="badge bg-primary" title="Group owner">Owner</span>
                @elseif(optional($member->pivot)->role === 'admin')
                  <span class="badge bg-secondary" title="Group admin">Admin</span>
                @endif
              </div>
              @if($member->id === auth()->id())
                <small class="text-muted">You</small>
              @endif
            </div>
          </div>
        @empty
          <div class="text-center py-3">
            <i class="bi bi-people display-4 text-muted mb-2" aria-hidden="true"></i>
            <p class="text-muted mb-0">No members yet</p>
          </div>
        @endforelse
      </div>
    </div>
  </div>
</div>

{{-- Edit Group Modal --}}
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="edit-group-form"
          action="{{ route('groups.update', $group) }}"
          method="POST" enctype="multipart/form-data" novalidate>
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h2 class="modal-title h5" id="editGroupLabel">Edit Group</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        {{-- Avatar Upload --}}
        <div class="d-flex align-items-center gap-3 mb-4">
          <div class="position-relative">
            <img id="groupAvatarPreview" src="{{ $groupData['avatar'] ?: asset('images/group-default.png') }}"
                 class="rounded-circle" width="80" height="80" style="object-fit: cover" 
                 alt="Group avatar preview">
            <div class="position-absolute bottom-0 end-0 bg-primary rounded-circle p-1 border border-2 border-white">
              <i class="bi bi-camera text-white" style="font-size: 0.75rem;"></i>
            </div>
          </div>
          <div class="flex-grow-1">
            <label for="groupAvatarInput" class="form-label mb-1">Group Avatar</label>
            <input type="file" name="avatar" id="groupAvatarInput" class="form-control" 
                   accept="image/png,image/jpeg,image/webp" aria-describedby="avatarHelp">
            <div id="avatarHelp" class="form-text">PNG, JPG or WebP. Max 2MB.</div>
          </div>
        </div>

        {{-- Group Name --}}
        <div class="mb-3">
          <label for="groupNameInput" class="form-label">Group Name</label>
          <input type="text" name="name" id="groupNameInput" class="form-control" 
                 value="{{ $group->name }}" maxlength="64" required
                 placeholder="Enter group name">
        </div>

        {{-- Group Description --}}
        <div class="mb-3">
          <label for="groupDescriptionInput" class="form-label">Description</label>
          <textarea name="description" id="groupDescriptionInput" class="form-control" 
                    rows="3" maxlength="500" placeholder="Optional group description">{{ $group->description }}</textarea>
          <div class="form-text">
            <span id="description-counter">0</span>/500 characters
          </div>
        </div>

        {{-- Privacy Setting --}}
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" role="switch" id="is_private_switch"
                 name="is_private" value="1" {{ $groupData['isPrivate'] ? 'checked' : '' }}>
          <label class="form-check-label fw-medium" for="is_private_switch">
            <i class="bi bi-lock-fill me-2" aria-hidden="true"></i>
            Private group
          </label>
          <div class="form-text">
            Private groups require invitation to join
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="edit-group-save">
          <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" style="display: none;"></span>
          Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Emoji Picker --}}
<div id="emoji-picker-wrap" class="emoji-wrap" aria-live="polite" style="display: none;">
  <emoji-picker id="emoji-picker" class="shadow-lg"></emoji-picker>
</div>

{{-- Forward Modal --}}
<div id="forward-modal" class="modal fade" tabindex="-1" aria-labelledby="forwardModalLabel" 
     aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="forwardModalLabel">Forward Message</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="forward-source-id" value="">

        <ul class="nav nav-tabs mb-3" id="forwardTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-dms" data-bs-toggle="tab" data-bs-target="#fwd-dms" 
                    type="button" role="tab" aria-controls="fwd-dms" aria-selected="true">
              <i class="bi bi-chat me-1" aria-hidden="true"></i>
              Chats
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-groups" data-bs-toggle="tab" data-bs-target="#fwd-groups" 
                    type="button" role="tab" aria-controls="fwd-groups" aria-selected="false">
              <i class="bi bi-people me-1" aria-hidden="true"></i>
              Groups
            </button>
          </li>
        </ul>

        <div class="tab-content" id="forwardTabsContent">
          {{-- Direct Messages Tab --}}
          <div class="tab-pane fade show active" id="fwd-dms" role="tabpanel" aria-labelledby="tab-dms">
            <div class="input-group mb-3">
              <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-search text-muted" aria-hidden="true"></i>
              </span>
              <input type="text" class="form-control border-start-0" id="fwd-search-dms" 
                     placeholder="Search chats..." aria-label="Search chats">
            </div>
            <div class="list-group list-scroll" id="fwd-dm-list" role="listbox" aria-label="Available chats"></div>
          </div>

          {{-- Groups Tab --}}
          <div class="tab-pane fade" id="fwd-groups" role="tabpanel" aria-labelledby="tab-groups">
            <div class="input-group mb-3">
              <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-search text-muted" aria-hidden="true"></i>
              </span>
              <input type="text" class="form-control border-start-0" id="fwd-search-groups" 
                     placeholder="Search groups..." aria-label="Search groups">
            </div>
            <div class="list-group list-scroll" id="fwd-group-list" role="listbox" aria-label="Available groups"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <div class="me-auto">
          <span class="text-muted">
            <span id="fwd-selected-count">0</span> selected
          </span>
        </div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="forward-confirm" disabled>
          Forward to <span id="forward-count">0</span>
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Hidden Data Stores --}}
<script id="forward-datasets" type="application/json">
@json($forwardData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG)
</script>

{{-- Notification Sound --}}
<audio id="notification-sound" preload="metadata" aria-hidden="true">
  <source src="{{ asset('sounds/notification.mp3') }}" type="audio/mpeg">
  <source src="{{ asset('sounds/notification.ogg') }}" type="audio/ogg">
</audio>

{{-- Component Styles --}}
@include('groups.partials.styles')

{{-- Component Scripts --}}
@include('groups.partials.scripts')

@endsection