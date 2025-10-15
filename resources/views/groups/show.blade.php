{{-- resources/views/groups/show.blade.php --}}
@extends('layouts.app')
@section('body_class', 'page-chat')

@section('content')
@php
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** @var \App\Models\Group $group */
$groupName = $group->name ?? 'Group';
$groupInit = strtoupper(mb_substr($groupName,0,1));
$groupAvatar = $group->avatar_path ? Storage::url($group->avatar_path) : null;

// Build light-weight datasets for the forward picker (no extra queries)
$forwardDMs = collect($conversations ?? [])->map(function($c){
$me = auth()->id();
$other = $c->user_one_id == $me ? $c->userTwo : $c->userOne;
$name = $other?->name ?: $other?->phone ?: 'Unknown';
$avatar = $other?->avatar_path ? Storage::url($other->avatar_path) : null;
return ['id' => $c->id, 'name' => $name, 'avatar' => $avatar];
})->values();

$forwardGroups = collect($groups ?? [])->filter(fn($g) => (int)$g->id !== (int)$group->id)->map(function($g){
return ['id'=>$g->id, 'name'=>$g->name, 'avatar' => $g->avatar_path ? Storage::url($g->avatar_path) : null];
})->values();

// Members for header preview
$members = $group->members ?? collect();
$memberCount = $members->count();
$preview = $members->take(3);
@endphp

<div class="container-fluid chat-container">
  <div class="row h-100 g-0">

    {{-- SIDEBAR --}}
    @include('partials.chat_sidebar', [
    'conversations' => $conversations ?? collect(),
    'groups' => $groups ?? collect(),
    'botConversation' => $botConversation ?? null,
    'users' => $users ?? collect()
    ])

    {{-- GROUP CHAT AREA --}}
    <div class="col-md-8 col-lg-9 d-flex flex-column" id="chat-area">

      {{-- Network status banner --}}
      <div id="net-banner" class="w-100 text-center small py-2" style="display:none;background:#b91c1c;color:#fff;">
        <span class="me-2"><i class="bi bi-wifi-off"></i></span>
        <span class="net-text">Network connection lost. Attempting to reconnect…</span>
        <span class="ms-2" id="net-retry-in" style="opacity:.8;"></span>
      </div>

      {{-- Header --}}
      <div class="chat-header p-3 border-bottom d-flex align-items-center" style="background:var(--card);">
        <button class="btn btn-sm d-md-none me-2" id="back-to-conversations" aria-label="Back">
          <i class="bi bi-arrow-left"></i>
        </button>

        @if(!empty($groupAvatar))
        <img src="{{ $groupAvatar }}" alt="{{ $groupName }} avatar"
          class="avatar avatar-img me-3" loading="lazy"
          onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'avatar me-3 rounded-circle bg-brand text-white',textContent:'{{ e($groupInit) }}'}));">
        @else
        <div class="avatar me-3 rounded-circle bg-brand text-white">{{ $groupInit }}</div>
        @endif

        <div class="flex-grow-1">
          <h5 class="mb-0" style="font-weight:800;letter-spacing:.2px;">{{ $groupName }}</h5>
          <small class="muted d-block">
            @if($memberCount)
            {{ $preview->pluck('name')->filter()->implode(', ') ?: $preview->pluck('phone')->implode(', ') }}
            @if($memberCount > 3) &nbsp;+{{ $memberCount - 3 }} more @endif
            @else
            No members
            @endif
          </small>
          <small class="muted" id="typing-indicator" style="display:none;">typing…</small>
        </div>

        <div class="d-flex align-items-center gap-3">
          <div id="online-list" class="d-none d-md-flex align-items-center gap-2"></div>
          <div class="dropdown">
            <button class="btn btn-sm" data-bs-toggle="dropdown" aria-label="More">
              <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              {{-- Group info offcanvas --}}
              <li><button class="dropdown-item" data-bs-toggle="offcanvas" data-bs-target="#groupDetails">Group info</button></li>
              <li><a class="dropdown-item" href="#" id="mute-chat-btn">Mute notifications</a></li>
              @if($group->owner_id === auth()->id() || $members->firstWhere('id', auth()->id())?->pivot?->role === 'admin')
              <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editGroupModal">Edit group</button></li>
              @endif
              <li>
                <hr class="dropdown-divider">
              </li>
              <li>
                <form method="POST" action="{{ route('groups.leave', $group) }}" onsubmit="return confirm('Leave this group?')">
                  @csrf
                  <button type="submit" class="dropdown-item text-danger">Leave group</button>
                </form>
              </li>
            </ul>
          </div>
        </div>
      </div>

      {{-- Messages --}}
  {{-- Messages --}}
