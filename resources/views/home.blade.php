@extends('layouts.marketing')

@section('title', 'GekyChat — Chat, Status, World Feed & Calls')
@section('meta_description', 'GekyChat is messaging with Status, World Feed, and voice/video calls — on Android, iOS, Windows, and the web. Sign in with your phone number.')

@section('content')
@php $webApp = 'https://web.gekychat.com'; @endphp

<style>
    .hero {
        position: relative;
        overflow: hidden;
        background:
            radial-gradient(ellipse 80% 60% at 10% 20%, rgba(15, 138, 95, 0.18), transparent 55%),
            radial-gradient(ellipse 70% 50% at 90% 10%, rgba(201, 146, 42, 0.16), transparent 50%),
            linear-gradient(165deg, #0C1A14 0%, #143028 48%, #0F241C 100%);
        color: #F2F7F4;
        padding: 4.5rem 1.25rem 5rem;
    }
    .hero-inner {
        max-width: 1120px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1.15fr 0.85fr;
        gap: 3rem;
        align-items: center;
    }
    .hero-brand {
        font-family: var(--display);
        font-size: clamp(2.8rem, 6vw, 4.25rem);
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1.05;
        margin: 0 0 1rem;
    }
    .hero-brand em {
        font-style: normal;
        color: #6EE7B7;
    }
    .hero h1 {
        font-family: var(--display);
        font-size: clamp(1.35rem, 2.5vw, 1.75rem);
        font-weight: 600;
        line-height: 1.35;
        margin: 0 0 1rem;
        max-width: 22ch;
        color: #E8F5EE;
    }
    .hero-lead {
        font-size: 1.1rem;
        color: rgba(232, 245, 238, 0.78);
        max-width: 38ch;
        margin: 0 0 1.75rem;
    }
    .hero-ctas { display: flex; flex-wrap: wrap; gap: 0.75rem; }
    .hero-visual {
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.12);
        background: linear-gradient(160deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02));
        padding: 1.5rem;
        min-height: 280px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        gap: 0.75rem;
        animation: rise 0.8s ease both;
    }
    .hero-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(12, 26, 20, 0.55);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        width: fit-content;
        animation: rise 0.7s ease both;
    }
    .hero-chip:nth-child(2) { animation-delay: 0.12s; }
    .hero-chip:nth-child(3) { animation-delay: 0.24s; }
    .hero-chip i { color: #FBBF24; }

    @keyframes rise {
        from { opacity: 0; transform: translateY(14px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .platforms {
        max-width: 1120px;
        margin: -1.75rem auto 0;
        padding: 0 1.25rem;
        position: relative;
        z-index: 2;
    }
    .platforms-inner {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1rem 1.25rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem 1.5rem;
        justify-content: center;
        box-shadow: 0 16px 40px rgba(12, 26, 20, 0.08);
    }
    .platforms-inner a, .platforms-inner span {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        text-decoration: none;
        color: var(--text);
        font-weight: 600;
        font-size: 0.95rem;
    }
    .platforms-inner a:hover { color: var(--gek-green); }
    .platforms-inner i { color: var(--gek-green); font-size: 1.2rem; }

    .section {
        max-width: 1120px;
        margin: 0 auto;
        padding: 4.5rem 1.25rem;
    }
    .section-kicker {
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--gek-green);
        margin: 0 0 0.5rem;
    }
    .section-title {
        font-family: var(--display);
        font-size: clamp(1.75rem, 3vw, 2.35rem);
        margin: 0 0 0.75rem;
        letter-spacing: -0.02em;
        max-width: 20ch;
    }
    .section-sub {
        color: var(--text-muted);
        max-width: 48ch;
        margin: 0 0 2.5rem;
        font-size: 1.05rem;
    }

    .pillars {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
    }
    .pillar {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.5rem;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .pillar:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 32px rgba(12, 26, 20, 0.08);
    }
    .pillar-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: var(--gek-green-soft);
        color: var(--gek-green-dark);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 1rem;
    }
    .pillar h3 {
        font-size: 1.15rem;
        margin: 0 0 0.5rem;
        font-weight: 700;
    }
    .pillar p {
        margin: 0;
        color: var(--text-muted);
        font-size: 0.98rem;
    }
    .pillar.wide { grid-column: span 1; }

    .steps {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        counter-reset: step;
    }
    .step {
        position: relative;
        padding: 1.5rem 1.25rem 1.5rem 1.5rem;
        background: #fff;
        border-radius: 16px;
        border: 1px solid var(--border);
    }
    .step::before {
        counter-increment: step;
        content: counter(step);
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        background: var(--gek-gold-soft);
        color: #7A5A12;
        font-weight: 700;
        margin-bottom: 1rem;
        font-size: 0.95rem;
    }
    .step h3 { margin: 0 0 0.4rem; font-size: 1.1rem; }
    .step p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }

    .privacy {
        background: linear-gradient(135deg, #E6F6EF 0%, #F8EFD9 100%);
        border-radius: 20px;
        padding: 2.5rem 2rem;
        border: 1px solid var(--border);
    }
    .privacy-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
        margin-top: 1.5rem;
    }
    .privacy-item {
        background: rgba(255,255,255,0.7);
        border-radius: 12px;
        padding: 1.1rem 1.2rem;
    }
    .privacy-item strong { display: block; margin-bottom: 0.25rem; }
    .privacy-item span { color: var(--text-muted); font-size: 0.95rem; }

    .cta-band {
        background: var(--bg-deep);
        color: #E8F5EE;
        padding: 4rem 1.25rem;
        text-align: center;
    }
    .cta-band h2 {
        font-family: var(--display);
        font-size: clamp(1.75rem, 3vw, 2.4rem);
        margin: 0 0 0.75rem;
    }
    .cta-band p { color: rgba(232,245,238,0.75); margin: 0 auto 1.5rem; max-width: 40ch; }
    .cta-band .hero-ctas { justify-content: center; }
    .note {
        margin-top: 1.25rem;
        font-size: 0.9rem;
        color: rgba(232,245,238,0.55);
    }

    @media (max-width: 900px) {
        .hero-inner { grid-template-columns: 1fr; }
        .hero-visual { min-height: 200px; }
        .pillars, .steps, .privacy-grid { grid-template-columns: 1fr; }
    }
</style>

<section class="hero">
    <div class="hero-inner">
        <div>
            <p class="hero-brand">Geky<em>Chat</em></p>
            <h1>Chat, Status, World Feed, and calls — one account everywhere.</h1>
            <p class="hero-lead">
                Real conversations on Android, iOS, Windows, and the web. Share Status updates, explore World Feed, and hop on voice or video when text isn’t enough.
            </p>
            <div class="hero-ctas">
                <a class="btn-gek btn-gek-gold" href="{{ route('landing.download') }}">Download the app</a>
                <a class="btn-gek-outline" style="border-color: rgba(255,255,255,0.25); color: #fff !important;" href="{{ $webApp }}">Open in browser</a>
            </div>
        </div>
        <div class="hero-visual" aria-hidden="true">
            <div class="hero-chip"><i class="bi bi-chat-dots-fill"></i> Messages & groups</div>
            <div class="hero-chip"><i class="bi bi-circle"></i> Status that disappears in 24h</div>
            <div class="hero-chip"><i class="bi bi-globe2"></i> World Feed posts</div>
            <div class="hero-chip"><i class="bi bi-camera-video"></i> Voice & video calls</div>
        </div>
    </div>
</section>

<section class="platforms">
    <div class="platforms-inner">
        <a href="{{ route('landing.download') }}"><i class="bi bi-android2"></i> Android</a>
        <a href="{{ route('landing.download') }}"><i class="bi bi-apple"></i> iOS</a>
        <a href="{{ route('landing.download') }}"><i class="bi bi-windows"></i> Windows</a>
        <a href="{{ $webApp }}"><i class="bi bi-browser-chrome"></i> Web</a>
    </div>
</section>

<section class="section" id="features">
    <p class="section-kicker">What you get</p>
    <h2 class="section-title">Built for how people actually talk</h2>
    <p class="section-sub">Not a clone of another messenger — GekyChat combines private chat with Status, a public World Feed, and LiveKit-powered calls.</p>

    <div class="pillars">
        <article class="pillar">
            <div class="pillar-icon"><i class="bi bi-chat-square-text"></i></div>
            <h3>Messaging & groups</h3>
            <p>One-to-one and group chats with media albums, documents, replies, reactions, and polls.</p>
        </article>
        <article class="pillar">
            <div class="pillar-icon"><i class="bi bi-circle-half"></i></div>
            <h3>Status</h3>
            <p>Share photos, video, or text that expire after 24 hours — the same account on phone and desktop.</p>
        </article>
        <article class="pillar">
            <div class="pillar-icon"><i class="bi bi-globe"></i></div>
            <h3>World Feed</h3>
            <p>A social feed for posts and discovery beyond your chat list — always available in the app.</p>
        </article>
        <article class="pillar">
            <div class="pillar-icon"><i class="bi bi-telephone"></i></div>
            <h3>Voice & video calls</h3>
            <p>Call contacts over LiveKit with the same sign-in you use for chat.</p>
        </article>
        <article class="pillar">
            <div class="pillar-icon"><i class="bi bi-laptop"></i></div>
            <h3>Multi-device</h3>
            <p>Use GekyChat on mobile, Windows desktop, and the web with one phone number and OTP.</p>
        </article>
        <article class="pillar">
            <div class="pillar-icon"><i class="bi bi-people"></i></div>
            <h3>Groups & channels</h3>
            <p>Collaborate in groups with admin tools, or follow channel-style updates when you need broadcast.</p>
        </article>
    </div>
</section>

<section class="section" style="padding-top: 0;">
    <p class="section-kicker">Getting started</p>
    <h2 class="section-title">Three steps. Phone number only.</h2>
    <p class="section-sub">No email signup form. Verify with OTP and you’re in.</p>
    <div class="steps">
        <div class="step">
            <h3>Sign in with your phone</h3>
            <p>Enter your number, confirm the one-time code, and create your profile.</p>
        </div>
        <div class="step">
            <h3>Find people you know</h3>
            <p>Sync contacts (with permission) or start a chat from a shared invite.</p>
        </div>
        <div class="step">
            <h3>Chat, Status, World, call</h3>
            <p>Message, post a Status, browse World Feed, or start a call — same account on every device.</p>
        </div>
    </div>
</section>

<section class="section" style="padding-top: 0;">
    <div class="privacy">
        <p class="section-kicker">Privacy</p>
        <h2 class="section-title" style="max-width: none;">Clear about how your data is handled</h2>
        <p class="section-sub" style="margin-bottom: 0;">We protect accounts and connections with modern transport security and give you controls over who sees what. We do not claim end-to-end encryption that the product does not provide.</p>
        <div class="privacy-grid">
            <div class="privacy-item">
                <strong>Secure connection</strong>
                <span>Traffic to GekyChat uses TLS so your sessions aren’t sent in the clear on the network.</span>
            </div>
            <div class="privacy-item">
                <strong>Account controls</strong>
                <span>Block, report, and manage who can message or see your profile from in-app settings.</span>
            </div>
            <div class="privacy-item">
                <strong>View-once & timed media</strong>
                <span>Send view-once media and disappearing options where available — not a promise that servers never store chat history.</span>
            </div>
            <div class="privacy-item">
                <strong>No selling your chats</strong>
                <span>We don’t sell your personal conversations for advertising. See the Privacy Policy for full details.</span>
            </div>
        </div>
    </div>
</section>

<section class="cta-band">
    <h2>Ready when you are</h2>
    <p>Install on your phone or PC, or jump straight into the browser. Closed beta builds update from Settings → About.</p>
    <div class="hero-ctas">
        <a class="btn-gek btn-gek-gold" href="{{ route('landing.download') }}">Download</a>
        <a class="btn-gek-outline" style="border-color: rgba(255,255,255,0.25); color: #fff !important;" href="{{ $webApp }}">Open web</a>
    </div>
    <p class="note">Also exploring AI assist and Sika wallet features inside the apps — details on the Features page.</p>
</section>
@endsection
