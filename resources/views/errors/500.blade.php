@extends('layouts.public')

@section('title', '500 - Server Error')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="card shadow-sm">
                <div class="card-body py-5">
                    <div class="mb-4">
                        <i class="bi bi-exclamation-triangle" style="font-size: 5rem; color: #dc3545;"></i>
                    </div>
                    <h1 class="display-1 fw-bold text-danger">500</h1>
                    <h2 class="mb-3">Server Error</h2>
                    <p class="text-muted mb-4">
                        Something went wrong on our end. We're working to fix the issue. 
                        Please try again later or contact support if the problem persists.
                    </p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="{{ url('/') }}" class="btn btn-wa">
                            <i class="bi bi-house me-2"></i>Go Home
                        </a>
                        <a href="javascript:location.reload()" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-2"></i>Reload Page
                        </a>
                    </div>
                    <div class="mt-4">
                        <a href="mailto:support@gekychat.com" class="text-muted text-decoration-none">
                            <i class="bi bi-envelope me-2"></i>Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
