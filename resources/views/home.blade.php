<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GekyChat - Modern Real-time Messaging Platform</title>
    
    {{-- Fonts & Icons --}}
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:300,400,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    {{-- Bootstrap CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            /* GekyChat Brand Colors - Green & Gold */
            --geky-green: #10B981;
            --geky-green-dark: #059669;
            --geky-green-light: #34D399;
            --geky-gold: #F59E0B;
            --geky-gold-dark: #D97706;
            --geky-gold-light: #FBBF24;
            /* Legacy support */
            --wa-green: var(--geky-green);
            --wa-deep: var(--geky-green-dark);
            --bg: #0B141A;
            --bg-accent: #1f2c34;
            --text: #E9EDF0;
            --card: #111B21;
            --border: #22303A;
            --wa-muted: #9BB0BD;
            --wa-shadow: 0 10px 30px rgba(0, 0, 0, .25);
            --nav-h: 0px;
            --space-1: 4px;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-5: 20px;
            --space-6: 24px;
            --fs-sm: .92rem;
            --fs-base: .98rem;
            --fs-lg: 1.05rem;
            --input-bg: #0f1a20;
            --input-border: var(--border);
            --bubble-sent-bg: #064E3B;
            --bubble-sent-text: #A7F3D0;
            --bubble-recv-bg: #202c33;
            --bubble-recv-text: var(--text);
            --primary: var(--geky-green);
            --primary-dark: var(--geky-green-dark);
            --secondary: var(--geky-gold);
            --dark: #0B141A;
            --dark-light: #1f2c34;
            --light: #E9EDF0;
            --gray: #9BB0BD;
            --success: var(--geky-green);
            --border-radius: 12px;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        [data-theme="light"] {
            --bg: #FFFFFF;
            --bg-accent: #E9EEF5;
            --text: #0B141A;
            --card: #F8FAFC;
            --border: #E2E8F0;
            --wa-muted: #6B7280;
            --wa-shadow: 0 10px 30px rgba(0, 0, 0, .08);
            --input-bg: #ffffff;
            --input-border: #E2E8F0;
            --bubble-sent-bg: #dcf8c6;
            --bubble-sent-text: #0b141a;
            --bubble-recv-bg: #ffffff;
            --bubble-recv-text: #0b141a;
            --light: #F8FAFC;
            --gray: #6B7280;
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background: radial-gradient(1100px 700px at 10% -10%, var(--bg-accent) 0, var(--bg) 60%), var(--bg);
            color: var(--text);
            transition: background-color .25s ease, color .25s ease;
            font-family: 'Nunito', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial;
            font-size: var(--fs-base);
            line-height: 1.55;
            letter-spacing: .1px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5 {
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1rem;
        }

        p {
            margin-bottom: 1.5rem;
            color: var(--gray);
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            font-size: 16px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-green-dark) 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--geky-green-dark) 0%, var(--geky-green) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary {
            background-color: transparent;
            color: var(--geky-green);
            border: 2px solid var(--geky-green);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-green-dark) 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-light {
            background-color: var(--light);
            color: var(--dark);
        }

        .btn-light:hover {
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        section {
            padding: 100px 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .section-title p {
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Header */
        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            background-color: var(--card);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            border-bottom: 1px solid var(--border);
        }

        header.scrolled {
            padding: 10px 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
        }

        .logo i {
            margin-right: 10px;
            font-size: 1.8rem;
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 30px;
        }

        nav ul li a {
            text-decoration: none;
            color: var(--text);
            font-weight: 500;
            transition: var(--transition);
        }

        nav ul li a:hover {
            color: var(--geky-green);
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text);
            cursor: pointer;
        }

        /* Hero Section */
        .hero {
            padding: 180px 0 100px;
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" opacity="0.05"><polygon fill="white" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
        }

        .hero-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .hero-text {
            flex: 1;
            max-width: 600px;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            color: white;
        }

        .hero-text p {
            font-size: 1.2rem;
            margin-bottom: 2.5rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
        }

        .hero-image {
            flex: 1;
            display: flex;
            justify-content: flex-end;
        }

        .chat-preview {
            width: 350px;
            height: 500px;
            background: var(--card);
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border);
        }

        .chat-header {
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-green-dark) 100%);
            color: #ffffff;
            padding: 15px;
            display: flex;
            align-items: center;
        }

        .chat-header .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }

        .chat-body {
            padding: 20px;
            height: calc(100% - 70px);
            display: flex;
            flex-direction: column;
        }

        .message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            margin-bottom: 15px;
            position: relative;
            animation: fadeIn 0.5s ease;
        }

        .message.received {
            background: var(--bubble-recv-bg);
            color: var(--bubble-recv-text);
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }

        .message.sent {
            background: var(--bubble-sent-bg);
            color: var(--bubble-sent-text);
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }

        .typing-indicator {
            display: flex;
            align-items: center;
            margin-top: 10px;
        }

        .typing-dots {
            display: flex;
            background: var(--bubble-recv-bg);
            padding: 10px 15px;
            border-radius: 18px;
            border-bottom-left-radius: 5px;
        }

        .typing-dots span {
            height: 8px;
            width: 8px;
            border-radius: 50%;
            background: var(--gray);
            margin: 0 2px;
            animation: typing 1.5s infinite;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-5px);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Features Section */
        .features {
            background-color: var(--bg);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: var(--card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-align: center;
            border: 1px solid var(--border);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(245, 158, 11, 0.1) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--geky-green);
            font-size: 1.8rem;
            border: 2px solid rgba(16, 185, 129, 0.2);
        }

        .feature-card h3 {
            font-size: 1.4rem;
            margin-bottom: 15px;
        }

        /* How It Works */
        .how-it-works {
            background-color: var(--bg-accent);
        }

        .steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            max-width: 900px;
            margin: 0 auto;
        }

        .steps::before {
            content: '';
            position: absolute;
            top: 40px;
            left: 10%;
            width: 80%;
            height: 2px;
            background: var(--primary);
            z-index: 1;
        }

        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-number {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 auto 20px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .step h3 {
            margin-bottom: 10px;
        }

        /* Security Section */
        .security {
            background-color: var(--bg);
        }

        .security-content {
            display: flex;
            align-items: center;
            gap: 50px;
        }

        .security-text {
            flex: 1;
        }

        .security-image {
            flex: 1;
            text-align: center;
        }

        .security-icon {
            font-size: 8rem;
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 0.7;
        }

        .security-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }

        .security-feature {
            display: flex;
            align-items: flex-start;
        }

        .security-feature i {
            color: var(--geky-green);
            margin-right: 10px;
            margin-top: 5px;
        }

        /* Testimonials */
        .testimonials {
            background: linear-gradient(135deg, var(--dark) 0%, var(--dark-light) 100%);
            color: var(--text);
        }

        .testimonials .section-title h2,
        .testimonials .section-title p {
            color: var(--text);
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .testimonial-card {
            background: var(--card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .testimonial-text {
            font-style: italic;
            margin-bottom: 20px;
            position: relative;
        }

        .testimonial-text::before {
            content: '"';
            font-size: 4rem;
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: absolute;
            top: -20px;
            left: -10px;
            opacity: 0.3;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #ffffff;
        }

        .author-info h4 {
            margin-bottom: 5px;
        }

        .author-info p {
            margin: 0;
            color: var(--gray);
        }

        /* CTA Section */
        .cta {
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            color: #ffffff;
            text-align: center;
            padding: 100px 0;
        }

        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .cta p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 2.5rem;
            color: rgba(255, 255, 255, 0.9);
        }

        /* Footer */
        footer {
            background: var(--dark);
            color: var(--text);
            padding: 80px 0 30px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin-bottom: 50px;
        }

        .footer-column h3 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-column h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, var(--geky-green) 0%, var(--geky-gold) 100%);
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column ul li {
            margin-bottom: 10px;
        }

        .footer-column ul li a {
            color: var(--gray);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-column ul li a:hover {
            color: var(--text);
            padding-left: 5px;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--dark-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text);
            text-decoration: none;
            transition: var(--transition);
        }

        .social-links a:hover {
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            color: #ffffff;
            transform: translateY(-3px);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid var(--border);
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Theme Toggle */
        .theme-toggle {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .theme-toggle:hover {
            background: var(--border);
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
            }

            .hero-text {
                margin-bottom: 50px;
            }

            .security-content {
                flex-direction: column;
            }

            .steps {
                flex-direction: column;
                gap: 40px;
            }

            .steps::before {
                display: none;
            }
        }

        @media (max-width: 768px) {
            nav ul {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
            }

            .hero-text h1 {
                font-size: 2.5rem;
            }

            .section-title h2 {
                font-size: 2rem;
            }

            .security-features {
                grid-template-columns: 1fr;
            }
        }

        /* Additional GekyChat-specific styles */
        .feature-highlight {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .feature-highlight i {
            color: var(--geky-green);
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .pricing-card {
            background: var(--card);
            border-radius: var(--border-radius);
            padding: 40px 30px;
            text-align: center;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .pricing-card.featured {
            border: 2px solid var(--geky-green);
            position: relative;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .pricing-card.featured::before {
            content: 'Most Popular';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            color: #ffffff;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .pricing-price {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 20px 0;
        }

        .pricing-features {
            list-style: none;
            margin: 30px 0;
        }

        .pricing-features li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .pricing-features li i {
            color: var(--geky-green);
            margin-right: 10px;
        }

        .integration-logos {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 40px;
        }

        .integration-logo {
            width: 80px;
            height: 80px;
            background: var(--card);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--geky-green);
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .integration-logo:hover {
            background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
            color: #ffffff;
            transform: translateY(-5px);
        }
    </style>
</head>
<body data-theme="dark">
    <!-- Header -->
    <header id="header">
        <div class="container header-container">
            <a href="#" class="logo">
                <i class="bi bi-chat-dots-fill"></i>
                GekyChat
            </a>
            <nav>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#security">Security</a></li>
                    <li><a href="#testimonials">Testimonials</a></li>
                    <li><a href="https://web.gekychat.com" class="btn btn-secondary">Open Chat</a></li>
                    <li>
                        <button class="theme-toggle" id="themeToggle">
                            <i class="bi bi-moon-fill"></i>
                            <span>Dark</span>
                        </button>
                    </li>
                </ul>
            </nav>
            <button class="mobile-menu-btn">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container hero-content">
            <div class="hero-text">
                <h1>Connect Instantly with GekyChat</h1>
                <p>Experience seamless real-time messaging with advanced features, complete privacy, and a modern interface designed for today's communication needs.</p>
                <div class="hero-buttons">
                    <a href="https://web.gekychat.com" class="btn btn-light">Start Chatting</a>
                    <a href="#features" class="btn btn-secondary">Learn More</a>
                </div>
                <div class="mt-4">
                    <div class="feature-highlight">
                        <i class="bi bi-shield-check"></i>
                        <span>End-to-End Encryption</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-lightning-charge"></i>
                        <span>Real-time Messaging</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-people"></i>
                        <span>Group Chats & Channels</span>
                    </div>
                </div>
            </div>
            <div class="hero-image">
                <div class="chat-preview">
                    <div class="chat-header">
                        <div class="avatar">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <div>
                            <h4>Alex Johnson</h4>
                            <p>Online</p>
                        </div>
                    </div>
                    <div class="chat-body">
                        <div class="message received">
                            Hey! Are we still meeting tomorrow?
                        </div>
                        <div class="message sent">
                            Yes, 3 PM at the usual place.
                        </div>
                        <div class="message received">
                            Perfect! I'll bring the documents 📄
                        </div>
                        <div class="message sent">
                            Great! I've forwarded the agenda to the team.
                        </div>
                        <div class="message received">
                            <i class="bi bi-geo-alt"></i> Shared location
                        </div>
                        <div class="typing-indicator">
                            <div class="typing-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title">
                <h2>Powerful Features</h2>
                <p>GekyChat comes packed with everything you need for modern communication</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-chat-dots"></i>
                    </div>
                    <h3>Real-time Messaging</h3>
                    <p>Instant message delivery with typing indicators and read receipts for seamless conversations.</p>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Typing indicators</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Read receipts</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Delivery status</span>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3>Group Chats</h3>
                    <p>Create groups and channels for team collaboration or community discussions with admin controls.</p>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Admin controls</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Member management</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Channel creation</span>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-file-earmark-arrow-up"></i>
                    </div>
                    <h3>File Sharing</h3>
                    <p>Share images, documents, and other files securely with built-in preview and download options.</p>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Multiple file types</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Preview support</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Secure transfers</span>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <h3>Location Sharing</h3>
                    <p>Share your location with contacts or groups for easy meetups and coordination.</p>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Real-time location</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Map integration</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Privacy controls</span>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-lock"></i>
                    </div>
                    <h3>End-to-End Encryption</h3>
                    <p>Your conversations are secured with advanced encryption to ensure complete privacy.</p>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Military-grade encryption</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Self-destructing messages</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>No data storage</span>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-emoji-smile"></i>
                    </div>
                    <h3>Message Reactions</h3>
                    <p>React to messages with emojis to express yourself without typing a response.</p>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Emoji reactions</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Quick replies</span>
                    </div>
                    <div class="feature-highlight">
                        <i class="bi bi-check-circle"></i>
                        <span>Message replies</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-title">
                <h2>How GekyChat Works</h2>
                <p>Get started in just a few simple steps</p>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Sign Up</h3>
                    <p>Create your account with email or phone number in seconds. No complicated setup required.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Find Contacts</h3>
                    <p>Connect with friends or colleagues using their phone numbers or invite them to join.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Start Chatting</h3>
                    <p>Send messages, share files, create groups and enjoy seamless communication.</p>
                </div>
            </div>
            <div class="text-center mt-5">
                <a href="https://web.gekychat.com" class="btn btn-primary">Get Started Now</a>
            </div>
        </div>
    </section>

    <!-- Security Section -->
    <section class="security" id="security">
        <div class="container">
            <div class="section-title">
                <h2>Your Privacy is Our Priority</h2>
                <p>We implement the highest security standards to protect your conversations</p>
            </div>
            <div class="security-content">
                <div class="security-text">
                    <h3>Advanced Security Features</h3>
                    <p>GekyChat uses state-of-the-art encryption and security protocols to ensure your messages remain private and secure. Our commitment to privacy means we never store your conversations on our servers.</p>
                    <div class="security-features">
                        <div class="security-feature">
                            <i class="bi bi-check-circle-fill"></i>
                            <div>
                                <h4>End-to-End Encryption</h4>
                                <p>Only you and your recipients can read your messages</p>
                            </div>
                        </div>
                        <div class="security-feature">
                            <i class="bi bi-check-circle-fill"></i>
                            <div>
                                <h4>Self-Destructing Messages</h4>
                                <p>Set messages to disappear after being read</p>
                            </div>
                        </div>
                        <div class="security-feature">
                            <i class="bi bi-check-circle-fill"></i>
                            <div>
                                <h4>Two-Factor Authentication</h4>
                                <p>Add an extra layer of security to your account</p>
                            </div>
                        </div>
                        <div class="security-feature">
                            <i class="bi bi-check-circle-fill"></i>
                            <div>
                                <h4>No Data Mining</h4>
                                <p>We never sell or share your personal information</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="security-image">
                    <i class="bi bi-shield-lock security-icon"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials" id="testimonials">
        <div class="container">
            <div class="section-title">
                <h2>What Our Users Say</h2>
                <p>Discover why thousands of users trust GekyChat for their communication needs</p>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        GekyChat has transformed how our team communicates. The group features and file sharing capabilities are exceptional. We've completely moved from other platforms.
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar">SJ</div>
                        <div class="author-info">
                            <h4>Sarah Johnson</h4>
                            <p>Project Manager</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        The security features give me peace of mind when discussing sensitive business information. Highly recommended for any professional use case.
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar">MR</div>
                        <div class="author-info">
                            <h4>Michael Rodriguez</h4>
                            <p>Business Owner</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        I love how intuitive GekyChat is. It has all the features of other messaging apps but with a much cleaner interface and better performance.
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar">ET</div>
                        <div class="author-info">
                            <h4>Emily Thompson</h4>
                            <p>UX Designer</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Get Started?</h2>
            <p>Join thousands of users who are already enjoying seamless communication with GekyChat</p>
            <a href="https://web.gekychat.com" class="btn btn-light">Start Chatting Now</a>
            <p class="mt-3">No credit card required • Free forever plan</p>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>GekyChat</h3>
                    <p>The modern messaging platform for individuals and teams who value privacy and efficiency.</p>
                    <div class="social-links">
                        <a href="#"><i class="bi bi-twitter"></i></a>
                        <a href="#"><i class="bi bi-facebook"></i></a>
                        <a href="#"><i class="bi bi-instagram"></i></a>
                        <a href="#"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Product</h3>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#security">Security</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="https://web.gekychat.com">Web App</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Press</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 GekyChat. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const nav = document.querySelector('nav ul');
        
        mobileMenuBtn.addEventListener('click', function() {
            nav.style.display = nav.style.display === 'flex' ? 'none' : 'flex';
        });

        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = themeToggle.querySelector('i');
        const themeText = themeToggle.querySelector('span');
        
        themeToggle.addEventListener('click', function() {
            const currentTheme = document.body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.body.setAttribute('data-theme', newTheme);
            
            if (newTheme === 'light') {
                themeIcon.className = 'bi bi-sun-fill';
                themeText.textContent = 'Light';
            } else {
                themeIcon.className = 'bi bi-moon-fill';
                themeText.textContent = 'Dark';
            }
            
            // Save theme preference
            localStorage.setItem('gekychat-theme', newTheme);
        });

        // Load saved theme preference
        const savedTheme = localStorage.getItem('gekychat-theme');
        if (savedTheme) {
            document.body.setAttribute('data-theme', savedTheme);
            if (savedTheme === 'light') {
                themeIcon.className = 'bi bi-sun-fill';
                themeText.textContent = 'Light';
            }
        }

        // Chat preview animation
        function animateChatPreview() {
            const messages = document.querySelectorAll('.message');
            let delay = 0;
            
            messages.forEach(message => {
                message.style.animationDelay = `${delay}s`;
                delay += 1;
            });
            
            // Show typing indicator after last message
            setTimeout(() => {
                document.querySelector('.typing-indicator').style.display = 'flex';
            }, delay * 1000);
        }
        
        // Initialize chat animation when page loads
        window.addEventListener('load', animateChatPreview);

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, observerOptions);

        // Observe feature cards for animation
        document.querySelectorAll('.feature-card').forEach(card => {
            observer.observe(card);
        });
    </script>
</body>
</html>