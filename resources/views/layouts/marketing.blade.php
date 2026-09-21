{{-- Shared marketing layout for gekychat.com (no WhatsApp tokens). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta_description', 'GekyChat — messaging, Status, World Feed, and calls on mobile, desktop, and web.')">
    <title>@yield('title', 'GekyChat')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gek-green: #0F8A5F;
            --gek-green-dark: #0A6B49;
            --gek-green-soft: #E6F6EF;
            --gek-gold: #C9922A;
            --gek-gold-soft: #F8EFD9;
            --text: #122018;
            --text-muted: #5A6B62;
            --bg: #F4F7F5;
            --bg-deep: #0C1A14;
            --card: #FFFFFF;
            --border: #D5E0DA;
            --radius: 14px;
            --font: "DM Sans", system-ui, sans-serif;
            --display: "Fraunces", Georgia, serif;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: var(--font);
            color: var(--text);
            background: var(--bg);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a { color: var(--gek-green-dark); }
        a:hover { color: var(--gek-green); }

        .mk-nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(244, 247, 245, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }
        .mk-nav-inner {
            max-width: 1120px;
            margin: 0 auto;
            padding: 0.85rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .mk-brand {
            font-family: var(--display);
            font-weight: 700;
            font-size: 1.35rem;
            color: var(--text) !important;
            text-decoration: none;
            letter-spacing: -0.02em;
        }
        .mk-brand span { color: var(--gek-green); }
        .mk-nav-links {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin-left: auto;
            list-style: none;
            padding: 0;
            margin-bottom: 0;
        }
        .mk-nav-links a {
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
        }
        .mk-nav-links a:hover { color: var(--text); background: rgba(15, 138, 95, 0.08); }
        .mk-nav-cta { display: flex; gap: 0.5rem; margin-left: 0.5rem; }
        .mk-burger {
            display: none;
            background: none;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.4rem 0.65rem;
            margin-left: auto;
        }

        .btn-gek {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            background: var(--gek-green);
            color: #fff !important;
            border: none;
            border-radius: 999px;
            padding: 0.65rem 1.25rem;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-gek:hover { background: var(--gek-green-dark); color: #fff !important; transform: translateY(-1px); }
        .btn-gek-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            background: transparent;
            color: var(--text) !important;
            border: 1.5px solid var(--border);
            border-radius: 999px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        .btn-gek-outline:hover {
            border-color: var(--gek-green);
            background: var(--gek-green-soft);
            color: var(--gek-green-dark) !important;
        }
        .btn-gek-gold {
            background: var(--gek-gold);
            color: #1a1408 !important;
        }
        .btn-gek-gold:hover { background: #B07E20; color: #1a1408 !important; }

        /* Compat aliases for migrated pages */
        .btn-wa { background: var(--gek-green); border-color: var(--gek-green); color: #fff; }
        .btn-wa:hover { background: var(--gek-green-dark); border-color: var(--gek-green-dark); color: #fff; }
        .text-wa, .text-gek { color: var(--gek-green) !important; }
        .bg-wa { background-color: var(--gek-green) !important; }
        .border-wa { border-color: var(--gek-green) !important; }

        main.mk-main { flex: 1; }

        .mk-footer {
            background: var(--bg-deep);
            color: #C8D8D0;
            margin-top: auto;
            padding: 3rem 1.25rem 2rem;
        }
        .mk-footer-inner {
            max-width: 1120px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr;
            gap: 2rem;
        }
        .mk-footer h3 {
            font-family: var(--display);
            color: #fff;
            font-size: 1.1rem;
            margin: 0 0 0.75rem;
        }
        .mk-footer p { margin: 0; font-size: 0.95rem; opacity: 0.85; max-width: 28ch; }
        .mk-footer ul { list-style: none; padding: 0; margin: 0; }
        .mk-footer li { margin-bottom: 0.45rem; }
        .mk-footer a { color: #C8D8D0; text-decoration: none; font-size: 0.95rem; }
        .mk-footer a:hover { color: #fff; }
        .mk-footer-bottom {
            max-width: 1120px;
            margin: 2rem auto 0;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            font-size: 0.85rem;
            opacity: 0.7;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 8px 24px rgba(12, 26, 20, 0.04);
        }
        .legal-content h2 {
            color: var(--gek-green-dark);
            border-bottom: 2px solid var(--gek-green-soft);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        .legal-content h3 { margin-top: 2rem; margin-bottom: 1rem; font-weight: 600; }
        .legal-content ul { padding-left: 1.5rem; }
        .legal-content li { margin-bottom: 0.5rem; }

        .page-shell { max-width: 960px; margin: 0 auto; padding: 2.5rem 1.25rem 4rem; }

        @media (max-width: 900px) {
            .mk-burger { display: inline-flex; }
            .mk-nav-links, .mk-nav-cta {
                display: none;
                position: absolute;
                left: 1rem;
                right: 1rem;
                top: 100%;
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 12px;
                flex-direction: column;
                padding: 0.75rem;
                box-shadow: 0 12px 32px rgba(0,0,0,0.08);
            }
            .mk-nav.open .mk-nav-links,
            .mk-nav.open .mk-nav-cta { display: flex; }
            .mk-nav.open .mk-nav-cta { top: calc(100% + 12.5rem); }
            .mk-footer-inner { grid-template-columns: 1fr; }
        }
    </style>
    @stack('styles')
    @stack('head')
</head>
<body>
    @php $webApp = 'https://web.gekychat.com'; @endphp
    <header class="mk-nav" id="mkNav">
        <div class="mk-nav-inner">
            <a class="mk-brand" href="{{ url('/') }}">Geky<span>Chat</span></a>
            <button class="mk-burger" type="button" aria-label="Menu" onclick="document.getElementById('mkNav').classList.toggle('open')">
                <i class="bi bi-list fs-4"></i>
            </button>
            <ul class="mk-nav-links">
                <li><a href="{{ route('landing.features') }}">Features</a></li>
                <li><a href="{{ route('landing.download') }}">Download</a></li>
                <li><a href="{{ route('landing.about') }}">About</a></li>
                <li><a href="{{ route('landing.help') }}">Help</a></li>
            </ul>
            <div class="mk-nav-cta">
                <a class="btn-gek-outline" href="{{ $webApp }}">Open web</a>
                <a class="btn-gek" href="{{ route('landing.download') }}">Download</a>
            </div>
        </div>
    </header>

    <main class="mk-main">
        @yield('content')
    </main>

    <footer class="mk-footer">
        <div class="mk-footer-inner">
            <div>
                <h3>GekyChat</h3>
                <p>Messaging, Status, World Feed, and calls — on the phone, desktop, and web you already use.</p>
            </div>
            <div>
                <h3>Product</h3>
                <ul>
                    <li><a href="{{ route('landing.features') }}">Features</a></li>
                    <li><a href="{{ route('landing.download') }}">Download</a></li>
                    <li><a href="{{ route('landing.about') }}">About</a></li>
                    <li><a href="{{ $webApp }}">Open web app</a></li>
                </ul>
            </div>
            <div>
                <h3>Support</h3>
                <ul>
                    <li><a href="{{ route('landing.help') }}">Help Center</a></li>
                    <li><a href="{{ route('landing.contact') }}">Contact</a></li>
                    <li><a href="{{ route('landing.privacy.policy') }}">Privacy Policy</a></li>
                    <li><a href="{{ route('landing.terms.service') }}">Terms of Service</a></li>
                    <li><a href="{{ route('landing.request.account.deletion') }}">Delete account</a></li>
                </ul>
            </div>
        </div>
        <div class="mk-footer-bottom">
            &copy; {{ date('Y') }} GekyChat. All rights reserved.
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
