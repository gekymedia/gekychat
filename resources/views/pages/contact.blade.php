{{-- resources/views/pages/contact.blade.php --}}
@extends('layouts.public')

@section('title', 'Contact Us - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="mb-4">
                <a href="{{ url('/') }}" class="btn btn-outline-secondary back-to-home">
                    <i class="bi bi-arrow-left me-2"></i> Back to Home
                </a>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-card border-bottom py-4">
                    <div class="text-center">
                        <i class="bi bi-envelope display-4 text-wa mb-3"></i>
                        <h1 class="h2 fw-bold text-text mb-2">Contact Us</h1>
                        <p class="text-muted mb-0">We're here to help with {{ config('app.name', 'GekyChat') }}</p>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <div class="legal-content">
                        <div class="alert alert-info border-wa mb-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle-fill text-wa me-3 fs-4"></i>
                                <div>
                                    Before contacting us, check the <a href="{{ url('/help') }}">Help Center</a> for answers to common questions about sign-in, chats, groups, and account deletion.
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-5">
                            <div class="col-md-6">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h2 class="h5 fw-semibold">General support</h2>
                                        <p class="text-muted mb-3">Account access, messaging, calls, groups, and app issues.</p>
                                        <p class="mb-2"><strong>Email:</strong> <a href="mailto:support@gekychat.com">support@gekychat.com</a></p>
                                        <p class="text-muted small mb-0">Typical response time: 24–48 hours on business days.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h2 class="h5 fw-semibold">Privacy &amp; data</h2>
                                        <p class="text-muted mb-3">Privacy questions, data export, or deletion assistance.</p>
                                        <p class="mb-2"><strong>Email:</strong> <a href="mailto:privacy@gekychat.com">privacy@gekychat.com</a></p>
                                        <p class="text-muted small mb-0">See also: <a href="{{ url('/privacy-policy') }}">Privacy Policy</a> · <a href="{{ url('/request-account-deletion') }}">Account deletion</a></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h2>What to include in your message</h2>
                        <ul>
                            <li>Your registered phone number (with country code)</li>
                            <li>Device type (Android, iOS, Windows, macOS, or web)</li>
                            <li>App version, if available from Settings → About</li>
                            <li>A clear description of the issue and steps to reproduce it</li>
                            <li>Screenshots, if helpful (never send passwords or OTP codes)</li>
                        </ul>

                        <h2>Other resources</h2>
                        <ul>
                            <li><a href="{{ url('/help') }}">Help Center</a> — FAQs and how-to guides</li>
                            <li><a href="{{ url('/terms-of-service') }}">Terms of Service</a></li>
                            <li><a href="{{ url('/privacy-policy') }}">Privacy Policy</a></li>
                            <li><a href="https://chat.gekychat.com/login">Sign in on web</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
