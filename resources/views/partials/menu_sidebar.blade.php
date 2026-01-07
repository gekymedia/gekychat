{{-- Thin Menu Sidebar --}}
@php
    use App\Services\FeatureFlagService;
    
    $user = auth()->user();
    $worldFeedEnabled = FeatureFlagService::isEnabled('world_feed', $user, 'web');
    $emailChatEnabled = FeatureFlagService::isEnabled('email_chat', $user, 'web');
    $advancedAiEnabled = FeatureFlagService::isEnabled('advanced_ai', $user, 'web');
    $liveBroadcastEnabled = FeatureFlagService::isEnabled('live_broadcast', $user, 'web');
    $channelsEnabled = FeatureFlagService::isEnabled('channels_enabled', $user, 'web');
    
    // Check if user has username for features that require it
    $hasUsername = !empty($user->username);
@endphp
<div class="menu-sidebar">
    <div class="menu-sidebar-content">
        <div class="menu-item-group">
            <a href="{{ route('chat.index') }}" 
               class="menu-item {{ request()->routeIs('chat.index') && !request()->routeIs('settings.*') ? 'active' : '' }}"
               title="Statuses"
               aria-label="Statuses">
                <i class="bi bi-circle-fill" aria-hidden="true"></i>
                <span class="menu-item-label">Statuses</span>
            </a>

            @if($channelsEnabled)
            <a href="{{ route('channels.index') }}" 
               class="menu-item {{ request()->routeIs('channels.*') ? 'active' : '' }}"
               title="Channels"
               aria-label="Channels">
                <i class="bi bi-broadcast-tower" aria-hidden="true"></i>
                <span class="menu-item-label">Channels</span>
            </a>
            @endif

            @if($worldFeedEnabled && $hasUsername)
            <a href="{{ route('world-feed.index') }}" 
               class="menu-item {{ request()->routeIs('world-feed.*') ? 'active' : '' }}"
               title="World Feed"
               aria-label="World Feed">
                <i class="bi bi-globe" aria-hidden="true"></i>
                <span class="menu-item-label">World</span>
            </a>
            @endif

            @if($emailChatEnabled && $hasUsername)
            <a href="{{ route('email-chat.index') }}" 
               class="menu-item {{ request()->routeIs('email-chat.*') ? 'active' : '' }}"
               title="Email Chat"
               aria-label="Email Chat">
                <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                <span class="menu-item-label">Mail</span>
            </a>
            @endif

            @if($advancedAiEnabled)
            <a href="{{ route('ai-chat.index') }}" 
               class="menu-item {{ request()->routeIs('ai-chat.*') ? 'active' : '' }}"
               title="AI Chat"
               aria-label="AI Chat">
                <i class="bi bi-robot" aria-hidden="true"></i>
                <span class="menu-item-label">AI</span>
            </a>
            @endif

            @if($liveBroadcastEnabled)
            <a href="{{ route('live-broadcast.index') }}" 
               class="menu-item {{ request()->routeIs('live-broadcast.*') ? 'active' : '' }}"
               title="Live Broadcast"
               aria-label="Live Broadcast">
                <i class="bi bi-camera-video-fill" aria-hidden="true"></i>
                <span class="menu-item-label">Live</span>
            </a>
            @endif

            <a href="{{ route('calls.index') }}" 
               class="menu-item {{ request()->routeIs('calls.*') ? 'active' : '' }}"
               title="Call Logs"
               aria-label="Call Logs">
                <i class="bi bi-telephone-fill" aria-hidden="true"></i>
                <span class="menu-item-label">Calls</span>
            </a>

            <a href="{{ route('settings.index') }}" 
               class="menu-item {{ request()->routeIs('settings.*') ? 'active' : '' }}"
               title="Settings"
               aria-label="Settings">
                <i class="bi bi-gear-fill" aria-hidden="true"></i>
                <span class="menu-item-label">Settings</span>
            </a>
        </div>
        
        {{-- Theme Toggle at Bottom --}}
        <div class="menu-item-group menu-item-group-bottom">
            <button class="menu-item theme-toggle-sidebar" 
                    title="Toggle theme" 
                    aria-label="Toggle theme"
                    aria-pressed="false"
                    type="button">
                <i class="bi bi-moon-stars-fill" aria-hidden="true"></i>
                <span class="menu-item-label">Theme</span>
            </button>
        </div>
    </div>
</div>

<style>
.menu-sidebar {
    /* Left vertical sidebar */
    position: relative !important;
    width: 72px !important;
    min-width: 72px !important;
    max-width: 72px !important;
    height: 100% !important;
    border-right: 1px solid var(--border) !important;
    border-top: none !important;
    border-bottom: none !important;
    background: var(--card) !important;
    flex-shrink: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    z-index: 10 !important;
}

.menu-sidebar-content {
    /* Vertical flex layout for left sidebar */
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between;
    padding: 0.75rem 0.5rem;
    height: 100%;
    width: 100%;
    gap: 0.5rem;
}

.menu-item-group {
    display: flex !important;
    flex-direction: column !important;
    gap: 0.5rem;
    flex: 1 1 auto;
}

.menu-item-group-bottom {
    display: flex !important;
    flex-direction: column !important;
    gap: 0.5rem;
    flex: 0 0 auto;
    margin-top: auto;
}

/* Menu items in vertical layout */
.menu-item-group .menu-item,
.menu-item-group-bottom .menu-item {
    display: flex !important;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-width: 0;
}

/* Hide menu sidebar on mobile */
@media (max-width: 768px) {
    .menu-sidebar {
        display: none !important;
    }
}

.menu-item {
    /* Vertical left sidebar layout */
    width: 100%;
    min-width: 56px;
    min-height: 56px;
    padding: 10px 8px;
    margin: 0;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    color: var(--text-muted);
    text-decoration: none;
    transition: all 0.2s ease;
    position: relative;
    cursor: pointer;
    border: none;
    background: none;
    font-family: inherit;
    font-size: inherit;
}

.menu-item:hover {
    background: var(--bg-hover, rgba(0, 0, 0, 0.05));
    color: var(--text);
    transform: translateY(-1px);
}

.menu-item.active {
    background: linear-gradient(135deg, var(--geky-green, #10B981) 0%, var(--geky-gold, #F59E0B) 100%);
    color: #fff;
}

.menu-item.active:hover {
    background: linear-gradient(135deg, var(--geky-green-dark, #059669) 0%, var(--geky-gold-dark, #D97706) 100%);
    transform: translateY(-1px);
}

.menu-item i {
    font-size: 1.5rem;
    margin-bottom: 6px;
    line-height: 1;
}

.menu-item-label {
    font-size: 0.7rem;
    font-weight: 500;
    text-align: center;
    line-height: 1.2;
    margin-top: 0;
    letter-spacing: 0.01em;
}

[data-theme="dark"] .menu-item:hover {
    background: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .menu-item.active {
    background: linear-gradient(135deg, var(--geky-green, #10B981) 0%, var(--geky-gold, #F59E0B) 100%);
    color: #fff;
}

[data-theme="dark"] .menu-item.active:hover {
    background: linear-gradient(135deg, var(--geky-green-dark, #059669) 0%, var(--geky-gold-dark, #D97706) 100%);
}

</style>
