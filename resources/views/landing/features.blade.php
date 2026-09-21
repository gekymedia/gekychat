@extends('layouts.marketing')

@section('title', 'Features — GekyChat')
@section('meta_description', 'GekyChat features: messaging, Status, World Feed, voice and video calls, multi-device, groups, and more.')

@section('content')
@php $webApp = 'https://web.gekychat.com'; @endphp
<style>
    .feat-hero { padding: 3.5rem 1.25rem 2rem; max-width: 1120px; margin: 0 auto; }
    .feat-hero h1 {
        font-family: var(--display);
        font-size: clamp(2rem, 4vw, 2.75rem);
        letter-spacing: -0.02em;
        margin: 0 0 0.75rem;
    }
    .feat-hero p { color: var(--text-muted); font-size: 1.1rem; max-width: 48ch; margin: 0; }
    .feat-grid {
        max-width: 1120px;
        margin: 0 auto;
        padding: 1rem 1.25rem 4rem;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
    }
    .feat-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.5rem;
    }
    .feat-card h2 { font-size: 1.2rem; margin: 0 0 0.5rem; }
    .feat-card p { margin: 0; color: var(--text-muted); }
    .feat-card .tag {
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--gek-green-dark);
        background: var(--gek-green-soft);
        padding: 0.25rem 0.55rem;
        border-radius: 6px;
        margin-bottom: 0.75rem;
    }
    .feat-cta {
        max-width: 1120px;
        margin: 0 auto 4rem;
        padding: 0 1.25rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    @media (max-width: 768px) { .feat-grid { grid-template-columns: 1fr; } }
</style>

<div class="feat-hero">
    <h1>Everything in GekyChat</h1>
    <p>A clear look at what ships today — messaging plus Status, World Feed, calls, and multi-device access.</p>
</div>

<div class="feat-grid">
    <article class="feat-card">
        <span class="tag">Core</span>
        <h2>Messaging & media</h2>
        <p>One-to-one and group chats with text, albums, documents, voice notes, replies, reactions, and polls.</p>
    </article>
    <article class="feat-card">
        <span class="tag">Core</span>
        <h2>Status</h2>
        <p>Share photo, video, or text updates that expire after 24 hours. View and post from mobile or desktop.</p>
    </article>
    <article class="feat-card">
        <span class="tag">Social</span>
        <h2>World Feed</h2>
        <p>A feed for posts beyond your DMs — discover and share without leaving GekyChat.</p>
    </article>
    <article class="feat-card">
        <span class="tag">Realtime</span>
        <h2>Voice & video calls</h2>
        <p>Call over LiveKit using the same account you chat with. Join from supported clients.</p>
    </article>
    <article class="feat-card">
        <span class="tag">Platforms</span>
        <h2>Multi-device</h2>
        <p>Android, iOS, Windows desktop, and web — one phone number, OTP sign-in, synced conversations.</p>
    </article>
    <article class="feat-card">
        <span class="tag">Community</span>
        <h2>Groups & channels</h2>
        <p>Group chats with admin controls, plus channel-style broadcast when you need one-to-many updates.</p>
    </article>
    <article class="feat-card">
        <span class="tag">Privacy tools</span>
        <h2>View-once & controls</h2>
        <p>View-once media, block/report, and profile privacy settings. See the Privacy Policy for how data is stored.</p>
    </article>
    <article class="feat-card">
        <span class="tag">More</span>
        <h2>AI assist & Sika</h2>
        <p>In-app AI chat and Sika wallet features are available in the product for exploration — ask support if you need details.</p>
    </article>
</div>

<div class="feat-cta">
    <a class="btn-gek" href="{{ route('landing.download') }}">Download</a>
    <a class="btn-gek-outline" href="{{ $webApp }}">Open web</a>
</div>
@endsection
