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
    width: 60px;
    background: var(--card);
    border-right: 1px solid var(--border);
    height: 100vh;
    display: flex;
    flex-direction: column;
    position: relative;
    flex-shrink: 0;
    z-index: 100;
    overflow-y: auto;
    overflow-x: hidden;
}

.menu-sidebar-content {
    padding: 1rem 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    height: 100%;
}

.menu-item-group {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
    align-items: center;
    padding: 0 0.5rem;
}

.menu-item-group-bottom {
    margin-top: auto;
    padding-bottom: 1rem;
}

.menu-item {
    width: 48px;
    min-height: 56px;
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
    padding: 8px 4px;
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
    background: var(--wa-green);
    color: #fff;
}

.menu-item.active:hover {
    background: #2dbd8a;
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
    background: var(--wa-green);
    color: #fff;
}

[data-theme="dark"] .menu-item.active:hover {
    background: #2dbd8a;
}

/* Responsive: Adjust for smaller screens */
@media (max-width: 768px) {
    .menu-sidebar {
        width: 56px;
    }
    
    .menu-item {
        width: 44px;
        min-height: 52px;
    }
    
    .menu-item i {
        font-size: 1.25rem;
    }
    
    .menu-item-label {
        font-size: 0.6rem;
    }
}

/* Very small screens: Hide labels */
@media (max-width: 576px) {
    .menu-sidebar {
        width: 50px;
    }
    
    .menu-item {
        width: 40px;
        min-height: 48px;
        padding: 6px 2px;
    }
    
    .menu-item-label {
        display: none;
    }
    
    .menu-item i {
        font-size: 1.2rem;
        margin-bottom: 0;
    }
    
    .menu-item-group {
        gap: 0.5rem;
    }
}
</style>
