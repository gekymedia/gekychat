<!-- resources/views/layouts/app.blade.php -->
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
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
  <link rel="manifest" href="/manifest.json">
  <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

  <!-- Favicons -->
  <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16x16.png">
  <link rel="shortcut icon" href="/icons/icon-512x512.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">

  <!-- Early theme (avoid flash) -->
  <script>
    (function () {
      try {
        const saved = localStorage.getItem('theme');
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
      --nav-h:64px; --space-1:4px; --space-2:8px; --space-3:12px; --space-4:16px; --space-5:20px; --space-6:24px;
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
    html, body { height:100%; }
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

    .wa-navbar{
      background: linear-gradient(135deg, var(--wa-deep), var(--wa-green));
      color:#fff; min-height: var(--nav-h); box-shadow: var(--wa-shadow);
    }
    .wa-navbar .container{ padding-block: var(--space-3); }
    .wa-navbar .navbar-brand{ color:#fff!important; font-weight:800; letter-spacing:.2px; display:flex; align-items:center; gap:10px; }
    .wa-navbar .nav-link, .wa-navbar .dropdown-item{ color:#fff!important; font-weight:600; }
    .wa-navbar .dropdown-menu{ border:none; border-radius:14px; background:var(--card); color:var(--text); box-shadow:var(--wa-shadow); }
    .wa-navbar .dropdown-item:hover{ background:rgba(255,255,255,.07); }
    .brand-badge{ display:inline-grid; place-items:center; width:28px; height:28px; border-radius:8px; background:rgba(255,255,255,.18); }

    .content-wrap{ padding-block: var(--space-6); }
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
  </style>

  @stack('head')
</head>
<body class="@yield('body_class')">
<div id="app">
  <nav class="navbar navbar-expand-md wa-navbar">
    <div class="container">
      <a class="navbar-brand fw-bold" href="{{ url('/') }}">
        <span class="brand-badge" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff" role="img" aria-label="chat logo">
            <path d="M20.52 3.48A11.94 11.94 0 0012 0C5.37 0 0 5.37 0 12c0 2.11.55 4.09 1.52 5.81L0 24l6.36-1.67A11.94 11.94 0 0012 24c6.63 0 12-5.37 12-12 0-3.2-1.28-6.18-3.48-8.52zM12 21.6a9.56 9.56 0 01-4.87-1.34l-.35-.21-3.76.99 1.01-3.65-.23-.38A9.55 9.55 0 012.4 12c0-5.29 4.31-9.6 9.6-9.6s9.6 4.31 9.6 9.6-4.31 9.6-9.6 9.6zm5.47-6.88c-.3-.15-1.79-.89-2.07-.98-.28-.1-.48-.15-.68.15-.2.3-.78.98-.96 1.19-.18.2-.36.22-.66.07-.3-.15-1.26-.46-2.4-1.47-.88-.79-1.47-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.36.45-.54.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.07-.15-.68-1.64-.93-2.25-.24-.58-.49-.5-.68-.51l-.58-.01c-.2 0-.53.08-.81.38-.28.3-1.07 1.05-1.07 2.56s1.1 2.96 1.26 3.17c.15.2 2.16 3.31 5.23 4.64.73.32 1.29.52 1.73.66.73.23 1.4.2 1.93.12.59-.09 1.79-.73 2.05-1.44.25-.71.25-1.33.17-1.46-.07-.13-.27-.2-.57-.35z"/>
          </svg>
        </span>
        {{ config('app.name', 'GekyChat') }}
      </a>

      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-label="Toggle navigation">
        <i class="bi bi-list text-white fs-2"></i>
      </button>

      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto"></ul>

        <ul class="navbar-nav ms-auto align-items-center gap-2">
          <li class="nav-item">
            <button id="theme-toggle" class="btn btn-sm btn-light text-dark rounded-pill px-3" title="Toggle theme" aria-pressed="false">
              <i class="bi bi-moon-stars-fill me-1"></i> Theme
            </button>
          </li>

          @guest
            @if (Route::has('login'))
              <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
            @endif
            @if (Route::has('register'))
              <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">Register</a></li>
            @endif
          @else
            <li class="nav-item dropdown">
              <a id="navbarDropdown" class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-2"></i> {{ Auth::user()->name ?? Auth::user()->phone }}
              </a>
              <div class="dropdown-menu dropdown-menu-end p-2">
                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('profile.edit') }}">
                  <i class="bi bi-person"></i> Profile
                </a>
                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('groups.create') }}">
                  <i class="bi bi-people"></i> New Group
                </a>
                <hr class="dropdown-divider">
                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('logout') }}"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                  <i class="bi bi-box-arrow-right"></i> Logout
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
              </div>
            </li>
          @endguest
        </ul>
      </div>
    </div>
  </nav>

  <main class="content-wrap">
    <div class="container">
      @yield('content')
    </div>
    <div class="container mt-3">
      <p class="fine-print text-center mb-0">
        By using GekyChat you agree to our <a href="#" class="text-decoration-none">Terms</a> & <a href="#" class="text-decoration-none">Privacy</a>.
      </p>
    </div>
  </main>
