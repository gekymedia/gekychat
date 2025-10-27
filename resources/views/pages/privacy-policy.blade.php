{{-- resources/views/pages/privacy-policy.blade.php --}}
@extends('layouts.public')

@section('title', 'Privacy Policy - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Back to Home -->
            <div class="mb-4">
                <a href="{{ url('/') }}" class="btn btn-outline-secondary back-to-home">
                    <i class="bi bi-arrow-left me-2"></i> Back to Home
                </a>
            </div>

            <!-- Main Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-card border-bottom py-4">
                    <div class="text-center">
                        <i class="bi bi-shield-check display-4 text-wa mb-3"></i>
                        <h1 class="h2 fw-bold text-text mb-2">Privacy Policy</h1>
                        <p class="text-muted mb-0">Last updated: {{ date('F j, Y') }}</p>
                    </div>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <div class="legal-content">
                        <div class="alert alert-info border-wa mb-5">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle-fill text-wa me-3 fs-4"></i>
                                <div>
                                    <strong>Transparency Matters:</strong> We believe in being clear about how we handle your data. 
                                    This policy explains what information we collect and how we use it to provide you with the best messaging experience.
                                </div>
                            </div>
                        </div>

                        <div class="mb-5">
                            <h2>1. Introduction</h2>
                            <p>Welcome to <strong>GekyChat</strong> ("we," "our," or "us"). We are committed to protecting your privacy and ensuring the security of your personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our mobile application and services.</p>
                            <p>By using GekyChat, you agree to the collection and use of information in accordance with this policy.</p>
                        </div>

                        <div class="mb-5">
                            <h2>2. Information We Collect</h2>
                            
                            <h3>2.1 Personal Information</h3>
                            <ul>
                                <li><strong>Phone Number:</strong> Required for account creation and authentication</li>
                                <li><strong>Profile Information:</strong> Name, profile picture, and status message</li>
                                <li><strong>Contact Information:</strong> When you sync your device contacts (optional)</li>
                                <li><strong>Google Account Information:</strong> If you choose to sync Google Contacts (optional)</li>
                            </ul>

                            <h3>2.2 Message Data</h3>
                            <ul>
                                <li><strong>Message Content:</strong> Text messages, images, videos, and files you send and receive</li>
                                <li><strong>Metadata:</strong> Timestamps, read receipts, and delivery status</li>
                                <li><strong>End-to-End Encryption:</strong> Your messages are encrypted and only readable by you and the recipient</li>
                            </ul>

                            <h3>2.3 Technical Information</h3>
                            <ul>
                                <li><strong>Device Information:</strong> Device type, operating system, and app version</li>
                                <li><strong>Usage Data:</strong> How you interact with the app and features you use</li>
                                <li><strong>Log Data:</strong> IP address, browser type, and error logs</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h2>3. How We Use Your Information</h2>
                            <ul>
                                <li><strong>Provide Services:</strong> To enable messaging and communication features</li>
                                <li><strong>Account Management:</strong> To create and maintain your account</li>
                                <li><strong>Contact Sync:</strong> To help you connect with people in your contacts</li>
                                <li><strong>Improve Services:</strong> To analyze usage patterns and enhance user experience</li>
                                <li><strong>Customer Support:</strong> To respond to your inquiries and provide assistance</li>
                                <li><strong>Security:</strong> To protect against fraud and unauthorized access</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h2>4. Data Sharing and Disclosure</h2>
                            <p>We do not sell, trade, or rent your personal information to third parties. We may share information in the following circumstances:</p>
                            <ul>
                                <li><strong>With Your Consent:</strong> When you explicitly agree to share information</li>
                                <li><strong>Service Providers:</strong> With trusted partners who help us operate our services</li>
                                <li><strong>Legal Requirements:</strong> When required by law or to protect our rights</li>
                                <li><strong>Business Transfers:</strong> In connection with a merger or acquisition</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h2>5. Data Security</h2>
                            <p>We implement appropriate technical and organizational security measures to protect your personal information, including:</p>
                            <ul>
                                <li><strong>End-to-End Encryption:</strong> Your messages are encrypted and cannot be read by us</li>
                                <li><strong>Secure Storage:</strong> Data is stored on secure servers with access controls</li>
                                <li><strong>Regular Security Audits:</strong> We regularly review our security practices</li>
                                <li><strong>Access Controls:</strong> Limited access to personal information within our organization</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h2>6. Data Retention</h2>
                            <p>We retain your personal information only for as long as necessary to provide our services and fulfill the purposes outlined in this policy. You can request deletion of your account and associated data at any time through the app settings.</p>
                        </div>

                        <div class="mb-5">
                            <h2>7. Your Rights</h2>
                            <p>You have the right to:</p>
                            <ul>
                                <li>Access and review your personal information</li>
                                <li>Correct inaccurate or incomplete information</li>
                                <li>Delete your account and personal data</li>
                                <li>Export your data in a portable format</li>
                                <li>Opt-out of certain data processing activities</li>
                                <li>Withdraw consent where processing is based on consent</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h2>8. Third-Party Services</h2>
                            <p>Our app may integrate with third-party services like Google Contacts. These services have their own privacy policies, and we encourage you to review them.</p>
                        </div>

                        <div class="mb-5">
                            <h2>9. Children's Privacy</h2>
                            <p>GekyChat is not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If you believe we have collected information from a child under 13, please contact us immediately.</p>
                        </div>

                        <div class="mb-5">
                            <h2>10. International Data Transfers</h2>
                            <p>Your information may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place to protect your data in accordance with this Privacy Policy.</p>
                        </div>

                        <div class="mb-5">
                            <h2>11. Changes to This Policy</h2>
                            <p>We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new policy on this page and updating the "Last updated" date. Continued use of our services after changes constitutes acceptance of the updated policy.</p>
                        </div>

                        <div class="mb-5">
                            <h2>12. Contact Us</h2>
                            <p>If you have any questions about this Privacy Policy or our data practices, please contact us:</p>
                            <ul>
                                <li><strong>Email:</strong> privacy@gekychat.com</li>
                                <li><strong>In-App:</strong> Settings → Help & Support</li>
                                <li><strong>Response Time:</strong> We typically respond within 24-48 hours</li>
                            </ul>
                        </div>

                        <div class="alert alert-success border-success mt-5">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill text-success me-3 fs-4"></i>
                                <div>
                                    <strong>Your Privacy is Protected:</strong> We're committed to maintaining the trust you place in us. 
                                    We continuously work to ensure your data remains secure and private.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection