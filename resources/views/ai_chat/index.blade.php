@extends('layouts.app')

@section('title', 'AI Chat')
@section('body_class', 'chat-page')

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="chat-header border-bottom p-3">
        <h4 class="mb-0">AI Chat</h4>
        <small class="text-muted">Chat with AI Assistant</small>
    </div>
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4">
        <div class="text-center">
            <i class="bi bi-robot display-1 text-muted mb-3"></i>
            <h5 class="mb-2">AI Chat</h5>
            <p class="text-muted">This feature is coming soon. API endpoints are available.</p>
            <p class="text-muted small">Check the API documentation for available endpoints.</p>
        </div>
    </div>
</div>
@endsection

