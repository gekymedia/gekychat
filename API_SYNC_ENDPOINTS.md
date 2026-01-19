# Backend API Sync Endpoints for Offline Messaging

This document describes the required backend API endpoints and modifications needed to support incremental message synchronization for offline messaging.

## Required Endpoint Modifications

### 1. Message Send Endpoint

**Endpoint:** `POST /api/v1/conversations/{id}/messages`

**Request Body Addition:**
```json
{
  "client_message_id": "uuid-string",  // NEW: Client-generated UUID for deduplication
  "body": "message text",
  "attachments": [...],
  ...
}
```

**Response Enhancement:**
The response should include the `client_message_id` in the response for reconciliation:
```json
{
  "data": {
    "id": 123,
    "client_message_id": "uuid-string",  // NEW: Echo back the client_message_id
    "body": "message text",
    "created_at": "2026-01-17T12:00:00Z",
    ...
  }
}
```

**Implementation Notes:**
- Store `client_uuid` in the messages table for deduplication
- When receiving a message with an existing `client_uuid`, return the existing message instead of creating a duplicate
- This prevents duplicate messages when the client retries after a network failure

### 2. Incremental Sync Endpoint

**Endpoint:** `GET /api/v1/conversations/{id}/messages`

**Query Parameters:**
- `after` (optional): ISO 8601 timestamp - return messages after this timestamp
- `after_id` (optional): Message ID - return messages after this message ID
- `limit` (optional): Maximum number of messages to return (default: 100)

**Example Request:**
```
GET /api/v1/conversations/123/messages?after=2026-01-17T10:00:00Z&limit=100
GET /api/v1/conversations/123/messages?after_id=456&limit=100
```

**Response Format:**
```json
{
  "data": [
    {
      "id": 457,
      "client_message_id": "uuid-if-available",
      "sender_id": 1,
      "sender": {
        "id": 1,
        "name": "User Name",
        "avatar_url": "..."
      },
      "body": "message text",
      "created_at": "2026-01-17T11:00:00Z",
      "attachments": [],
      ...
    }
  ],
  "meta": {
    "has_more": true,
    "last_id": 457
  }
}
```

**Implementation Notes:**
- Use `after` parameter for timestamp-based incremental sync
- Use `after_id` parameter for ID-based incremental sync (more reliable)
- Return messages in ascending order (oldest first)
- Include `client_message_id` in response if available

### 3. Bulk Message Sync Endpoint (Optional)

**Endpoint:** `POST /api/v1/conversations/sync`

**Request Body:**
```json
{
  "conversation_ids": [123, 456, 789],
  "last_sync": {
    "123": {
      "timestamp": "2026-01-17T10:00:00Z",
      "last_message_id": 456
    },
    "456": {
      "timestamp": "2026-01-17T09:00:00Z",
      "last_message_id": 789
    }
  }
}
```

**Response:**
```json
{
  "data": {
    "123": [
      // Messages for conversation 123
    ],
    "456": [
      // Messages for conversation 456
    ]
  }
}
```

This endpoint allows syncing multiple conversations in a single request, reducing network overhead.

## Database Schema Updates

### Messages Table

Add a column for client UUID:
```sql
ALTER TABLE messages ADD COLUMN client_uuid VARCHAR(100) NULL;
CREATE INDEX idx_messages_client_uuid ON messages(conversation_id, sender_id, client_uuid);
```

This index helps with deduplication when checking for existing messages with the same `client_uuid`.

## MessageController Updates

### Store Method (Send Message)

```php
public function store(Request $r, $conversationId)
{
    // ... existing validation ...
    
    // Check for existing message with client_uuid (deduplication)
    if ($r->filled('client_message_id')) {
        $existing = Message::where('client_uuid', $r->client_message_id)
            ->where('conversation_id', $conv->id)
            ->where('sender_id', $r->user()->id)
            ->first();
        
        if ($existing) {
            $existing->load(['sender','attachments','replyTo','forwardedFrom','reactions.user']);
            return response()->json([
                'data' => new MessageResource($existing)
            ], 200);
        }
    }
    
    // ... existing message creation ...
    
    // Store client_uuid if provided
    if ($r->filled('client_message_id')) {
        $message->client_uuid = $r->client_message_id;
        $message->save();
    }
    
    // ... rest of method ...
    
    // Return message with client_message_id
    return response()->json([
        'data' => new MessageResource($message->load([
            'sender','attachments','replyTo','forwardedFrom','reactions.user'
        ]))
    ], 201);
}
```

### Index Method (Get Messages)

```php
public function index(Request $r, $conversationId)
{
    $conv = Conversation::findOrFail($conversationId);
    $userId = $r->user()->id;
    
    abort_unless($conv->isParticipant($userId), 403);
    
    $query = Message::where('conversation_id', $conversationId)
        ->with(['sender','attachments','replyTo','forwardedFrom','reactions.user'])
        ->orderBy('created_at', 'asc')
        ->orderBy('id', 'asc');
    
    // Incremental sync: after timestamp
    if ($r->filled('after')) {
        $after = Carbon::parse($r->after);
        $query->where(function($q) use ($after) {
            $q->where('created_at', '>', $after)
              ->orWhere(function($q2) use ($after) {
                  $q2->where('created_at', '=', $after)
                     ->where('id', '>', $r->get('after_id', 0));
              });
        });
    }
    
    // Incremental sync: after message ID (more reliable)
    if ($r->filled('after_id')) {
        $query->where('id', '>', $r->after_id);
    }
    
    // Limit results
    $limit = min($r->get('limit', 100), 500); // Max 500 messages per request
    $messages = $query->limit($limit)->get();
    
    return response()->json([
        'data' => MessageResource::collection($messages),
        'meta' => [
            'has_more' => $messages->count() === $limit,
            'last_id' => $messages->last()?->id,
        ]
    ]);
}
```

## MessageResource Updates

Update the `MessageResource` to include `client_message_id`:

```php
public function toArray($request)
{
    return [
        'id' => $this->id,
        'client_message_id' => $this->client_uuid, // NEW
        'sender_id' => $this->sender_id,
        'sender' => new UserResource($this->sender),
        'body' => $this->body,
        'created_at' => $this->created_at->toIso8601String(),
        // ... rest of fields ...
    ];
}
```

## Testing

### Test Deduplication

1. Send message with `client_message_id: "test-uuid-123"`
2. Send same message again with same `client_message_id`
3. Verify second request returns existing message (no duplicate created)

### Test Incremental Sync

1. Get messages without parameters (should return latest)
2. Get messages with `after_id` parameter (should return only newer messages)
3. Verify no duplicates when syncing multiple times

## Migration

Run the database migration:
```php
php artisan make:migration add_client_uuid_to_messages_table

// In migration:
Schema::table('messages', function (Blueprint $table) {
    $table->string('client_uuid', 100)->nullable()->after('id');
    $table->index(['conversation_id', 'sender_id', 'client_uuid'], 'idx_messages_client_uuid');
});
```

## Notes

- `client_message_id` is optional but recommended for reliable offline messaging
- The deduplication check prevents duplicate messages when client retries
- Incremental sync reduces bandwidth and improves sync speed
- `after_id` is preferred over `after` timestamp for more reliable incremental sync