<div class="messages-container flex-grow-1 p-3 overflow-auto" id="chat-box">
  {{-- 👈 sentinel the observer watches --}}
  <div id="top-sentinel" aria-hidden="true" style="height:1px;"></div>

  <div id="messages-loader" class="text-center py-3" style="display:none;">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
  </div>

  <div id="messages-container">
    @foreach(($messages ?? $group->messages ?? collect()) as $msg)
      @php
        $fromMe = (int) $msg->sender_id === (int) auth()->id();
        $senderName = $msg->sender->name ?? $msg->sender->phone ?? 'User';
        $body = $msg->body ?? '';
        $isExpired = $msg->expires_at ?? null ? optional($msg->expires_at)->isPast() : false;
      @endphp
      @if(!$isExpired)
        <div class="message mb-3 d-flex {{ $fromMe ? 'justify-content-end' : 'justify-content-start' }}"
             data-message-id="{{ $msg->id }}"
             data-from-me="{{ $fromMe ? '1':'0' }}"
             data-read="{{ $msg->read_at ? '1' : '0' }}">
          <div class="message-bubble {{ $fromMe ? 'sent' : 'received' }}">
            @unless($fromMe)
              <small class="sender-name">{{ $senderName }}</small>
            @endunless

            <div class="message-content">
              @if($msg->reply_to_id && $msg->replyTo)
                <div class="reply-preview">
                  <small>Replying to: {{ Str::limit($msg->replyTo->body ?? '[message]', 80) }}</small>
                </div>
              @endif

              @if($msg->forwarded_from_id)
                <div class="mb-1"><small class="muted"><i class="bi bi-forward-fill me-1"></i>Forwarded</small></div>
              @endif

              <div class="message-text">
                {!! Str::of(e($body))->replaceMatches('/(https?:\/\/[^\s]+)/', fn($m) => '<a href="'.$m[0].'" target="_blank" class="linkify">'.$m[0].'</a>') !!}
              </div>

              {{-- 🔁 LAZY IMAGES: use data-src only (no src) --}}
              @foreach($msg->attachments as $file)
                @php
                  $url = method_exists($file, 'getUrlAttribute') ? $file->url : (Storage::url($file->file_path));
                  $ext = strtolower(pathinfo($file->file_path, PATHINFO_EXTENSION));
                  $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                @endphp
                @if($isImage)
                  <div class="mt-2">
                    <img data-src="{{ $url }}" alt="image" class="img-fluid rounded media-img" loading="lazy" style="max-width:220px;">
                  </div>
                @else
                  <div class="mt-2">
                    <a href="{{ $url }}" target="_blank" class="d-inline-flex align-items-center doc-link">
                      <i class="bi bi-file-earmark me-1"></i> {{ $file->original_name ?? basename($file->file_path) }}
                    </a>
                  </div>
                @endif
              @endforeach
            </div>

            <div class="message-footer d-flex justify-content-between align-items-center mt-1">
              <small class="muted">{{ optional($msg->created_at)->format('h:i A') }}</small>
              @if($fromMe)
                <div class="status-indicator">
                  @if($msg->read_at)
                    <i class="bi bi-check2-all text-primary" title="Read"></i>
                  @elseif($msg->delivered_at)
                    <i class="bi bi-check2-all muted" title="Delivered"></i>
                  @else
                    <i class="bi bi-check2 muted" title="Sent"></i>
                  @endif
                </div>
              @endif
            </div>

            <div class="reactions-container mt-1">
              @foreach($msg->reactions as $reaction)
                <span class="badge bg-reaction rounded-pill me-1" title="{{ $reaction->user->name ?? 'User' }}">
                  {{ $reaction->emoji ?? $reaction->reaction ?? '👍' }}
                </span>
              @endforeach
            </div>
          </div>

          <div class="message-actions dropdown">
            <button class="btn btn-sm p-0" data-bs-toggle="dropdown" aria-label="Actions">
              <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu">
              <li><button class="dropdown-item forward-btn" data-message-id="{{ $msg->id }}">Forward</button></li>
              <li><button class="dropdown-item reply-btn" data-message-id="{{ $msg->id }}">Reply</button></li>

              @if($fromMe || ($group->owner_id === auth()->id()) || ($members->firstWhere('id', auth()->id())?->pivot?->role === 'admin'))
                <li>
                  <button class="dropdown-item edit-btn"
                          data-message-id="{{ $msg->id }}"
                          data-body="{{ e($msg->body ?? '') }}"
                          data-edit-url="{{ route('groups.messages.update', ['group' => $group, 'message' => $msg]) }}">
                    Edit
                  </button>
                </li>
                <li>
                  <form method="POST" action="{{ route('groups.messages.delete', [$group, $msg]) }}" class="d-inline delete-form">
                    @csrf @method('DELETE')
                    <button type="submit" class="dropdown-item text-danger">Delete</button>
                  </form>
                </li>
              @endif

              <li><hr class="dropdown-divider"></li>
              <li>
                <div class="d-flex px-3 py-1">
                  @php $reactUrl = fn($m) => route('groups.messages.reactions', ['group' => $group, 'message' => $m]); @endphp
                  <button class="btn btn-sm reaction-btn" data-reaction="👍" data-react-url="{{ $reactUrl($msg) }}">👍</button>
                  <button class="btn btn-sm reaction-btn ms-1" data-reaction="❤️" data-react-url="{{ $reactUrl($msg) }}">❤️</button>
                  <button class="btn btn-sm reaction-btn ms-1" data-reaction="😂" data-react-url="{{ $reactUrl($msg) }}">😂</button>
                  <button class="btn btn-sm reaction-btn ms-1" data-reaction="😮" data-react-url="{{ $reactUrl($msg) }}">😮</button>
                </div>
              </li>
            </ul>
          </div>
        </div>
      @endif
    @endforeach
  </div>

  <div id="scroll-anchor"></div>
