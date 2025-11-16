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

    // Calculate total unread count for badge - NEW ADDITION
   // Calculate total unread count for badge - FIXED VERSION
    $totalUnreadCount = 0;

    // Include GekyBot conversation if it exists
    if (isset($botConversation)) {
        $totalUnreadCount += (int) ($botConversation->unread_count ?? 0);
    }

    // Include direct conversations
    foreach ($conversations ?? [] as $conversation) {
        $totalUnreadCount += (int) ($conversation->unread_count ?? 0);
    }

    // Include groups
    foreach ($groups ?? [] as $group) {
        $totalUnreadCount += (int) ($group->unread_count ?? 0);
    }
@endphp

<style>
    /* ===== SIDEBAR SPECIFIC STYLES ===== */
    .sidebar-container {
        background: var(--bg);
        border-right: 1px solid var(--border);
        height: 100vh;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }

    .sidebar-header {
        background: var(--card);
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 10;
        flex-shrink: 0;
    }

    .conversation-list {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
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

    .avatar-placeholder {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        margin-right: 12px;
        background: color-mix(in srgb, var(--wa-green) 15%, transparent);
        color: var(--wa-green);
    }

    .avatar-img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 12px;
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

    /* Enhanced Unread Styles */
    .conversation-item.unread {
        background: color-mix(in srgb, var(--wa-green) 8%, transparent);
        border-left: 3px solid var(--wa-green);
        font-weight: 600;
    }

    .conversation-item.unread .text-muted {
        color: var(--text) !important;
        opacity: 0.9;
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
        border-radius: 10px;
        padding: 0 6px;
    }

    /* Pulse animation for new unread messages */
    @keyframes pulse-unread {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.05);
        }

        100% {
            transform: scale(1);
        }
    }

    .conversation-item.unread.new-message {
        animation: pulse-unread 0.6s ease-in-out;
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

    /* New Chat & Group Creation */
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

    /* Status Button Styles */
    .btn-status {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-status:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        color: white;
    }

    /* Status Modal Styles */
    .status-modal-content {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .status-type-label {
        transition: all 0.3s ease;
        border: 2px solid var(--border) !important;
        background: var(--bg);
    }

    .status-type-label:hover {
        border-color: var(--wa-green) !important;
        background: color-mix(in srgb, var(--wa-green) 5%, transparent);
    }

    input[type="radio"]:checked+.status-type-label {
        border-color: var(--wa-green) !important;
        background: color-mix(in srgb, var(--wa-green) 10%, transparent);
    }

    /* Media Upload Styles */
    .media-upload-area {
        transition: all 0.3s ease;
        border: 2px dashed var(--border);
        background: var(--bg);
    }

    .media-upload-area:hover {
        border-color: var(--wa-green);
        background: color-mix(in srgb, var(--wa-green) 5%, transparent);
    }

    .media-upload-area.dragover {
        border-color: var(--wa-green);
        background: color-mix(in srgb, var(--wa-green) 10%, transparent);
        transform: scale(1.02);
    }

    /* Character Counter */
    .char-counter.warning {
        color: #dc2626;
        font-weight: 600;
    }

    /* Preview Styles */
    #text-preview {
        transition: all 0.3s ease;
        font-size: 1.1rem;
        font-weight: 500;
    }

    /* Form Control Colors */
    .form-control-color {
        height: 45px;
        border: 2px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
    }

    .form-control-color:hover {
        border-color: var(--wa-green);
    }

    /* Responsive Design for Status Modal */
    @media (max-width: 768px) {
        .status-type-option {
            margin-bottom: 1rem;
        }

        .status-type-label {
            padding: 1.5rem !important;
        }

        .media-upload-area {
            padding: 2rem !important;
        }
    }

    /* Animation for Status Creation */
    @keyframes statusCreated {
        0% {
            transform: scale(0.8);
            opacity: 0;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .status-created {
        animation: statusCreated 0.5s ease-out;
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

{{-- Updated Sidebar Header with Total Unread Count --}}
<div class="sidebar-container col-md-4 col-lg-3 d-flex flex-column" id="conversation-sidebar"
    data-conv-show-base="{{ $convShowBase }}" data-group-show-base="{{ $groupShowBase }}"
    data-user-ids="{{ json_encode($userIds) }}">

    {{-- Sidebar Header --}}
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
                {{-- Total Unread Count Badge --}}
                @if ($totalUnreadCount > 0)
                    <span id="total-unread-count" class="unread-badge"
                        aria-label="{{ $totalUnreadCount }} total unread messages">
                        {{ $totalUnreadCount }}
                    </span>
                @else
                    <span id="total-unread-count" class="unread-badge" style="display: none;"></span>
                @endif

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
    {{-- Admin Dashboard Link - Only show if user is admin --}}
    @auth
        @if(auth()->user()->is_admin)
            <li>
                <a class="dropdown-item d-flex align-items-center gap-2"
                    href="{{ route('admin.dashboard') }}">
                    <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
                    <span>Admin Dashboard</span>
                </a>
            </li>
        @endif
    @endauth
    {{-- End of optional Link available to only Admins Only --}}
    
    <li>
        <a class="dropdown-item d-flex align-items-center gap-2"
            href="{{ route('settings.index') }}">
            <i class="bi bi-person" aria-hidden="true"></i>
            <span>Profile</span>
        </a>
    </li>
    <li>
        <a class="dropdown-item d-flex align-items-center gap-2"
            href="{{ route('settings.quick-replies') }}">
            <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
            <span>Quick Replies</span>
        </a>
    </li>
    <li>
        <a class="dropdown-item d-flex align-items-center gap-2"
            href="{{ route('contacts.index') }}">
            <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
            <span>My Contacts</span>
        </a>
    </li>
  
    <li>
        <a class="dropdown-item d-flex align-items-center gap-2"
            href="{{ route('settings.index') }}">
            <i class="bi bi-gear" aria-hidden="true"></i>
            <span>Settings</span>
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

        {{-- Action Buttons Row --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0 fw-bold">Chats</h5>
            <div class="d-flex gap-2">
                {{-- Status Button --}}
                <button class="btn btn-status btn-sm" type="button" id="new-status-btn" aria-label="Create new status">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i> Status
                </button>

                {{-- New Chat Button --}}
                <button class="btn btn-wa btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#sb-new-chat"
                    aria-controls="sb-new-chat" id="new-chat-btn" aria-label="Start new chat">
                    <i class="bi bi-plus" aria-hidden="true"></i> New
                </button>

                {{-- New Group Button --}}
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
                <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="unread"
                    aria-pressed="false">Unread</button>
                <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="groups"
                    aria-pressed="false">Groups</button>
                <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="channels"
                    aria-pressed="false">Channels</button>
                <!-- Custom Label button: opens modal or page for managing labels -->
                <button class="btn btn-outline-secondary btn-sm" id="add-label-btn" type="button"
                    title="Add custom filter">
                    <i class="bi bi-plus"></i>
                </button>
                {{-- Dynamically render user labels as additional filters --}}
                @foreach(auth()->user()->labels ?? [] as $label)
                    <button class="btn btn-outline-secondary btn-sm filter-btn"
                            data-filter="label-{{ $label->id }}"
                            aria-pressed="false">
                        {{ $label->name }}
                    </button>
                @endforeach
            </div>

            {{-- Search Results --}}
            <div id="chat-search-results" class="search-results list-group position-absolute w-100 d-none"></div>
        </div>
    </div>
    @push('scripts')
    <script>
    // Custom filter handling for labels and other categories in the sidebar
    document.addEventListener('DOMContentLoaded', function () {
        const filterContainer = document.getElementById('search-filters');
        if (!filterContainer) return;
        const filterButtons = filterContainer.querySelectorAll('.filter-btn');
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const filter = this.dataset.filter;
                const items = document.querySelectorAll('.conversation-item');
                items.forEach(item => {
                    let show = true;
                    if (filter && filter.startsWith('label-')) {
                        const labelId = filter.split('-')[1];
                        const labels = (item.dataset.labels || '').split(',').filter(Boolean);
                        show = labels.includes(labelId);
                    } else if (filter === 'unread') {
                        show = (parseInt(item.dataset.unread) || 0) > 0;
                    } else if (filter === 'groups') {
                        // Show only private groups (non-channel) when filtering groups
                        show = item.hasAttribute('data-group-id') && item.dataset.groupType !== 'channel';
                    } else if (filter === 'channels') {
                        show = item.dataset.groupType === 'channel';
                    } else if (filter === 'all') {
                        show = true;
                    }
                    item.style.display = show ? '' : 'none';
                });
            });
        });

        // Add new label via prompt
        const addLabelBtn = document.getElementById('add-label-btn');
        if (addLabelBtn) {
            addLabelBtn.addEventListener('click', function () {
                const labelName = prompt('Enter a name for the new label:');
                if (!labelName) return;
                fetch('/api/v1/labels', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ name: labelName })
                }).then(async (resp) => {
                    if (resp.ok) {
                        // Reload to show the new label filter
                        location.reload();
                    } else {
                        const data = await resp.json().catch(() => ({}));
                        alert(data.error || 'Failed to create label');
                    }
                }).catch((err) => {
                    console.error(err);
                    alert('Failed to create label');
                });
            });
        }
    });
    </script>
    @endpush
{{-- Status Carousel Section --}}
<div class="status-section border-bottom pb-3">
    {{-- My Status Button --}}
    <div class="d-flex align-items-center justify-content-between px-3 mb-2">
        <h6 class="text-muted mb-0 small text-uppercase fw-semibold">Status Updates</h6>
    </div>

    {{-- Status Carousel --}}
    <div class="status-carousel px-3">
        <div class="d-flex gap-3 overflow-auto pb-2" style="scrollbar-width: thin;">
            {{-- My Status (Add Button) --}}
            <div class="status-item text-center" style="min-width: 60px;">
                <button class="btn btn-outline-wa rounded-circle p-0 d-flex align-items-center justify-content-center mx-auto mb-1 status-add-btn" 
                        style="width: 50px; height: 50px; border-width: 2px;" 
                        data-bs-toggle="modal" 
                        data-bs-target="#statusCreatorModal"
                        aria-label="Create new status">
                    <i class="bi bi-plus-lg" style="font-size: 1.2rem;"></i>
                </button>
                <small class="text-muted d-block" style="font-size: 0.7rem;">My Status</small>
            </div>

            {{-- Other Users' Statuses --}}
            @foreach($statuses ?? [] as $status)
                <div class="status-item text-center" style="min-width: 60px;">
                    <button class="btn p-0 border-0 status-view-btn" 
                            data-status-id="{{ $status->id }}"
                            data-user-id="{{ $status->user_id }}"
                            aria-label="View status from {{ $status->user->name ?? 'User' }}">
                        <div class="position-relative mx-auto mb-1">
                            @if($status->user->avatar_path)
                                <img src="{{ Storage::url($status->user->avatar_path) }}" 
                                     class="rounded-circle status-avatar" 
                                     style="width: 50px; height: 50px; object-fit: cover; border: 2px solid var(--wa-green);"
                                     alt="{{ $status->user->name ?? 'User' }}"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="avatar-placeholder rounded-circle d-none" 
                                     style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--wa-green) 15%, transparent); color: var(--wa-green); border: 2px solid var(--wa-green);">
                                    {{ Str::upper(Str::substr($status->user->name ?? 'U', 0, 1)) }}
                                </div>
                            @else
                                <div class="avatar-placeholder rounded-circle" 
                                     style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--wa-green) 15%, transparent); color: var(--wa-green); border: 2px solid var(--wa-green);">
                                    {{ Str::upper(Str::substr($status->user->name ?? 'U', 0, 1)) }}
                                </div>
                            @endif
                            
                            {{-- Unread indicator --}}
                            @if($status->is_unread)
                                <span class="position-absolute top-0 end-0 bg-danger rounded-circle" 
                                      style="width: 12px; height: 12px; border: 2px solid white;"></span>
                            @endif
                        </div>
                    </button>
                    <small class="text-muted d-block text-truncate" style="font-size: 0.7rem; max-width: 60px;">
                        {{ $status->user->name ?? 'User' }}
                    </small>
                    <small class="text-muted d-block" style="font-size: 0.6rem;">
                        {{ $status->created_at->diffForHumans() }}
                    </small>
                </div>
            @endforeach

            {{-- Empty State --}}
            @if(empty($statuses) || count($statuses) === 0)
                <div class="text-center text-muted py-3 w-100">
                    <i class="bi bi-chat-square-text display-6 text-muted mb-2 d-block"></i>
                    <small>No status updates yet</small>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Add this CSS to your existing style section --}}
