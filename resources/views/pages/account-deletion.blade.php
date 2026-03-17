{{-- resources/views/pages/account-deletion.blade.php --}}
{{-- Official page for Google Play "Delete account URL" (Data safety). --}}
@extends('layouts.public')

@section('title', 'Account & Data Deletion - ' . config('app.name', 'GekyChat'))

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
                        <i class="bi bi-person-x display-4 text-danger mb-3"></i>
                        <h1 class="h2 fw-bold text-text mb-2">Account & Data Deletion</h1>
                        <p class="text-muted mb-0">{{ config('app.name', 'GekyChat') }} — Request deletion of your account and associated data</p>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <div class="legal-content">
                        <div class="alert alert-info border-wa mb-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle-fill text-wa me-3 fs-4"></i>
                                <div>
                                    <strong>Your choice.</strong> You can request that your <strong>{{ config('app.name', 'GekyChat') }}</strong> account and associated data be deleted at any time. Deletion is permanent and cannot be undone.
                                </div>
                            </div>
                        </div>

                        <div class="mb-5">
                            <h2>How to request account and data deletion</h2>
                            <p>Follow these steps to delete your account and associated data in <strong>{{ config('app.name', 'GekyChat') }}</strong>:</p>

                            <div class="card bg-input-bg border-border mb-4">
                                <div class="card-body">
                                    <h3 class="h5 fw-semibold mb-3">In the {{ config('app.name', 'GekyChat') }} app (mobile or web)</h3>
                                    <ol class="mb-0 ps-3">
                                        <li>Open <strong>{{ config('app.name', 'GekyChat') }}</strong> and sign in to your account.</li>
                                        <li>Go to <strong>Settings</strong> (gear or profile menu).</li>
                                        <li>Open the <strong>Account</strong> tab.</li>
                                        <li>Scroll to the <strong>Danger Zone</strong> section.</li>
                                        <li>Tap or click <strong>Delete Account</strong>.</li>
                                        <li>Read the warning, check the confirmation box, then confirm with <strong>Delete My Account</strong>.</li>
                                    </ol>
                                </div>
                            </div>

                            <p class="text-muted small mb-0">If you use the web app, go to <strong>Settings → Account → Danger Zone → Delete Account</strong>. Account deletion is processed immediately after you confirm.</p>
                        </div>

                        <div class="mb-5">
                            <h2>Data that is deleted</h2>
                            <p>When you delete your account, the following data associated with your account is permanently removed:</p>
                            <ul>
                                <li>Your profile information (name, profile picture, status)</li>
                                <li>Your phone number and account credentials</li>
                                <li>All your messages (chats and group conversations)</li>
                                <li>Your contacts and contact sync data</li>
                                <li>Media files you have sent or received (images, videos, documents)</li>
                                <li>Group memberships and group data you created</li>
                                <li>API keys and developer credentials (if any)</li>
                                <li>Session and device records</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h2>Data that may be kept</h2>
                            <p>We do not retain your personal data after account deletion. The following may remain for limited legal or operational reasons:</p>
                            <ul>
                                <li><strong>Backups:</strong> Data may exist in backup systems for a short period (typically up to 90 days) before being overwritten. Backups are not used for active service.</li>
                                <li><strong>Anonymized or aggregated data:</strong> We may retain non-identifying, aggregated statistics (e.g. usage counts) that cannot be linked to you.</li>
                                <li><strong>Legal obligations:</strong> Where required by law (e.g. tax or legal hold), we may retain minimal records for the required period only.</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h2>Retention period</h2>
                            <p>Account deletion is effective immediately. Your account and the data listed above are removed from our active systems without delay. Any remaining copies in backups are not used to provide service and are overwritten within our normal backup cycle (typically within 90 days).</p>
                        </div>

                        <div class="mb-5">
                            <h2>Need help?</h2>
                            <p>If you cannot access the app or need assistance with account or data deletion, contact us:</p>
                            <ul>
                                <li><strong>Email:</strong> privacy@gekychat.com</li>
                                <li><strong>In-app:</strong> Settings → Help & Support</li>
                            </ul>
                            <p class="text-muted small mb-0">Include your registered phone number or email so we can locate your account. We will process deletion requests in line with this page and our <a href="{{ route('privacy.policy') }}">Privacy Policy</a>.</p>
                        </div>

                        <div class="alert alert-success border-success mt-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill text-success me-3 fs-4"></i>
                                <div>
                                    <strong>Summary:</strong> You can delete your {{ config('app.name', 'GekyChat') }} account and data at any time via <strong>Settings → Account → Danger Zone → Delete Account</strong>. Deletion is permanent; backups are overwritten within our normal cycle (typically within 90 days).
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
