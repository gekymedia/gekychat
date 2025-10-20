{{-- resources/views/partials/chat_sidebar.blade.php --}}
@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    // Efficient data loading with proper error handling
    $people = collect();
    $hasContactsTable = false;

    try {
        if (\Schema::hasTable('contacts')) {
            $hasContactsTable = true;
            $contacts = \App\Models\Contact::query()
                ->with([
                    'contactUser' => function ($query) {
                        $query->select('id', 'name', 'phone', 'avatar_path');
                    },
                ])
                ->where('user_id', auth()->id())
                ->whereNotNull('contact_user_id')
                ->orderByRaw('COALESCE(NULLIF(display_name, ""), normalized_phone)')
                ->get();

            $people = $contacts
                ->map(function ($contact) {
                    $user = $contact->contactUser;
                    if (!$user) {
                        return null;
                    }

                    return (object) [
                        'id' => $user->id,
                        'name' => $contact->display_name ?: $user->name,
                        'phone' => $user->phone,
                        'avatar_path' => $user->avatar_path,
                        'is_contact' => true,
                    ];
                })
                ->filter();
        }
    } catch (\Throwable $e) {
        // Log error in production
        if (app()->environment('production')) {
            \Log::error('Contacts table query failed', ['error' => $e->getMessage()]);
        }
    }

    // Fallback to users if no contacts found
    if ($people->isEmpty()) {
        $people = \App\Models\User::where('id', '!=', auth()->id())
            ->orderByRaw('COALESCE(NULLIF(name, ""), phone)')
            ->get(['id', 'name', 'phone', 'avatar_path'])
            ->map(function ($user) {
                return (object) [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'avatar_path' => $user->avatar_path,
                    'is_contact' => false,
                ];
            });
    }

    // Base URLs for navigation
    $convShowBase = route('chat.show', ['conversation' => 'SLUG']);
    $convShowBase = preg_replace('#/SLUG/?$#', '/', $convShowBase);

    $groupShowBase = route('groups.show', ['group' => 'SLUG']);
    $groupShowBase = preg_replace('#/SLUG/?$#', '/', $groupShowBase);

    // Cache user IDs for efficient filtering
    $userIds = $people->pluck('id')->toArray();
@endphp

