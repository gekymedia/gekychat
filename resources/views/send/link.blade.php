@extends('layouts.public')

@section('title', 'Send Message - GekyChat')

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
                    <h2 class="fw-bold mb-3 text-text">Send Message</h2>
                    
                    <!-- Description -->
                    <p class="text-muted mb-4">
                        @if($targetUser)
                            Send a message to <strong>{{ $targetUser->name ?? $phone }}</strong>
                        @else
                            Send a message to <strong>{{ $phone }}</strong>
                        @endif
                    </p>
                    
                    @if(!empty($text))
                    <!-- Message Preview -->
                    <div class="alert alert-light text-start mb-4" style="background: #f0f2f5;">
                        <small class="text-muted d-block mb-2">Message:</small>
                        <p class="mb-0">{{ $text }}</p>
                    </div>
                    @endif
                    
                    <!-- Open App Button -->
                    <a href="{{ $deepLink }}" 
                       id="openAppBtn"
                       class="btn btn-wa btn-lg w-100 mb-3 d-flex align-items-center justify-content-center"
                       style="min-height: 50px; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: none; color: white;">
                        <i class="bi bi-phone me-2"></i>
                        <span>Open in GekyChat App</span>
                    </a>
                    
                    <!-- Continue to Web Button -->
                    @if($targetUser)
                        @php
                            $conversation = \App\Models\Conversation::findOrCreateDirect($currentUser->id, $targetUser->id);
                            $webUrl = route('chat.show', ['conversation' => $conversation->slug]);
                            if (!empty($text)) {
                                $webUrl .= '?text=' . urlencode($text);
                            }
                        @endphp
                        <a href="{{ $webUrl }}" 
                           class="btn btn-outline-secondary btn-lg w-100 mb-4 d-flex align-items-center justify-content-center"
                           style="min-height: 50px;">
                            <i class="bi bi-globe me-2"></i>
                            <span>Continue to GekyChat Web</span>
                        </a>
                    @else
                        <a href="{{ route('direct.chat', $phone) }}" 
                           class="btn btn-outline-secondary btn-lg w-100 mb-4 d-flex align-items-center justify-content-center"
                           style="min-height: 50px;">
                            <i class="bi bi-globe me-2"></i>
                            <span>Continue to GekyChat Web</span>
                        </a>
                    @endif
                    
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

<script>
    // Try to open app, then fall back to web after timeout
    let appOpened = false;
    const openAppBtn = document.getElementById('openAppBtn');
    
    if (openAppBtn) {
        openAppBtn.addEventListener('click', function(e) {
            appOpened = true;
            // Give app 2 seconds to open
            setTimeout(function() {
                if (appOpened) {
                    // App didn't open, redirect to web
                    const webUrl = openAppBtn.nextElementSibling?.href || '{{ route("direct.chat", $phone) }}';
                    window.location.href = webUrl;
                }
            }, 2000);
        });
        
        // Also try to open app automatically
        window.addEventListener('blur', function() {
            appOpened = false; // App opened
        });
        
        // Auto-trigger app open after a short delay
        setTimeout(function() {
            if (!appOpened) {
                openAppBtn.click();
            }
        }, 500);
    }
</script>
@endsection