<style>
.status-section {
    background: var(--card);
    margin: 0 -1rem;
    padding: 0 1rem;
}

.status-carousel {
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

.status-carousel::-webkit-scrollbar {
    height: 4px;
}

.status-carousel::-webkit-scrollbar-track {
    background: transparent;
}

.status-carousel::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 2px;
}

.status-item {
    flex-shrink: 0;
}

.status-add-btn {
    transition: all 0.3s ease;
    border-color: var(--wa-green) !important;
    color: var(--wa-green);
}

.status-add-btn:hover {
    background: var(--wa-green) !important;
    color: white !important;
    transform: scale(1.05);
}

.status-avatar {
    transition: all 0.3s ease;
}

.status-view-btn:hover .status-avatar {
    transform: scale(1.05);
    border-color: color-mix(in srgb, var(--wa-green) 70%, transparent) !important;
}
</style>
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

          
        </div>
    </div>

    {{-- Create Group Panel - FIXED LAYOUT --}}
    <div id="sb-create-group" class="creation-panel collapse border-bottom">
        <div class="p-3">
            <form id="sb-gp-form" action="{{ route('groups.store') }}" method="POST" enctype="multipart/form-data"
                novalidate>
                @csrf

                {{-- Hidden field for is_private --}}
                <input type="hidden" name="is_private" id="sb-gp-is-private" value="0">

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
                        <div class="">
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

                    {{-- Type --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="sb-gp-channel"
                                    value="channel" checked data-is-private="0">
                                <label class="form-check-label" for="sb-gp-channel">
                                    <i class="bi bi-globe me-1" aria-hidden="true"></i> Channel (Public)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="sb-gp-group"
                                    value="group" data-is-private="1">
                                <label class="form-check-label" for="sb-gp-group">
                                    <i class="bi bi-lock me-1" aria-hidden="true"></i> Group (Private)
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
                <div class="d-flex gap-2 pt-3 border-top">
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
                $unreadCount = (int) ($botConversation->unread_count ?? 0);
            @endphp
            <a href="{{ route('chat.show', $botConversation->slug) }}"
                class="conversation-item d-flex align-items-center p-3 text-decoration-none {{ $unreadCount > 0 ? 'unread' : '' }}"
                data-conversation-id="{{ $botConversation->id }}" data-name="gekybot" data-phone=""
                data-last="{{ Str::lower($lastBot) }}" data-unread="{{ $unreadCount }}"
                aria-label="Chat with GekyBot, last message: {{ $lastBot }}">
                <div class="avatar me-3 bg-avatar">🤖</div>
                <div class="flex-grow-1 min-width-0">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-truncate">GekyBot</strong>
                        <div class="d-flex align-items-center gap-2">
                            @if ($unreadCount > 0)
                                <span class="unread-badge rounded-pill"
                                    aria-label="{{ $unreadCount }} unread messages">
                                    {{ $unreadCount }}
                                </span>
                            @endif
                            <small class="text-muted conversation-time">{{ $lastBotTime }}</small>
                        </div>
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

                // Use the model's unread count calculation
$unreadCount = (int) ($conversation->unread_count ?? 0);

$otherUser = $conversation->other_user;
$otherPhone = $otherUser?->phone ?? '';
            @endphp

            @php
                // Determine if this conversation is the one currently being viewed
                $isActive = request()->routeIs('chat.show') && (string)request()->route('conversation') === (string)$conversation->slug;
                // Prepare a comma-separated list of label IDs for filtering
                $convLabelIds = $conversation->labels?->pluck('id')->implode(',') ?? '';
            @endphp

            <a href="{{ route('chat.show', $conversation->slug) }}"
                class="conversation-item d-flex align-items-center p-3 text-decoration-none {{ $unreadCount > 0 ? 'unread' : '' }} {{ $isActive ? 'active' : '' }}"
                data-conversation-id="{{ $conversation->id }}" data-name="{{ Str::lower($displayName) }}"
                data-phone="{{ Str::lower($otherPhone) }}" data-last="{{ Str::lower($lastBody) }}"
                data-unread="{{ $unreadCount }}" data-labels="{{ $convLabelIds }}"
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
                        <div class="d-flex align-items-center gap-2">
                            @if ($unreadCount > 0)
                                <span class="unread-badge rounded-pill"
                                    aria-label="{{ $unreadCount }} unread messages">
                                    {{ $unreadCount }}
                                </span>
                            @endif
                            <small class="text-muted conversation-time">{{ $lastTime }}</small>
                        </div>
                    </div>
                    <p class="mb-0 text-truncate text-muted">{{ $lastBody }}</p>
                </div>
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
                    $unreadCount = (int) ($group->unread_count ?? 0); // Use group's unread count
                @endphp

                @php
                    // Determine if this group is currently being viewed
                    $isGroupActive = request()->routeIs('groups.show') && (string)request()->route('group') === (string)$group->slug;
                @endphp
                <a href="{{ route('groups.show', $group->slug) }}"
                    class="conversation-item d-flex align-items-center p-3 text-decoration-none {{ $unreadCount > 0 ? 'unread' : '' }} {{ $isGroupActive ? 'active' : '' }}"
                    data-group-id="{{ $group->id }}" data-name="{{ Str::lower($group->name ?? '') }}"
                    data-phone="" data-last="{{ Str::lower($lastBody) }}" data-unread="{{ $unreadCount }}"
                    data-group-type="{{ $group->type }}"
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
                            <div class="d-flex align-items-center gap-2">
                                @if ($unreadCount > 0)
                                    <span class="unread-badge rounded-pill"
                                        aria-label="{{ $unreadCount }} unread messages">
                                        {{ $unreadCount }}
                                    </span>
                                @endif
                                <small class="text-muted conversation-time">{{ $lastTime }}</small>
                            </div>
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
{{-- Status Creator Modal --}}
<div class="modal fade" id="statusCreatorModal" tabindex="-1" aria-labelledby="statusCreatorModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content status-modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="statusCreatorModalLabel">Create Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="status-form" enctype="multipart/form-data">
                    @csrf

                    {{-- Status Type Selection --}}
                    <div class="form-group mb-4">
                        <label class="form-label fw-semibold mb-3">Status Type</label>
                        <div class="d-flex gap-3">
                            <div class="status-type-option flex-grow-1 text-center">
                                <input type="radio" name="type" id="status-type-text" value="text"
                                    class="d-none" checked>
                                <label for="status-type-text"
                                    class="status-type-label p-3 rounded border cursor-pointer d-block">
                                    <i class="bi bi-chat-square-text display-6 text-primary"></i>
                                    <div class="mt-2 fw-semibold">Text</div>
                                    <small class="text-muted">Share thoughts</small>
                                </label>
                            </div>
                            <div class="status-type-option flex-grow-1 text-center">
                                <input type="radio" name="type" id="status-type-image" value="image"
                                    class="d-none">
                                <label for="status-type-image"
                                    class="status-type-label p-3 rounded border cursor-pointer d-block">
                                    <i class="bi bi-image display-6 text-success"></i>
                                    <div class="mt-2 fw-semibold">Image</div>
                                    <small class="text-muted">Share photos</small>
                                </label>
                            </div>
                            <div class="status-type-option flex-grow-1 text-center">
                                <input type="radio" name="type" id="status-type-video" value="video"
                                    class="d-none">
                                <label for="status-type-video"
                                    class="status-type-label p-3 rounded border cursor-pointer d-block">
                                    <i class="bi bi-camera-video display-6 text-info"></i>
                                    <div class="mt-2 fw-semibold">Video</div>
                                    <small class="text-muted">Share videos</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Text Content (Visible for text type) --}}
                    <div class="form-group mb-3" id="text-content-group">
                        <label for="status-content" class="form-label fw-semibold">What's on your mind?</label>
                        <textarea name="content" id="status-content" class="form-control" rows="4"
                            placeholder="Share what you're thinking about..." maxlength="500"></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">Max 500 characters</small>
                            <small class="text-muted char-counter">0/500</small>
                        </div>
                    </div>

                    {{-- Media Upload (Hidden by default) --}}
                    <div class="form-group mb-3 d-none" id="media-upload-group">
                        <label class="form-label fw-semibold">Upload Media</label>
                        <div class="media-upload-area border rounded p-4 text-center cursor-pointer"
                            id="media-dropzone">
                            <i class="bi bi-cloud-arrow-up display-4 text-muted"></i>
                            <div class="mt-2 fw-semibold">Click to upload or drag and drop</div>
                            <small class="text-muted">JPG, PNG, WebP or MP4 up to 10MB</small>
                            <input type="file" name="media" id="status-media" class="d-none"
                                accept="image/*,video/*">
                        </div>
                        <div id="media-preview" class="mt-3 text-center d-none">
                            <img id="media-preview-img" class="rounded shadow"
                                style="max-height: 200px; max-width: 100%;">
                            <video id="media-preview-video" class="rounded shadow d-none" controls
                                style="max-height: 200px; max-width: 100%;"></video>
                            <button type="button" class="btn btn-outline-danger btn-sm mt-2" id="remove-media">
                                <i class="bi bi-trash"></i> Remove
                            </button>
                        </div>
                    </div>

                    {{-- Text Styling Options (Visible for text type) --}}
                    <div class="form-group mb-3" id="text-styling-group">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="background-color" class="form-label fw-semibold">Background Color</label>
                                <input type="color" name="background_color" id="background-color"
                                    class="form-control form-control-color" value="#075e54">
                            </div>
                            <div class="col-md-6">
                                <label for="text-color" class="form-label fw-semibold">Text Color</label>
                                <input type="color" name="text_color" id="text-color"
                                    class="form-control form-control-color" value="#ffffff">
                            </div>
                        </div>
                    </div>

                    {{-- Duration --}}
                    <div class="form-group mb-4">
                        <label for="status-duration" class="form-label fw-semibold">Duration</label>
                        <select name="duration" id="status-duration" class="form-select">
                            <option value="86400">24 hours</option>
                            <option value="43200">12 hours</option>
                            <option value="21600">6 hours</option>
                            <option value="3600">1 hour</option>
                        </select>
                        <small class="text-muted">How long your status will be visible to others</small>
                    </div>

                    {{-- Preview --}}
                    <div class="form-group mb-4" id="text-preview-group">
                        <label class="form-label fw-semibold">Preview</label>
                        <div id="text-preview" class="p-4 rounded text-center"
                            style="background: #075e54; color: #ffffff; min-height: 120px; display: flex; align-items: center; justify-content: center;">
                            <span id="preview-text">Your status will appear here</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-wa" id="post-status-btn">
                    <i class="bi bi-send me-1"></i> Post Status
                </button>
            </div>
        </div>
    </div>
</div>
{{-- Add this JavaScript for real-time unread count updates --}}
@include('partials.sidebar_scripts')
