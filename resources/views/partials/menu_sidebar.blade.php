{{-- Thin Menu Sidebar --}}
@php
    use App\Services\FeatureFlagService;
    use App\Models\FeatureFlag;
    
    $user = auth()->user();
    
    // Helper function to check feature flag with fallback
    $checkFlag = function($key) use ($user) {
        // First check with 'web' platform
        if (FeatureFlagService::isEnabled($key, $user, 'web')) {
            return true;
        }
        // Then check if flag exists and is enabled (platform 'all' or no platform restriction)
        $flag = FeatureFlag::where('key', $key)->first();
        return $flag && $flag->enabled && ($flag->platform === 'all' || $flag->platform === null || $flag->platform === 'web');
    };
    
    $worldFeedEnabled = $checkFlag('world_feed');
    $emailChatEnabled = $checkFlag('email_chat');
    $advancedAiEnabled = $checkFlag('advanced_ai');
    $liveBroadcastEnabled = $checkFlag('live_broadcast');
    $channelsEnabled = $checkFlag('channels_enabled');
    
    // All users now have auto-generated usernames, so this check is always true
    // Kept for backward compatibility during transition
    $hasUsername = true;
@endphp
<div class="menu-sidebar">
    <div class="menu-sidebar-content">
        <div class="menu-item-group">
            <a href="{{ route('chat.index') }}" 
               class="menu-item chat-filter-btn"
               data-filter="chat"
               title="Chat"
               aria-label="Chat">
                <i class="bi bi-chat-dots-fill" aria-hidden="true"></i>
                <span class="menu-item-label">Chat</span>
            </a>

            <a href="{{ route('status.index') }}" 
               class="menu-item menu-item-hide-mobile {{ request()->routeIs('status.*') ? 'active' : '' }}"
               title="Statuses"
               aria-label="Statuses">
                <img src="{{ asset('icons/status_icon.png') }}" alt="Status" class="menu-item-icon" style="width: 20px; height: 20px; object-fit: contain;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                <i class="bi bi-circle-fill" aria-hidden="true" style="display: none;"></i>
                <span class="menu-item-label">Statuses</span>
            </a>

            <a href="{{ route('broadcast-lists.index') }}" 
               class="menu-item broadcast-filter-btn {{ request()->routeIs('broadcast-lists.*') ? 'active' : '' }}"
               data-filter="broadcast"
               title="Broadcast"
               aria-label="Broadcast">
                <i class="bi bi-broadcast" aria-hidden="true"></i>
                <span class="menu-item-label">Broadcast</span>
            </a>

            @if($channelsEnabled)
            <a href="{{ route('channels.index') }}" 
               class="menu-item {{ request()->routeIs('channels.*') ? 'active' : '' }}"
               title="Channels"
               aria-label="Channels">
                <i class="bi bi-megaphone" aria-hidden="true"></i>
                <span class="menu-item-label">Channels</span>
            </a>
            @endif

            @if($worldFeedEnabled && $hasUsername)
            <a href="{{ route('world-feed.index') }}" 
               class="menu-item {{ request()->routeIs('world-feed.index') ? 'active' : '' }}"
               title="World Feed"
               aria-label="World Feed">
                <i class="bi bi-globe" aria-hidden="true"></i>
                <span class="menu-item-label">World</span>
            </a>
            <a href="{{ route('world-feed.activity') }}" 
               class="menu-item menu-item-hide-mobile {{ request()->routeIs('world-feed.activity') ? 'active' : '' }}"
               title="Activity"
               aria-label="Activity">
                <i class="bi bi-heart" aria-hidden="true"></i>
                <span class="menu-item-label">Activity</span>
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
               class="menu-item menu-item-hide-mobile {{ request()->routeIs('ai-chat.*') ? 'active' : '' }}"
               title="AI Chat"
               aria-label="AI Chat">
                <i class="bi bi-robot" aria-hidden="true"></i>
                <span class="menu-item-label">AI</span>
            </a>
            @endif

            @if($liveBroadcastEnabled && $hasUsername)
            <a href="{{ route('live-broadcast.index') }}" 
               class="menu-item menu-item-hide-mobile {{ request()->routeIs('live-broadcast.*') ? 'active' : '' }}"
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

            <a href="{{ route('sika.wallet') }}" 
               class="menu-item {{ request()->routeIs('sika.wallet') ? 'active' : '' }}"
               title="Sika Wallet"
               aria-label="Sika Wallet">
                <i class="bi bi-coin" aria-hidden="true" style="color: #F59E0B;"></i>
                <span class="menu-item-label">Wallet</span>
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
        <div class="menu-item-group menu-item-group-bottom menu-item-hide-mobile">
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
    background: var(--bg-accent) !important;
    flex-shrink: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    z-index: 10 !important;
}

