<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
{{-- <!-- resources/views/layouts/app.blade.php --> --}}

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>{{ config('app.name', 'GekyChat') }} | Chat Instantly</title>
  <meta name="description" content="GekyChat is a real-time messaging app built for fast and secure communication.">
  <meta name="robots" content="index, follow">
  <meta name="author" content="GEKYMEDIA">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="color-scheme" content="light dark">
  <meta id="theme-color" name="theme-color" content="#0B141A">

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:title" content="GekyChat - Real-time Messaging">
  <meta property="og:description" content="Join GekyChat and chat instantly with others using real-time WebSockets.">
  <meta property="og:image" content="{{ asset('icons/icon-512x512.png') }}">
  <meta property="og:site_name" content="GekyChat">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="GekyChat">
  <meta name="twitter:description" content="Chat in real-time using GekyChat with file sharing and notifications.">
  <meta name="twitter:image" content="{{ asset('icons/icon-512x512.png') }}">

  <!-- Fonts & Icons -->
  <link rel="dns-prefetch" href="//fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=Nunito:300,400,600,700,800" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- PWA -->
  <link rel="manifest" href="{{ asset('icons/manifest.json') }}">

  <!-- Favicons (match provided pack) -->
  <link rel="icon" href="{{ asset('icons/favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32"  href="{{ asset('icons/icon-32x32.png') }}">
  <link rel="icon" type="image/png" sizes="48x48"  href="{{ asset('icons/icon-48x48.png') }}">
  <link rel="icon" type="image/png" sizes="72x72"  href="{{ asset('icons/icon-72x72.png') }}">
  <link rel="icon" type="image/png" sizes="96x96"  href="{{ asset('icons/icon-96x96.png') }}">
  <link rel="icon" type="image/png" sizes="128x128" href="{{ asset('icons/icon-128x128.png') }}">
  <link rel="icon" type="image/png" sizes="144x144" href="{{ asset('icons/icon-144x144.png') }}">
  <link rel="icon" type="image/png" sizes="152x152" href="{{ asset('icons/icon-152x152.png') }}">
  <link rel="icon" type="image/png" sizes="180x180" href="{{ asset('icons/icon-180x180.png') }}">
  <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
  <link rel="icon" type="image/png" sizes="256x256" href="{{ asset('icons/icon-256x256.png') }}">
  <link rel="icon" type="image/png" sizes="384x384" href="{{ asset('icons/icon-384x384.png') }}">
  <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icons/icon-512x512.png') }}">

  <!-- Apple Touch -->
  <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">

  <!-- Early theme (avoid flash) -->
  <script>
    (function () {
      try {
        const saved  = localStorage.getItem('theme');
        const system = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        document.documentElement.dataset.theme = saved || system || 'dark';
      } catch { document.documentElement.dataset.theme = 'dark'; }
    })();
  </script>

  <!-- Global theme + basic styles -->
  <style>
    :root{
      --wa-green:#25D366; --wa-deep:#128C7E;
      --bg:#0B141A; --bg-accent:#1f2c34; --text:#E9EDF0; --card:#111B21;
      --border:#22303A; --wa-muted:#9BB0BD; --wa-shadow:0 10px 30px rgba(0,0,0,.25);
      --nav-h:0px; /* Remove navbar height */
      --space-1:4px; --space-2:8px; --space-3:12px; --space-4:16px; --space-5:20px; --space-6:24px;
      --fs-sm:.92rem; --fs-base:.98rem; --fs-lg:1.05rem;
      --input-bg:#0f1a20; --input-border:var(--border);
      --bubble-sent-bg:#005c4b; --bubble-sent-text:#e6fffa;
      --bubble-recv-bg:#202c33; --bubble-recv-text:var(--text);
    }
    [data-theme="light"]{
      --bg:#FFFFFF; --bg-accent:#E9EEF5; --text:#0B141A; --card:#F8FAFC;
      --border:#E2E8F0; --wa-muted:#6B7280; --wa-shadow:0 10px 30px rgba(0,0,0,.08);
      --input-bg:#ffffff; --input-border:#E2E8F0;
      --bubble-sent-bg:#dcf8c6; --bubble-sent-text:#0b141a;
      --bubble-recv-bg:#ffffff; --bubble-recv-text:#0b141a;
    }
    :root{ --wa-card: var(--card); --wa-text: var(--text); --wa-border: var(--border); }
    html, body { height:100%; margin:0; padding:0; }
    body{
      background: radial-gradient(1100px 700px at 10% -10%, var(--bg-accent) 0, var(--bg) 60%), var(--bg);
      color:var(--text); transition: background-color .25s ease, color .25s ease;
      font-family:'Nunito', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial;
      font-size:var(--fs-base); line-height:1.55; letter-spacing:.1px;
      -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
    }
    body.page-chat{ overflow:hidden; }
    body.page-chat .content-wrap{ padding-block:0; }
    body.page-chat .content-wrap > .container{ max-width:100%; padding-left:0; padding-right:0; }
    body.page-chat .content-wrap + .container.mt-3{ display:none; }

    /* Remove navbar styles */
    .wa-navbar{ display:none; }

    .content-wrap{ padding-block:0; height:100vh; }
    .fine-print{ color:var(--wa-muted); font-size: var(--fs-sm); }

    .btn-wa{ background:var(--wa-green); border:none; color:#062a1f; font-weight:700; border-radius:14px; }
    .btn-wa:hover{ filter:brightness(1.05); }
    .btn-outline-wa{ border-color:var(--wa-green); color:var(--wa-green); border-radius:14px; }
    .btn-outline-wa:hover{ background:var(--wa-green); color:#062a1f; }
    .btn-link, a{ color:var(--wa-green); }
    .btn-link:hover, a:hover{ color:#1fc25a; }

    .wa-card{ background:var(--card); border:1px solid var(--border); border-radius:18px; box-shadow:var(--wa-shadow); }
    .form-control, .form-select{ background:var(--input-bg); color:var(--text); border:1px solid var(--input-border); border-radius:14px; }
    .form-control:focus, .form-select:focus{ border-color:var(--wa-green); box-shadow:none; }
    .muted{ color:var(--wa-muted)!important; }

    /* Sidebar header styles */
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

    .user-avatar-placeholder {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--wa-green);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #062a1f;
      font-weight: 700;
      border: 2px solid var(--wa-green);
    }

    /* Theme toggle in sidebar */
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

    /* Dropdown menu styles */
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

    /* ===== CRITICAL HEIGHT FIXES ===== */
html, body, #app, .content-wrap {
    height: 100% !important;
    margin: 0;
    padding: 0;
}

/* Chat container specific fixes */
.chat-container {
    height: 100vh !important;
    max-height: 100vh !important;
    overflow: hidden;
}

/* Ensure sidebar and chat area are proper height */
#conversation-sidebar,
#chat-area {
    height: 100% !important;
    max-height: 100vh !important;
}