</div>

<!-- Bootstrap Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>

<!-- Pusher + Laravel Echo from CDNs (no Vite) -->
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js" defer></script>

<script>
  // Expose CSRF globally
  window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // Theme toggle + dynamic nav height + SW
  (function () {
    const html = document.documentElement;
    const themeMeta = document.getElementById('theme-color');
    const btn = document.getElementById('theme-toggle');

    function applyTheme(t) {
      html.dataset.theme = t;
      try { localStorage.setItem('theme', t); } catch {}
      themeMeta?.setAttribute('content', t === 'dark' ? '#0B141A' : '#FFFFFF');
      if (btn) {
        btn.setAttribute('aria-pressed', String(t !== 'dark'));
        btn.innerHTML = (t === 'dark'
          ? '<i class="bi bi-brightness-high-fill me-1"></i> Light'
          : '<i class="bi bi-moon-stars-fill me-1"></i> Dark');
      }
    }

    const initial = html.dataset.theme || (() => {
      try { return localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'); }
      catch { return 'dark'; }
    })();
    applyTheme(initial);

    btn?.addEventListener('click', () => applyTheme((html.dataset.theme === 'dark') ? 'light' : 'dark'));

    if (!localStorage.getItem('theme')) {
      try {
        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => applyTheme(e.matches ? 'dark' : 'light'));
      } catch {}
    }

    // Dynamic nav height
    const setNavHeight = () => {
      const nav = document.querySelector('.wa-navbar');
      if (nav) document.documentElement.style.setProperty('--nav-h', nav.offsetHeight + 'px');
    };
    document.addEventListener('DOMContentLoaded', setNavHeight);
    window.addEventListener('resize', () => { clearTimeout(window.__navT); window.__navT = setTimeout(setNavHeight, 120); });

    // Optional: Service worker
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
      // echo.iife exports may appear as window.Echo (ctor) or window.Echo.Echo
      if (typeof window.Echo === 'function') return window.Echo;
      if (window.Echo && typeof window.Echo.Echo === 'function') return window.Echo.Echo;
      if (window.EchoIife && typeof window.EchoIife.Echo === 'function') return window.EchoIife.Echo;
      return null;
    }

    function initEcho() {
      if (typeof window.Pusher === 'undefined') return setTimeout(initEcho, 50);
      const EchoCtor = getEchoCtor();
      if (!EchoCtor) return setTimeout(initEcho, 50);

      // Reverb config from .env (VITE_* preferred)
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
        // pusher-js option validator wants a cluster; irrelevant for Reverb but harmless
        cluster: 'mt1',
        // Private/presence authorizer
        authorizer: (channel, options) => ({
          authorize: (socketId, callback) => {
            fetch("{{ url('/broadcasting/auth') }}", {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: JSON.stringify({ socket_id: socketId, channel_name: channel.name })
            })
            .then(r => r.json())
            .then(data => callback(false, data))
            .catch(err => callback(true, err));
          }
        })
      };

     <!-- Replace your pusher client init with: -->
const pusherClient = new Pusher(key, {
  cluster: 'mt1',
  wsHost: echoOptions.wsHost,
  wsPort: echoOptions.wsPort,
  wssPort: echoOptions.wssPort,
  forceTLS: echoOptions.forceTLS,
  enabledTransports: echoOptions.enabledTransports,
  disableStats: echoOptions.disableStats,

  // IMPORTANT: make Pusher POST here, with CSRF
  authEndpoint: '/pusher/auth',
  auth: {
    headers: {
      'X-CSRF-TOKEN': window.csrfToken,
      'X-Requested-With': 'XMLHttpRequest'
    }
  }
});

// Use the client; drop the custom authorizer block entirely
window.Echo = new EchoCtor({ ...echoOptions, client: pusherClient });


      // Let page scripts know Echo is ready (so they can attach listeners)
      document.dispatchEvent(new CustomEvent('echo:ready'));
    }

    initEcho();
  });
</script>

@stack('scripts')
</body>
</html>