<style>
    /* ===== SIDEBAR SPECIFIC STYLES ===== */
    .sidebar-container {
        background: var(--bg);
        border-right: 1px solid var(--border);
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .sidebar-header {
        background: var(--card);
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .conversation-list {
        flex: 1;
        overflow: auto;
        background: var(--bg);
    }

    /* Notification Styles */
    .notification-prompt {
        z-index: 1060;
        animation: slideInUp 0.3s ease-out;
    }

    .alert-wa-notify {
        background: var(--card);
        border: 1px dashed var(--border);
        color: var(--text);
        border-radius: 12px;
    }

    /* Button Styles */
    .btn-wa {
        background: var(--wa-green);
        color: #fff;
        border: none;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .btn-wa:hover {
        filter: brightness(0.95);
        transform: translateY(-1px);
    }

    .btn-outline-wa {
        border: 1px solid var(--wa-green);
        color: var(--wa-green);
        background: transparent;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .btn-outline-wa:hover {
        background: color-mix(in srgb, var(--wa-green) 10%, transparent);
    }

    .btn-wa.active,
    .btn-outline-wa.active {
        background: var(--wa-green);
        color: white;
    }

    /* Avatar Styles */
    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        flex-shrink: 0;
    }

    .avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid var(--border);
    }

    .bg-avatar {
        background: color-mix(in srgb, var(--wa-green) 15%, transparent);
        color: var(--wa-green);
    }

    /* Conversation Items */
    .conversation-item {
        transition: all 0.2s ease;
        border-bottom: 1px solid var(--border);
        position: relative;
    }

    .conversation-item:hover {
        background: color-mix(in srgb, var(--wa-green) 5%, transparent);
        transform: translateX(2px);
    }

    .conversation-item.unread {
        background: color-mix(in srgb, var(--wa-green) 8%, transparent);
        border-left: 3px solid var(--wa-green);
    }

    .conversation-item.unread::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: var(--wa-green);
    }

    .unread-badge {
        background: var(--wa-green);
        color: #062a1f;
        font-weight: 700;
        font-size: 0.75rem;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Search & Filter Styles */
    .search-container {
        position: relative;
    }

    .search-results {
        z-index: 1040;
        top: 100%;
        max-height: 400px;
        overflow: auto;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 0 0 12px 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .search-filters {
        animation: slideDown 0.2s ease-out;
    }

    .filter-btn {
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 16px;
        transition: all 0.2s ease;
    }

    .filter-btn.active {
        background: var(--wa-green);
        color: white;
        border-color: var(--wa-green);
    }

    /* New Chat & Group Creation - FIXED */
    .creation-panel {
        background: var(--bg);
        border-bottom: 1px solid var(--border);
        animation: slideDown 0.2s ease-out;
        overflow: auto;
        max-height: 70vh;
    }

    .creation-panel .p-3 {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .sticky-head {
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--bg);
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .chips-container {
        position: sticky;
        top: 0;
        z-index: 1;
        background: var(--bg);
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--border);
        max-height: 72px;
        overflow: auto;
    }

    .chip {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 4px 8px;
        font-size: 0.875rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.2s ease;
    }

    .chip:hover {
        background: color-mix(in srgb, var(--wa-green) 10%, var(--card));
    }

    .chip-remove {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--border);
        border: none;
        font-size: 0.75rem;
        transition: all 0.2s ease;
    }

    .chip-remove:hover {
        background: var(--wa-green);
        color: white;
    }

    /* List Styles */
    .list-container {
        max-height: 280px;
        overflow: auto;
        border-radius: 8px;
        flex-shrink: 0;
    }

    .list-item {
        transition: all 0.2s ease;
        border-radius: 8px;
        margin-bottom: 2px;
    }

    .list-item:hover {
        background: color-mix(in srgb, var(--wa-green) 5%, transparent);
    }

    .list-item.active {
        background: color-mix(in srgb, var(--wa-green) 10%, transparent);
        border-left: 3px solid var(--wa-green);
    }

    /* Character Counters */
    .char-counter {
        font-size: 0.75rem;
        transition: color 0.2s ease;
    }

    .char-counter.warning {
        color: #dc2626;
    }

    /* Animations */
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Scrollbar Styling */
    .conversation-list::-webkit-scrollbar,
    .list-container::-webkit-scrollbar,
    .search-results::-webkit-scrollbar {
        width: 6px;
    }

    .conversation-list::-webkit-scrollbar-track,
    .list-container::-webkit-scrollbar-track,
    .search-results::-webkit-scrollbar-track {
        background: transparent;
    }

    .conversation-list::-webkit-scrollbar-thumb,
    .list-container::-webkit-scrollbar-thumb,
    .search-results::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 3px;
    }

    .conversation-list::-webkit-scrollbar-thumb:hover,
    .list-container::-webkit-scrollbar-thumb:hover,
    .search-results::-webkit-scrollbar-thumb:hover {
        background: var(--wa-muted);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .sidebar-container {
            border-right: none;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-header {
            padding: 1rem;
        }

        .conversation-item {
            padding: 0.75rem 1rem;
        }

        .search-results {
            position: fixed;
            left: 0;
            right: 0;
            top: 140px;
            max-height: calc(100vh - 140px);
            z-index: 1050;
        }

        .creation-panel {
            position: fixed;
            left: 0;
            right: 0;
            top: 0;
            bottom: 0;
            z-index: 1060;
            overflow: auto;
            max-height: 100vh;
        }
    }

    @media (max-width: 576px) {
        .avatar {
            width: 32px;
            height: 32px;
            font-size: 0.875rem;
        }

        .conversation-item {
            padding: 0.5rem 0.75rem;
        }

        .btn-wa,
        .btn-outline-wa {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }
    }

    /* Loading States */
    .skeleton-loader {
        background: linear-gradient(90deg, var(--border) 25%, var(--card) 50%, var(--border) 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
        border-radius: 4px;
    }

    @keyframes loading {
        0% {
            background-position: 200% 0;
        }

        100% {
            background-position: -200% 0;
        }
    }

    /* Focus Management */
    .conversation-item:focus,
    .list-item:focus,
    .btn:focus {
        outline: 2px solid var(--wa-green);
        outline-offset: 2px;
    }

    /* High Contrast Support */
    @media (prefers-contrast: high) {
        .conversation-item {
            border-bottom: 2px solid var(--text);
        }

        .conversation-item.unread {
            border-left: 4px solid var(--wa-green);
        }
    }

    /* Reduced Motion */
    @media (prefers-reduced-motion: reduce) {

        .conversation-item,
        .btn-wa,
        .btn-outline-wa,
        .chip {
            transition: none;
        }

        .notification-prompt {
            animation: none;
        }
    }
</style>

{{-- Notification Prompt --}}
<div id="notification-prompt" class="notification-prompt position-fixed bottom-0 end-0 m-3" style="display: none;">
    <div class="card shadow-lg border-0" style="width: min(320px, 90vw); background: var(--card);">
        <div class="card-body p-3">
            <div class="d-flex align-items-start gap-3">
                <div class="flex-shrink-0">
                    <i class="bi bi-bell-fill text-wa" style="font-size: 1.5rem;"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1 fw-bold">Enable notifications</h6>
                    <p class="small mb-2 text-muted">
                        Get alerts for new messages even when this tab isn't active.
                    </p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-wa btn-sm flex-grow-1" id="enable-notifications">
                            Enable
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="dismiss-notifications">
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Sidebar Container --}}
<div class="sidebar-container col-md-4 col-lg-3 d-flex flex-column" id="conversation-sidebar"
    data-conv-show-base="{{ $convShowBase }}" data-group-show-base="{{ $groupShowBase }}"
    data-user-ids="{{ json_encode($userIds) }}">

    {{-- Sidebar Header --}}
    <div class="sidebar-header p-3">
        <div class="d-flex align-items-center justify-content-between">
            {{-- Logo/Brand --}}
            <a href="{{ url('/') }}" class="sidebar-brand">
                <div class="sidebar-brand-logo">
                    <img src="{{ asset('icons/icon-32x32.png') }}" alt="{{ config('app.name', 'GekyChat') }}"
                        loading="eager">
                </div>
                {{ config('app.name', 'GekyChat') }}
            </a>

            {{-- User Menu & Theme Toggle --}}
            <div class="d-flex align-items-center gap-2">
                {{-- Theme Toggle --}}
                <button class="theme-toggle-sidebar btn btn-sm" title="Toggle theme" aria-pressed="false">
                    <i class="bi bi-moon-stars-fill me-1" aria-hidden="true"></i> Theme
                </button>

                {{-- User Dropdown --}}
                @auth
                    <div class="dropdown sidebar-user-menu">
                        <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                            aria-label="User menu">
                            @if (Auth::user()->avatar_path)
                                <img src="{{ Storage::url(Auth::user()->avatar_path) }}" class="user-avatar"
                                    alt="{{ Auth::user()->name ?? Auth::user()->phone }}"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="user-avatar-placeholder" style="display: none;">
                                    {{ Str::upper(Str::substr(Auth::user()->name ?? (Auth::user()->phone ?? 'U'), 0, 1)) }}
                                </div>
                            @else
                                <div class="user-avatar-placeholder">
                                    {{ Str::upper(Str::substr(Auth::user()->name ?? (Auth::user()->phone ?? 'U'), 0, 1)) }}
                                </div>
                            @endif
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end sidebar-dropdown p-2">
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2"
                                    href="{{ route('profile.edit') }}">
                                    <i class="bi bi-person" aria-hidden="true"></i>
                                    <span>Profile</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2"
                                    href="{{ route('groups.create') }}">
                                    <i class="bi bi-people" aria-hidden="true"></i>
                                    <span>New Group</span>
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 text-danger"
                                    href="{{ route('logout') }}"
                                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                    <span>Logout</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                @endauth
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0 fw-bold">Chats</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-wa btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#sb-new-chat"
                    aria-controls="sb-new-chat" id="new-chat-btn" aria-label="Start new chat">
                    <i class="bi bi-plus" aria-hidden="true"></i> New
                </button>
                <button class="btn btn-outline-wa btn-sm" type="button" data-bs-toggle="collapse"
                    data-bs-target="#sb-create-group" aria-controls="sb-create-group" id="new-group-btn"
                    aria-label="Create new group">
                    <i class="bi bi-people" aria-hidden="true"></i> Group
                </button>
            </div>
        </div>

        {{-- Global Search --}}
        <div class="search-container">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                    <i class="bi bi-search text-muted" aria-hidden="true"></i>
                </span>
                <input type="text" class="form-control border-start-0"
                    placeholder="Search messages, contacts, groups…" id="chat-search" autocomplete="off"
                    aria-label="Search conversations">
            </div>

            {{-- Search Filters --}}
            <div id="search-filters" class="d-flex flex-wrap gap-1 mt-2" style="display: none;">
                <button class="btn btn-outline-secondary btn-sm filter-btn active" data-filter="all"
                    aria-pressed="true">All</button>
                <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="contacts"
                    aria-pressed="false">Contacts</button>
                <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="users"
                    aria-pressed="false">People</button>
                <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="groups"
                    aria-pressed="false">Groups</button>
                <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="messages"
                    aria-pressed="false">Messages</button>
                <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="unread"
                    aria-pressed="false">Unread</button>
            </div>

            {{-- Search Results --}}
            <div id="chat-search-results" class="search-results list-group position-absolute w-100 d-none"></div>
        </div>
    </div>

    {{-- New Chat Panel --}}
    <div id="sb-new-chat" class="creation-panel collapse border-bottom">
        <div class="p-3">
            <form id="sb-nc-form" action="{{ route('chat.start') }}" method="POST" class="d-none">
                @csrf
                <input type="hidden" name="user_id" id="sb-nc-user-id">
                <input type="hidden" name="phone" id="sb-nc-phone">
            </form>

            <div class="d-flex align-items-center justify-content-between mb-2">
                <strong class="h6 mb-0">Start a new chat</strong>
                <small class="text-muted" id="sb-nc-count">{{ $people->count() }} contacts</small>
            </div>

            {{-- Contact Search --}}
            <div class="input-group mb-2">
                <span class="input-group-text bg-light border-end-0">
                    <i class="bi bi-search text-muted" aria-hidden="true"></i>
                </span>
                <input type="text" id="sb-nc-search" class="form-control border-start-0"
                    placeholder="Search contacts by name or phone…" autocomplete="off" aria-label="Search contacts">
            </div>

            {{-- Phone Input --}}
            <div class="input-group mb-3">
                <span class="input-group-text bg-light border-end-0">
                    <i class="bi bi-telephone text-muted" aria-hidden="true"></i>
                </span>
                <input type="tel" id="sb-nc-phone-input" class="form-control border-start-0"
                    placeholder="Start by phone (e.g. +233…)" autocomplete="tel" aria-label="Phone number">
                <button class="btn btn-outline-wa border-start-0" id="sb-nc-start-phone" type="button">
                    Start
                </button>
            </div>

            {{-- Contacts List --}}
            <div id="sb-nc-list" class="list-container list-group">
                @foreach ($people as $user)
                    @php
                        $displayName = $user->name ?: $user->phone ?: 'User #' . $user->id;
                        $initial = Str::upper(Str::substr($displayName, 0, 1));
                        $avatar = $user->avatar_path ? Storage::url($user->avatar_path) : null;
                    @endphp
                    <button type="button"
                        class="list-item list-group-item list-group-item-action d-flex align-items-center gap-2 sb-nc-row"
                        data-id="{{ $user->id }}" data-name="{{ Str::lower($user->name ?? '') }}"
                        data-phone="{{ Str::lower($user->phone ?? '') }}"
                        aria-label="Start chat with {{ $displayName }}">
                        {{-- Avatar --}}
                        @if ($avatar)
                            <img src="{{ $avatar }}" class="avatar-img" width="32" height="32"
                                alt="" loading="lazy"
                                onerror="this.replaceWith(this.nextElementSibling); this.remove();">
                            <div class="avatar bg-avatar d-none">{{ $initial }}</div>
                        @else
                            <div class="avatar bg-avatar">{{ $initial }}</div>
                        @endif

                        {{-- User Info --}}
                        <div class="flex-grow-1 text-start min-width-0">
                            <div class="fw-semibold text-truncate">{{ $displayName }}</div>
                            <div class="small text-muted text-truncate">
                                {{ $user->phone ?: 'ID #' . $user->id }}
                            </div>
                        </div>

                        <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
                    </button>
                @endforeach
            </div>

            {{-- Action Buttons --}}
            <div class="d-flex gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm flex-grow-1" data-bs-toggle="collapse"
                    data-bs-target="#sb-new-chat">
                    Close
                </button>
                <button type="button" id="sb-nc-start" class="btn btn-wa btn-sm flex-grow-1" disabled>
                    <i class="bi bi-chat-dots me-1" aria-hidden="true"></i> Start Chat
                </button>
            </div>
        </div>
    </div>

    {{-- Create Group Panel - FIXED LAYOUT --}}
    <div id="sb-create-group" class="creation-panel collapse border-bottom">
        <div class="p-3">
            <form id="sb-gp-form" action="{{ route('groups.store') }}" method="POST" enctype="multipart/form-data"
                novalidate>
                @csrf

                <div class="sticky-head">
                    {{-- Group Photo --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-4">
                            <label for="sb-gp-avatar" class="form-label fw-semibold">Group Photo</label>
                            <div class="d-flex align-items-center gap-3">
                                <img id="sb-gp-avatar-preview" src="{{ asset('images/group-default.png') }}"
                                    class="rounded border" width="64" height="64" alt="Group avatar preview"
                                    style="object-fit: cover;">
                                <div class="flex-grow-1">
                                    <input type="file" name="avatar" accept="image/*"
                                        class="form-control form-control-sm" id="sb-gp-avatar"
                                        aria-describedby="avatarHelp">
                                    <div id="avatarHelp" class="form-text">
                                        JPG, PNG or WebP • up to 2MB
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Group Info --}}
                        <div class="col-12 col-sm-8">
                            <label for="sb-gp-name" class="form-label fw-semibold">
                                Group Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" id="sb-gp-name" maxlength="64"
                                class="form-control" placeholder="e.g. Family, Project, Study Group" required>
                            <div class="char-counter text-muted mt-1" id="sb-gp-name-left">64 characters left</div>

                            <label for="sb-gp-description" class="form-label fw-semibold mt-3">
                                Description
                            </label>
                            <textarea name="description" id="sb-gp-description" maxlength="200" class="form-control" rows="2"
                                placeholder="What's this group about?"></textarea>
                            <div class="char-counter text-muted mt-1" id="sb-gp-desc-left">200 characters left</div>
                        </div>
                    </div>

                    {{-- Group Type --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Group Type</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_private" id="sb-gp-public"
                                    value="0" checked>
                                <label class="form-check-label" for="sb-gp-public">
                                    <i class="bi bi-globe me-1" aria-hidden="true"></i> Public
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_private" id="sb-gp-private"
                                    value="1">
                                <label class="form-check-label" for="sb-gp-private">
                                    <i class="bi bi-lock me-1" aria-hidden="true"></i> Private
                                </label>
                            </div>
                        </div>
                    </div>
                    {{-- Type --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="sb-gp-channel"
                                    value="channel" checked>
                                <label class="form-check-label" for="sb-gp-channel">
                                    <i class="bi bi-globe me-1" aria-hidden="true"></i> Channel
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="sb-gp-group"
                                    value="group">
                                <label class="form-check-label" for="sb-gp-group">
                                    <i class="bi bi-lock me-1" aria-hidden="true"></i> Group
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Selected Members Chips --}}
                <div id="sb-gp-chips" class="chips-container d-flex flex-wrap gap-2 mb-3"></div>

                {{-- Add Participants --}}
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label fw-semibold mb-0">
                            Add participants <span class="text-danger">*</span>
                        </label>
                        <small class="text-muted">Selected: <span id="sb-gp-count">0</span></small>
                    </div>

                    {{-- Participant Search --}}
                    <div class="input-group mb-2">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted" aria-hidden="true"></i>
                        </span>
                        <input type="text" id="sb-gp-filter" class="form-control border-start-0"
                            placeholder="Search by name or phone…" autocomplete="off"
                            aria-label="Search participants">
                    </div>

                    {{-- Participants List --}}
                    <div id="sb-gp-list" class="list-container list-group">
                        @foreach ($people as $user)
                            @php
                                $displayName = $user->name ?: $user->phone ?: 'User #' . $user->id;
                                $initial = Str::upper(Str::substr($displayName, 0, 1));
                                $avatar = $user->avatar_path ? Storage::url($user->avatar_path) : null;
                            @endphp
                            <label class="list-item list-group-item d-flex align-items-center gap-2 sb-gp-row"
                                data-name="{{ Str::lower($user->name ?? '') }}"
                                data-phone="{{ Str::lower($user->phone ?? '') }}">
                                <input type="checkbox" class="form-check-input me-1 sb-gp-check flex-shrink-0"
                                    name="members[]" value="{{ $user->id }}"
                                    aria-label="Select {{ $displayName }}">

                                {{-- Avatar --}}
                                @if ($avatar)
                                    <img src="{{ $avatar }}" class="avatar-img" width="32" height="32"
                                        alt="" loading="lazy"
                                        onerror="this.replaceWith(this.nextElementSibling); this.remove();">
                                    <div class="avatar bg-avatar d-none">{{ $initial }}</div>
                                @else
                                    <div class="avatar bg-avatar">{{ $initial }}</div>
                                @endif

                                {{-- User Info --}}
                                <div class="flex-grow-1 text-start min-width-0">
                                    <div class="fw-semibold text-truncate">{{ $displayName }}</div>
                                    <div class="small text-muted text-truncate">
                                        {{ $user->phone ?: 'ID #' . $user->id }}
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    {{-- Selection Actions --}}
                    <div class="d-flex gap-2 mt-2">
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="sb-gp-clear">
                            Clear
                        </button>
                        <button class="btn btn-outline-wa btn-sm" type="button" id="sb-gp-select-all">
                            Select all (filtered)
                        </button>
                    </div>
                </div>

                {{-- Form Actions --}}
                <div class="d-flex gap-2 mt-auto pt-3">
                    <button type="button" class="btn btn-outline-secondary flex-grow-1 btn-sm"
                        data-bs-toggle="collapse" data-bs-target="#sb-create-group">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-wa flex-grow-1 btn-sm" id="sb-gp-create">
                        <i class="bi bi-people-fill me-1" aria-hidden="true"></i> Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Conversations List --}}
    <div class="conversation-list" id="conversation-list" aria-label="Conversations list">
        {{-- Notification Denied Reminder --}}
        <div id="notify-denied-inline" class="alert alert-wa-notify m-3 d-none" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-info-circle text-wa" aria-hidden="true"></i>
                <div class="flex-grow-1 small">
                    You disabled notifications in your browser settings. Turn them on in Site Settings to get message
                    alerts.
                </div>
                <button class="btn btn-outline-wa btn-sm" id="dismiss-denied-inline">
                    Dismiss
                </button>
            </div>
        </div>

        {{-- GekyBot Conversation --}}
        @if (isset($botConversation))
            @php
                $botLastMsg = optional($botConversation->latestMessage);
                $lastBot = $botLastMsg?->display_body ?? 'Start chatting with GekyBot';
                $lastBotTime = $botLastMsg?->created_at?->diffForHumans() ?? 'No messages yet';
            @endphp
            <a href="{{ route('chat.show', $botConversation->slug) }}"
                class="conversation-item d-flex align-items-center p-3 text-decoration-none" data-name="gekybot"
                data-phone="" data-last="{{ Str::lower($lastBot) }}"
                aria-label="Chat with GekyBot, last message: {{ $lastBot }}">
                <div class="avatar me-3 bg-avatar">🤖</div>
                <div class="flex-grow-1 min-width-0">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-truncate">GekyBot</strong>
                        <small class="text-muted">{{ $lastBotTime }}</small>
                    </div>
                    <p class="mb-0 text-truncate text-muted">{{ $lastBot }}</p>
                </div>
            </a>
        @endif

        {{-- Direct Conversations --}}
        @foreach ($conversations ?? collect() as $conversation)
            @php
                $displayName = $conversation->title;
                $initial = Str::upper(Str::substr($displayName, 0, 1));
                $avatarUrl = $conversation->avatar_url;
                $lastMsg = $conversation->lastMessage;
                $lastBody = $lastMsg?->display_body ?? ($lastMsg?->body ?? 'No messages yet');
                $lastTime = $lastMsg?->created_at?->diffForHumans() ?? 'No messages yet';
                $unreadCount = (int) ($conversation->unread_count ?? 0);
                $otherUser = $conversation->other_user;
                $otherPhone = $otherUser?->phone ?? '';
            @endphp

            <a href="{{ route('chat.show', $conversation->slug) }}"
                class="conversation-item d-flex align-items-center p-3 text-decoration-none {{ $unreadCount > 0 ? 'unread' : '' }}"
                data-name="{{ Str::lower($displayName) }}" data-phone="{{ Str::lower($otherPhone) }}"
                data-last="{{ Str::lower($lastBody) }}" data-unread="{{ $unreadCount }}"
                aria-label="{{ $displayName }}, {{ $unreadCount > 0 ? $unreadCount . ' unread messages, ' : '' }}last message: {{ $lastBody }}">

                {{-- Avatar --}}
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" class="avatar avatar-img me-3" alt="" loading="lazy"
                        onerror="this.replaceWith(this.nextElementSibling); this.remove();">
                    <div class="avatar me-3 bg-avatar d-none">{{ $initial }}</div>
                @else
                    <div class="avatar me-3 bg-avatar">{{ $initial }}</div>
                @endif

                {{-- Conversation Info --}}
                <div class="flex-grow-1 min-width-0">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-truncate">{{ $displayName }}</strong>
                        <small class="text-muted">{{ $lastTime }}</small>
                    </div>
                    <p class="mb-0 text-truncate text-muted">{{ $lastBody }}</p>
                </div>

                {{-- Unread Badge --}}
                @if ($unreadCount > 0)
                    <span class="unread-badge rounded-pill ms-2" aria-label="{{ $unreadCount }} unread messages">
                        {{ $unreadCount }}
                    </span>
                @endif
            </a>
        @endforeach

        {{-- Groups Section --}}
        @if (($groups ?? collect())->count() > 0)
            <div class="px-3 pt-3 pb-2 text-uppercase small text-muted fw-semibold">Groups</div>

            @foreach ($groups as $group)
                @php
                    $latestMessage = optional($group->messages->first());
                    $lastBody = $latestMessage?->body ?? 'No messages yet';
                    $lastTime = $latestMessage?->created_at?->diffForHumans() ?? 'No messages yet';
                    $initial = Str::upper(Str::substr($group->name ?? 'Group', 0, 1));
                    $avatarUrl = $group->avatar_path ? Storage::url($group->avatar_path) : null;
                @endphp

                <a href="{{ route('groups.show', $group->slug) }}"
                    class="conversation-item d-flex align-items-center p-3 text-decoration-none"
                    data-name="{{ Str::lower($group->name ?? '') }}" data-phone=""
                    data-last="{{ Str::lower($lastBody) }}"
                    aria-label="{{ $group->name }} group, last message: {{ $lastBody }}">

                    {{-- Group Avatar --}}
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" class="avatar avatar-img me-3" alt="" loading="lazy"
                            onerror="this.replaceWith(this.nextElementSibling); this.remove();">
                        <div class="avatar me-3 bg-avatar d-none">{{ $initial }}</div>
                    @else
                        <div class="avatar me-3 bg-avatar">{{ $initial }}</div>
                    @endif

                    {{-- Group Info --}}
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-truncate">{{ $group->name }}</strong>
                            <small class="text-muted">{{ $lastTime }}</small>
                        </div>
                        <p class="mb-0 text-truncate text-muted">{{ $lastBody }}</p>
                    </div>

                    <span class="badge rounded-pill ms-2 bg-light text-dark" aria-label="Group">
                        Group
                    </span>
                </a>
            @endforeach
        @endif

        <!-- Invite Modal -->
        <div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background: var(--card);">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold" id="inviteModalLabel">Invite to GekyChat</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            style="filter: invert(var(--invert,0));"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">This contact isn't on <strong>{{ config('app.name', 'GekyChat') }}</strong>
                            yet.</p>
                        <div class="small text-muted mb-3" id="invitePhoneHint"></div>

                        <div class="d-grid gap-2">
                            <a id="inviteSmsBtn" class="btn btn-wa btn-sm" target="_blank" rel="noopener">Send SMS
                                Invite</a>
                            <button id="inviteShareBtn" class="btn btn-outline-wa btn-sm">Share…</button>
                            <div class="input-group">
                                <input id="inviteLinkInput" class="form-control form-control-sm" readonly>
                                <button id="inviteCopyBtn" class="btn btn-outline-secondary btn-sm"
                                    type="button">Copy</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@include('partials.scripts')

<script>
    // Production-ready sidebar functionality will be provided in a separate script file
    // This includes: search, new chat, group creation, and notification management
</script>
<script>
    (function() {
        const startByPhoneBtn = document.getElementById('sb-nc-start-phone');
        const phoneInput = document.getElementById('sb-nc-phone-input');

        // Invite modal elements
        const inviteModalEl = document.getElementById('inviteModal');
        const invitePhoneHint = document.getElementById('invitePhoneHint');
        const inviteSmsBtn = document.getElementById('inviteSmsBtn');
        const inviteShareBtn = document.getElementById('inviteShareBtn');
        const inviteLinkInput = document.getElementById('inviteLinkInput');
        const inviteCopyBtn = document.getElementById('inviteCopyBtn');

        let bsInviteModal = null;
        if (inviteModalEl && window.bootstrap) {
            bsInviteModal = new bootstrap.Modal(inviteModalEl, {
                keyboard: true
            });
        }

        function normalizeDigits(s) {
            return (s || '').replace(/[^\d+]/g, '');
        }

        async function startChatByPhone() {
            const raw = phoneInput.value.trim();
            if (!raw) return;

            const payload = new URLSearchParams();
            payload.set('phone', raw);

            // CSRF (Laravel)
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const res = await fetch(`{{ url('/api/v1/start-chat-with-phone') }}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-CSRF-TOKEN': token || ''
                    },
                    body: payload.toString()
                });

                const data = await res.json();

                if (!data?.success) {
                    throw new Error('Unexpected response');
                }

                // Case 1: not on GekyChat → show invite UI
                if (data.not_registered) {
                    const phone = data.phone || raw;
                    const registerUrl = data.invite?.register_url || '';
                    const smsText = data.invite?.sms_text || (
                        `Join me on {{ config('app.name', 'GekyChat') }}: ${registerUrl}`);
                    const shareText = data.invite?.share_text || smsText;

                    // Fill modal
                    if (invitePhoneHint) {
                        invitePhoneHint.textContent = `Phone: ${phone}`;
                    }
                    if (inviteLinkInput) {
                        inviteLinkInput.value = registerUrl;
                    }

                    // SMS deep link (works on mobile; on desktop it may do nothing)
                    if (inviteSmsBtn) {
                        const smsPayload = encodeURIComponent(smsText);
                        const smsTarget = normalizeDigits(phone);
                        // sms: scheme differs by platform; this form works broadly
                        inviteSmsBtn.href = `sms:${smsTarget}?&body=${smsPayload}`;
                    }

                    // Share API
                    if (inviteShareBtn) {
                        if (navigator.share) {
                            inviteShareBtn.disabled = false;
                            inviteShareBtn.onclick = async () => {
                                try {
                                    await navigator.share({
                                        text: shareText,
                                        url: registerUrl
                                    });
                                } catch (e) {
                                    /* user cancelled */ }
                            };
                        } else {
                            // Hide Share button if not supported
                            inviteShareBtn.style.display = 'none';
                        }
                    }

                    // Copy
                    if (inviteCopyBtn) {
                        inviteCopyBtn.onclick = async () => {
                            try {
                                await navigator.clipboard.writeText(registerUrl);
                                inviteCopyBtn.textContent = 'Copied';
                                setTimeout(() => inviteCopyBtn.textContent = 'Copy', 1200);
                            } catch (e) {}
                        };
                    }

                    // Show modal
                    if (bsInviteModal) bsInviteModal.show();
                    return;
                }

                // Case 2: already registered → redirect to conversation
                if (data.redirect_url) {
                    window.location.assign(data.redirect_url);
                    return;
                }

                // Fallback
                alert('Could not start chat. Please try again.');
            } catch (e) {
                console.error(e);
                alert('Network error. Please try again.');
            }
        }

        if (startByPhoneBtn) {
            startByPhoneBtn.addEventListener('click', startChatByPhone);
        }
    })();
</script>
<script>
    (function() {
        const btn = document.getElementById('sb-nc-start-phone');
        const input = document.getElementById('sb-nc-phone-input');
        if (!btn || !input) return;

        function valid(v) {
            return (v || '').replace(/[^\d+]/g, '').length >= 10;
        }

        function sync() {
            btn.disabled = !valid(input.value.trim());
        }
        input.addEventListener('input', sync);
        sync();
    })();
</script>
