@extends('layouts.public')

@section('title', $ogTitle ?? 'GekyChat Live')

@push('head')
@if(!empty($canonicalUrl))
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:type" content="website">
@endif
@if(!empty($ogTitle))
<meta property="og:title" content="{{ $ogTitle }}">
<meta name="twitter:title" content="{{ $ogTitle }}">
@endif
@if(!empty($ogDescription))
<meta property="og:description" content="{{ $ogDescription }}">
<meta name="twitter:description" content="{{ $ogDescription }}">
@endif
@if(!empty($ogImage))
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:secure_url" content="{{ $ogImage }}">
<meta name="twitter:image" content="{{ $ogImage }}">
<meta name="twitter:card" content="summary_large_image">
@else
<meta name="twitter:card" content="summary">
@endif
<meta name="twitter:site" content="@gekychat">
@endpush

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <div class="mx-auto d-flex align-items-center justify-content-center"
                             style="width: 120px; height: 120px; background: linear-gradient(135deg, #e53935 0%, #b71c1c 100%); border-radius: 50%;">
                            <span class="text-white fw-bold" style="font-size: 2.5rem;">LIVE</span>
                        </div>
                    </div>

                    <h2 class="fw-bold mb-2 text-text">
                        @if($isLive)
                            {{ $creatorName }} is live
                        @else
                            Live ended
                        @endif
                    </h2>

                    @if($isLive && !empty($broadcast->title))
                        <p class="text-muted mb-4">{{ \Illuminate\Support\Str::limit($broadcast->title, 120) }}</p>
                    @else
                        <p class="text-muted mb-4">
                            @if($isLive)
                                Watch on the GekyChat app.
                            @else
                                This broadcast is no longer live. Open the app to see more.
                            @endif
                        </p>
                    @endif

                    <a href="{{ $deepLink }}"
                       id="openAppBtn"
                       class="btn btn-wa btn-lg w-100 mb-3 d-flex align-items-center justify-content-center"
                       style="min-height: 50px;">
                        <i class="bi bi-phone me-2"></i>
                        <span>Open in app</span>
                    </a>

                    <a href="{{ $webUrl }}"
                       class="btn btn-outline-secondary btn-lg w-100 mb-4 d-flex align-items-center justify-content-center"
                       style="min-height: 50px;">
                        <i class="bi bi-globe me-2"></i>
                        <span>Continue to GekyChat Web</span>
                    </a>

                    <div class="position-relative my-4">
                        <hr>
                        <span class="position-absolute top-50 start-50 translate-middle bg-white dark:bg-gray-800 px-3 text-muted small">
                            Don't have the app?
                        </span>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <a href="https://apps.apple.com/app/gekychat"
                               target="_blank"
                               rel="noopener"
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
                               rel="noopener"
                               class="btn btn-outline-dark w-100 d-flex flex-column align-items-center justify-content-center p-3"
                               style="min-height: 80px;">
                                <i class="bi bi-google-play mb-2" style="font-size: 1.5rem;"></i>
                                <span class="small">Download for</span>
                                <span class="small fw-bold">Android</span>
                            </a>
                        </div>
                    </div>

                    <div class="mt-3">
                        <a href="https://gekychat.com/download"
                           target="_blank"
                           rel="noopener"
                           class="btn btn-link text-muted text-decoration-none small">
                            <i class="bi bi-download me-1"></i>
                            Download for Desktop
                        </a>
                    </div>
                </div>
            </div>

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
    document.addEventListener('DOMContentLoaded', function() {
        const openAppBtn = document.getElementById('openAppBtn');
        let appOpened = false;
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                appOpened = true;
            }
        });
        openAppBtn.addEventListener('click', function() {
            setTimeout(function() {
                if (!appOpened && document.visibilityState === 'visible') {
                    window.location.href = '{{ $webUrl }}';
                }
            }, 2000);
        });
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isMobile) {
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
