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
                        <strong>Getting Started:</strong> Enable Developer Mode in your settings to get your unique Client ID and generate API keys (Client Secrets).
                    </div>

                    <!-- Authentication Section -->
                    <section class="mb-5">
                        <h2 class="h4 fw-bold text-text mb-3">Authentication</h2>
                        
                        <div class="mb-4">
                            <h5 class="fw-semibold text-text mb-2">Developer Mode (User API Keys)</h5>
                            <p class="text-muted">For individual developers using GekyChat API:</p>
                            <ol class="text-muted">
                                <li>Enable Developer Mode in your account settings</li>
                                <li>You'll receive a unique <code>client_id</code> (e.g., <code>dev_00000001_a1b2c3d4e5f6g7h8</code>)</li>
                                <li>Generate one or more API keys (Client Secrets) starting with <code>sk_</code></li>
                                <li>Use your Client Secret as Bearer token in API requests</li>
                            </ol>
                            <div class="bg-dark text-light p-3 rounded mt-3">
                                <code>
Authorization: Bearer sk_your_client_secret_here
                                </code>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5 class="fw-semibold text-text mb-2">Platform API Clients</h5>
                            <p class="text-muted">For platform integrations (CUG, schoolsgh, etc.):</p>
                            <ol class="text-muted">
                                <li>Contact admin to create a Platform API Client</li>
                                <li>You'll receive a <code>client_id</code> and <code>client_secret</code></li>
                                <li>Exchange credentials for access token using OAuth 2.0 Client Credentials flow</li>
                            </ol>
                            <div class="bg-dark text-light p-3 rounded mt-3">
                                <code>
POST /api/platform/oauth/token<br>
Content-Type: application/json<br><br>
{<br>
&nbsp;&nbsp;"client_id": "your_client_id",<br>
&nbsp;&nbsp;"client_secret": "your_client_secret",<br>
&nbsp;&nbsp;"grant_type": "client_credentials"<br>
}
                                </code>
                            </div>
                            <p class="text-muted mt-2">Then use the returned <code>access_token</code> as Bearer token:</p>
                            <div class="bg-dark text-light p-3 rounded">
                                <code>
