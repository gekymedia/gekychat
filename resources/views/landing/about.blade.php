@extends('layouts.marketing')

@section('title', 'About GekyChat')
@section('meta_description', 'GekyChat is a messaging product with Status, World Feed, and calls — available on mobile, desktop, and web.')

@section('content')
<style>
    .about-wrap { max-width: 720px; margin: 0 auto; padding: 3.5rem 1.25rem 4.5rem; }
    .about-wrap h1 {
        font-family: var(--display);
        font-size: clamp(2rem, 4vw, 2.6rem);
        letter-spacing: -0.02em;
        margin: 0 0 1rem;
    }
    .about-wrap p { color: var(--text-muted); font-size: 1.08rem; margin: 0 0 1.1rem; }
    .about-wrap strong { color: var(--text); }
    .about-cta { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 2rem; }
</style>

<div class="about-wrap">
    <h1>About GekyChat</h1>
    <p>
        <strong>GekyChat</strong> is a messaging product for people who want everyday chat plus Status updates,
        a World Feed, and voice or video calls — without juggling five different apps.
    </p>
    <p>
        Sign in with your <strong>phone number and OTP</strong>. Use the same account on
        <strong>Android, iOS, Windows, and the web</strong>. We’re shipping continuously in closed beta;
        download builds and in-app updates come from Settings → About on each client.
    </p>
    <p>
        We’re honest about privacy: connections use modern transport security and you control blocks and profile visibility.
        We don’t market GekyChat as end-to-end encrypted. Read the Privacy Policy for how message data is handled.
    </p>
    <div class="about-cta">
        <a class="btn-gek" href="{{ route('landing.download') }}">Download</a>
        <a class="btn-gek-outline" href="{{ route('landing.features') }}">See features</a>
        <a class="btn-gek-outline" href="{{ route('landing.contact') }}">Contact</a>
    </div>
</div>
@endsection
