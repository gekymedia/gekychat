@extends('layouts.app')

@section('title', 'API Documentation - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-wa text-white py-4">
                    <h1 class="h3 mb-0"><i class="bi bi-code-square me-2"></i>GekyChat API Documentation</h1>
                    <p class="mb-0 mt-2">Build amazing integrations with GekyChat</p>
                </div>

                <div class="card-body bg-bg p-4">
                    <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Getting Started:</strong> Enable Developer Mode in your settings and generate an API key to get started.
                    </div>

                    <section class="mb-5">
                        <h2 class="h4 fw-bold text-text mb-3">Authentication</h2>
                        <p class="text-muted">All API requests require authentication using Bearer tokens.</p>
                        <div class="bg-dark text-light p-3 rounded">
                            <code>
Authorization: Bearer YOUR_API_KEY
                            </code>
                        </div>
                    </section>

                    <section class="mb-5">
                        <h2 class="h4 fw-bold text-text mb-3">Base URL</h2>
                        <div class="bg-dark text-light p-3 rounded">
                            <code>{{ url('/api/v1') }}</code>
                        </div>
                    </section>

                    <section class="mb-5">
                        <h2 class="h4 fw-bold text-text mb-3">Endpoints</h2>

                        <div class="accordion" id="endpointsAccordion">
                            <!-- Send Message -->
                            <div class="accordion-item bg-card border-border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-card text-text" type="button" data-bs-toggle="collapse" data-bs-target="#sendMessage">
                                        <span class="badge bg-success me-2">POST</span> /messages/send
                                    </button>
                                </h2>
                                <div id="sendMessage" class="accordion-collapse collapse show" data-bs-parent="#endpointsAccordion">
                                    <div class="accordion-body">
                                        <p class="text-muted">Send a message to a user or conversation.</p>
                                        <h6 class="fw-semibold text-text">Request Body</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "conversation_id": 123,
  "body": "Hello, World!",
  "reply_to": 456 // optional
}</code></pre>
                                        <h6 class="fw-semibold text-text mt-3">Response</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": true,
  "message": {
    "id": 789,
    "body": "Hello, World!",
    "sender_id": 1,
    "conversation_id": 123,
    "created_at": "2025-01-20T10:00:00Z"
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>

                            <!-- Get Conversations -->
                            <div class="accordion-item bg-card border-border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-card text-text" type="button" data-bs-toggle="collapse" data-bs-target="#getConversations">
                                        <span class="badge bg-primary me-2">GET</span> /conversations
                                    </button>
                                </h2>
                                <div id="getConversations" class="accordion-collapse collapse" data-bs-parent="#endpointsAccordion">
                                    <div class="accordion-body">
                                        <p class="text-muted">Get all conversations for the authenticated user.</p>
                                        <h6 class="fw-semibold text-text">Response</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": true,
  "conversations": [
    {
      "id": 123,
      "title": "John Doe",
      "last_message": "Hey there!",
      "unread_count": 2,
      "updated_at": "2025-01-20T10:00:00Z"
    }
  ]
}</code></pre>
                                    </div>
                                </div>
                            </div>

                            <!-- Get Messages -->
                            <div class="accordion-item bg-card border-border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-card text-text" type="button" data-bs-toggle="collapse" data-bs-target="#getMessages">
                                        <span class="badge bg-primary me-2">GET</span> /conversations/{id}/messages
                                    </button>
                                </h2>
                                <div id="getMessages" class="accordion-collapse collapse" data-bs-parent="#endpointsAccordion">
                                    <div class="accordion-body">
                                        <p class="text-muted">Get messages in a specific conversation.</p>
                                        <h6 class="fw-semibold text-text">Query Parameters</h6>
                                        <ul class="text-muted">
                                            <li><code>limit</code> - Number of messages (default: 50, max: 100)</li>
                                            <li><code>before</code> - Message ID to fetch messages before</li>
                                        </ul>
                                        <h6 class="fw-semibold text-text mt-3">Response</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": true,
  "messages": [
    {
      "id": 789,
      "body": "Hello!",
      "sender_id": 1,
      "created_at": "2025-01-20T10:00:00Z"
    }
  ],
  "has_more": true
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="mb-5">
                        <h2 class="h4 fw-bold text-text mb-3">Rate Limits</h2>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th class="text-text">Endpoint</th>
                                        <th class="text-text">Rate Limit</th>
                                    </tr>
                                </thead>
                                <tbody class="text-muted">
                                    <tr>
                                        <td>Send Message</td>
                                        <td>60 requests per minute</td>
                                    </tr>
                                    <tr>
                                        <td>Get Conversations</td>
                                        <td>100 requests per minute</td>
                                    </tr>
                                    <tr>
                                        <td>Get Messages</td>
                                        <td>100 requests per minute</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section>
                        <h2 class="h4 fw-bold text-text mb-3">Need Help?</h2>
                        <p class="text-muted">For support or questions about the API, please contact us at <a href="mailto:support@gekychat.com">support@gekychat.com</a></p>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