/* Fix for conversation list scrolling */
.conversation-list {
    height: calc(100vh - 140px) !important; /* Account for header */
    overflow-y: auto !important;
    overflow-x: hidden !important;
}

/* Fix for messages container */
.messages-container {
    height: calc(100vh - 140px) !important; /* Account for header and composer */
    overflow: hidden !important;
}

#messages-container {
    height: 100% !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
}

/* Mobile responsive fixes */
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
    
    .conversation-list {
        height: calc(40vh - 120px) !important;
    }
    
    .messages-container {
        height: calc(60vh - 120px) !important;
    }
}
  </style>

  @stack('head')
</head>
<body class="@yield('body_class')">
<div id="app">
  {{-- Remove the entire navbar --}}
  
  <main class="content-wrap" id="main-content" tabindex="-1">
    <div class="container-fluid h-100 p-0">
      @yield('content')
    </div>
    
    {{-- Keep the fine print, but only show on non-chat pages
    @unless(Request::is('chat*') || Request::is('groups*'))
      <div class="container mt-3">
        <p class="fine-print text-center mb-0">
          By using GekyChat you agree to our <a href="#" class="text-decoration-none">Terms</a> &amp;
          <a href="#" class="text-decoration-none">Privacy</a>.
        </p>
      </div>
    @endunless --}}
  </main>
