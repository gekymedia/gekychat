{{-- layouts.app --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'GekyChat') }} | @yield('title', 'Chat Instantly')</title>
    <meta name="description" content="GekyChat is a real-time messaging app built for fast and secure communication.">
    <meta name="robots" content="index, follow">
    <meta name="author" content="GEKYMEDIA">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
    <meta name="current-user-id" content="{{ auth()->id() }}">
    @endauth
    <meta name="color-scheme" content="light dark">
    <meta id="theme-color" name="theme-color" content="#0B141A">

    {{-- Vite Assets - UPDATED: Consolidated files --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    {{-- External Stylesheets --}}
    @include('layouts.styles')
    {{-- Prevent caching --}}
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="GekyChat - Real-time Messaging">
    <meta property="og:description" content="Join GekyChat and chat instantly with others using real-time WebSockets.">
    <meta property="og:image" content="{{ asset('icons/icon-512x512.png') }}">
    <meta property="og:site_name" content="GekyChat">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="GekyChat">
    <meta name="twitter:description" content="Chat in real-time using GekyChat with file sharing and notifications.">
    <meta name="twitter:image" content="{{ asset('icons/icon-512x512.png') }}">

    {{-- Early theme (avoid flash) --}}
    <script>
        (function() {
            try {
                const saved = localStorage.getItem('theme');
                const system = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
                document.documentElement.dataset.theme = saved || system || 'dark';
            } catch {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    @stack('head')
</head>

<body class="@yield('body_class')">
    <div id="app">
        <main class="content-wrap" id="main-content" tabindex="-1">
            <div class="container-fluid h-100 p-0">
                @if(Request::is('c*') || Request::is('g*') || Request::is('contacts*') || Request::is('settings*') || Request::is('channels*') || Request::is('calls*') || Request::is('world-feed*') || Request::is('email-chat*') || Request::is('ai-chat*') || Request::is('live-broadcast*'))
                    {{-- Chat interface layout with sidebar --}}
                    <div class="d-flex h-100 chat-container" id="chat-container" style="position: relative; overflow: hidden;">
                        {{-- Thin Menu Sidebar --}}
                        @include('partials.menu_sidebar')
                        
                        {{-- Shared Sidebar --}}
                        <div class="flex-shrink-0" id="conversation-sidebar-wrapper" style="width: 360px; min-width: 280px; max-width: 360px; position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; isolation: isolate;">
                            @include('partials.chat_sidebar')
                        </div>

                        {{-- Main Content Area --}}
                        <div class="flex-grow-1 d-flex flex-column" id="chat-area" style="min-width: 0; position: relative; z-index: 1; height: 100%; overflow: hidden; isolation: isolate;">
                            @yield('content')
                        </div>
                    </div>
                    {{-- Mobile Chat Toggle Script --}}
                    @include('partials.mobile_chat_toggle')
                @else
                    {{-- Regular layout without sidebar (for auth, landing pages, etc.) --}}
                    @yield('content')
                @endif
            </div>
        </main>
    </div>
{{-- Quick Replies Modal (will be populated by ChatCore) --}}
<div id="quick-replies-modal" class="quick-replies-modal" style="display: none;">
    {{-- Content will be dynamically inserted by ChatCore --}}
</div>

{{-- Status Viewer Modal (will be populated by ChatCore) --}}
<div id="status-viewer-modal" class="status-viewer-modal" style="display: none; z-index: 9999 !important;">
    {{-- Content will be dynamically inserted by ChatCore --}}
</div>

        {{-- Google Contacts Modal: shown only on first login when flag is set --}}
        @if(session('show_google_contact_modal'))
            @include('partials.google_contact_modal')
        @endif

{{-- Status Creator Modal - Moved here to avoid stacking context issues --}}
<div class="modal fade" id="statusCreatorModal" tabindex="-1" aria-labelledby="statusCreatorModalLabel"
    aria-hidden="true" style="z-index: 9999 !important;">
    <div class="modal-dialog modal-dialog-centered modal-lg" style="z-index: 10000 !important;">
        <div class="modal-content status-modal-content" style="z-index: 10001 !important;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="statusCreatorModalLabel">Create Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="status-form" enctype="multipart/form-data">
                    @csrf

                    {{-- Text Content --}}
                    <div class="form-group mb-3" id="text-content-group">
                        <label for="status-content" class="form-label fw-semibold">What's on your mind?</label>
                        <textarea name="content" id="status-content" class="form-control" rows="4"
                            placeholder="Share what you're thinking about..." maxlength="500"></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">Max 500 characters</small>
                            <small class="text-muted char-counter">0/500</small>
                        </div>
                    </div>

                    {{-- Media Upload --}}
                    <div class="form-group mb-3" id="media-upload-group">
                        <label class="form-label fw-semibold">Upload Media (Optional)</label>
                        <div class="media-upload-area border rounded p-4 text-center cursor-pointer"
                            id="media-dropzone">
                            <i class="bi bi-cloud-arrow-up display-4 text-muted"></i>
                            <div class="mt-2 fw-semibold">Click to upload or drag and drop</div>
                            <small class="text-muted">Images (JPG, PNG, WebP) or Videos (MP4) up to 10MB each</small>
                            <input type="file" name="media[]" id="status-media" class="d-none"
                                accept="image/*,video/*" multiple>
                        </div>
                        <div id="media-preview" class="mt-3 d-none">
                            <div id="media-preview-container"></div>
                            <button type="button" class="btn btn-outline-danger btn-sm mt-2" id="remove-media">
                                <i class="bi bi-trash"></i> Remove All
                            </button>
                        </div>
                    </div>

                    {{-- Text Styling Options (Only shown when no media is selected) --}}
                    <div class="form-group mb-3 d-none" id="text-styling-group">
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

                    {{-- Duration (Hidden - fixed at 24 hours) --}}
                    <input type="hidden" name="duration" value="86400">

                    {{-- Preview (Only shown when no media is selected) --}}
                    <div class="form-group mb-4 d-none" id="text-preview-group">
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

{{-- Call Modal --}}
<div id="call-modal" class="call-modal" style="display: none;">
    <div class="call-modal-content">
        {{-- Call Header --}}
        <div class="call-header">
            <div class="call-user-info">
                <div class="call-avatar" id="call-avatar">
                    <div class="avatar-placeholder" id="call-avatar-placeholder">U</div>
                    <img id="call-avatar-img" src="" alt="" style="display: none;">
                </div>
                <div class="call-user-details">
                    <h3 id="call-user-name">User</h3>
                    <div class="call-status" id="call-status">Calling...</div>
                </div>
            </div>
            <button class="btn btn-sm btn-ghost text-white" id="call-minimize-btn" title="Minimize">
                <i class="bi bi-dash"></i>
            </button>
        </div>

        {{-- Video Container --}}
        <div class="call-video-container">
            {{-- Remote Video (other person) --}}
            <video id="remote-video" autoplay playsinline style="width: 100%; height: 100%; object-fit: cover; display: none;"></video>
            
            {{-- Local Video (self) --}}
            <video id="local-video" autoplay playsinline muted style="width: 200px; height: 150px; object-fit: cover; position: absolute; bottom: 80px; right: 20px; border-radius: 8px; display: none;"></video>
            
            {{-- Avatar/Name Display when no video --}}
            <div class="call-video-placeholder" id="call-video-placeholder">
                <div class="call-avatar-large" id="call-large-avatar">
                    <div class="avatar-placeholder avatar-xl" id="call-large-avatar-placeholder">U</div>
                    <img id="call-large-avatar-img" src="" alt="" style="display: none;">
                </div>
                <h2 id="call-large-user-name">User</h2>
            </div>
        </div>

        {{-- Call Controls --}}
        <div class="call-controls">
            <button class="call-control-btn" id="call-mute-btn" title="Mute">
                <i class="bi bi-mic"></i>
            </button>
            <button class="call-control-btn" id="call-video-toggle-btn" title="Turn video on/off">
                <i class="bi bi-camera-video"></i>
            </button>
            <button class="call-control-btn call-control-btn-end" id="call-end-btn" title="End call">
                <i class="bi bi-telephone-x"></i>
            </button>
        </div>

        {{-- Incoming Call UI --}}
        <div class="incoming-call-ui" id="incoming-call-ui" style="display: none;">
            <div class="call-actions">
                <button class="call-action-btn call-action-btn-decline" id="call-decline-btn">
                    <i class="bi bi-telephone-x"></i>
                </button>
                <button class="call-action-btn call-action-btn-accept" id="call-accept-btn">
                    <i class="bi bi-telephone"></i>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Minimized Call Bar --}}
<div id="call-minimized-bar" class="call-minimized-bar" style="display: none;">
    <div class="call-minimized-content">
        <div class="call-minimized-info">
            <div class="call-minimized-avatar" id="call-minimized-avatar">
                <div class="avatar-placeholder" id="call-minimized-avatar-placeholder">U</div>
                <img id="call-minimized-avatar-img" src="" alt="" style="display: none;">
            </div>
            <div class="call-minimized-details">
                <div class="call-minimized-name" id="call-minimized-name">User</div>
                <div class="call-minimized-status" id="call-minimized-status">In call</div>
            </div>
        </div>
        <div class="call-minimized-controls">
            <button class="btn btn-sm btn-link text-white" id="call-maximize-btn" title="Maximize">
                <i class="bi bi-arrows-angle-expand"></i>
            </button>
            <button class="btn btn-sm btn-link text-danger" id="call-end-minimized-btn" title="End call">
                <i class="bi bi-telephone-x"></i>
            </button>
        </div>
    </div>
</div>

{{-- Conversation Context Menu --}}
<div id="conversation-context-menu" class="conversation-context-menu" style="display: none;">
    <div class="context-menu-item" data-action="pin">
        <i class="bi bi-pin-angle"></i>
        <span class="pin-text">Pin conversation</span>
    </div>
    <div class="context-menu-item" data-action="unpin" style="display: none;">
        <i class="bi bi-pin-angle-fill"></i>
        <span>Unpin conversation</span>
    </div>
    <div class="context-menu-item" data-action="mark-read">
        <i class="bi bi-check2-all"></i>
        <span>Mark as read</span>
    </div>
    <div class="context-menu-item" data-action="mark-unread">
        <i class="bi bi-envelope"></i>
        <span>Mark as unread</span>
    </div>
    <div class="context-menu-divider"></div>
    <div class="context-menu-item" data-action="add-label">
        <i class="bi bi-tag"></i>
        <span>Add to label</span>
        <i class="bi bi-chevron-right ms-auto"></i>
    </div>
    <div class="context-menu-item" data-action="remove-label" style="display: none;">
        <i class="bi bi-tag-fill"></i>
        <span>Remove from label</span>
        <i class="bi bi-chevron-right ms-auto"></i>
    </div>
    <div id="label-submenu" class="context-submenu" style="display: none;">
        {{-- Labels will be populated dynamically --}}
    </div>
</div>
    {{-- Global JavaScript --}}
    @include('layouts.scripts')

    {{-- Page Specific Scripts and Styles --}}
    <script>
    console.log('📋 app.blade.php: Rendering scripts stack...');
    </script>
    @stack('scripts')
    <script>
    console.log('📋 app.blade.php: Scripts stack rendered');
    </script>
    @stack('styles')
</body>

</html>