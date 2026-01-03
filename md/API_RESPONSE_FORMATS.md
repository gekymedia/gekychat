# Laravel API Response Formats

This document describes the structure of all API responses from the Laravel backend for the mobile app.

## General Response Structure

All successful API responses follow one of these patterns:
- `{ "data": ... }` - Most common format
- `{ "message": ... }` - Used in MessageController store method (⚠️ **INCONSISTENT - needs fix**)
- `{ "status": "success", ... }` - Used for action responses
- Direct object - Used in some auth endpoints

---

## Authentication

### POST /api/v1/auth/phone
**Response:**
```json
{
  "success": true,
  "message": "OTP sent successfully",
  "expires_in": 300
}
```

### POST /api/v1/auth/verify
**Response:**
```json
{
  "token": "1|xxxxxxxxxxxxx",
  "user": {
    "id": 1,
    "name": "John Doe",
    "phone": "+1234567890",
    "avatar_url": "http://example.com/storage/avatars/1.jpg",
    "created_at": "2025-01-15T10:30:00Z"
  }
}
```

### GET /api/v1/me
**Response:**
```json
{
  "id": 1,
  "name": "John Doe",
  "phone": "+1234567890",
  "email": "user@example.com",
  "avatar_path": "avatars/1.jpg",
  "created_at": "2025-01-15T10:30:00Z",
  "updated_at": "2025-01-15T10:30:00Z"
}
```

---

## Conversations (One-on-One Chats)

### GET /api/v1/conversations
**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "type": "dm",
      "title": "John Doe",
      "other_user": {
        "id": 2,
        "name": "John Doe",
        "avatar": "http://example.com/storage/avatars/2.jpg"
      },
      "last_message": {
        "id": 100,
        "body_preview": "Hello, how are you?",
        "created_at": "2025-01-15T10:30:00Z"
      },
      "unread": 3,
      "pinned": false,
      "muted": false
    }
  ]
}
```

### POST /api/v1/conversations/start
**Response:**
```json
{
  "data": {
    "id": 1
  }
}
```

### GET /api/v1/conversations/{id}
**Response:**
```json
{
  "data": {
    "id": 1
  }
}
```

### GET /api/v1/conversations/{id}/messages
**Response:**
```json
{
  "data": [
    {
      "id": 100,
      "conversation_id": 1,
      "group_id": null,
      "sender": {
        "id": 2,
        "name": "John Doe",
        "avatar": "http://example.com/storage/avatars/2.jpg"
      },
      "sender_id": 2,
      "body": "Hello, how are you?",
      "is_encrypted": false,
      "attachments": [],
      "reply_to": null,
      "forwarded_from": null,
      "forward_chain": null,
      "reactions": [],
      "read_at": null,
      "delivered_at": "2025-01-15T10:30:00Z",
      "edited_at": null,
      "expires_at": null,
      "created_at": "2025-01-15T10:30:00Z",
      "updated_at": "2025-01-15T10:30:00Z"
    }
  ]
}
```

### POST /api/v1/conversations/{id}/messages
**Response:**
```json
{
  "data": {
    "id": 100,
    "conversation_id": 1,
    "group_id": null,
    "sender": {
      "id": 1,
      "name": "John Doe",
      "avatar": "http://example.com/storage/avatars/1.jpg"
    },
    "sender_id": 1,
    "body": "Hello!",
    "is_encrypted": false,
    "attachments": [],
    "reply_to": null,
    "forwarded_from": null,
    "forward_chain": null,
    "reactions": [],
    "read_at": null,
    "delivered_at": "2025-01-15T10:30:00Z",
    "edited_at": null,
    "expires_at": null,
    "created_at": "2025-01-15T10:30:00Z",
    "updated_at": "2025-01-15T10:30:00Z"
  }
}
```
✅ **NOTE:** Returns MessageResource format wrapped in `"data"` key for consistency

### POST /api/v1/conversations/{id}/read
**Response:**
```json
{
  "success": true,
  "marked_count": 5
}
```

---

## Groups

### GET /api/v1/groups
**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "type": "group",
      "name": "Team Chat",
      "avatar": "http://example.com/storage/groups/1.jpg",
      "last_message": {
        "id": 200,
        "body_preview": "Meeting at 3pm",
        "created_at": "2025-01-15T10:30:00Z"
      },
      "unread": 5,
      "pinned": false,
      "muted": false
    }
  ]
}
```

