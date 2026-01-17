@extends('layouts.public')

@section('title', 'GekyChat Invite')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center p-5">
                    <!-- Logo/Icon -->
                    <div class="mb-4">
                        <div class="mx-auto" style="width: 120px; height: 120px; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-chat-dots-fill text-white" style="font-size: 4rem;"></i>
                        </div>
                    </div>
                    
                    <!-- Title -->
                    <h2 class="fw-bold mb-3 text-text">GekyChat Invite</h2>
                    
                    <!-- Description -->
                    <p class="text-muted mb-4">
                        @if($type === 'post')
                            You've been invited to view a post on GekyChat
                        @else
                            You've been invited to join a group on GekyChat
                        @endif
                    </p>
                    
                    <!-- Open App Button -->
                    <a href="{{ $deepLink }}" 
                       id="openAppBtn"
                       class="btn btn-wa btn-lg w-100 mb-3 d-flex align-items-center justify-content-center"
                       style="min-height: 50px;">
                        <i class="bi bi-phone me-2"></i>
                        <span>Open app</span>
                    </a>
                    
                    <!-- Continue to Web Button -->
                    <a href="{{ $webUrl }}" 
                       class="btn btn-outline-secondary btn-lg w-100 mb-4 d-flex align-items-center justify-content-center"
                       style="min-height: 50px;">
                        <i class="bi bi-globe me-2"></i>
                        <span>Continue to GekyChat Web</span>
                    </a>
                    
                    <!-- Divider -->
                    <div class="position-relative my-4">
                        <hr>
                        <span class="position-absolute top-50 start-50 translate-middle bg-white dark:bg-gray-800 px-3 text-muted small">
                            Don't have the app?
                        </span>
                    </div>
                    
                    <!-- Download Section -->
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="https://apps.apple.com/app/gekychat" 
                               target="_blank"
                               class="btn btn-outline-dark w-100 d-flex flex-column align-items-center justify-content-center p-3"
                               style="min-height: 80px;">
                                <i class="bi bi-apple mb-2" style="font-size: 1.5rem;"></i>
                                <span class="small">Download for</span>
                                <span class="small fw-bold">iOS</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="https://play.google.com/store/apps/details?id=com.gekychat.app" 
                               target="_blank"
                               class="btn btn-outline-dark w-100 d-flex flex-column align-items-center justify-content-center p-3"
                               style="min-height: 80px;">
                                <i class="bi bi-google-play mb-2" style="font-size: 1.5rem;"></i>
                                <span class="small">Download for</span>
                                <span class="small fw-bold">Android</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Desktop Download -->
                    <div class="mt-3">
                        <a href="https://gekychat.com/download" 
                           target="_blank"
                           class="btn btn-link text-muted text-decoration-none small">
                            <i class="bi bi-download me-1"></i>
                            Download for Desktop
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Footer Note -->
            <p class="text-center text-muted small mt-4">
                By continuing, you agree to GekyChat's 
                <a href="{{ route('terms.service') }}" class="text-decoration-none">Terms of Service</a> 
                and 
                <a href="{{ route('privacy.policy') }}" class="text-decoration-none">Privacy Policy</a>
            </p>
        </div>
    </div>
</div>

@push('styles')
<style>
    [data-bs-theme="dark"] .bg-white {
        background-color: var(--card) !important;
    }
    
    .btn-wa {
        background-color: #25D366;
        border-color: #25D366;
        color: white;
        font-weight: 500;
    }
    
    .btn-wa:hover {
        background-color: #128C7E;
        border-color: #128C7E;
        color: white;
    }
    
    .card {
        border-radius: 20px;
    }
</style>
@endpush

@push('scripts')
<script>
    // Try to open app, fallback to web after timeout
    document.addEventListener('DOMContentLoaded', function() {
        const openAppBtn = document.getElementById('openAppBtn');
        let appOpened = false;
        
        // Track if app was opened
        const startTime = Date.now();
        
        // Listen for page visibility change (user came back = app didn't open)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                appOpened = true;
            }
        });
        
        // If user clicks open app, wait a bit then redirect to web if app didn't open
        openAppBtn.addEventListener('click', function(e) {
            setTimeout(function() {
                if (!appOpened && document.visibilityState === 'visible') {
                    // App didn't open, redirect to web
                    window.location.href = '{{ $webUrl }}';
                }
            }, 2000);
        });
        
        // Auto-detect mobile and try to open app
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isMobile) {
            // On mobile, try to open app automatically
            setTimeout(function() {
                if (!appOpened) {
                    window.location.href = '{{ $deepLink }}';
                }
            }, 500);
        }
    });
</script>
@endpush
@endsection
