{{-- resources/views/chat/index.blade.php --}}
@extends('layouts.app')
@section('body_class', 'page-chat')

@section('content')
@php
  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Str;

  // Header info for the open 1:1 conversation
  if (isset($conversation)) {
      $headerUser = $conversation->user_one_id == auth()->id()
          ? $conversation->userTwo
          : $conversation->userOne;

      $headerName   = $headerUser->name ?? $headerUser->phone ?? 'GekyBot';
      $headerInit   = strtoupper(mb_substr($headerName,0,1));
      $headerAvatar = $headerUser->avatar_path ? Storage::url($headerUser->avatar_path) : null;
  }

  // Ensure we have the user's groups with the latest message loaded (for sidebar)
  if (!isset($groups)) {
      $groups = auth()->user()
          ->groups()
          ->with(['messages' => function ($q) { $q->latest()->limit(1); }])
          ->get();
  }

  // Build light-weight datasets for the forward picker (no extra queries)
  $forwardDMs = collect($conversations ?? [])->map(function($c){
      $me = auth()->id();
      $other = $c->user_one_id == $me ? $c->userTwo : $c->userOne;
      $name = $other?->name ?: $other?->phone ?: 'Unknown';
      $avatar = $other?->avatar_path ? Storage::url($other->avatar_path) : null;
      return ['id' => $c->id, 'name' => $name, 'avatar' => $avatar];
  })->values();

  $forwardGroups = collect($groups ?? [])->map(function($g){
      return ['id'=>$g->id, 'name'=>$g->name, 'avatar' => $g->avatar_path ? Storage::url($g->avatar_path) : null];
  })->values();
@endphp