</div>

      {{-- Reply preview (composer) --}}
      <div class="reply-preview-container p-2 border-top" id="reply-preview" style="display:none;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <small class="muted">Replying to:</small>
            <div class="reply-preview-content"></div>
          </div>
          <button class="btn btn-sm btn-outline-danger" id="cancel-reply" aria-label="Cancel reply">
            <i class="bi bi-x"></i>
          </button>
        </div>
      </div>

      {{-- Composer --}}
      <div class="message-input-container p-3 border-top" style="background:var(--bg);">
        <form id="chat-form" action="{{ route('groups.messages.store', $group) }}" method="POST" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="reply_to_id" id="reply-to-id" value="">
          {{-- (Forward-from support) --}}
          <input type="hidden" name="forward_from_id" id="forward-from-id" value="">

          <div class="input-group composer" id="drop-zone">
            <button class="btn btn-ghost" type="button" id="emoji-btn" aria-label="Emoji">
              <i class="bi bi-emoji-smile"></i>
            </button>

            <input type="text" name="body" class="form-control" placeholder="Message {{ $groupName }}"
              required id="message-input" autocomplete="off">

            <div class="btn-group">
              <button class="btn btn-ghost dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Attach">
                <i class="bi bi-paperclip"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <label class="dropdown-item">
                    <i class="bi bi-image me-2"></i> Photo
                    <input type="file" name="attachments[]" accept="image/*" class="d-none" id="photo-upload" multiple>
                  </label>
                </li>
                <li>
                  <label class="dropdown-item">
                    <i class="bi bi-file-earmark me-2"></i> Document
                    <input type="file" name="attachments[]" class="d-none" id="doc-upload" multiple>
                  </label>
                </li>
              </ul>
            </div>

            <button class="btn btn-wa" type="submit" id="send-btn" aria-label="Send">
              <i class="bi bi-send"></i>
            </button>
          </div>

          <div id="upload-progress" class="progress mt-2" style="display:none;height:4px;">
            <div class="progress-bar" role="progressbar" style="width:0%"></div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Group Details Offcanvas --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="groupDetails" aria-labelledby="groupDetailsLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="groupDetailsLabel">Group info</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <div class="d-flex align-items-center mb-3">
      @if($groupAvatar)
      <img src="{{ $groupAvatar }}" class="rounded-circle me-3" width="56" height="56" style="object-fit:cover" alt="group">
      @else
      <div class="rounded-circle bg-brand text-white d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
        {{ $groupInit }}
      </div>
      @endif
      <div>
        <strong class="d-block">{{ $groupName }}</strong>
        <small class="muted">{{ $memberCount }} {{ Str::plural('member',$memberCount) }}</small>
      </div>
    </div>

    @if(!empty($group->description))
    <div class="mb-4">
      <h6 class="mb-1">Description</h6>
      <p class="mb-0" style="white-space:pre-wrap">{{ $group->description }}</p>
    </div>
    @endif

    <div class="mb-3 d-grid">
      <button class="btn btn-outline-secondary" id="copy-invite">
        <i class="bi bi-link-45deg me-1"></i> Copy invite link
      </button>
    </div>

    <h6 class="mt-4 mb-2">Members</h6>
    <div class="d-flex flex-column gap-2">
      @forelse($members as $m)
      <div class="d-flex align-items-center gap-2">
        @if($m->avatar_path)
        <img src="{{ Storage::url($m->avatar_path) }}" class="rounded-circle" width="28" height="28" alt="avatar">
        @else
        <div class="rounded-circle bg-avatar text-white d-flex align-items-center justify-content-center" style="width:28px;height:28px;">
          <small>{{ strtoupper(mb_substr($m->name ?? $m->phone ?? 'U',0,1)) }}</small>
        </div>
        @endif
        <div class="flex-grow-1">
          <small>{{ $m->name ?? $m->phone ?? 'User' }}</small>
        </div>
        @if($group->owner_id === $m->id)
        <span class="badge bg-secondary">Owner</span>
        @elseif(optional($m->pivot)->role === 'admin')
        <span class="badge bg-secondary">Admin</span>
        @endif
      </div>
      @empty
      <small class="muted">No members yet</small>
      @endforelse
    </div>
  </div>