Authorization: Bearer your_access_token
                                </code>
                            </div>
                        </div>
                    </section>

                    <!-- Base URLs -->
                    <section class="mb-5">
                        <h2 class="h4 fw-bold text-text mb-3">Base URLs</h2>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th class="text-text">API Type</th>
                                        <th class="text-text">Base URL</th>
                                    </tr>
                                </thead>
                                <tbody class="text-muted">
                                    <tr>
                                        <td>User API (Developer Mode)</td>
                                        <td><code>{{ url('/api/v1') }}</code></td>
                                    </tr>
                                    <tr>
                                        <td>Platform API</td>
                                        <td><code>{{ url('/api/platform') }}</code></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Special API Creation Privilege -->
                    <section class="mb-5">
                        <h2 class="h4 fw-bold text-text mb-3">Special API Creation Privilege</h2>
                        <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Auto-Create Users:</strong> API clients with Special API Creation Privilege can automatically create GekyChat users when sending messages to unregistered phone numbers. This privilege must be granted by an administrator.
                        </div>
                        <p class="text-muted">When enabled:</p>
                        <ul class="text-muted">
                            <li>Messages to unregistered phone numbers will automatically create user accounts</li>
                            <li>Users are created with the phone number as their name (can be updated later)</li>
                            <li>Phone verification is not required for auto-created users</li>
                            <li>Perfect for multi-tenant systems like schoolsgh where each school needs to send notifications</li>
                        </ul>
                    </section>

                    <!-- Platform API Endpoints -->
                    <section class="mb-5">
                        <h2 class="h4 fw-bold text-text mb-3">Platform API Endpoints</h2>
                        <p class="text-muted mb-3">For platform integrations and systems that need to send messages programmatically.</p>

                        <div class="accordion" id="platformEndpointsAccordion">
                            <!-- Get Access Token -->
                            <div class="accordion-item bg-card border-border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-card text-text" type="button" data-bs-toggle="collapse" data-bs-target="#getToken">
                                        <span class="badge bg-success me-2">POST</span> /platform/oauth/token
                                    </button>
                                </h2>
                                <div id="getToken" class="accordion-collapse collapse show" data-bs-parent="#platformEndpointsAccordion">
                                    <div class="accordion-body">
                                        <p class="text-muted">Exchange client credentials for an access token.</p>
                                        <h6 class="fw-semibold text-text">Request Body</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "client_id": "your_client_id",
  "client_secret": "your_client_secret",
  "grant_type": "client_credentials"
}</code></pre>
                                        <h6 class="fw-semibold text-text mt-3">Response</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "access_token": "1|abc123...",
  "token_type": "Bearer",
  "expires_in": null,
  "scope": ["messages.send", "users.read", "users.create"]
}</code></pre>
                                    </div>
                                </div>
                            </div>

                            <!-- Find User by Phone -->
                            <div class="accordion-item bg-card border-border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-card text-text" type="button" data-bs-toggle="collapse" data-bs-target="#findUserByPhone">
                                        <span class="badge bg-primary me-2">GET</span> /platform/users/by-phone
                                    </button>
                                </h2>
                                <div id="findUserByPhone" class="accordion-collapse collapse" data-bs-parent="#platformEndpointsAccordion">
                                    <div class="accordion-body">
                                        <p class="text-muted">Find a user by phone number. Auto-creates user if you have Special API Creation Privilege.</p>
                                        <h6 class="fw-semibold text-text">Query Parameters</h6>
                                        <ul class="text-muted">
                                            <li><code>phone</code> (required) - Phone number in any format</li>
                                        </ul>
                                        <h6 class="fw-semibold text-text mt-3">Response (User Found)</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "data": {
    "id": 123,
    "phone": "+233241234567",
    "name": "John Doe",
    "normalized_phone": "+233241234567",
    "auto_created": false
  }
}</code></pre>
                                        <h6 class="fw-semibold text-text mt-3">Response (Auto-Created - with Privilege)</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "data": {
    "id": 124,
    "phone": "+233241234568",
    "name": "+233241234568",
    "normalized_phone": "+233241234568",
    "auto_created": true
  }
}</code></pre>
                                        <h6 class="fw-semibold text-text mt-3">Response (Not Found - without Privilege)</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "error": "User not found. Phone number is not registered on GekyChat.",
  "code": "USER_NOT_FOUND"
}</code></pre>
                                    </div>
                                </div>
                            </div>

                            <!-- Send Message to Phone -->
                            <div class="accordion-item bg-card border-border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-card text-text" type="button" data-bs-toggle="collapse" data-bs-target="#sendToPhone">
                                        <span class="badge bg-success me-2">POST</span> /platform/messages/send-to-phone
                                    </button>
                                </h2>
                                <div id="sendToPhone" class="accordion-collapse collapse" data-bs-parent="#platformEndpointsAccordion">
                                    <div class="accordion-body">
                                        <p class="text-muted">Send a message directly to a phone number. Auto-creates user and conversation if needed (with Special API Creation Privilege).</p>
                                        <h6 class="fw-semibold text-text">Request Body</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "phone": "+233241234567",
  "body": "Hello! This is a test message.",
  "external_ref": "optional-reference-id",
  "metadata": {
    "custom_field": "value"
  },
  "bot_user_id": 0
}</code></pre>
                                        <h6 class="fw-semibold text-text mt-3">Response</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "data": {
    "message_id": 789,
    "conversation_id": 456,
    "user_id": 123,
    "user_auto_created": false,
    "status": "sent"
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>

                            <!-- Send Message to Conversation -->
                            <div class="accordion-item bg-card border-border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-card text-text" type="button" data-bs-toggle="collapse" data-bs-target="#sendToConversation">
                                        <span class="badge bg-success me-2">POST</span> /platform/messages/send
                                    </button>
                                </h2>
                                <div id="sendToConversation" class="accordion-collapse collapse" data-bs-parent="#platformEndpointsAccordion">
                                    <div class="accordion-body">
                                        <p class="text-muted">Send a message to an existing conversation.</p>
                                        <h6 class="fw-semibold text-text">Request Body</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "conversation_id": 456,
  "body": "Hello! This is a test message.",
  "external_ref": "optional-reference-id",
  "metadata": {
    "custom_field": "value"
  }
}</code></pre>
                                        <h6 class="fw-semibold text-text mt-3">Response</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "data": {
    "message_id": 789,
    "conversation_id": 456,
    "status": "sent"
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- User API Endpoints -->
                    <section class="mb-5">
                        <h2 class="h4 fw-bold text-text mb-3">User API Endpoints</h2>
                        <p class="text-muted mb-3">For developers building applications that interact with GekyChat on behalf of users.</p>

                        <div class="accordion" id="userEndpointsAccordion">
                            <!-- Send Message -->
                            <div class="accordion-item bg-card border-border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button bg-card text-text" type="button" data-bs-toggle="collapse" data-bs-target="#sendMessage">
                                        <span class="badge bg-success me-2">POST</span> /v1/messages/send
                                    </button>
                                </h2>
                                <div id="sendMessage" class="accordion-collapse collapse show" data-bs-parent="#userEndpointsAccordion">
                                    <div class="accordion-body">
                                        <p class="text-muted">Send a message to a user or conversation.</p>
                                        <h6 class="fw-semibold text-text">Request Body</h6>
                                        <pre class="bg-dark text-light p-3 rounded"><code>{
  "conversation_id": 123,
  "body": "Hello, World!",
  "reply_to": 456
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
                                        <span class="badge bg-primary me-2">GET</span> /v1/conversations
                                    </button>
                                </h2>
                                <div id="getConversations" class="accordion-collapse collapse" data-bs-parent="#userEndpointsAccordion">
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
                                        <span class="badge bg-primary me-2">GET</span> /v1/conversations/{id}/messages
                                    </button>
                                </h2>
                                <div id="getMessages" class="accordion-collapse collapse" data-bs-parent="#userEndpointsAccordion">
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

                    <!-- Rate Limits -->
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
                                        <td>Send Message (Platform)</td>
                                        <td>100 requests per minute</td>
                                    </tr>
                                    <tr>
                                        <td>Send Message (User)</td>
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

                    <!-- Need Help -->
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
