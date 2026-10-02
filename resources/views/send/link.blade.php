@extends('layouts.public')

@section('title', 'Send Message - GekyChat')

@section('content')
@php
    $displayName = $targetUser->name ?? $phone;
@endphp
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        @if($targetUser && !empty($targetUser->avatar_url))
                            <img
                                src="{{ $targetUser->avatar_url }}"
                                alt="{{ $displayName }}"
                                class="rounded-circle mx-auto"
                                style="width: 120px; height: 120px; object-fit: cover;"
                            >
                        @else
                            <div class="mx-auto" style="width: 120px; height: 120px; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-chat-dots-fill text-white" style="font-size: 4rem;"></i>
                            </div>
                        @endif
                    </div>

                    <h2 class="fw-bold mb-2 text-text">{{ $displayName }}</h2>
                    <p class="text-muted mb-4">
                        @if($targetUser)
                            Send a message on GekyChat
                        @else
                            Send a message to <strong>{{ $phone }}</strong>
                        @endif
                    </p>

                    @if(!empty($text))
                    <div class="alert alert-light text-start mb-4" style="background: #f0f2f5;">
                        <small class="text-muted d-block mb-2">Message:</small>
                        <p class="mb-0" style="white-space: pre-wrap;">{{ $text }}</p>
                    </div>
                    @endif

                    <a href="{{ $deepLink }}"
                       id="openAppBtn"
                       class="btn btn-wa btn-lg w-100 mb-3 d-flex align-items-center justify-content-center"
                       style="min-height: 50px; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: none; color: white;">
                        <i class="bi bi-phone me-2"></i>
                        <span>Open app</span>
                    </a>

                    <a href="{{ $webContinueUrl }}"
                       id="continueWebBtn"
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
                            <a href="{{ config('app.app_store_url') }}"
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

{{--
  WhatsApp-style: show the chooser and wait for the user.
  Do not auto-click Open app or force-redirect to web — that skipped prefill
  and jumped into the wrong client.
--}}
@endsection
