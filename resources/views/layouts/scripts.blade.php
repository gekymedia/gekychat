<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
// ---- Global flags (read from meta to avoid import.meta in classic script)
(function () {
    const $ = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') || '';
    window.APP = {
        env: $('app-env') || 'production',
        hasReverbKey: $('has-reverb-key') === '1',
        csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        userId: Number({{ auth()->id() ?? 'null' }}) || null,
    };
})();

// ---- Early theme (unchanged)
(function () {
    try {
        const saved = localStorage.getItem('theme');
        const system = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        document.documentElement.dataset.theme = saved || system || 'dark';
    } catch {
        document.documentElement.dataset.theme = 'dark';
    }
})();

// ---- Theme toggles (unchanged)
(function () {
    const html = document.documentElement;
    const themeMeta = document.getElementById('theme-color');

    function applyTheme(t) {
        html.dataset.theme = t;
        try { localStorage.setItem('theme', t); } catch {}
        themeMeta?.setAttribute('content', t === 'dark' ? '#0B141A' : '#FFFFFF');
        document.querySelectorAll('.theme-toggle-sidebar').forEach(btn => {
            btn.setAttribute('aria-pressed', String(t !== 'dark'));
            // Check if it's a menu sidebar button (has menu-item class)
            const isMenuButton = btn.classList.contains('menu-item');
            if (isMenuButton) {
                // For menu sidebar, keep the same structure but update icon and label
                btn.innerHTML = t === 'dark'
                    ? '<i class="bi bi-brightness-high-fill" aria-hidden="true"></i><span class="menu-item-label">Theme</span>'
                    : '<i class="bi bi-moon-stars-fill" aria-hidden="true"></i><span class="menu-item-label">Theme</span>';
            } else {
                // For other theme toggles, use the original format
                btn.innerHTML = t === 'dark'
                    ? '<i class="bi bi-brightness-high-fill me-1"></i> Light'
                    : '<i class="bi bi-moon-stars-fill me-1"></i> Dark';
            }
        });
    }

    const initial = html.dataset.theme || (() => {
        try {
            return localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
        } catch { return 'dark'; }
    })();
    applyTheme(initial);

    document.addEventListener('click', (e) => {
        if (e.target.closest('.theme-toggle-sidebar')) {
            applyTheme((html.dataset.theme === 'dark') ? 'light' : 'dark');
        }
    });

    if (!localStorage.getItem('theme')) {
        try {
            matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => applyTheme(e.matches ? 'dark' : 'light'));
        } catch {}
    }
})();

// ---- Service Worker (ALWAYS disable for localhost/dev to prevent CSRF issues)
if ('serviceWorker' in navigator) {
    const host = window.location.hostname;
    const isLocalHost = host === '127.0.0.1' || host === 'localhost' || host.startsWith('127.') || host.startsWith('192.168.');
    const isDevPort = Number(window.location.port) === 5173 || Number(window.location.port) === 5174 || Number(window.location.port) === 8000;
    const isDevEnv = (window.APP.env === 'local' || window.APP.env === 'development');
    const isDevelopment = isLocalHost || isDevPort || isDevEnv;

    // FORCE unregister service worker in development/localhost to prevent CSRF/419 errors
    if (isDevelopment || isLocalHost) {
        navigator.serviceWorker.getRegistrations?.().then(rs => {
            if (rs.length > 0) {
                rs.forEach(r => {
                    r.unregister().then(() => {
                        console.log('🚫 SW unregistered for dev/localhost');
                    });
                });
                // Also clear caches
                caches.keys().then(keys => {
                    keys.forEach(key => caches.delete(key));
                });
            }
        });
    } else {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js')
                .then((registration) => {
                    console.log('✅ SW registered');
                    registration.addEventListener('updatefound', () => {
                        const nw = registration.installing;
                        nw?.addEventListener('statechange', () => {
                            if (nw.state === 'installed' && navigator.serviceWorker.controller) {
                                if (confirm('A new version of GekyChat is available. Update now?')) {
                                    window.location.reload();
                                }
                            }
                        });
                    });
                })
                .catch((err) => console.log('❌ SW registration failed:', err));
        });

        navigator.serviceWorker.addEventListener?.('message', (event) => {
            if (event.data && event.data.type === 'NOTIFICATION_CLICK') window.focus();
        });
    }
}

// ---- Debug info (no import.meta here)
document.addEventListener('DOMContentLoaded', () => {
    console.log('🔧 Debug info:', {
        csrfToken: window.APP.csrf ? '✓ Set' : '✗ Missing',
        currentUserId: window.APP.userId,
        echoAvailable: typeof Echo !== 'undefined',
        reverbKey: window.APP.hasReverbKey ? '✓ Set' : '✗ Missing',
        env: window.APP.env
    });

    // Echo quick test (will be re-initialized by app.js anyway)
    if (window.Echo && window.Echo.socketId && window.Echo.socketId() !== 'no-op-socket-id') {
        console.log('🔌 Testing Echo connection...');
        window.Echo.channel('test-channel').listen('.TestEvent', (e) => {
            console.log('✅ Test event received:', e);
        });
    }
});

// Echo-ready custom event handler (unchanged; uses injected event.detail.echo)
document.addEventListener('echo:ready', (event) => {
    const { echo, isNoOp } = event.detail || {};
    if (!echo) return;
    if (isNoOp) {
        console.warn('⚠️ Echo is in no-op mode - real-time features disabled');
        return;
    }
    echo.channel('test-channel')
        .listen('.TestEvent', (e) => console.log('✅ Test event received:', e))
        .error((err) => console.error('❌ Test channel error:', err));

    if (window.currentChatId && window.currentChatType === 'direct') {
        echo.private(`chat.${window.currentChatId}`)
            .listen('.MessageSent', (e) => console.log('✅ Private message:', e))
            .error((err) => console.error('❌ Private channel auth error:', err));
    }
});