</div>

{{-- Edit Group Modal --}}
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="edit-group-form"
      action="{{ route('groups.update', $group) }}"
      method="POST" enctype="multipart/form-data">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title" id="editGroupLabel">Edit group</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <img id="groupAvatarPreview" src="{{ $groupAvatar ?: asset('icons/icon-192x192.png') }}"
            class="rounded-circle" width="56" height="56" style="object-fit:cover" alt="avatar">
          <div>
            <label class="form-label mb-1">Avatar</label>
            <input type="file" name="avatar" id="groupAvatarInput" class="form-control" accept="image/*">
            <small class="muted">PNG/JPG up to 2MB</small>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" value="{{ $group->name }}" maxlength="64" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3" maxlength="200">{{ $group->description }}</textarea>
        </div>

        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="is_private_switch"
            name="is_private" value="1" {{ $group->is_private ? 'checked' : '' }}>
          <label class="form-check-label" for="is_private_switch">Private group</label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-wa" id="edit-group-save">Save changes</button>
      </div>
    </form>
  </div>
</div>

{{-- Emoji picker host --}}
<div id="emoji-picker-wrap" class="emoji-wrap" style="display:none;">
  <emoji-picker id="emoji-picker" class="shadow-lg"></emoji-picker>
</div>

{{-- Forward modal (multi-select, searchable; DMs + Groups) --}}
<div id="forward-modal" class="modal fade" tabindex="-1" aria-labelledby="forwardModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="forwardModalLabel">Forward Message</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="forward-source-id" value="">

        <ul class="nav nav-tabs mb-3" id="forwardTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-dms" data-bs-toggle="tab" data-bs-target="#fwd-dms" type="button" role="tab">Chats</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-groups" data-bs-toggle="tab" data-bs-target="#fwd-groups" type="button" role="tab">Groups</button>
          </li>
        </ul>

        <div class="tab-content" id="forwardTabsContent">
          {{-- DMs --}}
          <div class="tab-pane fade show active" id="fwd-dms" role="tabpanel" aria-labelledby="tab-dms">
            <div class="input-group mb-2">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control" id="fwd-search-dms" placeholder="Search chats by name…">
            </div>
            <div class="list-group list-scroll" id="fwd-dm-list"></div>
          </div>

          {{-- Groups --}}
          <div class="tab-pane fade" id="fwd-groups" role="tabpanel" aria-labelledby="tab-groups">
            <div class="input-group mb-2">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control" id="fwd-search-groups" placeholder="Search groups…">
            </div>
            <div class="list-group list-scroll" id="fwd-group-list"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <div class="me-auto small muted"><span id="fwd-selected-count">0</span> selected</div>
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="forward-confirm" disabled>Forward</button>
      </div>
    </div>
  </div>