<div class="container-fluid chat-container">
  <div class="row h-100 g-0">

    {{-- SIDEBAR (now contains the Telegram-like New Chat overlay + Group modal) --}}
    @include('partials.chat_sidebar', [
      'conversations'   => $conversations ?? collect(),
      'groups'          => $groups ?? collect(),
      'botConversation' => $botConversation ?? null,
      // pass $users if your controller already provides; otherwise the sidebar will gracefully hide the Group modal
      'users'           => $users ?? null
    ])

    {{-- CHAT AREA --}}
    <div class="col-md-8 col-lg-9 d-flex flex-column" id="chat-area">

      {{-- Network status banner --}}
      <div id="net-banner" class="w-100 text-center small py-2" style="display:none;background:#b91c1c;color:#fff;">
        <span class="me-2"><i class="bi bi-wifi-off"></i></span>
        <span class="net-text">Network connection lost. Attempting to reconnect…</span>
        <span class="ms-2" id="net-retry-in" style="opacity:.8;"></span>
      </div>

      @if(isset($conversation))
        <div class="chat-header p-3 border-bottom d-flex align-items-center" style="background:var(--card);">
          <button class="btn btn-sm d-md-none me-2" id="back-to-conversations" aria-label="Back">
            <i class="bi bi-arrow-left"></i>
          </button>

          @if(!empty($headerAvatar))
            <img src="{{ $headerAvatar }}" alt="{{ $headerName }} avatar"
                 class="avatar avatar-img me-3" loading="lazy"
                 onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'avatar me-3 rounded-circle bg-brand text-white',textContent:'{{ e($headerInit) }}'}));">
          @else
            <div class="avatar me-3 rounded-circle bg-brand text-white">{{ $headerInit }}</div>
          @endif

          <div class="flex-grow-1">
            <h5 class="mb-0" style="font-weight:800;letter-spacing:.2px;">{{ $headerName }}</h5>
            <small class="muted" id="typing-indicator" style="display:none;">typing…</small>
          </div>

          <div class="d-flex align-items-center gap-3">
            <div id="online-list" class="d-none d-md-flex align-items-center gap-2"></div>
            <div class="dropdown">
              <button class="btn btn-sm" data-bs-toggle="dropdown" aria-label="More">
                <i class="bi bi-three-dots-vertical"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}">View profile</a></li>
                <li><a class="dropdown-item" href="#" id="mute-chat-btn">Mute notifications</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#" id="clear-chat-btn">Clear chat</a></li>
              </ul>
            </div>
          </div>
        </div>

        <div class="messages-container flex-grow-1 p-3 overflow-auto" id="chat-box">
          <div id="messages-loader" class="text-center py-3" style="display:none;">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>

          <div id="messages-container">
            @foreach($conversation->messages as $msg)
              @if(!$msg->is_expired)
                <div
                  class="message mb-3 d-flex {{ $msg->is_own ? 'justify-content-end' : 'justify-content-start' }}"
                  data-message-id="{{ $msg->id }}"
                  data-from-me="{{ $msg->is_own ? '1':'0' }}"
                  data-read="{{ $msg->read_at ? '1' : '0' }}"
                >
                  <div class="message-bubble {{ $msg->is_own ? 'sent' : 'received' }}">
                    @unless($msg->is_own)
                      <small class="sender-name">{{ $msg->sender->name ?? $msg->sender->phone }}</small>
                    @endunless

                    <div class="message-content">

                      {{-- Reply preview --}}
                      @if($msg->reply_to)
                        <div class="reply-preview">
                          <small>
                            Replying to:
                            {{ Str::limit($msg->replyTo->display_body ?? $msg->replyTo->body ?? '[message]', 100) }}
                          </small>
                        </div>
                      @endif

                      {{-- Forwarded header --}}
                      @if($msg->is_forwarded)
                        <div class="mb-1">
                          <small class="muted"><i class="bi bi-forward-fill me-1"></i>Forwarded</small>
                        </div>
                      @endif

                      {{-- Message text --}}
                      <div class="message-text">
                        {!! Str::of(e($msg->display_body ?? $msg->body))
                              ->replaceMatches('/(https?:\/\/[^\s]+)/', fn($m) =>
                                  '<a href="'.$m[0].'" target="_blank" class="linkify">'.$m[0].'</a>') !!}
                      </div>

                      {{-- Attachments --}}
                      @foreach($msg->attachments as $file)
                        @php
                          $ext = strtolower(pathinfo($file->file_path, PATHINFO_EXTENSION));
                          $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                          $fileUrl = Storage::url($file->file_path);
                        @endphp
                        @if($isImage)
                          <div class="mt-2">
                            <img src="{{ $fileUrl }}" alt="image" class="img-fluid rounded media-img" loading="lazy"
                                 data-src="{{ $fileUrl }}" style="max-width:220px;">
                          </div>
                        @else
                          <div class="mt-2">
                            <a href="{{ $fileUrl }}" target="_blank" class="d-inline-flex align-items-center doc-link">
                              <i class="bi bi-file-earmark me-1"></i> {{ $file->original_name }}
                            </a>
                          </div>
                        @endif
                      @endforeach
                    </div>

                    {{-- Footer with timestamp + status --}}
                    <div class="message-footer d-flex justify-content-between align-items-center mt-1">
                      <small class="muted">{{ $msg->created_at->format('h:i A') }}</small>
                      @if($msg->is_own)
                        <div class="status-indicator">
                          @if($msg->status === 'read')
                            <i class="bi bi-check2-all text-primary" title="Read"></i>
                          @elseif($msg->status === 'delivered')
                            <i class="bi bi-check2-all muted" title="Delivered"></i>
                          @else
                            <i class="bi bi-check2 muted" title="Sent"></i>
                          @endif
                        </div>
                      @endif
                    </div>

                    {{-- Reactions --}}
                    <div class="reactions-container mt-1">
                      @foreach($msg->reactions as $reaction)
                        <span class="badge bg-reaction rounded-pill me-1" title="{{ $reaction->user->name }}">
                          {{ $reaction->reaction }}
                        </span>
                      @endforeach
                    </div>
                  </div>

                  {{-- Actions --}}
                  <div class="message-actions dropdown">
                    <button class="btn btn-sm p-0" data-bs-toggle="dropdown" aria-label="Actions">
                      <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu">
                      <li><button class="dropdown-item reply-btn" data-message-id="{{ $msg->id }}">Reply</button></li>
                      <li><button class="dropdown-item forward-btn" data-message-id="{{ $msg->id }}">Forward</button></li>
                      @if($msg->is_own)
                        <li><button class="dropdown-item delete-btn" data-message-id="{{ $msg->id }}">Delete</button></li>
                      @endif
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <div class="d-flex px-3 py-1">
                          <button class="btn btn-sm reaction-btn" data-reaction="👍">👍</button>
                          <button class="btn btn-sm reaction-btn ms-1" data-reaction="❤️">❤️</button>
                          <button class="btn btn-sm reaction-btn ms-1" data-reaction="😂">😂</button>
                          <button class="btn btn-sm reaction-btn ms-1" data-reaction="😮">😮</button>
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
          <form id="chat-form" action="{{ route('chat.send') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
            <input type="hidden" name="reply_to" id="reply-to" value="">
            <input type="hidden" name="is_encrypted" id="is-encrypted" value="0">
            <input type="hidden" name="expires_in" id="expires-in" value="">

            <div class="input-group composer" id="drop-zone">
              <button class="btn btn-ghost" type="button" id="emoji-btn" aria-label="Emoji">
                <i class="bi bi-emoji-smile"></i>
              </button>

              <input type="text" name="body" class="form-control" placeholder="Type a message"
                     required id="message-input" autocomplete="off">

              <div class="btn-group">
                <button class="btn btn-ghost" type="button" id="security-btn" aria-label="Security options">
                  <i class="bi bi-shield-lock"></i>
                </button>
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
            <small class="muted d-block mt-1 d-md-none">Tip: Use the sidebar to start a new chat or group.</small>
          </form>
        </div>
      @else
        {{-- Empty state when no conversation is open --}}
        <div class="d-flex flex-column align-items-center justify-content-center h-100">
          <div class="text-center p-4">
            <div class="avatar bg-card mb-3 mx-auto rounded-circle d-flex align-items-center justify-content-center" style="width:80px;height:80px;">
              <i class="bi bi-chat-left-text" style="font-size:2rem;"></i>
            </div>
            <h4 style="font-weight:800;">Welcome to GekyChat</h4>
            <p class="muted mb-3">Select a conversation or start a new one</p>
            {{-- Open the Telegram-style New Chat overlay that lives in the sidebar --}}
            <button type="button" class="btn btn-wa mt-2" id="open-new-chat-empty">
              <i class="bi bi-plus"></i> New Chat
            </button>
            {{-- Open the Create Group modal if present --}}
            <button type="button" class="btn btn-outline-wa mt-2 ms-2" data-bs-toggle="modal" data-bs-target="#createGroupModal">
              <i class="bi bi-people"></i> New Group
            </button>
          </div>
        </div>

        <script>
          // Proxy the empty-state button to the sidebar "New Chat" opener
          document.getElementById('open-new-chat-empty')?.addEventListener('click', () => {
            document.getElementById('open-new-chat')?.click();
          });
        </script>
      @endif
    </div>
  </div>
