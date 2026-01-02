{{-- Thin Menu Sidebar --}}
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

            <a href="{{ route('channels.index') }}" 
               class="menu-item {{ request()->routeIs('channels.*') ? 'active' : '' }}"
               title="Channels"
               aria-label="Channels">
                <i class="bi bi-broadcast-tower" aria-hidden="true"></i>
                <span class="menu-item-label">Channels</span>
            </a>

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
    /* TESTING: Show horizontal at bottom on all screen sizes */
    position: fixed !important;
    bottom: 0 !important;
    left: 0 !important;
    right: 0 !important;
    top: auto !important;
    width: 100% !important;
    height: auto !important;
    min-height: 70px !important;
    max-height: 80px !important;
    border-right: none !important;
    border-top: 1px solid var(--border) !important;
    border-bottom: none !important;
    z-index: 1000 !important;
    background: var(--card) !important;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1) !important;
    padding-bottom: env(safe-area-inset-bottom);
    overflow: hidden !important;
    flex: none !important;
    opacity: 1 !important;
    pointer-events: auto !important;
    display: flex !important;
    visibility: visible !important;
}

/* Hide menu sidebar from normal flow on mobile (it's fixed at bottom) */
@media (max-width: 768px) {
    /* Completely remove h-100 constraint from parent flex container on mobile */
    .d-flex.h-100,
    .chat-container {
        height: auto !important;
        min-height: auto !important;
    }
    
    /* Override h-100 for the specific parent container - remove all height constraints */
    .container-fluid.h-100 > .d-flex.h-100,
    .container-fluid.h-100 > .chat-container {
        height: auto !important;
        min-height: auto !important;
    }
    
    /* Remove menu sidebar from parent flow - but keep it visible at bottom */
    .chat-container > .menu-sidebar,
    .d-flex.h-100 > .menu-sidebar {
        width: 0 !important;
        min-width: 0 !important;
        max-width: 0 !important;
        border-right: none !important;
        overflow: visible !important; /* Allow it to show at bottom */
        height: 0 !important;
        min-height: 0 !important;
        max-height: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        flex: 0 0 0 !important;
        flex-shrink: 0 !important;
        flex-grow: 0 !important;
        /* DO NOT set opacity: 0 or pointer-events: none - we want it visible at bottom */
    }
    
    /* Ensure container-fluid doesn't force height on mobile */
    .container-fluid.h-100 {
        height: auto !important;
        min-height: calc(100vh - 80px) !important;
    }
    
    /* Ensure chat container and sidebar wrapper work properly */
    .chat-container {
        position: relative;
        width: 100%;
    }
    
    #conversation-sidebar-wrapper {
        position: relative;
        z-index: 1;
        display: none;
    }
}

.menu-sidebar-content {
    /* TESTING: Grid layout on all screens for horizontal bottom menu */
    display: grid !important;
    grid-template-columns: repeat(5, 1fr) !important;
    align-items: center;
    padding: 0.5rem 0.25rem;
    height: 100%;
    gap: 0.25rem;
    width: 100%;
}

/* Flatten groups - they're now grid items */
.menu-item-group {
    display: contents !important; /* Make groups transparent to grid */
}

.menu-item-group-bottom {
    display: contents !important; /* Make groups transparent to grid */
}

/* All menu items become direct grid children */
.menu-item-group .menu-item,
.menu-item-group-bottom .menu-item {
    display: flex !important;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-width: 0;
}

.menu-item {
    /* TESTING: Horizontal layout on all screens */
    width: auto;
    min-width: 0;
    min-height: 56px;
    max-width: 100%;
    padding: 8px 2px;
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
    font-size: 1.35rem;
    margin-bottom: 4px;
    line-height: 1;
}

.menu-item-label {
    font-size: 0.625rem;
    font-weight: 600;
    text-align: center;
    line-height: 1.2;
    margin-top: 2px;
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

/* Responsive: Move to bottom on mobile */
@media (max-width: 768px) {
    .menu-sidebar {
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        top: auto !important;
        width: 100% !important;
        height: auto !important;
        min-height: 70px !important;
        max-height: 80px !important;
        border-right: none !important;
        border-top: 1px solid var(--border) !important;
        border-bottom: none !important;
        z-index: 1000 !important;
        background: var(--card) !important;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1) !important;
        /* Safe area for mobile devices */
        padding-bottom: env(safe-area-inset-bottom);
        /* Ensure it doesn't expand */
        overflow: hidden !important;
        flex: none !important;
        /* Ensure it's always visible when left sidebar disappears */
        opacity: 1 !important;
        pointer-events: auto !important;
        display: flex !important;
        visibility: visible !important;
    }
    
    .menu-sidebar-content {
        display: grid !important;
        grid-template-columns: repeat(5, 1fr) !important;
        align-items: center;
        padding: 0.5rem 0.25rem;
        height: 100%;
        gap: 0.25rem;
        width: 100%;
    }
    
    /* Flatten groups - they're now grid items */
    .menu-item-group {
        display: contents !important; /* Make groups transparent to grid */
    }
    
    /* All menu items become direct grid children */
    .menu-item-group .menu-item,
    .menu-item-group-bottom .menu-item {
        display: flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-width: 0;
    }
    
    .menu-item {
        width: auto;
        min-width: 0;
        min-height: 56px;
        max-width: 100%;
        padding: 8px 2px;
        margin: 0;
        box-sizing: border-box;
    }
    
    .menu-item i {
        font-size: 1.3rem;
        margin-bottom: 4px;
    }
    
    .menu-item-label {
        font-size: 0.65rem;
        display: block;
    }
    
    /* ❌ Removed padding-bottom tricks - using proper height management instead */
}

/* Very small screens: Optimize spacing */
@media (max-width: 576px) {
    .menu-sidebar {
        min-height: 65px;
        max-height: 70px;
    }
    
    .menu-sidebar-content {
        padding: 0.4rem 0.25rem;
    }
    
    .menu-item-group {
        flex-direction: row !important;
        gap: 0 !important;
    }
    
    .menu-item {
        min-width: 45px;
        min-height: 50px;
        max-width: none;
        padding: 6px 2px;
        flex: 1;
    }
    
    .menu-item i {
        font-size: 1.2rem;
        margin-bottom: 3px;
    }
    
    .menu-item-label {
        font-size: 0.6rem;
    }
    
    /* ❌ Removed padding-bottom tricks - using proper height management instead */
}
</style>
