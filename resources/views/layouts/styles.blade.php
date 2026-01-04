{{-- Fonts & Icons --}}
<link rel="dns-prefetch" href="//fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=Nunito:300,400,600,700,800" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

{{-- Bootstrap CSS --}}
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

{{-- PWA --}}
<link rel="manifest" href="{{ asset('icons/manifest.json') }}">

{{-- Favicons --}}
<link rel="icon" href="{{ asset('icons/favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/icon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="48x48" href="{{ asset('icons/icon-48x48.png') }}">
<link rel="icon" type="image/png" sizes="72x72" href="{{ asset('icons/icon-72x72.png') }}">
<link rel="icon" type="image/png" sizes="96x96" href="{{ asset('icons/icon-96x96.png') }}">
<link rel="icon" type="image/png" sizes="128x128" href="{{ asset('icons/icon-128x128.png') }}">
<link rel="icon" type="image/png" sizes="144x144" href="{{ asset('icons/icon-144x144.png') }}">
<link rel="icon" type="image/png" sizes="152x152" href="{{ asset('icons/icon-152x152.png') }}">
<link rel="icon" type="image/png" sizes="180x180" href="{{ asset('icons/icon-180x180.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
<link rel="icon" type="image/png" sizes="256x256" href="{{ asset('icons/icon-256x256.png') }}">
<link rel="icon" type="image/png" sizes="384x384" href="{{ asset('icons/icon-384x384.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icons/icon-512x512.png') }}">

{{-- Apple Touch --}}
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">