### GET /api/v1/groups/{id}
**Response:**
```json
{
  "data": {
    "id": 1
  }
}
```

### GET /api/v1/groups/{id}/messages
**Response:**
```json
{
  "data": [
    {
      "id": 200,
      "conversation_id": null,
      "group_id": 1,
      "sender": {
        "id": 2,
        "name": "John Doe",
        "avatar": "http://example.com/storage/avatars/2.jpg"
      },
      "sender_id": 2,
      "body": "Meeting at 3pm",
      "is_encrypted": false,
      "attachments": [],
      "reply_to": null,
      "forwarded_from": null,
      "forward_chain": null,
      "reactions": [],
      "read_at": null,
      "delivered_at": "2025-01-15T10:30:00Z",
      "edited_at": null,
      "expires_at": null,
      "created_at": "2025-01-15T10:30:00Z",
      "updated_at": "2025-01-15T10:30:00Z"
    }
  ]
}
```

### POST /api/v1/groups/{id}/messages
**Response:**
```json
{
  "data": {
    "id": 200,
    "group_id": 1,
    "sender_id": 1,
    "body": "Hello everyone!",
    "created_at": "2025-01-15T10:30:00Z",
    "updated_at": "2025-01-15T10:30:00Z",
    "sender": { ... },
    "attachments": [ ... ],
    "replyTo": null,
    "forwardedFrom": null,
    "reactions": []
  }
}
```

---

## Status/Stories

### GET /api/v1/statuses
**Response:**
```json
{
  "statuses": [
    {
      "user_id": 2,
      "user_name": "John Doe",
      "user_avatar": "http://example.com/storage/avatars/2.jpg",
      "updates": [
        {
          "id": 1,
          "user_id": 2,
          "type": "text",
          "text": "Hello world!",
          "media_url": null,
          "thumbnail_url": null,
          "background_color": "#00A884",
          "font_family": "default",
          "created_at": "2025-01-15T10:30:00Z",
          "expires_at": "2025-01-16T10:30:00Z",
          "view_count": 5,
          "viewed": false
        }
      ],
      "last_updated_at": "2025-01-15T10:30:00Z",
      "has_unviewed": true,
      "is_muted": false
    }
  ]
}
```

### GET /api/v1/statuses/mine
**Response:**
```json
{
  "updates": [
    {
      "id": 1,
      "user_id": 1,
      "type": "text",
      "text": "My status",
      "media_url": null,
      "thumbnail_url": null,
      "background_color": "#00A884",
      "font_family": "default",
      "created_at": "2025-01-15T10:30:00Z",
      "expires_at": "2025-01-16T10:30:00Z",
      "view_count": 10,
      "viewed": true
    }
  ],
  "last_updated_at": "2025-01-15T10:30:00Z",
  "total_views": 10
}
```

### GET /api/v1/statuses/user/{userId}
**Response:**
```json
{
  "user_id": 2,
  "user_name": "John Doe",
  "user_avatar": "http://example.com/storage/avatars/2.jpg",
  "updates": [
    {
      "id": 1,
      "user_id": 2,
      "type": "text",
      "text": "Hello world!",
      "media_url": null,
      "thumbnail_url": null,
      "background_color": "#00A884",
      "font_family": "default",
      "created_at": "2025-01-15T10:30:00Z",
      "expires_at": "2025-01-16T10:30:00Z",
      "view_count": 5,
      "viewed": false
    }
  ],
  "last_updated_at": "2025-01-15T10:30:00Z",
  "has_unviewed": true,
  "is_muted": false
}
```