[data-theme="dark"] .menu-sidebar {
    background: var(--bg-accent) !important;
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

/* Mobile: Convert to bottom navigation bar */
@media (max-width: 768px) {
    /* Hide less important items on mobile to keep bottom nav clean */
    .menu-item-hide-mobile {
        display: none !important;
    }
    
    .menu-sidebar {
        /* Bottom horizontal bar */
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        min-height: 60px !important;
        max-height: 70px !important;
        border-right: none !important;
        border-top: 1px solid var(--border) !important;
        flex-direction: row !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        z-index: 1000 !important;
        padding: 0 !important;
        /* Hide scrollbar but allow scrolling */
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
        /* Safe area for iOS */
        padding-bottom: env(safe-area-inset-bottom, 0) !important;
    }
    
    .menu-sidebar::-webkit-scrollbar {
        display: none !important;
    }
    
    .menu-sidebar-content {
        /* Horizontal layout */
        flex-direction: row !important;
        justify-content: flex-start !important;
        align-items: center !important;
        padding: 0.5rem 0.75rem !important;
        padding-bottom: calc(0.5rem + env(safe-area-inset-bottom, 0)) !important;
        height: 100% !important;
        width: max-content !important;
        min-width: 100% !important;
        gap: 0.25rem !important;
    }
    
    .menu-item-group {
        flex-direction: row !important;
        flex: 0 0 auto !important;
        gap: 0.25rem !important;
    }
    
    .menu-item-group-bottom {
        flex-direction: row !important;
        flex: 0 0 auto !important;
        margin-top: 0 !important;
        margin-left: auto !important;
        padding-left: 0.5rem !important;
        border-left: 1px solid var(--border) !important;
    }
    
    .menu-item-group .menu-item,
    .menu-item-group-bottom .menu-item {
        flex-direction: column !important;
        width: auto !important;
        min-width: 56px !important;
        max-width: 64px !important;
        padding: 6px 8px !important;
        flex-shrink: 0 !important;
    }
    
    .menu-item {
        min-height: 44px !important;
        padding: 6px 8px !important;
    }
    
    .menu-item i {
        font-size: 1.1rem !important;
        margin-bottom: 2px !important;
    }
    
    .menu-item-label {
        font-size: 0.6rem !important;
        white-space: nowrap !important;
    }
    
    /* Scroll indicator gradient on right edge */
    .menu-sidebar::after {
        content: '';
        position: fixed;
        bottom: 0;
        right: 0;
        width: 30px;
        height: calc(70px + env(safe-area-inset-bottom, 0));
        background: linear-gradient(to left, var(--bg-accent) 0%, transparent 100%);
        pointer-events: none;
        z-index: 1001;
        opacity: var(--scroll-indicator-opacity, 0.8);
        transition: opacity 0.2s ease;
    }
}

.menu-item {
    /* Vertical left sidebar layout */
    width: 100%;
    min-width: 48px;
    min-height: 48px;
    padding: 8px 6px;
    margin: 0;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
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
    font-size: 1.15rem;
    margin-bottom: 4px;
    line-height: 1;
}

.menu-item-label {
    font-size: 0.65rem;
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuSidebar = document.querySelector('.menu-sidebar');
    if (!menuSidebar) return;
    
    // Handle scroll indicator visibility
    function updateScrollIndicator() {
        if (window.innerWidth > 768) return;
        
        const scrollLeft = menuSidebar.scrollLeft;
        const scrollWidth = menuSidebar.scrollWidth;
        const clientWidth = menuSidebar.clientWidth;
        const isAtEnd = scrollLeft + clientWidth >= scrollWidth - 10;
        
        // Hide the gradient indicator when scrolled to end
        menuSidebar.style.setProperty('--scroll-indicator-opacity', isAtEnd ? '0' : '0.8');
    }
    
    menuSidebar.addEventListener('scroll', updateScrollIndicator);
    window.addEventListener('resize', updateScrollIndicator);
    
    // Initial check
    setTimeout(updateScrollIndicator, 100);
    
    // Scroll active item into view on mobile
    if (window.innerWidth <= 768) {
        const activeItem = menuSidebar.querySelector('.menu-item.active');
        if (activeItem) {
            setTimeout(() => {
                activeItem.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }, 200);
        }
    }
});
</script>
