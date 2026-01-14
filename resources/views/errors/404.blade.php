@extends('layouts.public')

@section('title', '404 - Page Not Found')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="card shadow-sm">
                <div class="card-body py-5">
                    <div class="mb-4">
                        <i class="bi bi-question-circle" style="font-size: 5rem; color: #ffc107;"></i>
                    </div>
                    <h1 class="display-1 fw-bold text-warning">404</h1>
                    <h2 class="mb-3">Page Not Found</h2>
                    <p class="text-muted mb-4">
                        The page you're looking for doesn't exist or has been moved. 
                        Please check the URL and try again.
                    </p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="{{ url('/') }}" class="btn btn-wa">
                            <i class="bi bi-house me-2"></i>Go Home
                        </a>
                        <a href="javascript:history.back()" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Go Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