</div>

{{-- Emoji picker host --}}
<div id="emoji-picker-wrap" class="emoji-wrap" style="display:none;">
  <emoji-picker id="emoji-picker" class="shadow-lg"></emoji-picker>
</div>

{{-- Security modal --}}
<div id="security-modal" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Security Options</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="encrypt-toggle">
          <label class="form-check-label" for="encrypt-toggle">End-to-end encryption</label>
        </div>
        <div class="mb-3">
          <label class="form-label">Self-destruct timer</label>
          <select class="form-select" id="expiration-select">
            <option value="0" selected>No expiration</option>
            <option value="1">1 hour</option>
            <option value="24">1 day</option>
            <option value="168">1 week</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="apply-security">Apply</button>
      </div>
    </div>
  </div>
</div>

{{-- Forward modal (multi-select, searchable) --}}
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
{!! json_encode(['conversations' => $forwardDMs, 'groups' => $forwardGroups], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}
</script>

<audio id="notification-sound" src="{{ asset('sounds/notification.wav') }}" preload="auto"></audio>

<style>
  :root{ --bubble-radius:16px; --reaction-bg: rgba(0,0,0,0.1); }
  .chat-container{ height: calc(100dvh - var(--nav-h)); }

  /* Independent scroll columns */
  #conversation-sidebar, #chat-area{ min-height:0; height: 100%; }
  #conversation-sidebar{ display:flex; flex-direction:column; }
  #chat-area{ display:flex; flex-direction:column; }
  .conversation-list{ flex:1 1 auto; overflow:auto; }
  .messages-container{ flex:1 1 auto; overflow:auto; min-height:0; position: relative; contain: strict; }

  .search-wrap .input-group-text{ background:var(--card); border-color:var(--border); color:var(--wa-muted); }
  .search-wrap .form-control{ background:var(--card); border-color:var(--border); color:var(--text); }

  .conversation-item{ color:var(--text); transition:background-color .2s ease; border-bottom:1px solid var(--border); }
  .conversation-item:hover{ background:rgba(255,255,255,.04); }
  [data-theme="light"] .conversation-item:hover{ background:rgba(0,0,0,.04); }
  .conversation-item.unread{ font-weight:700; }
  .unread-badge{ background:var(--wa-green); color:#062a1f; }

  .avatar{ width:40px; height:40px; font-size:1.1rem; display:grid; place-items:center; }
  .avatar-img{ width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid var(--border); display:block; }
  .bg-brand{ background:var(--wa-deep)!important; }
  .bg-avatar{ background:#667085!important; }
  .bg-card{ background:var(--card)!important; }
  .bg-reaction{ background:var(--reaction-bg)!important; }

  .messages-container{
    background: radial-gradient(1000px 600px at 10% -10%, var(--bg-accent, #0f1a20) 0, var(--bg) 60%), var(--bg);
    display:flex; flex-direction:column; padding:12px;
  }

  .message-bubble{
    max-width:min(75%, 680px); padding:10px 12px; border-radius:var(--bubble-radius);
    position:relative; box-shadow:0 2px 6px rgba(0,0,0,.08);
  }
  .message-bubble.sent{ background:var(--bubble-sent-bg); color:var(--bubble-sent-text); border-top-right-radius:6px; }
  .message-bubble.received{ background:var(--bubble-recv-bg); color:var(--bubble-recv-text); border-top-left-radius:6px; }

  .sender-name{ font-weight:700; opacity:.85; display:block; margin-bottom:2px; }
  .message-text{ white-space:pre-wrap; word-break:break-word; }
  .media-img{ max-width:220px; border-radius:10px; transition:opacity 0.3s ease; }
  .media-img[data-src]:not([src]){ opacity:0; }
  .media-img.loading{ opacity:0.5; }

  .linkify{ color:var(--wa-green); text-decoration:none; }
  .linkify:hover{ text-decoration:underline; }

  .message-actions{ visibility:hidden; align-self:center; margin:0 8px; }
  .message:hover .message-actions{ visibility:visible; }

  .reply-preview{ border-left:3px solid var(--border); padding-left:8px; margin-bottom:6px; opacity:.85; font-style:italic; }
  .reply-preview-container{ background:var(--card); }

  .reactions-container{ display:flex; flex-wrap:wrap; gap:2px; }
  .muted{ color:var(--wa-muted)!important; font-size:var(--fs-sm); }

  .composer .form-control{ background:var(--input-bg); border-color:var(--input-border); color:var(--text); border-radius:14px; }
  .composer .form-control:focus{ border-color:var(--wa-green); box-shadow:none; }
  .btn-ghost{ background:var(--card); border:1px solid var(--border); color:var(--text); border-radius:14px; }
  .btn-ghost:hover{ background:rgba(255,255,255,.06); }
  [data-theme="light"] .btn-ghost:hover{ background:rgba(0,0,0,.04); }

  .btn-wa{ background:var(--wa-green); border:none; color:#062a1f; font-weight:800; border-radius:14px; }
  .btn-outline-wa{ border-color:var(--wa-green); color:var(--wa-green); border-radius:14px; }

  .message-input-container{ position:sticky; bottom:0; z-index:2; background:var(--bg); }

  .emoji-wrap{ position:fixed; right:16px; bottom:86px; z-index:1000; }
  emoji-picker{ --emoji-size:1.2rem; width:320px; height:380px; }

  @media (max-width: 768px){
    #conversation-sidebar{ display:block; }
    #chat-area{ display:none; }
    .chat-active #conversation-sidebar{ display:none; }
    .chat-active #chat-area{ display:flex; }
  }

  .conversation-list::-webkit-scrollbar,
  .messages-container::-webkit-scrollbar{ width:10px; }
  .conversation-list::-webkit-scrollbar-thumb,
  .messages-container::-webkit-scrollbar-thumb{ background:var(--border); border-radius:10px; }

  .drop-hover{ outline:2px dashed var(--wa-green); outline-offset:4px; border-radius:14px; }

  /* Forward modal list height */
  .list-scroll{ max-height: 360px; overflow:auto; }
  .list-avatar{ width:32px; height:32px; border-radius:50%; object-fit:cover; border:1px solid var(--border); }
</style>

{{-- Emoji Picker web component --}}
<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>

{{-- Chat scripts (reply/forward/delete/react, Echo, etc.) --}}
@include('chat.partials.scripts')
@endsection