</div>

<!-- Bootstrap Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>

<!-- Pusher + Laravel Echo from CDNs (no Vite) -->
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/pusher.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js" defer></script>

<script>
  // Expose CSRF globally
  window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // Theme toggle + Service Worker
  (function () {
    const html = document.documentElement;
    const themeMeta = document.getElementById('theme-color');

    function applyTheme(t) {
      html.dataset.theme = t;
      try { localStorage.setItem('theme', t); } catch {}
      themeMeta?.setAttribute('content', t === 'dark' ? '#0B141A' : '#FFFFFF');
      
      // Update all theme toggle buttons
      document.querySelectorAll('.theme-toggle-sidebar').forEach(btn => {
        btn.setAttribute('aria-pressed', String(t !== 'dark'));
        btn.innerHTML = t === 'dark'
          ? '<i class="bi bi-brightness-high-fill me-1"></i> Light'
          : '<i class="bi bi-moon-stars-fill me-1"></i> Dark';
      });
    }

    const initial = html.dataset.theme || (() => {
      try { return localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'); }
      catch { return 'dark'; }
    })();
    applyTheme(initial);

    // Theme toggle event listeners
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

    // Service worker
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
          .then(reg => console.log('SW registered', reg))
          .catch(err => console.error('SW registration failed', err));
      });
    }
  })();
</script>

{{-- Echo bootstrapper (runs after Pusher + Echo scripts load) --}}
<script>
  document.addEventListener('DOMContentLoaded', function () {
    function getEchoCtor() {
      if (typeof window.Echo === 'function') return window.Echo;
      if (window.Echo && typeof window.Echo.Echo === 'function') return window.Echo.Echo;
      if (window.EchoIife && typeof window.EchoIife.Echo === 'function') return window.EchoIife.Echo;
      return null;
    }

    function initEcho() {
      if (typeof window.Pusher === 'undefined') return setTimeout(initEcho, 50);
      const EchoCtor = getEchoCtor();
      if (!EchoCtor) return setTimeout(initEcho, 50);

      const key    = @json(env('VITE_REVERB_APP_KEY', env('REVERB_APP_KEY')));
      const host   = @json(env('VITE_REVERB_HOST', '127.0.0.1'));
      const port   = Number(@json(env('VITE_REVERB_PORT', 8080)));
      const scheme = @json(env('VITE_REVERB_SCHEME', 'http'));
      const forceTLS = scheme === 'https';

      const echoOptions = {
        broadcaster: 'pusher',
        key: key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: forceTLS,
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        cluster: 'mt1'
      };

      // Initialize the Pusher client explicitly so we can attach CSRF headers
      const pusherClient = new Pusher(key, {
        cluster: 'mt1',
        wsHost: echoOptions.wsHost,
        wsPort: echoOptions.wsPort,
        wssPort: echoOptions.wssPort,
        forceTLS: echoOptions.forceTLS,
        enabledTransports: echoOptions.enabledTransports,
        disableStats: echoOptions.disableStats,
        authEndpoint: '/pusher/auth',
        auth: {
          headers: {
            'X-CSRF-TOKEN': window.csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
          }
        }
      });

      // Use the client; drop custom authorizer (Pusher will POST to authEndpoint)
      window.Echo = new EchoCtor({ ...echoOptions, client: pusherClient });

      // Notify when ready
      document.dispatchEvent(new CustomEvent('echo:ready'));
    }

    initEcho();
  });
</script>

@stack('scripts')
</body>
</html>