{{-- Global Theme Styles --}}
<style>
    /* ✅ Step 1: Lock the main app height properly */
    html, body {
        height: 100%;
    }

    #app {
        height: 100vh;
    }

    :root {
        --wa-green: #25D366;
        --wa-deep: #128C7E;
        --bg: #0B141A;
        --bg-accent: #1f2c34;
        --text: #E9EDF0;
        --card: #111B21;
        --border: #22303A;
        --wa-muted: #9BB0BD;
        --wa-shadow: 0 10px 30px rgba(0, 0, 0, .25);
        --nav-h: 0px;
        --space-1: 4px;
        --space-2: 8px;
        --space-3: 12px;
        --space-4: 16px;
        --space-5: 20px;
        --space-6: 24px;
        --fs-sm: .92rem;
        --fs-base: .98rem;
        --fs-lg: 1.05rem;
        --input-bg: #0f1a20;
        --input-border: var(--border);
        --bubble-sent-bg: #005c4b;
        --bubble-sent-text: #e6fffa;
        --bubble-recv-bg: #202c33;
        --bubble-recv-text: var(--text);
    }

    [data-theme="light"] {
        --bg: #FFFFFF;
        --bg-accent: #E9EEF5;
        --text: #0B141A;
        --card: #F8FAFC;
        --border: #E2E8F0;
        --wa-muted: #6B7280;
        --wa-shadow: 0 10px 30px rgba(0, 0, 0, .08);
        --input-bg: #ffffff;
        --input-border: #E2E8F0;
        --bubble-sent-bg: #dcf8c6;
        --bubble-sent-text: #0b141a;
        --bubble-recv-bg: #ffffff;
        --bubble-recv-text: #0b141a;
    }

    :root {
        --wa-card: var(--card);
        --wa-text: var(--text);
        --wa-border: var(--border);
    }

    /* Base Styles */
    /* ✅ Step 1: Lock the main app height properly */
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
    }

    #app {
        height: 100vh;
        display: flex;
        flex-direction: column;
    }

    body {
        background: radial-gradient(1100px 700px at 10% -10%, var(--bg-accent) 0, var(--bg) 60%), var(--bg);
        color: var(--text);
        transition: background-color .25s ease, color .25s ease;
        font-family: 'Nunito', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial;
        font-size: var(--fs-base);
        line-height: 1.55;
        letter-spacing: .1px;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* Layout Utilities */
    /* 🔥 Step 1: Lock the main layout height properly */
    .content-wrap {
        display: flex;
        flex-direction: column;
        height: 100vh; /* ← THIS FIXES EVERYTHING */
        overflow: hidden;
    }
    
    /* 🔥 Step 2: Fix the sidebar wrapper */
    #conversation-sidebar-wrapper {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
    }
    
    /* 📱 Mobile-specific correction */
    @media (max-width: 768px) {
        .content-wrap {
            height: 100dvh; /* mobile-safe viewport */
        }
        
        .sidebar-container {
            height: 100%;
        }
        
    }

    .fine-print {
        color: var(--wa-muted);
        font-size: var(--fs-sm);
    }

    /* Component Styles */
    .btn-wa {
        background: var(--wa-green);
        border: none;
        color: #062a1f;
        font-weight: 700;
        border-radius: 14px;
    }

    .btn-wa:hover {
        filter: brightness(1.05);
    }

    .btn-outline-wa {
        border-color: var(--wa-green);
        color: var(--wa-green);
        border-radius: 14px;
    }

    .btn-outline-wa:hover {
        background: var(--wa-green);
        color: #062a1f;
    }

    .btn-link, a {
        color: var(--wa-green);
    }

    .btn-link:hover, a:hover {
        color: #1fc25a;
    }

    .wa-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: var(--wa-shadow);
    }

    .form-control, .form-select {
        background: var(--input-bg);
        color: var(--text);
        border: 1px solid var(--input-border);
        border-radius: 14px;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--wa-green);
        box-shadow: none;
    }

    .muted {
        color: var(--wa-muted) !important;
    }

    /* Bootstrap Overrides */
    .list-group {
        --bs-list-group-bg: var(--card) !important;
        --bs-list-group-color: var(--text) !important;
        --bs-list-group-border-color: var(--border) !important;
        --bs-list-group-action-color: var(--text) !important;
        --bs-list-group-action-hover-color: var(--text) !important;
        --bs-list-group-action-hover-bg: color-mix(in srgb, var(--wa-green) 5%, transparent) !important;
        --bs-list-group-action-active-color: var(--text) !important;
        --bs-list-group-action-active-bg: color-mix(in srgb, var(--wa-green) 10%, transparent) !important;
    }

    .list-group-item {
        background-color: var(--card) !important;
        color: var(--text) !important;
        border-color: var(--border) !important;
    }

    .modal {
        --bs-modal-bg: var(--card) !important;
        --bs-modal-color: var(--text) !important;
        --bs-modal-border-color: var(--border) !important;
    }

    .modal-content {
        background-color: var(--card) !important;
        color: var(--text) !important;
        border-color: var(--border) !important;
    }

    .modal-header {
        border-bottom-color: var(--border) !important;
    }

    .modal-footer {
        border-top-color: var(--border) !important;
    }

    .form-control, .form-select {
        background-color: var(--input-bg) !important;
        color: var(--text) !important;
        border-color: var(--input-border) !important;
    }

    .form-control:focus, .form-select:focus {
        background-color: var(--input-bg) !important;
        color: var(--text) !important;
        border-color: var(--wa-green) !important;
        box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--wa-green) 25%, transparent) !important;
    }

    .input-group-text {
        background-color: var(--input-bg) !important;
        color: var(--wa-muted) !important;
        border-color: var(--input-border) !important;
    }

    .nav-tabs {
        --bs-nav-tabs-border-color: var(--border) !important;
        --bs-nav-tabs-link-hover-border-color: var(--border) !important;
        --bs-nav-tabs-link-active-bg: var(--card) !important;
        --bs-nav-tabs-link-active-color: var(--wa-green) !important;
        --bs-nav-tabs-link-active-border-color: var(--border) var(--border) var(--wa-green) !important;
    }

    .nav-tabs .nav-link {
        color: var(--wa-muted) !important;
    }

    .nav-tabs .nav-link:hover {
        color: var(--text) !important;
        border-color: var(--border) !important;
    }

    .nav-tabs .nav-link.active {
        color: var(--wa-green) !important;
        border-bottom-color: var(--wa-green) !important;
    }

    .form-check-input {
        background-color: var(--input-bg) !important;
        border-color: var(--border) !important;
    }

    .form-check-input:checked {
        background-color: var(--wa-green) !important;
        border-color: var(--wa-green) !important;
    }

    .btn-outline-secondary {
        --bs-btn-color: var(--text) !important;
        --bs-btn-border-color: var(--border) !important;
        --bs-btn-hover-bg: var(--border) !important;
        --bs-btn-hover-border-color: var(--border) !important;
        --bs-btn-active-bg: var(--border) !important;
        --bs-btn-active-border-color: var(--border) !important;
    }

    .btn-primary {
        --bs-btn-bg: var(--wa-green) !important;
        --bs-btn-border-color: var(--wa-green) !important;
        --bs-btn-hover-bg: color-mix(in srgb, var(--wa-green) 80%, black) !important;
        --bs-btn-hover-border-color: color-mix(in srgb, var(--wa-green) 80%, black) !important;
        --bs-btn-active-bg: color-mix(in srgb, var(--wa-green) 70%, black) !important;
        --bs-btn-active-border-color: color-mix(in srgb, var(--wa-green) 70%, black) !important;
        --bs-btn-color: #062a1f !important;
    }

    .text-muted {
        color: var(--wa-muted) !important;
    }

    body {
        --bs-body-bg: var(--bg) !important;
        --bs-body-color: var(--text) !important;
    }

    /* Sidebar Styles */
    .sidebar-header {
        background: var(--card);
        border-bottom: 1px solid var(--border);
        padding: 1rem;
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: var(--text) !important;
        font-weight: 700;
        font-size: 1.25rem;
    }

    .sidebar-brand-logo {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--wa-deep), var(--wa-green));
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .sidebar-brand-logo img {
        width: 20px;
        height: 20px;
    }

    .sidebar-user-menu {
        margin-left: auto;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 2px solid var(--wa-green);
        object-fit: cover;
    }

    /* User avatar placeholder now uses global .avatar-placeholder class from app.css */

    .theme-toggle-sidebar {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text);
        border-radius: 20px;
        padding: 6px 12px;
        font-size: 0.875rem;
        transition: all 0.2s ease;
    }

    .theme-toggle-sidebar:hover {
        background: var(--border);
    }

    .sidebar-dropdown {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: var(--wa-shadow);
    }

    .sidebar-dropdown .dropdown-item {
        color: var(--text);
        padding: 8px 16px;
        border-radius: 8px;
        margin: 2px 8px;
        width: auto;
    }

    .sidebar-dropdown .dropdown-item:hover {
        background: color-mix(in srgb, var(--wa-green) 15%, transparent);
    }

    .sidebar-dropdown .dropdown-divider {
        border-color: var(--border);
        margin: 4px 0;
    }

    /* Chat Layout */
    body.page-chat {
        overflow: hidden;
    }

    body.page-chat .content-wrap {
        padding-block: 0;
    }

    body.page-chat .content-wrap>.container {
        max-width: 100%;
        padding-left: 0;
        padding-right: 0;
    }

    body.page-chat .content-wrap+.container.mt-3 {
        display: none;
    }

    .wa-navbar {
        display: none;
    }

    .chat-container {
        height: 100vh !important;
        max-height: 100vh !important;
        overflow: hidden;
    }

    #conversation-sidebar, #chat-area {
        height: 100% !important;
        max-height: 100vh !important;
    }

    /* ✅ Fixed: Removed conflicting min-height: 100vh - using min-height: 0 for proper flex behavior */
    .sidebar-container {
        height: 100%;
        min-height: 0; /* 🔥 VERY IMPORTANT - allows flex children to shrink properly */
    }

    .conversation-list {
        flex: 1;
        min-height: 0;
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }

    .messages-container {
        height: calc(100vh - 140px) !important;
        overflow: hidden !important;
    }

    #messages-container {
        height: 100% !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }

    /* COMMENTED OUT - Not working correctly
    @media (max-width: 768px) {
        .chat-container {
            height: 100vh !important;
        }

        #conversation-sidebar {
            height: 40vh !important;
            max-height: 40vh !important;
        }

        #chat-area {
            height: 60vh !important;
            max-height: 60vh !important;
        }
        #conversation-sidebar-wrapper{
            display: none;
        }
        .conversation-list {
            height: calc(40vh - 120px) !important;
        }

        .messages-container {
            height: calc(60vh - 120px) !important;
        }
    }
    */

    /* Animations */
    .message-received {
        animation: messageSlideIn 0.3s ease-out;
    }

    @keyframes messageSlideIn {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .typing-indicator {
        padding: 8px 16px;
        font-style: italic;
        color: var(--wa-muted);
        background: var(--bg-accent);
        border-radius: 18px;
        display: none;
    }
</style>