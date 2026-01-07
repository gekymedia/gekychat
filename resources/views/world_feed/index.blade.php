@extends('layouts.app')

@section('title', 'World Feed')
@section('body_class', 'chat-page')

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="chat-header border-bottom p-3">
        <h4 class="mb-0">World Feed</h4>
        <small class="text-muted">Discover content from around the world</small>
    </div>
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4">
        <div class="text-center">
            <i class="bi bi-globe display-1 text-muted mb-3"></i>
            <h5 class="mb-2">World Feed</h5>
            <p class="text-muted">This feature is coming soon. API endpoints are available.</p>
            <p class="text-muted small">Check the API documentation for available endpoints.</p>
        </div>
    </div>
</div>
@endsection

