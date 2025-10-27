{{-- resources/views/layouts/public.blade.php --}}
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name', 'GekyChat'))</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --wa-green: #25D366;
            --wa-green-dark: #128C7E;
            --wa-green-light: #DCF8C6;
            --text: #333333;
            --text-light: #666666;
            --bg: #ffffff;
            --card: #ffffff;
            --border: #e0e0e0;
            --input-bg: #f8f9fa;
            --input-border: #dee2e6;
            --wa-muted: #6c757d;
        }

        [data-bs-theme="dark"] {
            --text: #ffffff;
            --text-light: #b0b0b0;
            --bg: #1a1a1a;
            --card: #2d2d2d;
            --border: #404040;
            --input-bg: #2d2d2d;
            --input-border: #404040;
            --wa-muted: #8a8a8a;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
        }

        .navbar-brand {
            font-weight: 600;
            color: var(--wa-green) !important;
        }

        .btn-wa {
            background-color: var(--wa-green);
            border-color: var(--wa-green);
            color: #ffffff;
        }

        .btn-wa:hover {
            background-color: var(--wa-green-dark);
            border-color: var(--wa-green-dark);
            color: #ffffff;
        }

        .text-wa {
            color: var(--wa-green) !important;
        }

        .bg-wa {
            background-color: var(--wa-green) !important;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .content-section {
            background: var(--bg);
            min-height: 100vh;
        }

        .footer {
            background: var(--card);
            border-top: 1px solid var(--border);
        }

        /* Content styling for legal pages */
        .legal-content h2 {
            color: var(--wa-green);
            border-bottom: 2px solid var(--wa-green);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .legal-content h3 {
            color: var(--text);
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .legal-content ul {
            padding-left: 1.5rem;
        }

        .legal-content li {
            margin-bottom: 0.5rem;
            position: relative;
        }

        .legal-content li:before {
            content: "•";
            color: var(--wa-green);
            font-weight: bold;
            position: absolute;
            left: -1rem;
        }

        .back-to-home {
            transition: all 0.3s ease;
        }

        .back-to-home:hover {
            transform: translateX(-5px);
        }

        @media (max-width: 768px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            .card-body {
                padding: 1.5rem !important;
            }
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-card border-bottom border-border">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="{{ url('/') }}">
                <i class="bi bi-chat-dots-fill me-2"></i>
                <span class="fw-bold">{{ config('app.name', 'GekyChat') }}</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-text" href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-text" href="{{ route('register') }}">
                            <i class="bi bi-person-plus me-1"></i> Sign Up
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="content-section">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="footer py-4 mt-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted mb-0">
                        &copy; {{ date('Y') }} {{ config('app.name', 'GekyChat') }}. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="{{ route('privacy.policy') }}" class="text-muted text-decoration-none me-3">
                        Privacy Policy
                    </a>
                    <a href="{{ route('terms.service') }}" class="text-muted text-decoration-none me-3">
                        Terms of Service
                    </a>
                    <a href="mailto:support@gekychat.com" class="text-muted text-decoration-none">
                        Contact Us
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    @stack('scripts')
</body>
</html>