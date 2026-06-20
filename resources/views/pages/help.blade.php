{{-- resources/views/pages/help.blade.php --}}
@extends('layouts.public')

@section('title', 'Help Center - ' . config('app.name', 'GekyChat'))

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
                        <i class="bi bi-question-circle display-4 text-wa mb-3"></i>
                        <h1 class="h2 fw-bold text-text mb-2">Help Center</h1>
                        <p class="text-muted mb-0">FAQs, guides, and support resources for {{ config('app.name', 'GekyChat') }}</p>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <div class="legal-content">
                        <div class="row g-3 mb-5">
                            <div class="col-md-4">
                                <a href="{{ url('/contact') }}" class="card h-100 text-decoration-none border">
                                    <div class="card-body">
                                        <i class="bi bi-envelope text-wa fs-4"></i>
                                        <h3 class="h6 fw-semibold mt-2 mb-1">Contact support</h3>
                                        <p class="text-muted small mb-0">Email our team for account or technical help.</p>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="{{ url('/request-account-deletion') }}" class="card h-100 text-decoration-none border">
                                    <div class="card-body">
                                        <i class="bi bi-person-x text-wa fs-4"></i>
                                        <h3 class="h6 fw-semibold mt-2 mb-1">Delete your account</h3>
                                        <p class="text-muted small mb-0">Steps to permanently remove your account and data.</p>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="{{ url('/privacy-policy') }}" class="card h-100 text-decoration-none border">
                                    <div class="card-body">
                                        <i class="bi bi-shield-check text-wa fs-4"></i>
                                        <h3 class="h6 fw-semibold mt-2 mb-1">Privacy policy</h3>
                                        <p class="text-muted small mb-0">How we collect, use, and protect your information.</p>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <h2>Getting started</h2>
                        <h3>How do I sign in?</h3>
                        <p>Open the {{ config('app.name', 'GekyChat') }} app on mobile or desktop, enter your phone number, and verify the OTP code sent to you. You can also sign in on the web at <a href="https://chat.gekychat.com/login">chat.gekychat.com</a>.</p>

                        <h3>How do I start a chat?</h3>
                        <p>Tap the new chat icon, select a contact from your list, or search by name or phone number. If the person is on {{ config('app.name', 'GekyChat') }}, you can message them immediately.</p>

                        <h3>How do contacts sync?</h3>
                        <p>When you allow contact access, we match phone numbers securely so you see the names you saved on your device. You can manage synced contacts in Settings.</p>

                        <h2>Account &amp; privacy</h2>
                        <h3>How do I delete my account?</h3>
                        <p>Go to <strong>Settings → Account → Danger Zone → Delete Account</strong> in the app, or follow the guide on our <a href="{{ url('/request-account-deletion') }}">account deletion page</a>.</p>

                        <h3>Where can I read your privacy policy?</h3>
                        <p>Our full policy is available at <a href="{{ url('/privacy-policy') }}">{{ url('/privacy-policy') }}</a>.</p>

                        <h2>Messaging &amp; groups</h2>
                        <h3>Can I send photos, videos, and files?</h3>
                        <p>Yes. Use the attachment button in any chat to share media, documents, location, contacts, and polls. Group chats support the same features.</p>

                        <h3>How do polls work?</h3>
                        <p>In a chat or group, open the attachment menu and choose <strong>Poll</strong>. Enter a question and options, then send. Members can vote; results update for everyone in the conversation.</p>

                        <h2>Still need help?</h2>
                        <p>Visit our <a href="{{ url('/contact') }}">contact page</a> or email <a href="mailto:support@gekychat.com">support@gekychat.com</a>. For privacy-related requests, email <a href="mailto:privacy@gekychat.com">privacy@gekychat.com</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
