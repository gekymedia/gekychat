# Backend API Updates Summary

## ✅ Completed Updates

### 1. MessageResource Updated
- **File:** `app/Http/Resources/MessageResource.php`
- **Change:** Added `client_message_id` field to response
- **Purpose:** Allows mobile app to reconcile sent messages with server responses

### 2. MessageController - Store Method
- **File:** `app/Http/Controllers/Api/V1/MessageController.php`
- **Changes:**
  - Added support for `client_message_id` parameter (alternative to `client_id`)
  - Added support for `reply_to_id` parameter (alternative to `reply_to`)
  - Enhanced deduplication check to use either `client_id` or `client_message_id`
  - Already had client_uuid support and deduplication logic

### 3. MessageController - Index Method (Incremental Sync)
- **File:** `app/Http/Controllers/Api/V1/MessageController.php`
- **Changes:**
  - Added `after_id` parameter for ID-based incremental sync
  - Added `after_timestamp` parameter for timestamp-based incremental sync
  - Changed default ordering to ascending for incremental sync
  - Added metadata in response (`has_more`, `last_id`, `count`)
  - Increased max limit to 500 messages per request

**New Query Parameters:**
- `after_id`: Message ID - return messages after this ID (preferred method)
- `after_timestamp`: ISO 8601 timestamp - return messages after this timestamp
- `after`: Still supported for backward compatibility (treats as `after_id`)
- `before`: Still supported for pagination (loading older messages)
- `limit`: Maximum messages to return (default: 50, max: 500)

**Example Requests:**
```
GET /api/v1/conversations/123/messages?after_id=456&limit=100
GET /api/v1/conversations/123/messages?after_timestamp=2026-01-17T10:00:00Z&limit=100
```

### 4. Database Migration
- **File:** `database/migrations/2026_01_18_215444_ensure_client_uuid_index_on_messages_table.php`
- **Purpose:** 
  - Ensures `client_uuid` column exists
  - Creates composite index for efficient deduplication queries
  - Index: `idx_messages_client_uuid` on `(conversation_id, sender_id, client_uuid)`

## Migration Instructions

Run the migration:
```bash
cd gekychat
php artisan migrate
```

## Testing

### Test Deduplication
1. Send message with `client_message_id: "test-uuid-123"`
2. Send same message again with same `client_message_id`
3. Verify second request returns existing message (status 200, not 201)

### Test Incremental Sync
1. Get messages: `GET /api/v1/conversations/123/messages?limit=10`
2. Note the last message ID from response
3. Get next batch: `GET /api/v1/conversations/123/messages?after_id={last_id}&limit=10`
4. Verify no duplicates and messages are in ascending order

### Test Timestamp Sync
1. Get messages: `GET /api/v1/conversations/123/messages?after_timestamp=2026-01-17T10:00:00Z`
2. Verify only messages after the timestamp are returned

## API Response Format

### Message Response (with client_message_id)
```json
{
  "data": {
    "id": 123,
    "client_message_id": "uuid-string",
    "sender_id": 1,
    "body": "message text",
    "created_at": "2026-01-17T12:00:00Z",
    ...
  }
}
```

### Messages List Response (with metadata)
```json
{
  "data": [
    {
      "id": 123,
      "client_message_id": "uuid-string",
      ...
    }
  ],
  "has_more": true,
  "meta": {
    "has_more": true,
    "last_id": 123,
    "count": 50
  }
}
```

## Backward Compatibility

All changes are backward compatible:
- `client_id` still works (alongside `client_message_id`)
- `reply_to` still works (alongside `reply_to_id`)
- `after` parameter still works (treated as `after_id`)
- Existing API consumers will continue to work

## Notes

- The `client_uuid` column already existed in the messages table
- The deduplication logic was already implemented
- These updates enhance the API for better offline messaging support
- The index improves query performance for deduplication checks