// ---- Desktop App Deep Link Handler
(function() {
    // Check if user has already dismissed the prompt
    const DISMISSED_KEY = 'gekychat_desktop_prompt_dismissed';
    const ALWAYS_ALLOW_KEY = 'gekychat_desktop_always_allow';
    
    // Detect if we're on a desktop OS
    function isDesktop() {
        const userAgent = navigator.userAgent.toLowerCase();
        const mobileRegex = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i;
        return !mobileRegex.test(userAgent);
    }
    
    // Check if desktop app is installed (by trying to open the protocol)
    function checkDesktopAppInstalled(callback) {
        if (!isDesktop()) {
            callback(false);
            return;
        }
        
        // Try to open the protocol link
        const testLink = 'gekychat://test';
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = testLink;
        document.body.appendChild(iframe);
        
        let appInstalled = false;
        const timeout = setTimeout(() => {
            document.body.removeChild(iframe);
            callback(appInstalled);
        }, 1000);
        
        // If the iframe loads, the app might be installed
        iframe.onload = () => {
            appInstalled = true;
            clearTimeout(timeout);
            document.body.removeChild(iframe);
            callback(true);
        };
        
        // Fallback: assume app might be installed if we're on Windows
        window.addEventListener('blur', () => {
            appInstalled = true;
        }, { once: true });
    }
    
    // Show prompt to open desktop app
    function showDesktopAppPrompt() {
        // Check if user has dismissed or set to always allow
        const dismissed = localStorage.getItem(DISMISSED_KEY);
        const alwaysAllow = localStorage.getItem(ALWAYS_ALLOW_KEY) === 'true';
        
        if (dismissed && !alwaysAllow) {
            return; // User dismissed, don't show again
        }
        
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'desktop-app-prompt-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        `;
        
        const dialog = document.createElement('div');
        dialog.style.cssText = `
            background: var(--bs-dark, #202C33);
            border-radius: 12px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            color: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        `;
        
        dialog.innerHTML = `
            <h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 600;">Open GekyChat?</h3>
            <p style="margin: 0 0 20px 0; color: rgba(255, 255, 255, 0.7); font-size: 14px;">
                ${window.location.hostname} wants to open this application.
            </p>
            <label style="display: flex; align-items: center; margin-bottom: 20px; cursor: pointer;">
                <input type="checkbox" id="always-allow-checkbox" style="margin-right: 8px; width: 18px; height: 18px;">
                <span style="font-size: 14px; color: rgba(255, 255, 255, 0.9);">
                    Always allow ${window.location.hostname} to open links of this type in the associated app
                </span>
            </label>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button id="cancel-btn" style="
                    background: transparent;
                    border: 1px solid rgba(255, 255, 255, 0.3);
                    color: white;
                    padding: 10px 20px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                ">Cancel</button>
                <button id="open-btn" style="
                    background: #673ab7;
                    border: none;
                    color: white;
                    padding: 10px 20px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                ">Open GekyChat</button>
            </div>
        `;
        
        modal.appendChild(dialog);
        document.body.appendChild(modal);
        
        // Get current URL to pass to desktop app
        const currentUrl = window.location.href;
        const protocolLink = `gekychat://web?url=${encodeURIComponent(currentUrl)}`;
        
        // Handle open button
        document.getElementById('open-btn').addEventListener('click', () => {
            const alwaysAllow = document.getElementById('always-allow-checkbox').checked;
            if (alwaysAllow) {
                localStorage.setItem(ALWAYS_ALLOW_KEY, 'true');
            }
            
            // Try to open the desktop app
            window.location.href = protocolLink;
            
            // Close modal after a short delay
            setTimeout(() => {
                document.body.removeChild(modal);
            }, 300);
        });
        
        // Handle cancel button
        document.getElementById('cancel-btn').addEventListener('click', () => {
            localStorage.setItem(DISMISSED_KEY, 'true');
            document.body.removeChild(modal);
        });
        
        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                localStorage.setItem(DISMISSED_KEY, 'true');
                document.body.removeChild(modal);
            }
        });
    }
    
    // Initialize on page load (only for authenticated users on desktop)
    document.addEventListener('DOMContentLoaded', () => {
        if (!isDesktop()) return;
        if (!window.APP || !window.APP.userId) return; // Only for logged-in users
        
        // Only show prompt on web.gekychat.com (main chat domain)
        if (window.location.hostname !== 'web.gekychat.com') {
            return;
        }
        
        // Check if we should show the prompt
        const alwaysAllow = localStorage.getItem(ALWAYS_ALLOW_KEY) === 'true';
        const dismissed = localStorage.getItem(DISMISSED_KEY);
        
        // Show prompt on first visit or if user hasn't set preference
        if (!dismissed || alwaysAllow) {
            // Small delay to avoid interrupting page load
            setTimeout(() => {
                // Only show if user hasn't explicitly dismissed
                if (!dismissed) {
                    showDesktopAppPrompt();
                } else if (alwaysAllow) {
                    // If always allow is set, try to open automatically
                    const currentUrl = window.location.href;
                    const protocolLink = `gekychat://web?url=${encodeURIComponent(currentUrl)}`;
                    window.location.href = protocolLink;
                }
            }, 2000); // 2 second delay
        }
    });
})();
</script>