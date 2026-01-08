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
    /* 🔥 Step 3: Fix sidebar container properly */
    .sidebar-container {
        background: var(--bg);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
        overflow: hidden; /* OK now because height is stable */
        width: 100%;
        position: relative;
    }

    .sidebar-header {
        background: var(--card);
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 10;
        flex-shrink: 0;
    }

    /* ✅ Step 4: Allow the conversation list to scroll */
    .conversation-list {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        background: var(--bg);
        min-height: 0; /* 🔥 VERY IMPORTANT - allows flex children to shrink properly */
        /* Hide scrollbar but keep scrolling functionality */
        scrollbar-width: thin;
        scrollbar-color: transparent transparent;
    }
    
    /* Hide scrollbar for webkit browsers but keep functionality */
    .conversation-list::-webkit-scrollbar {
        width: 0px;
        background: transparent;
    }
    
    .conversation-list::-webkit-scrollbar-thumb {
        background: transparent;
    }
    
    /* Show scrollbar on hover for better UX */
    .conversation-list:hover::-webkit-scrollbar {
        width: 6px;
    }
    
    .conversation-list:hover::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 3px;
    }
    
    .conversation-list:hover::-webkit-scrollbar-thumb:hover {
        background: var(--wa-muted);
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

    /* .avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid var(--border);
    } */

    /* Avatar styles now use global .avatar-placeholder class from app.css */

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

    .conversation-item.filtered-out {
        display: none !important;
        visibility: hidden !important;
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

    /* Active conversation styles */
    .conversation-item.active {
        background: color-mix(in srgb, var(--wa-green) 12%, transparent) !important;
        border-left: 3px solid var(--wa-green);
        font-weight: 600;
    }

    .conversation-item.active:hover {
        background: color-mix(in srgb, var(--wa-green) 15%, transparent) !important;
    }

    .conversation-item.active .text-muted {
        color: var(--text) !important;
        opacity: 0.95;
    }

    /* Fix text-muted to use theme-aware colors */
    .text-muted {
        color: var(--wa-muted) !important;
    }

    /* Fix channel/group badges to use theme-aware colors */
    .badge.rounded-pill[aria-label*="Channel"],
    .badge.rounded-pill[aria-label*="Group"],
    .badge.rounded-pill[aria-label*="follower"] {
        background-color: var(--bg-accent) !important;
        color: var(--text) !important;
    }

    /* Fix search input placeholder color to use theme-aware colors */
    #chat-search::placeholder {
        color: var(--wa-muted) !important;
        opacity: 1;
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

    .search-filters-container {
        position: relative;
        width: 100%;
        overflow: hidden;
    }

    .search-filters-scroll {
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: thin;
        scrollbar-color: var(--border) transparent;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 4px;
        /* Hide scrollbar but keep functionality */
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE and Edge */
    }

    .search-filters-scroll::-webkit-scrollbar {
        display: none; /* Chrome, Safari, Opera */
    }

    .search-filters-scroll {
        /* Ensure smooth scrolling */
        scroll-behavior: smooth;
        /* Prevent wrapping */
        flex-wrap: nowrap;
        /* Add padding for better UX */
        padding-right: 8px;
    }

    .filter-btn {
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 16px;
        transition: all 0.2s ease;
        white-space: nowrap;
        flex-shrink: 0;
        cursor: pointer;
        user-select: none;
    }

    .filter-btn:hover {
        background: color-mix(in srgb, var(--wa-green) 10%, transparent);
        border-color: var(--wa-green);
        transform: translateY(-1px);
    }

    .filter-btn:active {
        transform: translateY(0);
        background: color-mix(in srgb, var(--wa-green) 20%, transparent);
    }

    .filter-btn.active {
        background: var(--wa-green);
        color: white;
        border-color: var(--wa-green);
    }

    .filter-btn.active:hover {
        background: color-mix(in srgb, var(--wa-green) 90%, black);
        transform: translateY(-1px);
    }

    .filter-btn.active:active {
        transform: translateY(0);
        background: color-mix(in srgb, var(--wa-green) 80%, black);
    }

    #add-label-btn {
        flex-shrink: 0;
        white-space: nowrap;
        cursor: pointer;
        user-select: none;
        transition: all 0.2s ease;
    }

    #add-label-btn:hover {
        background: color-mix(in srgb, var(--wa-green) 10%, transparent) !important;
        border-color: var(--wa-green) !important;
        transform: scale(1.1);
    }

    #add-label-btn:active {
        transform: scale(1.0);
        background: color-mix(in srgb, var(--wa-green) 20%, transparent) !important;
    }

    /* Context Menu Styles */
    .conversation-context-menu {
        position: fixed;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        min-width: 200px;
        padding: 4px 0;
        display: none;
    }

    .context-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 16px;
        cursor: pointer;
        font-size: 0.9rem;
        color: var(--text);
        transition: background-color 0.2s ease;
    }

    .context-menu-item:hover {
        background: color-mix(in srgb, var(--wa-green) 10%, transparent);
    }

    .context-menu-item i {
        font-size: 1rem;
        width: 20px;
        text-align: center;
    }

    .context-menu-divider {
        height: 1px;
        background: var(--border);
        margin: 4px 0;
    }

    .context-submenu {
        position: absolute;
        left: 100%;
        top: 0;
        margin-left: 4px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        min-width: 180px;
        max-height: 300px;
        overflow-y: auto;
        padding: 4px 0;
    }

    .context-submenu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 16px;
        cursor: pointer;
        font-size: 0.9rem;
        color: var(--text);
        transition: background-color 0.2s ease;
    }

    .context-submenu-item:hover {
        background: color-mix(in srgb, var(--wa-green) 10%, transparent);
    }

    .conversation-item {
        user-select: none;
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

    /* Scrollbar Styling - Override for list-container and search-results only */
    .list-container::-webkit-scrollbar,
    .search-results::-webkit-scrollbar {
        width: 6px;
    }

    .list-container::-webkit-scrollbar-track,
    .search-results::-webkit-scrollbar-track {
        background: transparent;
    }

    .list-container::-webkit-scrollbar-thumb,
    .search-results::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 3px;
    }

    .list-container::-webkit-scrollbar-thumb:hover,
    .search-results::-webkit-scrollbar-thumb:hover {
        background: var(--wa-muted);
    }

    /* Responsive Design */
    /* ✅ Step 5: Fix mobile media query properly */
    @media (max-width: 768px) {
        .sidebar-container {
            border-right: none;
            border-bottom: 1px solid var(--border);
            height: 100vh;
            max-height: 100vh;
            min-height: 0; /* 🔥 VERY IMPORTANT */
        }

        .sidebar-header {
            padding: 1rem;
            flex-shrink: 0;
        }

        .conversation-item {
            padding: 0.75rem 1rem;
        }

        .conversation-list {
            flex: 1;
            min-height: 0; /* 🔥 VERY IMPORTANT */
            overflow-y: auto;
            overflow-x: hidden;
            padding-bottom: 0; /* Remove fake spacing */
            /* Completely hide scrollbar on mobile */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        /* Hide scrollbar completely on mobile webkit browsers */
        .conversation-list::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            background: transparent !important;
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
    .btn-status-modern {
        background: linear-gradient(135deg, var(--geky-green, #10B981) 0%, var(--geky-gold, #F59E0B) 100%);
        color: white;
        border: none;
        font-weight: 600;
        padding: 0.375rem 0.75rem;
        border-radius: 8px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(37, 211, 102, 0.2);
    }

    .btn-status-modern:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 211, 102, 0.35);
        color: white;
        background: linear-gradient(135deg, var(--geky-gold, #F59E0B) 0%, var(--geky-green, #10B981) 100%);
    }

    .btn-status-modern:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(37, 211, 102, 0.2);
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

    /* Media Preview Item Styles */
    .media-preview-item {
        background: var(--card);
        border: 1px solid var(--border) !important;
    }

    .media-preview-item .media-caption {
        font-size: 0.875rem;
    }

    .media-preview-item .remove-single-media {
        flex-shrink: 0;
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

    /* Group Creation Form Text */
    #avatarHelp {
        white-space: nowrap !important;
        display: inline-block !important;
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
<div class="sidebar-container d-flex flex-column" id="conversation-sidebar"
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

                {{-- User Dropdown --}}
                @auth
                    <div class="dropdown sidebar-user-menu">
                        <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                            aria-label="User menu">
                            @if (Auth::user()->avatar_path)
                                <img src="{{ Auth::user()->avatar_url }}" 
                                    class="rounded-circle" 
                                    style="width: 40px; height: 40px; object-fit: cover;"
                                    alt="{{ Auth::user()->name ?? Auth::user()->phone }}"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="avatar-placeholder avatar-md" style="display: none;">
                                    {{ Auth::user()->initial }}
                                </div>
                            @else
                                <div class="avatar-placeholder avatar-md">
                                    {{ Auth::user()->initial }}
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
            <div id="search-filters-container" class="search-filters-container mt-2">
                <div id="search-filters" class="search-filters-scroll d-flex gap-1">
                    <button class="btn btn-outline-secondary btn-sm filter-btn active" data-filter="all"
                        aria-pressed="true">All</button>
                    <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="unread"
                        aria-pressed="false">Unread</button>
                    <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="groups"
                        aria-pressed="false">Groups</button>
                    <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="channels"
                        aria-pressed="false">Channels</button>
                    <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="mail"
                        aria-pressed="false">Mail</button>
                    {{-- Dynamically render user labels as additional filters --}}
                    @foreach(auth()->user()->labels ?? [] as $label)
                        <button class="btn btn-outline-secondary btn-sm filter-btn"
                                data-filter="label-{{ $label->id }}"
                                aria-pressed="false">
                            {{ $label->name }}
                        </button>
                    @endforeach
                    <!-- Custom Label button: always at the end -->
                    <button class="btn btn-outline-secondary btn-sm" id="add-label-btn" type="button"
                        title="Add custom filter" style="flex-shrink: 0;">
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
            </div>

            {{-- Search Results --}}
            <div id="chat-search-results" class="search-results list-group position-absolute w-100 d-none"></div>
        </div>
    </div>
    @push('scripts')
    <script>
    // Filter handling is now done in sidebar_scripts.blade.php via handleFilterClick
    // This ensures consistent behavior between search filtering and sidebar filtering

    // Store contact display names for client-side lookup
    window.contactDisplayNames = @json(
        \App\Models\Contact::where('user_id', auth()->id())
            ->whereNotNull('contact_user_id')
            ->get()
            ->mapWithKeys(function ($contact) {
                return [$contact->contact_user_id => $contact->display_name];
            })
    );

    // Add new label via prompt
    const addLabelBtn = document.getElementById('add-label-btn');
    if (addLabelBtn) {
        addLabelBtn.addEventListener('click', function () {
            const modal = new bootstrap.Modal(document.getElementById('addLabelModal'));
            const input = document.getElementById('label-name-input');
            input.value = '';
            modal.show();
            // Focus input after modal is shown
            setTimeout(() => input.focus(), 300);
        });
    }

    // Handle add label form submission
    const addLabelForm = document.getElementById('add-label-form');
    if (addLabelForm) {
        addLabelForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const labelName = document.getElementById('label-name-input').value.trim();
            if (!labelName) return;
            
            const submitBtn = document.getElementById('add-label-submit-btn');
            const spinner = submitBtn.querySelector('.spinner-border');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            
            try {
                const response = await fetch('{{ route("labels.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ name: labelName })
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('addLabelModal')).hide();
                    // Reload to show new label
                    location.reload();
                } else {
                    const errorMsg = data.message || data.error || 'Failed to create label';
                    alert(errorMsg);
                }
            } catch (err) {
                console.error('Label creation error:', err);
                alert('Failed to create label. Please try again.');
            }
        });
    }
    </script>
    @endpush
{{-- Status Carousel Section (hide on channels page) --}}
@if (!request()->routeIs('channels.*'))
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
                <button class="btn status-add-btn-new rounded-circle p-0 d-flex align-items-center justify-content-center mx-auto mb-1" 
                        style="width: 56px; height: 56px; background: linear-gradient(135deg, var(--geky-green, #10B981) 0%, var(--geky-gold, #F59E0B) 100%); border: 3px solid var(--bg); box-shadow: 0 2px 8px rgba(0,0,0,0.15); transition: all 0.2s ease; cursor: pointer;" 
                        data-bs-toggle="modal" 
                        data-bs-target="#statusCreatorModal"
                        aria-label="Create new status"
                        onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 12px rgba(37,211,102,0.3)';"
                        onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)';">
                    <i class="bi bi-plus-lg text-white fw-bold" style="font-size: 1.4rem;"></i>
                </button>
                <small class="text-muted d-block mt-1" style="font-size: 0.7rem; font-weight: 500;">My Status</small>
            </div>

            {{-- Statuses (grouped by user) --}}
            @foreach($statuses ?? [] as $statusGroup)
                @if($statusGroup->user ?? null)
                @php
                    $statusCount = $statusGroup->status_count ?? 1;
                    $borderColor = $statusGroup->is_unread ?? false ? 'var(--wa-green)' : '#ddd';
                    $borderWidth = $statusGroup->is_unread ?? false ? 3 : 2.5;
                    $userName = $statusGroup->display_name ?? ($statusGroup->user->name ?? ($statusGroup->user->phone ?? 'User'));
                    $userId = $statusGroup->user_id;
                    // Calculate circumference and segment length for segmented border
                    $totalSize = 56;
                    $center = $totalSize / 2; // 28
                    $radius = $center - ($borderWidth / 2); // Account for border width
                    $circumference = 2 * M_PI * $radius;
                    $segmentLength = $circumference / $statusCount;
                    $gapLength = max(1.5, min(3, $segmentLength * 0.1)); // Gap between segments (10% of segment, min 1.5px, max 3px)
                    $dashLength = $segmentLength - $gapLength;
                    $avatarSize = $totalSize - ($borderWidth * 2); // Avatar size accounting for border
                @endphp
                <div class="status-item text-center" style="min-width: 60px; cursor: pointer;">
                    <button class="btn p-0 border-0 status-view-btn w-100 h-100" 
                            data-user-id="{{ $userId }}"
                            aria-label="View status from {{ $userName }}"
                            style="background: none; border: none; padding: 0; cursor: pointer;">
                        <div class="position-relative mx-auto mb-1 status-avatar-wrapper d-flex align-items-center justify-content-center" 
                             style="width: 56px; height: 56px;"
                             data-status-count="{{ $statusCount }}"
                             data-border-color="{{ $borderColor }}"
                             data-border-width="{{ $borderWidth }}">
                            {{-- Segmented border using SVG --}}
                            @if($statusCount > 1)
                            <svg class="status-border-segmented" width="56" height="56" style="position: absolute; top: 0; left: 0; pointer-events: none; overflow: visible;">
                                <circle cx="{{ $center }}" cy="{{ $center }}" r="{{ $radius }}" fill="none" 
                                        stroke="{{ $borderColor }}" 
                                        stroke-width="{{ $borderWidth }}" 
                                        stroke-dasharray="{{ $dashLength }} {{ $gapLength }}"
                                        stroke-dashoffset="0"
                                        transform="rotate(-90 {{ $center }} {{ $center }})"
                                        stroke-linecap="round"/>
                            </svg>
                            @else
                            <div class="status-border-single" style="position: absolute; top: 0; left: 0; width: 56px; height: 56px; border-radius: 50%; border: {{ $borderWidth }}px solid {{ $borderColor }}; pointer-events: none;"></div>
                            @endif
                            {{-- Avatar --}}
                            @if($statusGroup->user->avatar_path ?? null)
                                <img src="{{ $statusGroup->user->avatar_url }}" 
                                     class="rounded-circle status-avatar" 
                                     style="width: {{ $avatarSize }}px; height: {{ $avatarSize }}px; object-fit: cover; position: relative; z-index: 1; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.2s ease;"
                                     alt="{{ $userName }}"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                     onmouseover="this.style.transform='scale(1.05)'"
                                     onmouseout="this.style.transform='scale(1)'">
                                <div class="avatar-placeholder avatar-lg status-avatar {{ ($statusGroup->is_unread ?? false) ? 'unread' : '' }} d-none" 
                                     style="width: {{ $avatarSize }}px; height: {{ $avatarSize }}px; position: relative; z-index: 1; cursor: pointer; transition: transform 0.2s ease;"
                                     onmouseover="this.style.transform='scale(1.05)'"
                                     onmouseout="this.style.transform='scale(1)'">
                                    {{ $statusGroup->user->initial ?? 'U' }}
                                </div>
                            @else
                                <div class="avatar-placeholder avatar-lg status-avatar {{ ($statusGroup->is_unread ?? false) ? 'unread' : '' }}" 
                                     style="width: {{ $avatarSize }}px; height: {{ $avatarSize }}px; position: relative; z-index: 1; cursor: pointer; transition: transform 0.2s ease;"
                                     onmouseover="this.style.transform='scale(1.05)'"
                                     onmouseout="this.style.transform='scale(1)'">
                                    {{ $statusGroup->user->initial ?? 'U' }}
                                </div>
                            @endif
                        </div>
                    </button>
                    <small class="text-muted d-block text-truncate mt-1" style="font-size: 0.7rem; max-width: 60px; font-weight: 500;">
                        {{ $userName }}
                    </small>
                </div>
                @endif
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
@endif

{{-- Add this CSS to your existing style section --}}
<style>
.status-section {
    background: var(--card);
    margin: 0 -1rem;
    padding: 1rem 1rem 1.25rem 1rem;
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
    cursor: pointer;
    user-select: none;
    transition: transform 0.2s ease;
}

.status-item:active {
    transform: scale(0.95);
}

.status-view-btn {
    background: none !important;
    border: none !important;
    padding: 0;
    cursor: pointer;
    transition: opacity 0.2s ease;
}

.status-view-btn:hover {
    opacity: 0.8;
}

.status-view-btn:active {
    opacity: 0.6;
    transform: scale(0.98);
}

.status-add-btn-new {
    cursor: pointer;
    user-select: none;
}

.status-add-btn {
    transition: all 0.3s ease;
    border-color: var(--wa-green) !important;
    color: var(--wa-green);
    cursor: pointer;
}

.status-add-btn:hover {
    background: var(--wa-green) !important;
    color: white !important;
    transform: scale(1.05);
}

.status-add-btn:active {
    transform: scale(1.0);
}

.status-avatar {
    transition: all 0.3s ease;
}

.status-view-btn:hover .status-avatar {
    transform: scale(1.05);
    border-color: color-mix(in srgb, var(--wa-green) 70%, transparent) !important;
}

.status-view-btn:active .status-avatar {
    transform: scale(1.0);
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
                        $avatar = $user->avatar_url ?? null;
                    @endphp
                    <button type="button"
                        class="list-item list-group-item list-group-item-action d-flex align-items-center gap-2 sb-nc-row"
                        data-id="{{ $user->id }}" data-name="{{ Str::lower($user->name ?? '') }}"
                        data-phone="{{ Str::lower($user->phone ?? '') }}"
                        aria-label="Start chat with {{ $displayName }}">
                        {{-- Avatar --}}
                        @if ($avatar)
                            <img src="{{ $avatar }}" 
                                class="rounded-circle" 
                                style="width: 32px; height: 32px; object-fit: cover;"
                                alt="" 
                                loading="lazy"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="avatar-placeholder avatar-sm" style="display: none;">{{ $user->initial ?? 'U' }}</div>
                        @else
                            <div class="avatar-placeholder avatar-sm">{{ $user->initial ?? 'U' }}</div>
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
                                <img id="sb-gp-avatar-preview" src="{{ \App\Helpers\UrlHelper::secureAsset('images/group-default.png') }}"
                                    class="rounded border" width="64" height="64" alt="Group avatar preview"
                                    style="object-fit: cover;">
                                <div class="flex-grow-1">
                                    <input type="file" name="avatar" accept="image/*"
                                        class="form-control form-control-sm" id="sb-gp-avatar"
                                        aria-describedby="avatarHelp">
                                    <small id="avatarHelp" class="form-text text-muted" style="white-space: nowrap; display: inline-block;">
                                        JPG, PNG or WebP • up to 2MB
                                    </small>
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
                                    <img src="{{ $avatar }}" 
                                        class="rounded-circle" 
                                        style="width: 32px; height: 32px; object-fit: cover;"
                                        alt="" 
                                        loading="lazy"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="avatar-placeholder avatar-sm" style="display: none;">{{ $user->initial ?? 'U' }}</div>
                                @else
                                    <div class="avatar-placeholder avatar-sm">{{ $user->initial ?? 'U' }}</div>
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
                        <i class="bi bi-people-fill me-1" aria-hidden="true"></i> <span id="sb-gp-create-text">Create Channel</span>
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

        {{-- GekyBot Conversation (hide on channels page) --}}
        @if (isset($botConversation) && !request()->routeIs('channels.*'))
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
                <div class="avatar-placeholder avatar-md" style="margin-right: 12px;">🤖</div>
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

        {{-- Direct Conversations (hide on channels page) --}}
        @if (!request()->routeIs('channels.*'))
            @foreach ($conversations ?? collect() as $conversation)
                @php
                $displayName = $conversation->title;
                $avatarUrl = $conversation->avatar_url;
                $lastMsg = $conversation->lastMessage;
                $lastBody = $lastMsg?->display_body ?? ($lastMsg?->body ?? 'No messages yet');
                $lastTime = $lastMsg?->created_at?->diffForHumans() ?? 'No messages yet';

                // Use the model's unread count calculation
$unreadCount = (int) ($conversation->unread_count ?? 0);

$otherUser = $conversation->other_user;
$otherPhone = $otherUser?->phone ?? '';
// Get initial from other user or conversation title
$initial = $otherUser?->initial ?? strtoupper(substr($displayName, 0, 1));
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
                        <img src="{{ $avatarUrl }}" 
                            class="rounded-circle" 
                            style="width: 40px; height: 40px; object-fit: cover; margin-right: 12px;"
                            alt="" 
                            loading="lazy"
                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="avatar-placeholder avatar-md" style="margin-right: 12px; display: none;">{{ $initial }}</div>
                    @else
                        <div class="avatar-placeholder avatar-md" style="margin-right: 12px;">{{ $initial }}</div>
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
        @endif

        {{-- Channels Section (only show on channels page) --}}
        @if (request()->routeIs('channels.*') && isset($channels) && ($channels ?? collect())->count() > 0)
            @foreach ($channels as $group)
                @php
                    $latestMessage = optional($group->messages->first());
                    $lastBody = $latestMessage?->body ?? 'No messages yet';
                    $lastTime = $latestMessage?->created_at?->diffForHumans() ?? 'No messages yet';
                    $initial = Str::upper(Str::substr($group->name ?? 'Channel', 0, 1));
                    $avatarUrl = $group->avatar_url ?? null;
                    $unreadCount = (int) ($group->unread_count ?? 0);
                    $memberCount = $group->members()->count();
                    $isChannelMember = $group->isMember(auth()->id());
                @endphp

                @php
                    $isGroupActive = request()->routeIs('groups.show') && (string)request()->route('group') === (string)$group->slug;
                    $displayText = !$isChannelMember 
                        ? ($memberCount . ' ' . ($memberCount === 1 ? 'follower' : 'followers'))
                        : $lastBody;
                @endphp
                <a href="{{ route('groups.show', $group->slug) }}"
                    class="conversation-item d-flex align-items-center p-3 text-decoration-none {{ $unreadCount > 0 ? 'unread' : '' }} {{ $isGroupActive ? 'active' : '' }}"
                    data-group-id="{{ $group->id }}" data-name="{{ Str::lower($group->name ?? '') }}"
                    data-phone="" data-last="{{ Str::lower($lastBody) }}" data-unread="{{ $unreadCount }}"
                    data-group-type="channel"
                    aria-label="{{ $group->name }} channel, {{ !$isChannelMember ? 'followers: ' . $memberCount : 'last message: ' . $lastBody }}">

                    {{-- Channel Avatar --}}
                    @if ($avatarUrl)
                        <div style="position: relative; margin-right: 12px;">
                            <img src="{{ $avatarUrl }}" 
                                class="rounded-circle" 
                                style="width: 40px; height: 40px; object-fit: cover; display: block;"
                                alt="{{ $group->name }} channel avatar" 
                                loading="lazy"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="avatar-placeholder avatar-md rounded-circle d-flex align-items-center justify-content-center" style="position: absolute; top: 0; left: 0; width: 40px; height: 40px; display: none; background: linear-gradient(135deg, var(--geky-green, #10B981) 0%, var(--geky-gold, #F59E0B) 100%);">
                                <i class="bi bi-broadcast-tower text-white" style="font-size: 1.2rem;" aria-hidden="true"></i>
                            </div>
                        </div>
                    @else
                        <div class="avatar-placeholder avatar-md rounded-circle d-flex align-items-center justify-content-center" style="margin-right: 12px; width: 40px; height: 40px; background: linear-gradient(135deg, var(--geky-green, #10B981) 0%, var(--geky-gold, #F59E0B) 100%);">
                            <i class="bi bi-broadcast-tower text-white" style="font-size: 1.2rem;" aria-hidden="true"></i>
                        </div>
                    @endif

                    {{-- Channel Info --}}
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="d-flex align-items-center gap-2 flex-grow-1 min-width-0">
                                <strong class="text-truncate">{{ $group->name }}</strong>
                                @if ($group->is_verified)
                                    <i class="bi bi-check-circle-fill text-primary" style="font-size: 0.875rem; flex-shrink: 0;" title="Verified Channel" aria-label="Verified Channel"></i>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                @if ($unreadCount > 0 && $isChannelMember)
                                    <span class="unread-badge rounded-pill"
                                        aria-label="{{ $unreadCount }} unread messages">
                                        {{ $unreadCount }}
                                    </span>
                                @endif
                                @if ($isChannelMember)
                                    <small class="text-muted conversation-time">{{ $lastTime }}</small>
                                @endif
                            </div>
                        </div>
                        <p class="mb-0 text-truncate text-muted">{{ $displayText }}</p>
                    </div>

                    <div class="d-flex align-items-center gap-1 ms-2">
                        <i class="bi bi-megaphone" style="font-size: 0.875rem; color: var(--text);" aria-label="Channel" title="Channel"></i>
                    </div>
                </a>
            @endforeach
        @endif

        {{-- Groups Section (only show on non-channels pages) --}}
        @if (!request()->routeIs('channels.*') && ($groups ?? collect())->count() > 0)
            @foreach ($groups as $group)
                @php
                    $latestMessage = optional($group->messages->first());
                    $lastBody = $latestMessage?->body ?? 'No messages yet';
                    $lastTime = $latestMessage?->created_at?->diffForHumans() ?? 'No messages yet';
                    $initial = Str::upper(Str::substr($group->name ?? ($group->type === 'channel' ? 'Channel' : 'Group'), 0, 1));
                    $avatarUrl = $group->avatar_url ?? null;
                    $unreadCount = (int) ($group->unread_count ?? 0); // Use group's unread count
                    $memberCount = $group->members()->count(); // Get member/follower count
                    $isChannelMember = $group->type === 'channel' ? $group->isMember(auth()->id()) : true; // For channels, check membership; for groups, assume member (since we're showing user's groups)
                @endphp

                @php
                    // Determine if this group is currently being viewed
                    $isGroupActive = request()->routeIs('groups.show') && (string)request()->route('group') === (string)$group->slug;
                    // For channels: if not a member, show follower count; if member, show last message
                    $displayText = ($group->type === 'channel' && !$isChannelMember) 
                        ? ($memberCount . ' ' . ($memberCount === 1 ? 'follower' : 'followers'))
                        : $lastBody;
                @endphp
                <a href="{{ route('groups.show', $group->slug) }}"
                    class="conversation-item d-flex align-items-center p-3 text-decoration-none {{ $unreadCount > 0 ? 'unread' : '' }} {{ $isGroupActive ? 'active' : '' }}"
                    data-group-id="{{ $group->id }}" data-name="{{ Str::lower($group->name ?? '') }}"
                    data-phone="" data-last="{{ Str::lower($lastBody) }}" data-unread="{{ $unreadCount }}"
                    data-group-type="{{ $group->type }}"
                    aria-label="{{ $group->name }} {{ $group->type === 'channel' ? 'channel' : 'group' }}, {{ ($group->type === 'channel' && !$isChannelMember) ? 'followers: ' . $memberCount : 'last message: ' . $lastBody }}">

                    {{-- Group Avatar --}}
                    @if ($avatarUrl)
                        <div style="position: relative; margin-right: 12px;">
                            <img src="{{ $avatarUrl }}" 
                                class="rounded-circle" 
                                style="width: 40px; height: 40px; object-fit: cover; display: block;"
                                alt="{{ $group->name }} {{ $group->type === 'channel' ? 'channel' : 'group' }} avatar" 
                                loading="lazy"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="avatar-placeholder avatar-md rounded-circle d-flex align-items-center justify-content-center" style="position: absolute; top: 0; left: 0; width: 40px; height: 40px; display: none;">{{ $initial }}</div>
                        </div>
                    @else
                        <div class="avatar-placeholder avatar-md rounded-circle d-flex align-items-center justify-content-center" style="margin-right: 12px; width: 40px; height: 40px;">{{ $initial }}</div>
                    @endif

                    {{-- Group Info --}}
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="d-flex align-items-center gap-2 flex-grow-1 min-width-0">
                                <strong class="text-truncate">{{ $group->name }}</strong>
                                @if ($group->type === 'channel' && $group->is_verified)
                                    <i class="bi bi-check-circle-fill text-primary" style="font-size: 0.875rem; flex-shrink: 0;" title="Verified Channel" aria-label="Verified Channel"></i>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                @if ($unreadCount > 0 && $isChannelMember)
                                    <span class="unread-badge rounded-pill"
                                        aria-label="{{ $unreadCount }} unread messages">
                                        {{ $unreadCount }}
                                    </span>
                                @endif
                                @if ($isChannelMember)
                                    <small class="text-muted conversation-time">{{ $lastTime }}</small>
                                @endif
                            </div>
                        </div>
                        <p class="mb-0 text-truncate text-muted">{{ $displayText }}</p>
                    </div>

                    @if ($group->type === 'channel')
                        <div class="d-flex align-items-center gap-1 ms-2">
                            <i class="bi bi-megaphone" style="font-size: 0.875rem; color: var(--text);" aria-label="Channel" title="Channel"></i>
                        </div>
                    @else
                        <i class="bi bi-people-fill ms-2" style="font-size: 0.875rem; color: var(--text);" aria-label="Group" title="Group"></i>
                    @endif
                </a>
            @endforeach
        @endif

        <!-- Add Label Modal -->
        <div class="modal fade" id="addLabelModal" tabindex="-1" aria-labelledby="addLabelModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background: var(--card);">
                    <div class="modal-header border-bottom" style="border-color: var(--border);">
                        <h5 class="modal-title" id="addLabelModalLabel">Add Custom Filter</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="add-label-form">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="label-name-input" class="form-label">Filter Name</label>
                                <input type="text" class="form-control" id="label-name-input" 
                                    placeholder="Enter filter name" required maxlength="50" autofocus>
                                <small class="text-muted">This will create a custom filter for organizing your conversations.</small>
                            </div>
                        </div>
                        <div class="modal-footer border-top" style="border-color: var(--border);">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-wa" id="add-label-submit-btn">
                                <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                                Create Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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
{{-- Sidebar scripts will be pushed to scripts stack --}}
@push('scripts')
@include('partials.sidebar_scripts')
@endpush

{{-- Also include directly as fallback in case stack doesn't work --}}
<script>
console.log('📋 chat_sidebar.blade.php: About to include sidebar_scripts');
</script>