</div>

{{-- Data for the forward picker --}}
<script id="forward-datasets" type="application/json">
  {
    !!json_encode(['conversations' => $forwardDMs, 'groups' => $forwardGroups], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!
  }
</script>

<audio id="notification-sound" src="{{ asset('sounds/notification.wav') }}" preload="auto"></audio>

<style>
  :root {
    --bubble-radius: 16px;
    --reaction-bg: rgba(0, 0, 0, 0.1);
  }

  .chat-container {
    height: calc(100dvh - var(--nav-h));
  }

  #conversation-sidebar,
  #chat-area {
    min-height: 0;
    height: 100%;
  }

  #conversation-sidebar {
    display: flex;
    flex-direction: column;
  }

  #chat-area {
    display: flex;
    flex-direction: column;
  }

  .conversation-list {
    flex: 1 1 auto;
    overflow: auto;
  }

  .messages-container {
    flex: 1 1 auto;
    overflow: auto;
    min-height: 0;
    position: relative;
    contain: strict;
  }

  .search-wrap .input-group-text {
    background: var(--card);
    border-color: var(--border);
    color: var(--wa-muted);
  }

  .search-wrap .form-control {
    background: var(--card);
    border-color: var(--border);
    color: var(--text);
  }

  .conversation-item {
    color: var(--text);
    transition: background-color .2s ease;
    border-bottom: 1px solid var(--border);
  }

  .conversation-item:hover {
    background: rgba(255, 255, 255, .04);
  }

  [data-theme="light"] .conversation-item:hover {
    background: rgba(0, 0, 0, .04);
  }

  .conversation-item.unread {
    font-weight: 700;
  }

  .unread-badge {
    background: var(--wa-green);
    color: #062a1f;
  }

  .avatar {
    width: 40px;
    height: 40px;
    font-size: 1.1rem;
    display: grid;
    place-items: center;
  }

  .avatar-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border);
    display: block;
  }

  .bg-brand {
    background: var(--wa-deep) !important;
  }

  .bg-avatar {
    background: #667085 !important;
  }

  .bg-card {
    background: var(--card) !important;
  }

  .bg-reaction {
    background: var(--reaction-bg) !important;
  }

  .messages-container {
    background: radial-gradient(1000px 600px at 10% -10%, var(--bg-accent, #0f1a20) 0, var(--bg) 60%), var(--bg);
    display: flex;
    flex-direction: column;
    padding: 12px;
  }

  .message-bubble {
    max-width: min(75%, 680px);
    padding: 10px 12px;
    border-radius: var(--bubble-radius);
    position: relative;
    box-shadow: 0 2px 6px rgba(0, 0, 0, .08);
  }

  .message-bubble.sent {
    background: var(--bubble-sent-bg);
    color: var(--bubble-sent-text);
    border-top-right-radius: 6px;
  }

  .message-bubble.received {
    background: var(--bubble-recv-bg);
    color: var(--bubble-recv-text);
    border-top-left-radius: 6px;
  }

  .sender-name {
    font-weight: 700;
    opacity: .85;
    display: block;
    margin-bottom: 2px;
  }

  .message-text {
    white-space: pre-wrap;
    word-break: break-word;
  }

  .media-img {
    max-width: 220px;
    border-radius: 10px;
    transition: opacity 0.3s ease;
  }

  .media-img[data-src]:not([src]) {
    opacity: 0;
  }

  .media-img.loading {
    opacity: 0.5;
  }

  .linkify {
    color: var(--wa-green);
    text-decoration: none;
  }

  .linkify:hover {
    text-decoration: underline;
  }

  .message-actions {
    visibility: hidden;
    align-self: center;
    margin: 0 8px;
  }

  .message:hover .message-actions {
    visibility: visible;
  }

  .reply-preview {
    border-left: 3px solid var(--border);
    padding-left: 8px;
    margin-bottom: 6px;
    opacity: .85;
    font-style: italic;
  }

  .reply-preview-container {
    background: var(--card);
  }

  .reactions-container {
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
  }

  .muted {
    color: var(--wa-muted) !important;
    font-size: var(--fs-sm);
  }

  .composer .form-control {
    background: var(--input-bg);
    border-color: var(--input-border);
    color: var(--text);
    border-radius: 14px;
  }

  .composer .form-control:focus {
    border-color: var(--wa-green);
    box-shadow: none;
  }

  .btn-ghost {
    background: var(--card);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 14px;
  }

  .btn-ghost:hover {
    background: rgba(255, 255, 255, .06);
  }

  [data-theme="light"] .btn-ghost:hover {
    background: rgba(0, 0, 0, .04);
  }

  .btn-wa {
    background: var(--wa-green);
    border: none;
    color: #062a1f;
    font-weight: 800;
    border-radius: 14px;
  }

  .btn-outline-wa {
    border-color: var(--wa-green);
    color: var(--wa-green);
    border-radius: 14px;
  }

  .message-input-container {
    position: sticky;
    bottom: 0;
    z-index: 2;
    background: var(--bg);
  }

  .emoji-wrap {
    position: fixed;
    right: 16px;
    bottom: 86px;
    z-index: 1000;
  }

  emoji-picker {
    --emoji-size: 1.2rem;
    width: 320px;
    height: 380px;
  }

  @media (max-width: 768px) {
    #conversation-sidebar {
      display: block;
    }

    #chat-area {
      display: none;
    }

    .chat-active #conversation-sidebar {
      display: none;
    }

    .chat-active #chat-area {
      display: flex;
    }
  }

  .conversation-list::-webkit-scrollbar,
  .messages-container::-webkit-scrollbar {
    width: 10px;
  }

  .conversation-list::-webkit-scrollbar-thumb,
  .messages-container::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 10px;
  }

  .drop-hover {
    outline: 2px dashed var(--wa-green);
    outline-offset: 4px;
    border-radius: 14px;
  }

  /* Forward modal list height */
  .list-scroll {
    max-height: 360px;
    overflow: auto;
  }

  .list-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border);
  }
</style>

<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>
@include('groups.partials.scripts')
@endsection