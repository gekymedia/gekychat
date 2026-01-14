@extends('layouts.public')

@section('title', '403 - Forbidden')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="card shadow-sm">
                <div class="card-body py-5">
                    <div class="mb-4">
                        <i class="bi bi-shield-exclamation" style="font-size: 5rem; color: #dc3545;"></i>
                    </div>
                    <h1 class="display-1 fw-bold text-danger">403</h1>
                    <h2 class="mb-3">Access Forbidden</h2>
                    <p class="text-muted mb-4">
                        You don't have permission to access this resource. 
                        If you believe this is an error, please contact support.
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
