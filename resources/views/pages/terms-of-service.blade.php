{{-- resources/views/pages/terms-of-service.blade.php --}}
@extends('layouts.public')

@section('title', 'Terms of Service - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-card border-bottom py-4">
                    <div class="text-center">
                        <h1 class="h2 fw-bold text-text mb-2">Terms of Service</h1>
                        <p class="text-muted mb-0">Last updated: {{ date('F j, Y') }}</p>
                    </div>
                </div>
                <div class="card-body bg-bg p-5">
                    <div class="content text-text">
                        <div class="alert alert-info border-wa">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle-fill text-wa me-3 fs-4"></i>
                                <div>
                                    <strong>Important:</strong> Please read these Terms of Service carefully before using GekyChat. By accessing or using our services, you agree to be bound by these terms.
                                </div>
                            </div>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">1. Acceptance of Terms</h2>
                            <p>Welcome to GekyChat. These Terms of Service ("Terms") govern your access to and use of the GekyChat mobile application, website, and related services (collectively, the "Services").</p>
                            <p>By creating an account or using our Services, you agree to be bound by these Terms and our Privacy Policy. If you do not agree to these Terms, you may not use our Services.</p>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">2. Eligibility</h2>
                            <p>To use GekyChat, you must:</p>
                            <ul class="list-unstyled ms-3">
                                <li class="mb-2">• Be at least 13 years of age (or the minimum age in your jurisdiction)</li>
                                <li class="mb-2">• Have the legal capacity to enter into binding contracts</li>
                                <li class="mb-2">• Provide accurate and complete registration information</li>
                                <li class="mb-2">• Maintain the security of your account credentials</li>
                                <li class="mb-2">• Be responsible for all activities that occur under your account</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">3. Account Registration</h2>
                            <p>To access certain features, you must register for an account using your phone number. You agree to:</p>
                            <ul class="list-unstyled ms-3">
                                <li class="mb-2">• Provide accurate, current, and complete information</li>
                                <li class="mb-2">• Maintain and promptly update your account information</li>
                                <li class="mb-2">• Keep your password secure and confidential</li>
                                <li class="mb-2">• Notify us immediately of any unauthorized use of your account</li>
                                <li class="mb-2">• Accept responsibility for all activities that occur under your account</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">4. User Conduct</h2>
                            <p>You agree not to use the Services to:</p>
                            <ul class="list-unstyled ms-3">
                                <li class="mb-2">• Violate any applicable laws or regulations</li>
                                <li class="mb-2">• Infringe upon the rights of others</li>
                                <li class="mb-2">• Send spam, unsolicited messages, or bulk communications</li>
                                <li class="mb-2">• Transmit harmful or malicious code</li>
                                <li class="mb-2">• Harass, abuse, or harm another person</li>
                                <li class="mb-2">• Impersonate any person or entity</li>
                                <li class="mb-2">• Engage in fraudulent, deceptive, or misleading practices</li>
                                <li class="mb-2">• Interfere with or disrupt the Services</li>
                                <li class="mb-2">• Attempt to gain unauthorized access to the Services</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">5. Content and Messages</h2>
                            <h3 class="h5 fw-semibold mb-2">5.1 Your Content</h3>
                            <p>You retain ownership of the content you create and share through GekyChat. However, you grant us a worldwide, non-exclusive, royalty-free license to use, reproduce, and display your content solely for the purpose of providing the Services.</p>

                            <h3 class="h5 fw-semibold mb-2 mt-4">5.2 Prohibited Content</h3>
                            <p>You may not share content that:</p>
                            <ul class="list-unstyled ms-3">
                                <li class="mb-2">• Is illegal, obscene, defamatory, or threatening</li>
                                <li class="mb-2">• Infringes intellectual property rights</li>
                                <li class="mb-2">• Contains private information of others without consent</li>
                                <li class="mb-2">• Promotes violence, hatred, or discrimination</li>
                                <li class="mb-2">• Contains malware, viruses, or harmful code</li>
                            </ul>

                            <h3 class="h5 fw-semibold mb-2 mt-4">5.3 Message Encryption</h3>
                            <p>GekyChat uses end-to-end encryption to protect your messages. While we implement security measures, we cannot guarantee absolute security of your communications.</p>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">6. Privacy</h2>
                            <p>Your privacy is important to us. Our <a href="{{ route('privacy.policy') }}" class="text-wa">Privacy Policy</a> explains how we collect, use, and protect your personal information. By using our Services, you consent to our collection and use of your information as described in the Privacy Policy.</p>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">7. Intellectual Property</h2>
                            <p>The GekyChat name, logo, and all related graphics, trademarks, and service marks are owned by us and may not be used without our prior written consent. All other trademarks are the property of their respective owners.</p>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">8. Third-Party Services</h2>
                            <p>Our Services may integrate with third-party services, such as Google Contacts. These services are subject to their own terms and conditions, and we are not responsible for their content, policies, or practices.</p>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">9. Service Availability</h2>
                            <p>We strive to provide reliable Services but cannot guarantee uninterrupted or error-free operation. We may temporarily suspend access for maintenance, updates, or security reasons without prior notice.</p>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">10. Termination</h2>
                            <p>You may terminate your account at any time by following the instructions in the app. We may suspend or terminate your access to the Services if you violate these Terms or for any other reason at our discretion.</p>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">11. Disclaimer of Warranties</h2>
                            <p>THE SERVICES ARE PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED. TO THE FULLEST EXTENT PERMISSIBLE BY LAW, WE DISCLAIM ALL WARRANTIES, EXPRESS OR IMPLIED, INCLUDING IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT.</p>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">12. Limitation of Liability</h2>
                            <p>TO THE FULLEST EXTENT PERMITTED BY LAW, WE SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, OR ANY LOSS OF PROFITS OR REVENUES, WHETHER INCURRED DIRECTLY OR INDIRECTLY, OR ANY LOSS OF DATA, USE, GOODWILL, OR OTHER INTANGIBLE LOSSES RESULTING FROM:</p>
                            <ul class="list-unstyled ms-3">
                                <li class="mb-2">• YOUR ACCESS TO OR USE OF OR INABILITY TO ACCESS OR USE THE SERVICES</li>
                                <li class="mb-2">• ANY CONDUCT OR CONTENT OF ANY THIRD PARTY ON THE SERVICES</li>
                                <li class="mb-2">• ANY CONTENT OBTAINED FROM THE SERVICES</li>
                                <li class="mb-2">• UNAUTHORIZED ACCESS, USE, OR ALTERATION OF YOUR TRANSMISSIONS OR CONTENT</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">13. Indemnification</h2>
                            <p>You agree to indemnify and hold harmless GekyChat and its affiliates, officers, directors, employees, and agents from any claims, damages, losses, liabilities, and expenses arising out of your use of the Services or violation of these Terms.</p>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">14. Governing Law</h2>
                            <p>These Terms shall be governed by and construed in accordance with the laws of [Your Country/State], without regard to its conflict of law provisions.</p>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">15. Changes to Terms</h2>
                            <p>We may modify these Terms at any time. If we make material changes, we will notify you through the Services or by other means. Your continued use of the Services after such changes constitutes acceptance of the modified Terms.</p>
                        </div>

                        <div class="mb-5">
                            <h2 class="h4 fw-bold mb-3">16. Contact Information</h2>
                            <p>If you have any questions about these Terms, please contact us:</p>
                            <ul class="list-unstyled ms-3">
                                <li class="mb-1">• Email: legal@gekychat.com</li>
                                <li class="mb-1">• Address: [Your Company Address]</li>
                                <li>• Through the app: Settings → Help & Support</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning border-warning mt-5">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill text-warning me-3 fs-4"></i>
                                <div>
                                    <strong>Note:</strong> These Terms of Service constitute the entire agreement between you and GekyChat regarding the Services and supersede all prior agreements.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-card border-top py-4 text-center">
                    <p class="text-muted mb-0">&copy; {{ date('Y') }} {{ config('app.name', 'GekyChat') }}. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.content h2 {
    color: var(--wa-green);
    border-bottom: 2px solid var(--wa-green);
    padding-bottom: 0.5rem;
    margin-bottom: 1.5rem;
}

.content h3 {
    color: var(--text);
    margin-top: 1.5rem;
}

.content ul li {
    position: relative;
    padding-left: 1rem;
}

.content ul li:before {
    content: "•";
    color: var(--wa-green);
    font-weight: bold;
    position: absolute;
    left: 0;
}

.alert {
    border: 1px solid;
    background: color-mix(in srgb, var(--card) 95%, transparent);
}

.alert-info {
    border-color: var(--wa-green);
}

.alert-warning {
    border-color: #ffc107;
}

.card {
    background: var(--card);
    border-color: var(--border);
}

.card-header {
    background: var(--card) !important;
    border-color: var(--border) !important;
}

.card-body {
    background: var(--bg) !important;
}

.card-footer {
    background: var(--card) !important;
    border-color: var(--border) !important;
}

a.text-wa {
    color: var(--wa-green) !important;
    text-decoration: none;
}

a.text-wa:hover {
    text-decoration: underline;
}
</style>
@endpush