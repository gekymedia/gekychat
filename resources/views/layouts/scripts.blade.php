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
</script>