### POST /api/v1/statuses
**Response:**
```json
{
  "status": {
    "id": 1,
    "user_id": 1,
    "type": "text",
    "text": "Hello world!",
    "media_url": null,
    "thumbnail_url": null,
    "background_color": "#00A884",
    "font_family": "default",
    "created_at": "2025-01-15T10:30:00Z",
    "expires_at": "2025-01-16T10:30:00Z",
    "view_count": 0,
    "viewed": false
  }
}
```

### POST /api/v1/statuses/{id}/view
**Response:**
```json
{
  "success": true,
  "view_count": 5
}
```

### GET /api/v1/statuses/{id}/viewers
**Response:**
```json
{
  "viewers": [
    {
      "user_id": 2,
      "user_name": "John Doe",
      "user_avatar": "http://example.com/storage/avatars/2.jpg",
      "viewed_at": "2025-01-15T10:30:00Z"
    }
  ]
}
```

### DELETE /api/v1/statuses/{id}
**Response:**
```json
{
  "success": true
}
```

### GET /api/v1/statuses/privacy
**Response:**
```json
{
  "privacy": "contacts",
  "excluded_user_ids": [],
  "included_user_ids": []
}
```

### PUT /api/v1/statuses/privacy
**Response:**
```json
{
  "success": true
}
```

---

## Message Resource Structure

The `MessageResource` is used for both DM and Group messages and returns:

```json
{
  "id": 100,
  "conversation_id": 1,  // null for group messages
  "group_id": 1,         // null for DM messages
  "sender": {
    "id": 2,
    "name": "John Doe",
    "avatar": "http://example.com/storage/avatars/2.jpg"
  },
  "sender_id": 2,
  "body": "Message text",
  "is_encrypted": false,
  "attachments": [
    {
      "id": 1,
      "url": "http://example.com/storage/attachments/1.jpg",
      "mime_type": "image/jpeg",
      "size": 102400,
      "is_image": true,
      "is_video": false,
      "is_document": false,
      "original_name": "photo.jpg"
    }
  ],
  "reply_to": {
    "id": 99,
    "sender_id": 2,
    "body_preview": "Previous message..."
  },
  "forwarded_from": {
    "id": 98,
    "sender_id": 3,
    "body_preview": "Forwarded message..."
  },
  "forward_chain": null,
  "reactions": [
    {
      "emoji": "❤️",
      "user": {
        "id": 2,
        "name": "John Doe",
        "avatar": "http://example.com/storage/avatars/2.jpg"
      }
    }
  ],
  "read_at": "2025-01-15T10:30:00Z",
  "delivered_at": "2025-01-15T10:30:00Z",
  "edited_at": null,
  "expires_at": null,
  "created_at": "2025-01-15T10:30:00Z",
  "updated_at": "2025-01-15T10:30:00Z"
}
```

---

## Error Responses

### Validation Error (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["The field name is required."]
  }
}
```

### Authentication Error (401)
```json
{
  "message": "Invalid OTP code"
}
```

### Authorization Error (403)
```json
{
  "message": "Forbidden"
}
```

### Not Found (404)
```json
{
  "message": "User not found"
}
```

---

## Known Issues / Inconsistencies

✅ **FIXED:** POST /api/v1/conversations/{id}/messages now returns `{ "data": ... }` using MessageResource format for consistency with other endpoints.

---

## Notes

- All timestamps are in ISO 8601 format (e.g., "2025-01-15T10:30:00Z")
- Avatar URLs are full URLs using `asset('storage/...')`
- The `MessageResource` handles both DM messages and Group messages
- Encrypted messages are decrypted automatically in the resource
- Most endpoints wrap responses in a `data` key for consistency
